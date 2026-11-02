@extends('layouts.cbt')

@section('content')
<div class="max-w-7xl mx-auto space-y-6 w-full">

    {{-- Header --}}
    <div class="flex flex-col sm:flex-row sm:items-center sm:justify-between gap-4">
        <div>
            <h1 class="text-2xl font-bold text-slate-800 flex items-center gap-2">
                <span>Review Hasil Import Soal</span>
                @if(($meta['source_type'] ?? '') === 'word')
                    <span class="text-xs px-2.5 py-1 bg-blue-100 text-blue-700 font-semibold rounded-lg">Format Word (.docx)</span>
                @else
                    <span class="text-xs px-2.5 py-1 bg-purple-100 text-purple-700 font-semibold rounded-lg">Format PDF</span>
                @endif
            </h1>
            <p class="text-slate-500 text-sm mt-1">
                Periksa dan lengkapi soal, gambar, dan tabel sebelum disimpan ke database.
                Nomor soal mengikuti urutan dokumen asli.
            </p>
        </div>
        <div class="flex gap-3 flex-shrink-0">
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-slate-100 text-slate-700 text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                {{ $totalSoal }} soal
            </span>
            @if($needsReviewCount > 0)
            <span class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full bg-amber-100 text-amber-700 text-sm font-medium">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 3a9 9 0 100 18A9 9 0 0012 3z"/></svg>
                {{ $needsReviewCount }} perlu dilengkapi
            </span>
            @endif
        </div>
    </div>

    {{-- Flash Info --}}
    @if(session('info'))
    <div class="flex items-start gap-3 p-4 bg-blue-50 border border-blue-200 rounded-xl text-blue-800 text-sm">
        <svg class="w-5 h-5 flex-shrink-0 mt-0.5" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M18 10a8 8 0 11-16 0 8 8 0 0116 0zm-7-4a1 1 0 11-2 0 1 1 0 012 0zM9 9a1 1 0 000 2v3a1 1 0 001 1h1a1 1 0 100-2v-3a1 1 0 00-1-1H9z" clip-rule="evenodd"/></svg>
        <span>{{ session('info') }}</span>
    </div>
    @endif

    {{-- Info paket ujian --}}
    <div class="bg-white border border-slate-200 rounded-xl p-5 shadow-sm">
        <h2 class="font-semibold text-slate-700 mb-3 text-sm uppercase tracking-wide">Informasi Paket Ujian</h2>
        <div class="grid grid-cols-2 md:grid-cols-4 gap-4 text-sm">
            <div>
                <span class="text-slate-400 block">Judul</span>
                <span class="font-medium text-slate-800">{{ $meta['title'] }}</span>
            </div>
            <div>
                <span class="text-slate-400 block">Jenjang</span>
                <span class="font-medium text-slate-800">{{ $meta['grade_level'] }}</span>
            </div>
            <div>
                <span class="text-slate-400 block">Durasi</span>
                <span class="font-medium text-slate-800">{{ $meta['duration_minutes'] }} menit</span>
            </div>
            <div>
                <span class="text-slate-400 block">KKM</span>
                <span class="font-medium text-slate-800">{{ $meta['passing_score'] }}</span>
            </div>
        </div>
    </div>

    {{-- Legend status --}}
    <div class="flex flex-wrap gap-3 text-xs">
        <span class="flex items-center gap-1.5 text-slate-500"><span class="w-3 h-3 rounded-full bg-emerald-400 inline-block"></span> Berhasil diekstrak</span>
        <span class="flex items-center gap-1.5 text-slate-500"><span class="w-3 h-3 rounded-full bg-amber-400 inline-block"></span> Perlu gambar / diagram / grafik</span>
        <span class="flex items-center gap-1.5 text-slate-500"><span class="w-3 h-3 rounded-full bg-orange-400 inline-block"></span> Perlu tabel / pernyataan</span>
        <span class="flex items-center gap-1.5 text-slate-500"><span class="w-3 h-3 rounded-full bg-slate-300 inline-block"></span> Perlu pemeriksaan manual</span>
    </div>

    {{-- Form review dengan enctype multipart untuk upload gambar --}}
    <form method="POST" action="{{ route('guru.exams.import.confirm') }}" enctype="multipart/form-data">
        @csrf
        <div class="space-y-5">
            @foreach($questionsData as $idx => $q)
            @php
                $qNum = $q['question_number'] ?? ($idx + 1);
                $hasReview = !empty($q['needs_review']);
                $reviewNote = $q['review_note'] ?? null;
                $isVisualImg = $hasReview && $reviewNote && (str_contains($reviewNote, 'GAMBAR') || str_contains($reviewNote, 'figure'));
                $isVisualDiag = $hasReview && $reviewNote && str_contains($reviewNote, 'DIAGRAM');
                $isVisualGraf = $hasReview && $reviewNote && str_contains($reviewNote, 'GRAFIK');
                $isVisualTbl = $hasReview && $reviewNote && str_contains($reviewNote, 'TABEL');
                $isVisualPrn = $hasReview && $reviewNote && str_contains($reviewNote, 'PERNYATAAN');

                $badgeColor = 'bg-emerald-100 text-emerald-700 border-emerald-200';
                $badgeText = 'Berhasil';
                $cardBorder = 'border-slate-200';

                if ($hasReview) {
                    if ($isVisualImg || $isVisualDiag || $isVisualGraf) {
                        $badgeColor = 'bg-amber-100 text-amber-700 border-amber-200';
                        $badgeText = $isVisualImg ? 'Perlu Gambar' : ($isVisualDiag ? 'Perlu Diagram' : 'Perlu Grafik');
                        $cardBorder = 'border-amber-300';
                    } elseif ($isVisualTbl || $isVisualPrn) {
                        $badgeColor = 'bg-orange-100 text-orange-700 border-orange-200';
                        $badgeText = $isVisualPrn ? 'Perlu Pernyataan' : 'Perlu Tabel';
                        $cardBorder = 'border-orange-300';
                    } else {
                        $badgeColor = 'bg-slate-100 text-slate-600 border-slate-300';
                        $badgeText = 'Perlu Diperiksa';
                        $cardBorder = 'border-slate-300';
                    }
                }
            @endphp

            <div class="bg-white border {{ $cardBorder }} rounded-xl shadow-sm overflow-hidden" id="card_question_{{ $idx }}">
                {{-- Header soal --}}
                <div class="flex items-center justify-between px-5 py-3 border-b {{ $cardBorder }} bg-slate-50">
                    <div class="flex items-center gap-3">
                        <span class="font-bold text-slate-700 text-sm">Soal #{{ $qNum }}</span>
                        <span class="text-xs px-2 py-0.5 rounded-full border font-medium {{ $badgeColor }}">{{ $badgeText }}</span>
                        @if(($q['type'] ?? '') === 'true_false_table')
                            <span class="text-xs text-teal-700 bg-teal-100 border border-teal-200 px-2.5 py-0.5 rounded-full font-bold">✅ Tepat / Tidak Tepat</span>
                        @elseif(($q['type'] ?? '') === 'multiple_select' || ($q['type'] ?? '') === 'complex_multiple_choice')
                            <span class="text-xs text-amber-700 bg-amber-100 border border-amber-200 px-2.5 py-0.5 rounded-full font-bold">☑️ PG Kompleks</span>
                        @else
                            <span class="text-xs text-blue-700 bg-blue-100 border border-blue-200 px-2.5 py-0.5 rounded-full font-bold">Pilihan Ganda</span>
                        @endif
                    </div>
                    <button type="button"
                        onclick="toggleEdit({{ $idx }})"
                        id="btn_toggle_{{ $idx }}"
                        class="text-xs text-blue-600 hover:text-blue-800 font-medium flex items-center gap-1 px-2.5 py-1 rounded-lg hover:bg-blue-50 transition">
                        <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg>
                        Edit & Lengkapi Soal
                    </button>
                </div>

                <div class="p-5 space-y-4">
                    {{-- Catatan review --}}
                    @if($hasReview && $reviewNote)
                    <div class="flex items-start gap-2 p-3 rounded-lg bg-amber-50 border border-amber-200 text-amber-800 text-xs">
                        <svg class="w-4 h-4 flex-shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01M12 3a9 9 0 100 18A9 9 0 0012 3z"/></svg>
                        <div>
                            <span class="font-semibold">Perlu dilengkapi secara manual:</span>
                            <span class="ml-1">{{ preg_replace('/\[.+?\]\s*/', '', $reviewNote) }}</span>
                        </div>
                    </div>
                    @endif

                    {{-- ── TAMPILAN VIEW (Sebelum/Sesudah diedit) ── --}}
                    <div id="view_{{ $idx }}" class="space-y-3">
                        <div>
                            <p class="text-xs text-slate-400 mb-1 font-semibold uppercase tracking-wider">Pertanyaan</p>
                            <div id="view_text_{{ $idx }}" class="text-slate-800 text-sm leading-relaxed soal-rich-content">
                                {!! str_contains($q['question_text'], '<table') ? $q['question_text'] : nl2br(e($q['question_text'])) !!}
                            </div>
                        </div>

                        {{-- Tampilan Gambar Soal di View --}}
                        <div id="view_image_container_{{ $idx }}" class="{{ !empty($q['image']) ? 'block' : 'hidden' }} my-3">
                            <p class="text-xs text-slate-400 mb-1 font-semibold uppercase tracking-wider">Gambar Soal:</p>
                            <div class="p-2 bg-slate-50 border border-slate-200 rounded-xl inline-block max-w-md">
                                <img id="view_image_img_{{ $idx }}"
                                     src="{{ !empty($q['image']) ? asset('storage/' . $q['image']) : '' }}"
                                     alt="Gambar Soal #{{ $qNum }}"
                                     class="max-h-60 max-w-full object-contain rounded-lg">
                            </div>
                        </div>
                    </div>

                    {{-- ── FORM EDIT SOAL (Tersembunyi, terbuka jika tombol Edit diklik) ── --}}
                    <div id="edit_{{ $idx }}" class="hidden space-y-4 pt-2 border-t border-slate-200">
                        {{-- Toolbar Editor: Tambah Gambar & Tambah Tabel --}}
                        <div class="flex flex-wrap items-center justify-between gap-2 bg-slate-50 p-2.5 rounded-xl border border-slate-200">
                            <span class="text-xs font-bold text-slate-700 flex items-center gap-1.5">
                                <svg class="w-4 h-4 text-blue-600" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg>
                                Editor Teks, Gambar & Tabel
                            </span>
                            <div class="flex items-center gap-2">
                                {{-- Tombol Tambah Tabel --}}
                                <button type="button"
                                    onclick="openTableModal('review_{{ $idx }}')"
                                    class="px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-indigo-50 text-indigo-700 hover:bg-indigo-100 border border-indigo-200 flex items-center gap-1.5 shadow-sm transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M3 14h18m-9-4v8m-7 4h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    Tambah / Edit Tabel
                                </button>

                                {{-- Tombol Tambah Gambar --}}
                                <button type="button"
                                    onclick="document.getElementById('review_file_image_{{ $idx }}').click()"
                                    class="px-2.5 py-1.5 text-xs font-semibold rounded-lg bg-purple-50 text-purple-700 hover:bg-purple-100 border border-purple-200 flex items-center gap-1.5 shadow-sm transition">
                                    <svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16l4.586-4.586a2 2 0 012.828 0L16 16m-2-2l1.586-1.586a2 2 0 012.828 0L20 14m-6-6h.01M6 20h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                                    Tambah Gambar
                                </button>
                            </div>
                        </div>

                        {{-- Hidden File Input untuk Gambar Soal --}}
                        <input type="file"
                               id="review_file_image_{{ $idx }}"
                               name="questions[{{ $idx }}][image]"
                               accept="image/jpeg,image/png,image/webp,image/jpg"
                               class="hidden"
                               onchange="handleReviewImageSelect(this, {{ $idx }})">

                        {{-- Box Preview & Manajemen Gambar di Editor --}}
                        <div id="review_edit_image_container_{{ $idx }}"
                             class="{{ !empty($q['image']) ? 'flex' : 'hidden' }} items-center gap-3 p-3 bg-purple-50 border border-purple-200 rounded-xl">
                            <img id="review_edit_image_preview_{{ $idx }}"
                                 src="{{ !empty($q['image']) ? asset('storage/' . $q['image']) : '' }}"
                                 alt="Preview Gambar Soal"
                                 class="w-16 h-16 object-contain rounded-lg border border-purple-200 bg-white">
                            <div class="flex-1 text-xs">
                                <span class="font-bold text-purple-900 block">🖼️ Gambar Soal Terlampir</span>
                                <span id="review_image_filename_{{ $idx }}" class="text-[11px] text-purple-700 block truncate max-w-xs">
                                    {{ !empty($q['image']) ? basename($q['image']) : 'Gambar siap disimpan' }}
                                </span>
                                <div class="flex items-center gap-3 mt-1.5">
                                    <button type="button"
                                        onclick="document.getElementById('review_file_image_{{ $idx }}').click()"
                                        class="text-blue-600 hover:text-blue-800 font-semibold underline">
                                        Ganti Gambar
                                    </button>
                                    <span class="text-slate-300">|</span>
                                    <button type="button"
                                        onclick="removeReviewImage({{ $idx }})"
                                        class="text-rose-600 hover:text-rose-800 font-semibold underline">
                                        Hapus Gambar
                                    </button>
                                </div>
                            </div>
                        </div>

                        {{-- Container Visual Table Editor --}}
                        <div id="table_builder_review_{{ $idx }}" class="hidden p-3.5 bg-indigo-50/50 border border-indigo-200 rounded-xl space-y-3">
                            <!-- Diisi secara dinamis oleh JavaScript Table Editor -->
                        </div>

                        {{-- Textarea Pertanyaan --}}
                        <div>
                            <label class="text-xs text-slate-500 font-medium block mb-1">
                                Teks Pertanyaan <span class="text-slate-400 font-normal">(bisa memuat paragraf, gambar, atau tabel)</span>
                            </label>
                            <textarea id="review_textarea_review_{{ $idx }}"
                                name="questions[{{ $idx }}][question_text]"
                                rows="4"
                                oninput="onQuestionTextInput('review_{{ $idx }}')"
                                class="w-full text-xs font-mono border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400 resize-y leading-relaxed">{{ $q['question_text'] }}</textarea>
                        </div>

                        {{-- Live Preview Tampilan Soal --}}
                        <div class="p-3 bg-slate-50 border border-slate-200 rounded-lg text-xs space-y-1.5">
                            <span class="text-[10px] font-bold text-slate-500 uppercase tracking-wider block">
                                👁️ Live Preview Tampilan Soal (Termasuk Tabel):
                            </span>
                            <div id="live_preview_review_{{ $idx }}" class="text-slate-800 text-sm leading-relaxed soal-rich-content bg-white p-3 rounded border border-slate-200">
                                {!! str_contains($q['question_text'], '<table') ? $q['question_text'] : nl2br(e($q['question_text'])) !!}
                            </div>
                        </div>

                        {{-- Textarea Pembahasan --}}
                        <div>
                            <label class="text-xs text-slate-500 font-medium block mb-1">Catatan / Pembahasan (opsional)</label>
                            <textarea name="questions[{{ $idx }}][explanation]"
                                rows="2"
                                placeholder="Tambahkan pembahasan atau catatan untuk soal ini..."
                                class="w-full text-xs border border-slate-300 rounded-lg px-3 py-2 focus:outline-none focus:ring-2 focus:ring-blue-400 resize-y">{{ $q['explanation'] ?? '' }}</textarea>
                        </div>

                        <div class="flex justify-end pt-1">
                            <button type="button" onclick="toggleEdit({{ $idx }})"
                                class="px-3 py-1.5 rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-100 text-xs font-medium">
                                Selesai Edit
                            </button>
                        </div>
                    </div>

                    {{-- ── OPSI JAWABAN (Pilihan Ganda / Kompleks / Matching / Urutan / TFT) ── --}}
                    @if(in_array($q['type'] ?? 'multiple_choice', ['multiple_choice', 'multiple_select']))
                    @if(!empty($q['option_a']) || !empty($q['option_b']))
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 pt-2">
                        @foreach(['a' => $q['option_a'], 'b' => $q['option_b'], 'c' => $q['option_c'], 'd' => $q['option_d']] as $letter => $opt)
                        @if(!empty($opt))
                        @php
                            $isCorrect = false;
                            if (($q['type'] ?? '') === 'multiple_select') {
                                $isCorrect = in_array($letter, $q['sequence_data'] ?? []);
                            } else {
                                $isCorrect = strtolower($q['correct_option'] ?? '') === $letter;
                            }
                        @endphp
                        <div class="flex items-start gap-2 text-sm px-3 py-2 rounded-lg {{ $isCorrect ? 'bg-emerald-50 border border-emerald-200' : 'bg-slate-50 border border-slate-200' }}">
                            <span class="font-bold {{ $isCorrect ? 'text-emerald-600' : 'text-slate-500' }} w-4 flex-shrink-0 uppercase">{{ $letter }}.</span>
                            <span class="{{ $isCorrect ? 'text-emerald-800' : 'text-slate-700' }}">{!! $opt !!}</span>
                            @if($isCorrect)
                            <svg class="w-4 h-4 text-emerald-500 flex-shrink-0 ml-auto" fill="currentColor" viewBox="0 0 20 20"><path fill-rule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clip-rule="evenodd"/></svg>
                            @endif
                        </div>
                        @endif
                        @endforeach
                    </div>
                    @else
                    <p class="text-xs text-slate-400 italic">Opsi jawaban tidak berhasil diekstrak otomatis. Edit soal dan lengkapi melalui dashboard setelah import.</p>
                    @endif

                    {{-- Info kunci multiple_select --}}
                    @if(($q['type'] ?? '') === 'multiple_select' && !empty($q['sequence_data']))
                    <p class="text-xs text-slate-500">Jawaban benar: <span class="font-semibold text-emerald-700">{{ strtoupper(implode(', ', $q['sequence_data'])) }}</span></p>
                    @endif

                    @elseif(($q['type'] ?? '') === 'true_false_table')
                    {{-- Tabel pernyataan Tepat/Tidak Tepat --}}
                    @if(!empty($q['pair_data']))
                    <div class="overflow-x-auto pt-2">
                        <table class="w-full text-sm border-collapse soal-table">
                            <thead>
                                <tr class="bg-slate-100">
                                    <th class="text-left px-3 py-2 border border-slate-200 font-medium text-slate-600">Pernyataan</th>
                                    <th class="px-3 py-2 border border-slate-200 font-medium text-slate-600 w-28 text-center">Jawaban</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach($q['pair_data'] as $row)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2 border border-slate-200 text-slate-700">{{ $row['statement'] ?? '-' }}</td>
                                    <td class="px-3 py-2 border border-slate-200 text-center">
                                        <span class="{{ ($row['answer'] ?? '') === 'benar' ? 'text-emerald-600 font-semibold' : 'text-rose-500 font-semibold' }}">
                                            {{ ($row['answer'] ?? '') === 'benar' ? 'Tepat' : 'Tidak Tepat' }}
                                        </span>
                                    </td>
                                </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    @else
                    <p class="text-xs text-orange-600 italic bg-orange-50 border border-orange-200 rounded px-3 py-2">
                        ⚠️ Pernyataan Tepat/Tidak Tepat belum berhasil diekstrak. Silakan lengkapi melalui dashboard setelah soal disimpan.
                    </p>
                    @endif

                    @elseif(($q['type'] ?? '') === 'matching' && !empty($q['pair_data']))
                    <div class="space-y-1 pt-2">
                        @foreach($q['pair_data'] as $pair)
                        <div class="flex items-center gap-3 text-sm text-slate-700">
                            <span class="text-slate-600">{{ $pair['left'] ?? '' }}</span>
                            <span class="text-slate-400">→</span>
                            <span class="font-medium">{{ $pair['right'] ?? '' }}</span>
                        </div>
                        @endforeach
                    </div>
                    @endif

                    {{-- Pembahasan yang sudah ada --}}
                    @if(!empty($q['explanation']) && !str_contains($q['explanation'] ?? '', '[PERLU'))
                    <div class="text-xs text-slate-500 bg-slate-50 px-3 py-2 rounded">
                        <span class="font-medium">Pembahasan:</span> {{ $q['explanation'] }}
                    </div>
                    @endif
                </div>
            </div>
            @endforeach
        </div>

        {{-- Tombol konfirmasi di bawah halaman --}}
        <div class="mt-8 flex flex-col sm:flex-row gap-3 justify-end sticky bottom-4 bg-white/95 backdrop-blur-sm p-4 rounded-2xl border border-slate-200 shadow-lg">
            <a href="{{ route('guru.dashboard') }}"
                class="px-6 py-3 rounded-xl border border-slate-300 text-slate-700 text-sm font-medium hover:bg-slate-50 text-center transition">
                Batalkan Import
            </a>
            <button type="submit"
                class="px-8 py-3 bg-emerald-600 hover:bg-emerald-700 text-white rounded-xl text-sm font-semibold shadow-sm transition flex items-center justify-center gap-2">
                <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                Simpan {{ $totalSoal }} Soal ke Database
            </button>
        </div>
    </form>
