<?php

namespace App\Services;

use DOMDocument;
use DOMElement;
use DOMNode;
use Illuminate\Support\Facades\Storage;
use Normalizer;
use ZipArchive;

class WordQuestionParser
{
    /**
     * Parse file Word (.docx / .doc) menjadi kumpulan data soal yang terstruktur.
     */
    public function parse(string $filePath): array
    {
        // 1. Cek apakah berkas merupakan arsip OpenXML ZIP (.docx)
        $zip = new ZipArchive();
        if ($zip->open($filePath) === true) {
            $hasDocXml = $zip->locateName('word/document.xml') !== false;
            if ($hasDocXml) {
                $questions = $this->parseDocxZip($zip);
                $zip->close();
                if (!empty($questions)) {
                    return $questions;
                }
            } else {
                $zip->close();
            }
        }

        // 2. Fallback untuk berkas HTML-based Word (.doc)
        $rawContent = @file_get_contents($filePath);
        if ($rawContent && (str_contains($rawContent, '<html') || str_contains($rawContent, '<w:worddocument'))) {
            return $this->parseWordHtml($rawContent);
        }

        // 3. Fallback PhpOffice\PhpWord
        if (class_exists(\PhpOffice\PhpWord\IOFactory::class)) {
            try {
                $phpWord = \PhpOffice\PhpWord\IOFactory::load($filePath);
                return $this->parseWithPhpWord($phpWord);
            } catch (\Exception $e) {
                // Lanjut ke fallback teks biasa
            }
        }

        // 4. Fallback teks plain
        if ($rawContent) {
            $plainText = strip_tags($rawContent);
            return $this->parseLinesToQuestions(explode("\n", $plainText));
        }

        return [];
    }

    /**
     * Normalisasi teks: mengubah karakter matematika/italic (seperti 𝐽𝑎𝑤𝑎𝑏𝑎𝑛) menjadi ASCII,
     * serta merapikan tanda minus/dash khusus.
     */
    private function normalizeText(string $str): string
    {
        if (function_exists('normalizer_normalize')) {
            $norm = normalizer_normalize($str, Normalizer::FORM_KD);
            if ($norm !== false) {
                $str = $norm;
            }
        }
        $str = str_replace(["\xE2\x80\x93", "\xE2\x80\x94", "–", "—", "−"], '-', $str);
        return trim($str);
    }

    /**
     * Parse berkas OpenXML .docx (arsip ZIP) secara langsung.
     * Mengekstrak paragraf, tabel HTML, tabel Tepat/Tidak Tepat, dan berkas gambar dari word/media/.
     */
    private function parseDocxZip(ZipArchive $zip): array
    {
        // Peta relasi media gambar: rId => path (misal: rId4 => word/media/image1.png)
        $relationships = [];
        $relsXml = $zip->getFromName('word/_rels/document.xml.rels');
        if ($relsXml) {
            $domRels = new DOMDocument();
            @$domRels->loadXML($relsXml, LIBXML_NOENT | LIBXML_NOERROR | LIBXML_NOWARNING);
            foreach ($domRels->getElementsByTagName('Relationship') as $rel) {
                $id = $rel->getAttribute('Id');
                $target = $rel->getAttribute('Target');
                $type = $rel->getAttribute('Type');
                if (str_contains($type, 'image') || preg_match('/\.(png|jpe?g|gif|webp|bmp)$/i', $target)) {
                    $cleanTarget = ltrim(str_replace('../', '', $target), '/');
                    if (!str_starts_with($cleanTarget, 'word/')) {
                        $cleanTarget = 'word/' . $cleanTarget;
                    }
                    $relationships[$id] = $cleanTarget;
                }
            }
        }

        // Baca konten utama dokumen: word/document.xml
        $docXml = $zip->getFromName('word/document.xml');
        if (!$docXml) {
            return [];
        }

        $dom = new DOMDocument();
        @$dom->loadXML($docXml, LIBXML_NOENT | LIBXML_NOERROR | LIBXML_NOWARNING);

        $body = $dom->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 'body')->item(0);
        if (!$body) {
            return [];
        }