</div>

{{-- ── MODAL SETUP UKURAN TABEL ── --}}
<div id="modal_table_config" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/60 backdrop-blur-sm">
    <div class="bg-white border border-slate-200 w-full max-w-sm rounded-2xl p-5 shadow-2xl space-y-4">
        <div class="flex items-center justify-between border-b border-slate-100 pb-2.5">
            <h3 class="font-bold text-slate-800 text-sm flex items-center gap-2">
                <span>📊</span> Tentukan Ukuran Tabel
            </h3>
            <button type="button" onclick="closeTableModal()" class="text-slate-400 hover:text-slate-700 text-sm font-bold">✕</button>
        </div>
        <div class="grid grid-cols-2 gap-3 text-xs">
            <div class="space-y-1">
                <label class="font-semibold text-slate-600">Jumlah Baris:</label>
                <input type="number" id="modal_table_rows" min="1" max="25" value="3"
                       class="w-full border border-slate-300 rounded-lg px-2.5 py-2 text-center text-sm font-bold">
                <span class="text-[10px] text-slate-400 block">(Termasuk baris judul)</span>
            </div>
            <div class="space-y-1">
                <label class="font-semibold text-slate-600">Jumlah Kolom:</label>
                <input type="number" id="modal_table_cols" min="1" max="10" value="2"
                       class="w-full border border-slate-300 rounded-lg px-2.5 py-2 text-center text-sm font-bold">
                <span class="text-[10px] text-slate-400 block">(Maksimal 10 kolom)</span>
            </div>
        </div>
        <div class="flex justify-end gap-2 pt-2 border-t border-slate-100">
            <button type="button" onclick="closeTableModal()"
                    class="px-3 py-1.5 text-xs rounded-lg border border-slate-300 text-slate-600 hover:bg-slate-50 font-medium">
                Batal
            </button>
            <button type="button" onclick="confirmCreateTable()"
                    class="px-4 py-1.5 text-xs rounded-lg bg-indigo-600 hover:bg-indigo-700 text-white font-semibold">
                Sisipkan Tabel
            </button>
        </div>
    </div>
</div>

{{-- ── STYLESHEET TABEL SOAL ── --}}
<style>
.soal-rich-content table, .soal-table {
    width: 100%;
    margin: 10px 0;
    border-collapse: collapse;
    font-size: 0.875rem;
    line-height: 1.25rem;
}
.soal-rich-content th, .soal-table th {
    background-color: #f1f5f9;
    color: #1e293b;
    font-weight: 700;
    padding: 8px 12px;
    border: 1px solid #cbd5e1;
    text-align: center;
}
.soal-rich-content td, .soal-table td {
    padding: 8px 12px;
    border: 1px solid #e2e8f0;
    text-align: center;
    color: #334155;
    background-color: #ffffff;
}
</style>

{{-- ── JAVASCRIPT TABLE BUILDER & IMAGE HANDLER ── --}}
<script>
// Toggle Edit Form vs View
function toggleEdit(idx) {
    const view = document.getElementById('view_' + idx);
    const edit = document.getElementById('edit_' + idx);
    const btn = document.getElementById('btn_toggle_' + idx);
    
    if (view && edit) {
        view.classList.toggle('hidden');
        edit.classList.toggle('hidden');
        
        if (!edit.classList.contains('hidden')) {
            // Ketika form edit dibuka, inisialisasi table builder jika sudah ada tabel
            initTableBuilderForContext('review_' + idx);
            updateLivePreview('review_' + idx);
            if (btn) btn.innerHTML = '✕ Tutup Edit';
        } else {
            if (btn) btn.innerHTML = '<svg class="w-3.5 h-3.5" fill="none" stroke="currentColor" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/></svg> Edit & Lengkapi Soal';
        }
    }
}