        // Ekstraksi elemen dokumen secara sekuensial (paragraf, tabel, gambar)
        $items = [];
        foreach ($body->childNodes as $node) {
            if (!($node instanceof DOMElement)) {
                continue;
            }

            if ($node->localName === 'p') {
                $text = $this->extractTextFromParagraphNode($node);
                $images = $this->extractImagesFromParagraphNode($node, $relationships, $zip);

                $normText = $this->normalizeText($text);
                if ($normText !== '' || !empty($images)) {
                    // Cek jika baris memiliki inline answer marker di ujung baris (misal: "24 hari Jawaban: B")
                    if (preg_match('/^(.*?)(?:\s+|(?<=[^\s]))((?:Jawaban|Kunci\s*Jawaban|Kunci)\s*:\s*.*)$/iu', $normText, $m) && !empty($m[1])) {
                        $before = trim($m[1]);
                        $ansPart = trim($m[2]);
                        if ($before !== '') {
                            $items[] = [
                                'type'   => 'p',
                                'text'   => $before,
                                'images' => $images,
                            ];
                        }
                        $items[] = [
                            'type'   => 'p',
                            'text'   => $ansPart,
                            'images' => [],
                        ];
                    } else {
                        $items[] = [
                            'type'   => 'p',
                            'text'   => $normText,
                            'images' => $images,
                        ];
                    }
                }
            } elseif ($node->localName === 'tbl') {
                $tableItem = $this->parseWordTableNode($node);
                if (!empty($tableItem)) {
                    $items[] = $tableItem;
                }
            }
        }