// ── MANAJEMEN GAMBAR DI HALAMAN REVIEW ──
function handleReviewImageSelect(input, idx) {
    if (input.files && input.files[0]) {
        const file = input.files[0];
        if (file.size > 512000) {
            alert('Ukuran file gambar melebihi batas maksimal 500 KB.');
            input.value = '';
            return;
        }

        const url = URL.createObjectURL(file);
        
        // Update di dalam panel edit
        const editContainer = document.getElementById('review_edit_image_container_' + idx);
        const editPreview = document.getElementById('review_edit_image_preview_' + idx);
        const filename = document.getElementById('review_image_filename_' + idx);
        if (editPreview) editPreview.src = url;
        if (filename) filename.textContent = file.name + ' (' + Math.round(file.size / 1024) + ' KB)';
        if (editContainer) {
            editContainer.classList.remove('hidden');
            editContainer.classList.add('flex');
        }

        // Update tampilan view
        const viewContainer = document.getElementById('view_image_container_' + idx);
        const viewImg = document.getElementById('view_image_img_' + idx);
        if (viewImg) viewImg.src = url;
        if (viewContainer) viewContainer.classList.remove('hidden');
    }
}

function removeReviewImage(idx) {
    const input = document.getElementById('review_file_image_' + idx);
    if (input) input.value = '';

    const editContainer = document.getElementById('review_edit_image_container_' + idx);
    if (editContainer) {
        editContainer.classList.add('hidden');
        editContainer.classList.remove('flex');
    }

    const viewContainer = document.getElementById('view_image_container_' + idx);
    if (viewContainer) viewContainer.classList.add('hidden');
}

// ── UNIVERSAL VISUAL TABLE BUILDER ──
let activeTableContext = null;
const tableDataStore = {}; // Menyimpan data tabel per contextId

function openTableModal(contextId) {
    activeTableContext = contextId;
    const textarea = document.getElementById('review_textarea_' + contextId);
    if (textarea && /<table[\s\S]*?<\/table>/i.test(textarea.value)) {
        // Jika tabel sudah ada, langsung tampilkan editor tabel
        initTableBuilderForContext(contextId);
        const builder = document.getElementById('table_builder_' + contextId);
        if (builder) builder.scrollIntoView({ behavior: 'smooth', block: 'nearest' });
        return;
    }
    document.getElementById('modal_table_config').classList.remove('hidden');
}

function closeTableModal() {
    document.getElementById('modal_table_config').classList.add('hidden');
}

function confirmCreateTable() {
    if (!activeTableContext) return;
    const rows = parseInt(document.getElementById('modal_table_rows').value) || 3;
    const cols = parseInt(document.getElementById('modal_table_cols').value) || 2;
    closeTableModal();

    // Bangun grid data tabel kosong
    const grid = [];
    for (let r = 0; r < rows; r++) {
        const row = [];
        for (let c = 0; c < cols; c++) {
            row.push(r === 0 ? ('Judul ' + (c + 1)) : '');
        }
        grid.push(row);
    }

    tableDataStore[activeTableContext] = grid;

    // Sisipkan tabel HTML ke posisi kursor di textarea
    insertTableIntoTextarea(activeTableContext, buildHtmlTable(grid));
    renderTableBuilder(activeTableContext);
    updateLivePreview(activeTableContext);
}