        return $this->convertItemsToQuestions($items);
    }

    /**
     * Ekstraksi teks dari node <w:p>
     */
    private function extractTextFromParagraphNode(DOMElement $pNode): string
    {
        $text = '';
        foreach ($pNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/wordprocessingml/2006/main', 't') as $tNode) {
            $text .= $tNode->textContent;
        }
        return $text;
    }

    /**
     * Ekstraksi gambar yang disematkan dalam node <w:p>
     */
    private function extractImagesFromParagraphNode(DOMElement $pNode, array $relationships, ZipArchive $zip): array
    {
        $images = [];

        // 1. Drawing ML: <a:blip r:embed="rIdX">
        $blips = $pNode->getElementsByTagNameNS('http://schemas.openxmlformats.org/drawingml/2006/main', 'blip');
        foreach ($blips as $blip) {
            $embedId = $blip->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'embed')
                    ?: $blip->getAttribute('r:embed');
            if ($embedId && isset($relationships[$embedId])) {
                $savedPath = $this->saveZipMediaToStorage($zip, $relationships[$embedId]);
                if ($savedPath) {
                    $images[] = $savedPath;
                }
            }
        }

        // 2. VML: <v:imagedata r:id="rIdX">
        $vmlImages = $pNode->getElementsByTagNameNS('urn:schemas-microsoft-com:vml', 'imagedata');
        foreach ($vmlImages as $vml) {
            $relId = $vml->getAttributeNS('http://schemas.openxmlformats.org/officeDocument/2006/relationships', 'id')
                  ?: $vml->getAttribute('r:id');
            if ($relId && isset($relationships[$relId])) {
                $savedPath = $this->saveZipMediaToStorage($zip, $relationships[$relId]);
                if ($savedPath) {
                    $images[] = $savedPath;
                }
            }
        }

        return $images;
    }

    /**
     * Simpan berkas media dari ZIP ke storage public/questions/
     */
    private function saveZipMediaToStorage(ZipArchive $zip, string $zipEntryPath): ?string
    {
        $binary = $zip->getFromName($zipEntryPath);
        if (!$binary) {
            return null;
        }

        $ext = pathinfo($zipEntryPath, PATHINFO_EXTENSION) ?: 'png';
        $filename = 'word_' . md5($binary) . '.' . strtolower($ext);
        $storagePath = 'questions/' . $filename;

        if (!Storage::disk('public')->exists($storagePath)) {
            Storage::disk('public')->put($storagePath, $binary);
        }

        return $storagePath;
    }

    /**
     * Parse node tabel <w:tbl> Word.
     * Otomatis membedakan tabel pernyataan Tepat/Tidak Tepat dengan tabel data biasa.
     */
    private function parseWordTableNode(DOMElement $tblNode): ?array
    {
        $wNs = 'http://schemas.openxmlformats.org/wordprocessingml/2006/main';
        $rows = [];

        foreach ($tblNode->getElementsByTagNameNS($wNs, 'tr') as $tr) {
            $cells = [];
            foreach ($tr->getElementsByTagNameNS($wNs, 'tc') as $tc) {
                $cellTexts = [];
                foreach ($tc->getElementsByTagNameNS($wNs, 'p') as $p) {
                    $t = $this->extractTextFromParagraphNode($p);
                    if ($t !== '') {
                        $cellTexts[] = trim($t);
                    }
                }
                $cells[] = implode(' ', $cellTexts);
            }
            if (!empty($cells) && array_filter($cells, fn($c) => trim($c) !== '')) {
                $rows[] = $cells;
            }
        }

        if (empty($rows)) {
            return null;
        }

        // Cek apakah tabel ini adalah tabel pernyataan Tepat / Tidak Tepat
        // Header kolom biasanya berisi: "Pernyataan", "Tepat", "Tidak Tepat" (atau Benar/Salah)
        $isTrueFalseTable = false;
        if (count($rows[0]) >= 2) {
            $headerStr = strtolower(implode(' ', $rows[0]));
            if (str_contains($headerStr, 'pernyataan') && (
                str_contains($headerStr, 'tepat') ||
                str_contains($headerStr, 'benar') ||
                str_contains($headerStr, 'tidak tepat') ||
                str_contains($headerStr, 'salah')
            )) {
                $isTrueFalseTable = true;
            }
        }

        if ($isTrueFalseTable) {
            $statements = [];
            for ($i = 1; $i < count($rows); $i++) {
                if (!empty($rows[$i][0]) && trim($rows[$i][0]) !== '') {
                    $statements[] = trim($rows[$i][0]);
                }
            }
            return [
                'type'       => 'tbl_true_false',
                'statements' => $statements,
                'rows'       => $rows,
            ];
        }

        // Jika bukan tabel pernyataan Tepat/Tidak Tepat, buat tabel HTML responsif biasa
        $html = "<div class=\"table-responsive my-3 overflow-x-auto\">\n";
        $html .= "  <table class=\"soal-table w-full border-collapse border border-slate-300 dark:border-zinc-700 text-sm\">\n";
        $html .= "    <thead>\n      <tr class=\"bg-slate-100 dark:bg-zinc-800 font-bold\">\n";
        foreach ($rows[0] as $h) {
            $html .= "        <th class=\"border border-slate-300 dark:border-zinc-700 px-3 py-2 text-center\">" . htmlspecialchars($h) . "</th>\n";
        }
        $html .= "      </tr>\n    </thead>\n    <tbody>\n";
        for ($i = 1; $i < count($rows); $i++) {
            $html .= "      <tr>\n";
            foreach ($rows[$i] as $c) {
                $html .= "        <td class=\"border border-slate-300 dark:border-zinc-700 px-3 py-2 text-center\">" . htmlspecialchars($c) . "</td>\n";
            }
            $html .= "      </tr>\n";
        }
        $html .= "    </tbody>\n  </table>\n</div>";

        return [
            'type' => 'tbl',
            'html' => $html,
        ];
    }

    /**
     * Konversi daftar item dokumen sekuensial menjadi kumpulan butir soal terstruktur
     */
    private function convertItemsToQuestions(array $items): array
    {
        if (empty($items)) {
            return [];
        }

        // Cek berapa banyak penanda kunci jawaban di dalam items
        $ansMarkersCount = 0;
        foreach ($items as $item) {
            if ($item['type'] === 'p' && preg_match('/^(?:Jawaban|Kunci\s*Jawaban|Kunci|Key|Ans)\s*:/iu', $item['text'])) {
                $ansMarkersCount++;
            }
        }

        // Cek segmentasi nomor eksplisit
        $explicitBlocks = $this->segmentByExplicitNumbering($items);

        if (count($explicitBlocks) >= 2 && count($explicitBlocks) >= ($ansMarkersCount * 0.7)) {
            $questionBlocks = $explicitBlocks;
        } elseif ($ansMarkersCount >= 2) {
            $questionBlocks = $this->segmentByAnswerMarker($items);
        } else {
            $questionBlocks = !empty($explicitBlocks) ? $explicitBlocks : $this->segmentByAnswerMarker($items);
        }

        // Jika masih kosong, satukan seluruh item jadi 1 soal
        if (empty($questionBlocks) && !empty($items)) {
            $allImages = [];
            foreach ($items as $it) {
                if (!empty($it['images'])) $allImages = array_merge($allImages, $it['images']);
            }
            $questionBlocks[] = [
                'q_num'  => 1,
                'items'  => $items,
                'images' => $allImages,
            ];
        }

        // Format setiap blok soal menjadi array soal CBT
        $questions = [];
        foreach ($questionBlocks as $block) {
            $parsed = $this->formatWordQuestionBlock($block);
            if ($parsed && !empty($parsed['question_text'])) {
                $questions[] = $parsed;
            }
        }

        return $questions;
    }

    /**
     * Strategi Segmentasi A: Berdasarkan Nomor Soal Eksplisit (1. ..., 2. ...)
     */
    private function segmentByExplicitNumbering(array $items): array
    {
        $blocks = [];
        $currentQNum = null;
        $currentItems = [];
        $currentImages = [];

        foreach ($items as $item) {
            if ($item['type'] === 'p') {
                $text = $item['text'];
                if ($text === '') continue;

                if (preg_match('/^(?:Soal\s*(?:No\.?|Nomor)?\s*|No\.?\s*)?(\d+)[\.\:\)](?:\s+(.*)|\s*$)/iu', $text, $m)) {
                    $qNum = (int)$m[1];
                    $rest = isset($m[2]) ? trim($m[2]) : '';

                    $isUnit = preg_match('/^(?:kg|g|gram|liter|l|meter|m|cm|mm|dm|hektare|ha|buah|bungkus|orang|siswa|lembar|menit|jam|detik)\b/i', $rest);

                    if (!$isUnit && (!empty($rest) || !empty($item['images']))) {
                        if ($currentQNum !== null && !empty($currentItems)) {
                            $blocks[] = [
                                'q_num'  => $currentQNum,
                                'items'  => $currentItems,
                                'images' => $currentImages,
                            ];
                        }
                        $currentQNum = $qNum;
                        $currentItems = [];
                        $currentImages = $item['images'];
                        if (!empty($rest)) {
                            $currentItems[] = [
                                'type' => 'p',
                                'text' => $rest,
                            ];
                        }
                        continue;
                    }
                }

                if ($currentQNum !== null) {
                    $currentItems[] = $item;
                    if (!empty($item['images'])) {
                        $currentImages = array_merge($currentImages, $item['images']);
                    }
                }
            } elseif ($item['type'] === 'tbl' || $item['type'] === 'tbl_true_false') {
                if ($currentQNum !== null) {
                    $currentItems[] = $item;
                }
            }
        }

        if ($currentQNum !== null && !empty($currentItems)) {
            $blocks[] = [
                'q_num'  => $currentQNum,
                'items'  => $currentItems,
                'images' => $currentImages,
            ];
        }

        return $blocks;
    }

    /**
     * Strategi Segmentasi B: Berdasarkan Pembatas Kunci Jawaban (Jawaban: ...)
     * Digunakan ketika dokumen tidak memuat angka penomoran soal eksplisit.
     */
    private function segmentByAnswerMarker(array $items): array
    {
        $blocks = [];
        $currentItems = [];
        $currentImages = [];
        $qNum = 1;

        // Abaikan judul dokumen di awal (misal: "BANK SOAL TKA KELAS 6 SD")
        $startIdx = 0;
        while ($startIdx < count($items)) {
            $it = $items[$startIdx];
            if ($it['type'] === 'p' && preg_match('/^(?:bank\s*soal|tryout|penilaian|ujian|naskah\s*soal|soal\s*tka)\b/iu', $it['text'])) {
                $startIdx++;
                continue;
            }
            break;
        }

        for ($i = $startIdx; $i < count($items); $i++) {
            $item = $items[$i];
            $currentItems[] = $item;
            if (!empty($item['images'])) {
                $currentImages = array_merge($currentImages, $item['images']);
            }

            // Jika item merupakan penanda jawaban, simpan sebagai akhir dari blok butir soal saat ini
            if ($item['type'] === 'p' && preg_match('/^(?:Jawaban|Kunci\s*Jawaban|Kunci|Key|Ans)\s*:/iu', $item['text'])) {
                $blocks[] = [
                    'q_num'  => $qNum++,
                    'items'  => $currentItems,
                    'images' => $currentImages,
                ];
                $currentItems = [];
                $currentImages = [];
            }
        }

        if (!empty($currentItems)) {
            $blocks[] = [
                'q_num'  => $qNum++,
                'items'  => $currentItems,
                'images' => $currentImages,
            ];
        }

        return $blocks;
    }

    /**
     * Format 1 blok soal Word menjadi data soal CBT terstruktur
     */
    private function formatWordQuestionBlock(array $block): ?array
    {
        $qNum = $block['q_num'];
        $items = $block['items'];
        $images = $block['images'];

        $ansLine = null;
        $contentItems = [];
        $hasTfTable = false;
        $tfStatements = [];

        // Pisahkan item jawaban dengan item konten dan identifikasi tabel pernyataan
        foreach ($items as $item) {
            if ($item['type'] === 'p' && preg_match('/^(?:Jawaban|Kunci\s*Jawaban|Kunci|Key|Ans)\s*:\s*(.*)$/iu', $item['text'], $km)) {
                $ansLine = trim($km[1]);
            } elseif ($item['type'] === 'tbl_true_false') {
                $hasTfTable = true;
                $tfStatements = $item['statements'];
            } else {
                $contentItems[] = $item;
            }
        }

        // Analisa tipe kunci jawaban:
        // 1. Tipe Tepat / Tidak Tepat (True/False Table)
        $isTrueFalse = false;
        $tfTokens = [];
        if ($ansLine !== null) {
            $rawTokens = preg_split('/[\s\-\–\—\,\.]+/u', $ansLine, -1, PREG_SPLIT_NO_EMPTY);
            if (count($rawTokens) >= 2) {
                // Periksa apakah seluruh token merupakan token T/TT/B/S/Tepat/Tidak Tepat
                $allTfTokens = true;
                foreach ($rawTokens as $t) {
                    $u = strtoupper(trim($t));
                    if (!in_array($u, ['T', 'TT', 'B', 'S', 'TEPAT', 'TIDAK TEPAT'])) {
                        $allTfTokens = false;
                        break;
                    }
                }
                if ($allTfTokens) {
                    $isTrueFalse = true;
                    $tfTokens = $rawTokens;
                }
            }
        }

        // Jika blok memiliki tabel pernyataan Tepat/Tidak Tepat, otomatis kategorikan sebagai true_false_table
        if ($hasTfTable) {
            $isTrueFalse = true;
        }

        // 2. Tipe Pilihan Ganda Kompleks (Jawaban: A dan B / A, C, D)
        $isMultiSelect = false;
        $letters = [];
        if (!$isTrueFalse && $ansLine !== null) {
            if (preg_match_all('/\b([a-eA-E])\b/u', $ansLine, $lm)) {
                $letters = array_values(array_unique(array_map('strtolower', $lm[1])));
                sort($letters);
                if (count($letters) > 1) {
                    $isMultiSelect = true;
                }
            }
        }

        // 3. Tipe Pilihan Ganda Biasa
        $singleLetter = 'a';
        if (!$isTrueFalse && !$isMultiSelect && $ansLine !== null) {
            if (preg_match('/^([a-eA-E])\b/u', $ansLine, $sm)) {
                $singleLetter = strtolower($sm[1]);
            } elseif (!empty($letters)) {
                $singleLetter = $letters[0];
            }
        }

        // Proses konten (teks soal & opsi)
        $questionParts = [];
        $options = ['a' => null, 'b' => null, 'c' => null, 'd' => null];
        $pairData = null;
        $explanation = null;

        if ($isTrueFalse) {
            $type = 'true_false_table';

            // Kumpulkan teks pertanyaan dan tabel data (tabel non-pernyataan)
            foreach ($contentItems as $cit) {
                if ($cit['type'] === 'tbl') {
                    $questionParts[] = $cit['html'];
                } else {
                    $questionParts[] = $cit['text'];
                }
            }

            // Petakan setiap pernyataan ke kunci jawaban
            $tfLabels = [];
            $pairData = [];
            if (!empty($tfStatements)) {
                foreach ($tfStatements as $idx => $stmt) {
                    $tok = isset($tfTokens[$idx]) ? strtoupper(trim($tfTokens[$idx])) : 'T';
                    $isTepat = ($tok === 'T' || $tok === 'B' || $tok === 'TEPAT');
                    $ansValue = $isTepat ? 'benar' : 'salah'; // 'benar' = Tepat, 'salah' = Tidak Tepat
                    $label = $isTepat ? 'Tepat' : 'Tidak Tepat';
                    $tfLabels[] = ($idx + 1) . '. ' . $label;

                    $pairData[] = [
                        'statement' => $stmt,
                        'answer'    => $ansValue,
                    ];
                }
                $explanation = "Kunci (Tepat/Tidak Tepat): " . implode(' - ', $tfLabels);
            } else {
                // Jika tabel pernyataan tidak ada di node tabel, ambil dari teks
                $explanation = "Kunci: " . $ansLine;
            }

            $correctOption = null;
        } elseif ($isMultiSelect) {
            $type = 'multiple_select';
            $correctOption = implode(',', array_map('strtoupper', $letters));
            $explanation = "Kunci Jawaban: " . $correctOption;

            $textLines = [];
            foreach ($contentItems as $cit) {
                if ($cit['type'] === 'tbl') {
                    $textLines[] = $cit['html'];
                } else {
                    $textLines[] = $cit['text'];
                }
            }

            if (count($textLines) >= 5) {
                $optLines = array_slice($textLines, -4);
                $qLines = array_slice($textLines, 0, -4);
                $questionParts = $qLines;
                $keys = ['a', 'b', 'c', 'd'];
                foreach ($optLines as $idx => $ol) {
                    $cleanOl = preg_replace('/^[a-dA-D][\.\)]\s*/', '', $ol);
                    $options[$keys[$idx]] = $cleanOl;
                }
            } else {
                $questionParts = $textLines;
            }
        } else {
            $type = 'multiple_choice';
            $correctOption = $singleLetter;
            $explanation = "Kunci Jawaban: " . strtoupper($singleLetter);

            $textLines = [];
            foreach ($contentItems as $cit) {
                if ($cit['type'] === 'tbl') {
                    $textLines[] = $cit['html'];
                } else {
                    $textLines[] = $cit['text'];
                }
            }

            // Cek apakah ada opsi dengan label A., B., C., D.
            $hasOptionLabels = false;
            $optMap = [];
            $qLines = [];

            foreach ($textLines as $l) {
                if (preg_match('/^(?:\(?([a-dA-D])[\.\)]|\(([a-dA-D])\))[ \t]+(.*)$/u', $l, $om)) {
                    $hasOptionLabels = true;
                    $optMap[strtolower(!empty($om[1]) ? $om[1] : $om[2])] = trim($om[3]);
                } elseif (!$hasOptionLabels) {
                    $qLines[] = $l;
                }
            }

            if ($hasOptionLabels && count($optMap) >= 2) {
                $questionParts = $qLines;
                foreach ($optMap as $k => $v) {
                    $options[$k] = $v;
                }
            } else {
                // Opsi tanpa label A/B/C/D (misal 4 baris angka/teks sebelum jawaban)
                if (count($textLines) >= 5) {
                    $optLines = array_slice($textLines, -4);
                    $qLines = array_slice($textLines, 0, -4);
                    $questionParts = $qLines;
                    $keys = ['a', 'b', 'c', 'd'];
                    foreach ($optLines as $idx => $ol) {
                        $options[$keys[$idx]] = $ol;
                    }
                } else {
                    $questionParts = $textLines;
                }
            }
        }

        $questionText = trim(implode("\n", $questionParts));
        $questionText = preg_replace('/^(?:Soal\s*(?:No\.?|Nomor)?\s*|No\.?\s*)?\d+[\.\:\)]\s*/iu', '', $questionText);
        $questionText = trim($questionText);
        if (empty($questionText)) {
            $questionText = !empty($options['a']) ? 'Soal Pilihan Ganda' : 'Soal Ujian';
        }

        // Pastikan opsi tidak kosong untuk multiple choice
        if (in_array($type, ['multiple_choice', 'multiple_select'])) {
            if (empty($options['a']) && empty($options['b'])) {
                $options['a'] = 'Pilihan A';
                $options['b'] = 'Pilihan B';
                $options['c'] = 'Pilihan C';
                $options['d'] = 'Pilihan D';
            }
        }

        // Gambar soal
        $attachedImage = !empty($images) ? $images[0] : null;

        // Deteksi indikasi visual yang belum terlampir
        $needsReview = false;
        $reviewNote = null;
        $qtLower = mb_strtolower($questionText);
        if (str_contains($qtLower, 'gambar') && empty($attachedImage)) {
            $needsReview = true;
            $reviewNote = '[PERLU GAMBAR] Soal ini mengacu pada gambar. Pastikan gambar sudah terlampir dengan benar.';
        } elseif (str_contains($qtLower, 'diagram') && empty($attachedImage)) {
            $needsReview = true;
            $reviewNote = '[PERLU DIAGRAM] Soal ini mengacu pada diagram. Silakan lampirkan gambar diagram.';
        } elseif (str_contains($qtLower, 'grafik') && empty($attachedImage)) {
            $needsReview = true;
            $reviewNote = '[PERLU GRAFIK] Soal ini mengacu pada grafik. Silakan lampirkan gambar grafik.';
        }

        if ($type === 'true_false_table' && empty($pairData)) {
            $needsReview = true;
            $reviewNote = ($reviewNote ? $reviewNote . ' ' : '') . '[PERLU PERNYATAAN] Pernyataan tabel Tepat/Tidak Tepat perlu diperiksa secara manual.';
        }

        return [
            'question_number'  => $qNum,
            'question_text'    => $questionText,
            'materi'           => null,
            'indikator'        => null,
            'cognitive_level'  => 'C2',
            'bobot'            => 1,
            'type'             => $type,
            'image'            => $attachedImage,
            'option_a'         => in_array($type, ['multiple_choice', 'multiple_select']) ? $options['a'] : null,
            'option_b'         => in_array($type, ['multiple_choice', 'multiple_select']) ? $options['b'] : null,
            'option_c'         => in_array($type, ['multiple_choice', 'multiple_select']) ? $options['c'] : null,
            'option_d'         => in_array($type, ['multiple_choice', 'multiple_select']) ? $options['d'] : null,
            'correct_option'   => in_array($type, ['multiple_choice', 'multiple_select']) ? $correctOption : null,
            'explanation'      => $explanation,
            'pair_data'        => $pairData,
            'sequence_data'    => $type === 'multiple_select' ? array_values(array_map('strtolower', $letters)) : null,
            'needs_review'     => $needsReview,
            'review_note'      => $reviewNote,
        ];
    }

    /**
     * Fallback untuk dokumen Word berbasis HTML (.doc)
     */
    private function parseWordHtml(string $html): array
    {
        $dom = new DOMDocument();
        @$dom->loadHTML(mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8'), LIBXML_NOERROR | LIBXML_NOWARNING);

        $items = [];
        $body = $dom->getElementsByTagName('body')->item(0);
        if (!$body) {
            return [];
        }

        foreach ($body->childNodes as $node) {
            if ($node->nodeType !== XML_ELEMENT_NODE) continue;

            if (in_array(strtolower($node->nodeName), ['table', 'tbl'])) {
                $rows = [];
                foreach ($node->getElementsByTagName('tr') as $tr) {
                    $cells = [];
                    foreach ($tr->getElementsByTagName('td') as $td) {
                        $cells[] = trim($td->textContent);
                    }
                    if (empty($cells)) {
                        foreach ($tr->getElementsByTagName('th') as $th) {
                            $cells[] = trim($th->textContent);
                        }
                    }
                    if (!empty($cells)) {
                        $rows[] = $cells;
                    }
                }
                if (!empty($rows)) {
                    // Cek tabel Tepat / Tidak Tepat
                    $isTrueFalse = false;
                    if (count($rows[0]) >= 2) {
                        $hdr = strtolower(implode(' ', $rows[0]));
                        if (str_contains($hdr, 'pernyataan') && (str_contains($hdr, 'tepat') || str_contains($hdr, 'benar'))) {
                            $isTrueFalse = true;
                        }
                    }

                    if ($isTrueFalse) {
                        $stmts = [];
                        for ($i = 1; $i < count($rows); $i++) {
                            if (!empty($rows[$i][0])) $stmts[] = trim($rows[$i][0]);
                        }
                        $items[] = ['type' => 'tbl_true_false', 'statements' => $stmts, 'rows' => $rows];
                    } else {
                        $htmlTable = "<div class=\"table-responsive my-3 overflow-x-auto\"><table class=\"soal-table w-full border-collapse border border-slate-300 text-sm\">";
                        foreach ($rows as $r) {
                            $htmlTable .= "<tr>";
                            foreach ($r as $c) {
                                $htmlTable .= "<td class=\"border border-slate-300 px-3 py-1.5\">" . htmlspecialchars($c) . "</td>";
                            }
                            $htmlTable .= "</tr>";
                        }
                        $htmlTable .= "</table></div>";
                        $items[] = ['type' => 'tbl', 'html' => $htmlTable];
                    }
                }
            } else {
                $text = trim($node->textContent);
                if ($text !== '') {
                    $normText = $this->normalizeText($text);
                    if (preg_match('/^(.*?)(?:\s+|(?<=[^\s]))((?:Jawaban|Kunci\s*Jawaban|Kunci)\s*:\s*.*)$/iu', $normText, $m) && !empty($m[1])) {
                        $items[] = ['type' => 'p', 'text' => trim($m[1]), 'images' => []];
                        $items[] = ['type' => 'p', 'text' => trim($m[2]), 'images' => []];
                    } else {
                        $items[] = ['type' => 'p', 'text' => $normText, 'images' => []];
                    }
                }
            }
        }

        return $this->convertItemsToQuestions($items);
    }

    /**
     * Fallback menggunakan PhpOffice\PhpWord
     */
    private function parseWithPhpWord(\PhpOffice\PhpWord\PhpWord $phpWord): array
    {
        $items = [];
        foreach ($phpWord->getSections() as $section) {
            foreach ($section->getElements() as $element) {
                if ($element instanceof \PhpOffice\PhpWord\Element\Table) {
                    $rows = [];
                    foreach ($element->getRows() as $row) {
                        $cells = [];
                        foreach ($row->getCells() as $cell) {
                            $cellText = '';
                            foreach ($cell->getElements() as $cellEl) {
                                if ($cellEl instanceof \PhpOffice\PhpWord\Element\Text) {
                                    $cellText .= $cellEl->getText() . ' ';
                                } elseif ($cellEl instanceof \PhpOffice\PhpWord\Element\TextRun) {
                                    foreach ($cellEl->getElements() as $sub) {
                                        if ($sub instanceof \PhpOffice\PhpWord\Element\Text) {
                                            $cellText .= $sub->getText() . ' ';
                                        }
                                    }
                                }
                            }
                            $cells[] = trim($cellText);
                        }
                        if (!empty($cells) && array_filter($cells, fn($c) => trim($c) !== '')) {
                            $rows[] = $cells;
                        }
                    }

                    if (!empty($rows)) {
                        $isTf = false;
                        if (count($rows[0]) >= 2) {
                            $hdr = strtolower(implode(' ', $rows[0]));
                            if (str_contains($hdr, 'pernyataan') && (str_contains($hdr, 'tepat') || str_contains($hdr, 'benar'))) {
                                $isTf = true;
                            }
                        }
                        if ($isTf) {
                            $stmts = [];
                            for ($i = 1; $i < count($rows); $i++) {
                                if (!empty($rows[$i][0])) $stmts[] = trim($rows[$i][0]);
                            }
                            $items[] = ['type' => 'tbl_true_false', 'statements' => $stmts, 'rows' => $rows];
                        } else {
                            $htmlTable = "<div class=\"table-responsive my-3 overflow-x-auto\"><table class=\"soal-table w-full border-collapse border border-slate-300 text-sm\">";
                            foreach ($rows as $r) {
                                $htmlTable .= "<tr>";
                                foreach ($r as $c) {
                                    $htmlTable .= "<td class=\"border border-slate-300 px-3 py-1.5\">" . htmlspecialchars($c) . "</td>";
                                }
                                $htmlTable .= "</tr>";
                            }
                            $htmlTable .= "</table></div>";
                            $items[] = ['type' => 'tbl', 'html' => $htmlTable];
                        }
                    }
                } elseif ($element instanceof \PhpOffice\PhpWord\Element\TextRun) {
                    $t = '';
                    foreach ($element->getElements() as $child) {
                        if ($child instanceof \PhpOffice\PhpWord\Element\Text) {
                            $t .= $child->getText();
                        }
                    }
                    $normText = $this->normalizeText($t);
                    if ($normText !== '') {
                        if (preg_match('/^(.*?)(?:\s+|(?<=[^\s]))((?:Jawaban|Kunci\s*Jawaban|Kunci)\s*:\s*.*)$/iu', $normText, $m) && !empty($m[1])) {
                            $items[] = ['type' => 'p', 'text' => trim($m[1]), 'images' => []];
                            $items[] = ['type' => 'p', 'text' => trim($m[2]), 'images' => []];
                        } else {
                            $items[] = ['type' => 'p', 'text' => $normText, 'images' => []];
                        }
                    }
                } elseif ($element instanceof \PhpOffice\PhpWord\Element\Text) {
                    $normText = $this->normalizeText($element->getText());
                    if ($normText !== '') {
                        if (preg_match('/^(.*?)(?:\s+|(?<=[^\s]))((?:Jawaban|Kunci\s*Jawaban|Kunci)\s*:\s*.*)$/iu', $normText, $m) && !empty($m[1])) {
                            $items[] = ['type' => 'p', 'text' => trim($m[1]), 'images' => []];
                            $items[] = ['type' => 'p', 'text' => trim($m[2]), 'images' => []];
                        } else {
                            $items[] = ['type' => 'p', 'text' => $normText, 'images' => []];
                        }
                    }
                }
            }
        }

        return $this->convertItemsToQuestions($items);
    }

    /**
     * Fallback baris teks mentah / markdown
     */
    private function parseLinesToQuestions(array $lines): array
    {
        $items = [];
        $tableLines = [];

        foreach ($lines as $l) {
            $trimmed = trim($l);
            if ($trimmed === '') continue;

            if (str_starts_with($trimmed, '|')) {
                $tableLines[] = $trimmed;
            } else {
                if (!empty($tableLines)) {
                    $tblItem = $this->parseMarkdownTableLines($tableLines);
                    if ($tblItem) $items[] = $tblItem;
                    $tableLines = [];
                }

                $t = $this->normalizeText($trimmed);
                if ($t !== '') {
                    if (preg_match('/^(.*?)(?:\s+|(?<=[^\s]))((?:Jawaban|Kunci\s*Jawaban|Kunci)\s*:\s*.*)$/iu', $t, $m) && !empty($m[1])) {
                        $items[] = ['type' => 'p', 'text' => trim($m[1]), 'images' => []];
                        $items[] = ['type' => 'p', 'text' => trim($m[2]), 'images' => []];
                    } else {
                        $items[] = ['type' => 'p', 'text' => $t, 'images' => []];
                    }
                }
            }
        }

        if (!empty($tableLines)) {
            $tblItem = $this->parseMarkdownTableLines($tableLines);
            if ($tblItem) $items[] = $tblItem;
        }

        return $this->convertItemsToQuestions($items);
    }

    /**
     * Parse baris tabel markdown
     */
    private function parseMarkdownTableLines(array $tableLines): ?array
    {
        $rows = [];
        foreach ($tableLines as $tl) {
            $cols = array_values(array_filter(array_map('trim', explode('|', $tl)), fn($c) => $c !== ''));
            if (!empty($cols)) {
                $rows[] = $cols;
            }
        }

        if (empty($rows)) {
            return null;
        }

        $isTf = false;
        if (count($rows[0]) >= 2) {
            $hdr = strtolower(implode(' ', $rows[0]));
            if (str_contains($hdr, 'pernyataan') && (str_contains($hdr, 'tepat') || str_contains($hdr, 'benar'))) {
                $isTf = true;
            }
        }

        if ($isTf) {
            $stmts = [];
            for ($i = 1; $i < count($rows); $i++) {
                if (!empty($rows[$i][0])) $stmts[] = trim($rows[$i][0]);
            }
            return ['type' => 'tbl_true_false', 'statements' => $stmts, 'rows' => $rows];
        }

        $htmlTable = "<div class=\"table-responsive my-3 overflow-x-auto\"><table class=\"soal-table w-full border-collapse border border-slate-300 text-sm\">";
        foreach ($rows as $r) {
            $htmlTable .= "<tr>";
            foreach ($r as $c) {
                $htmlTable .= "<td class=\"border border-slate-300 px-3 py-1.5\">" . htmlspecialchars($c) . "</td>";
            }
            $htmlTable .= "</tr>";
        }
        $htmlTable .= "</table></div>";

        return ['type' => 'tbl', 'html' => $htmlTable];
    }
}