function parseTableFromHtml(html) {
    const parser = new DOMParser();
    const doc = parser.parseFromString(html, 'text/html');
    const table = doc.querySelector('table');
    if (!table) return null;

    const grid = [];
    table.querySelectorAll('tr').forEach(tr => {
        const row = [];
        tr.querySelectorAll('th, td').forEach(cell => {
            row.push(cell.textContent.trim());
        });
        if (row.length > 0) grid.push(row);
    });
    return grid.length > 0 ? grid : null;
}

function buildHtmlTable(grid) {
    if (!grid || grid.length === 0) return '';
    let html = '<div class="table-responsive my-3 overflow-x-auto">\n';
    html += '  <table class="soal-table w-full border-collapse border border-slate-300 text-sm">\n';
    html += '    <thead>\n      <tr class="bg-slate-100 font-bold">\n';
    (grid[0] || []).forEach(h => {
        html += '        <th class="border border-slate-300 px-3 py-2 text-center">' + escapeHtml(h) + '</th>\n';
    });
    html += '      </tr>\n    </thead>\n    <tbody>\n';
    for (let r = 1; r < grid.length; r++) {
        html += '      <tr>\n';
        (grid[r] || []).forEach(cell => {
            html += '        <td class="border border-slate-300 px-3 py-2 text-center">' + escapeHtml(cell) + '</td>\n';
        });
        html += '      </tr>\n';
    }
    html += '    </tbody>\n  </table>\n</div>';
    return html;
}

function escapeHtml(str) {
    return String(str || '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');
}

function insertTableIntoTextarea(contextId, tableHtml) {
    const textarea = document.getElementById('review_textarea_' + contextId);
    if (!textarea) return;

    const val = textarea.value;
    const tableRegex = /<div class="table-responsive[\s\S]*?<\/div>|<table[\s\S]*?<\/table>/i;

    if (tableRegex.test(val)) {
        // Ganti tabel yang sudah ada
        textarea.value = val.replace(tableRegex, tableHtml);
    } else {
        // Sisipkan pada posisi kursor atau di akhir teks
        const start = textarea.selectionStart;
        const end = textarea.selectionEnd;
        if (start !== undefined && end !== undefined && start !== end) {
            textarea.value = val.substring(0, start) + '\n\n' + tableHtml + '\n\n' + val.substring(end);
        } else if (start !== undefined) {
            textarea.value = val.substring(0, start) + '\n\n' + tableHtml + '\n\n' + val.substring(start);
        } else {
            textarea.value = val.trim() + '\n\n' + tableHtml;
        }
    }
}

function initTableBuilderForContext(contextId) {
    const textarea = document.getElementById('review_textarea_' + contextId);
    if (!textarea) return;

    const val = textarea.value;
    const tableRegex = /<table[\s\S]*?<\/table>/i;
    const match = val.match(tableRegex);

    if (match) {
        const grid = parseTableFromHtml(match[0]);
        if (grid) {
            tableDataStore[contextId] = grid;
            renderTableBuilder(contextId);
            return;
        }
    }
    const builder = document.getElementById('table_builder_' + contextId);
    if (builder) builder.classList.add('hidden');
}

function renderTableBuilder(contextId) {
    const builder = document.getElementById('table_builder_' + contextId);
    if (!builder) return;

    const grid = tableDataStore[contextId];
    if (!grid || grid.length === 0) {
        builder.classList.add('hidden');
        return;
    }

    builder.classList.remove('hidden');

    let html = `
        <div class="flex flex-wrap items-center justify-between gap-2 border-b border-indigo-200 pb-2">
            <span class="text-xs font-bold text-indigo-900 flex items-center gap-1.5">
                <span>📊</span> Editor Visual Tabel Soal
                <span class="text-[10px] text-indigo-600 font-normal">(${grid.length} baris × ${grid[0].length} kolom)</span>
            </span>
            <div class="flex items-center gap-1 flex-wrap">
                <button type="button" onclick="addTableRow('${contextId}')" class="px-2 py-1 text-[11px] font-semibold bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-50 rounded">+ Baris</button>
                <button type="button" onclick="removeTableRow('${contextId}')" class="px-2 py-1 text-[11px] font-semibold bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-50 rounded">- Baris</button>
                <button type="button" onclick="addTableCol('${contextId}')" class="px-2 py-1 text-[11px] font-semibold bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-50 rounded">+ Kolom</button>
                <button type="button" onclick="removeTableCol('${contextId}')" class="px-2 py-1 text-[11px] font-semibold bg-white border border-indigo-200 text-indigo-700 hover:bg-indigo-50 rounded">- Kolom</button>
                <button type="button" onclick="deleteTable('${contextId}')" class="px-2 py-1 text-[11px] font-semibold bg-rose-50 border border-rose-200 text-rose-700 hover:bg-rose-100 rounded">🗑️ Hapus Tabel</button>
            </div>
        </div>
        <div class="overflow-x-auto max-h-64 overflow-y-auto">
            <table class="w-full border-collapse border border-indigo-200 text-xs">
                <tbody>
    `;

    grid.forEach((row, rIdx) => {
        const isHeader = rIdx === 0;
        html += `<tr class="${isHeader ? 'bg-indigo-100/70 font-bold' : (rIdx % 2 === 0 ? 'bg-white' : 'bg-indigo-50/30')}">`;
        row.forEach((cell, cIdx) => {
            html += `
                <td class="p-1 border border-indigo-200">
                    <input type="text"
                           value="${escapeHtml(cell)}"
                           placeholder="${isHeader ? 'Judul Kolom' : 'Isi sel...'}"
                           oninput="updateTableCell('${contextId}', ${rIdx}, ${cIdx}, this.value)"
                           class="w-full text-xs px-2 py-1 border border-transparent focus:border-indigo-400 focus:bg-white rounded focus:outline-none ${isHeader ? 'font-bold text-indigo-900 bg-transparent' : 'text-slate-800 bg-transparent'}">
                </td>
            `;
        });
        html += `</tr>`;
    });

    html += `
                </tbody>
            </table>
        </div>
    `;

    builder.innerHTML = html;
}

function updateTableCell(contextId, rIdx, cIdx, val) {
    if (!tableDataStore[contextId]) return;
    tableDataStore[contextId][rIdx][cIdx] = val;
    syncTableToTextarea(contextId);
}

function addTableRow(contextId) {
    if (!tableDataStore[contextId]) return;
    const grid = tableDataStore[contextId];
    const cols = grid[0] ? grid[0].length : 2;
    const newRow = new Array(cols).fill('');
    grid.push(newRow);
    renderTableBuilder(contextId);
    syncTableToTextarea(contextId);
}

function removeTableRow(contextId) {
    if (!tableDataStore[contextId]) return;
    const grid = tableDataStore[contextId];
    if (grid.length <= 1) {
        alert('Tabel minimal harus memiliki 1 baris.');
        return;
    }
    grid.pop();
    renderTableBuilder(contextId);
    syncTableToTextarea(contextId);
}

function addTableCol(contextId) {
    if (!tableDataStore[contextId]) return;
    const grid = tableDataStore[contextId];
    if (grid[0] && grid[0].length >= 10) {
        alert('Maksimal 10 kolom.');
        return;
    }
    grid.forEach((row, rIdx) => {
        row.push(rIdx === 0 ? ('Kolom ' + (row.length + 1)) : '');
    });
    renderTableBuilder(contextId);
    syncTableToTextarea(contextId);
}

function removeTableCol(contextId) {
    if (!tableDataStore[contextId]) return;
    const grid = tableDataStore[contextId];
    if (grid[0] && grid[0].length <= 1) {
        alert('Tabel minimal harus memiliki 1 kolom.');
        return;
    }
    grid.forEach(row => row.pop());
    renderTableBuilder(contextId);
    syncTableToTextarea(contextId);
}

function deleteTable(contextId) {
    if (!confirm('Apakah Anda yakin ingin menghapus tabel dari soal ini?')) return;
    delete tableDataStore[contextId];

    const textarea = document.getElementById('review_textarea_' + contextId);
    if (textarea) {
        textarea.value = textarea.value.replace(/<div class="table-responsive[\s\S]*?<\/div>|<table[\s\S]*?<\/table>/gi, '').trim();
    }
    const builder = document.getElementById('table_builder_' + contextId);
    if (builder) builder.classList.add('hidden');
    updateLivePreview(contextId);
}

function syncTableToTextarea(contextId) {
    const grid = tableDataStore[contextId];
    if (!grid) return;
    const tableHtml = buildHtmlTable(grid);
    insertTableIntoTextarea(contextId, tableHtml);
    updateLivePreview(contextId);
}

function onQuestionTextInput(contextId) {
    initTableBuilderForContext(contextId);
    updateLivePreview(contextId);
}

function updateLivePreview(contextId) {
    const textarea = document.getElementById('review_textarea_' + contextId);
    const preview = document.getElementById('live_preview_' + contextId);
    const viewText = document.getElementById('view_text_' + contextId.replace('review_', ''));

    if (textarea) {
        const val = textarea.value;
        const formatted = /<table[\s\S]*?>/i.test(val) ? val : val.replace(/\n/g, '<br>');
        if (preview) preview.innerHTML = formatted;
        if (viewText) viewText.innerHTML = formatted;
    }
}
</script>
@endsection
