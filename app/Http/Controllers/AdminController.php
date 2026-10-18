<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class AdminController extends Controller
{
    public function dashboard()
    {
        // ✅ Pagination 50 user per halaman — mencegah load ribuan record sekaligus
        // Tetap bisa di-loop di view dengan @foreach($users as $u)
        $users = User::select('id', 'name', 'username', 'email', 'role', 'grade_level', 'created_at')
            ->latest()
            ->paginate(50);

        // ✅ Cache role counts 3 menit — data ini jarang berubah
        $roleCounts = Cache::driver('file')->remember('admin_user_role_counts', 180, function () {
            return User::selectRaw("
                SUM(CASE WHEN role = 'murid' THEN 1 ELSE 0 END) as murid_count,
                SUM(CASE WHEN role = 'guru'  THEN 1 ELSE 0 END) as guru_count,
                SUM(CASE WHEN role = 'admin' THEN 1 ELSE 0 END) as admin_count
            ")->first();
        });

        $totalStudents = (int) ($roleCounts->murid_count ?? 0);
        $totalTeachers = (int) ($roleCounts->guru_count  ?? 0);
        $totalAdmins   = (int) ($roleCounts->admin_count ?? 0);

        // Ambil subjects & exams langsung dari database (menghindari incomplete object saat unserialize)
        $subjects = Subject::select('id', 'title', 'code', 'icon', 'teacher_id')
            ->with('teacher:id,name')
            ->get();

        $exams = Exam::select('id', 'subject_id', 'title', 'grade_level', 'status', 'created_by', 'created_at')
            ->with([
                'subject:id,title,code',
                'creator:id,name',
            ])
            ->latest()
            ->get();

        $attemptsCount = Cache::driver('file')->remember('admin_attempts_count', 120, fn () => ExamAttempt::count());

        return view('admin.dashboard', compact(
            'users',
            'totalStudents',
            'totalTeachers',
            'totalAdmins',
            'subjects',
            'exams',
            'attemptsCount'
        ));
    }

    public function storeUser(Request $request)
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'username' => 'required|string|max:255|unique:users,username',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,guru,murid',
            'grade_level' => 'nullable|string',
        ]);

        User::create([
            'name'        => strtolower($request->name) !== $request->name ? $request->name : ucwords($request->name),
            'username'    => strtolower($request->username),
            'email'       => strtolower($request->email),
            'password'    => Hash::make($request->password),
            'role'        => $request->role,
            'grade_level' => $request->grade_level ?: 'SMP Kelas 7',
        ]);

        // ✅ Invalidasi cache role counts
        Cache::driver('file')->forget('admin_user_role_counts');

        return redirect()->route('admin.dashboard')->with('success', 'Pengguna baru berhasil ditambahkan!');
    }

    public function destroyUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.dashboard')->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }

        $user->delete();

        // ✅ Invalidasi cache role counts setelah hapus user
        Cache::driver('file')->forget('admin_user_role_counts');

        return redirect()->route('admin.dashboard')->with('success', 'Pengguna berhasil dihapus.');
    }

    public function storeSubject(Request $request)
    {
        $request->validate([
            'title' => 'required|string|max:255',
            'code' => 'required|string|unique:subjects,code',
            'description' => 'nullable|string',
            'teacher_id' => 'nullable|exists:users,id',
        ]);

        Subject::create([
            'title' => $request->title,
            'code' => strtoupper($request->code),
            'description' => $request->description,
            'teacher_id' => $request->teacher_id,
        ]);

        return redirect()->route('admin.dashboard')->with('success', 'Mata Pelajaran berhasil ditambahkan!');
    }

    /**
     * Import users (murid & guru) from Excel / CSV spreadsheet
     */
    public function importUsers(Request $request)
    {
        $request->validate([
            'file' => 'required|file|mimes:csv,txt,xls,xlsx,html|max:10240',
            'default_role' => 'nullable|in:auto,murid,guru',
            'default_grade' => 'nullable|string',
        ]);

        $file = $request->file('file');
        $rows = $this->parseUploadedSpreadsheet($file->getRealPath(), $file->getClientOriginalName());

        if (empty($rows)) {
            return redirect()->route('admin.dashboard')->with('error', 'File kosong atau format kolom tidak dikenali. Pastikan terdapat baris judul kolom (header).');
        }

        $createdStudents = 0;
        $createdTeachers = 0;
        $updatedUsers = 0;
        $skippedRows = 0;

        $defaultRoleParam = $request->input('default_role', 'auto');
        $defaultGradeParam = $request->input('default_grade', 'SMP Kelas 7');

        foreach ($rows as $index => $row) {
            // Find key values irrespective of case/naming variations
            $name = $this->getFieldValue($row, ['name', 'nama', 'nama_lengkap', 'nama_siswa', 'nama_guru', 'full_name']);
            if (empty($name)) {
                $skippedRows++;
                continue;
            }

            $username = $this->getFieldValue($row, ['username', 'nisn', 'nip', 'user', 'id_pengguna', 'kode']);
            if (empty($username)) {
                // Generate username from name
                $base = strtolower(preg_replace('/[^a-z0-9]/', '', $name));
                $username = ($base ?: 'user') . rand(100, 999);
            } else {
                $username = strtolower(preg_replace('/[^a-z0-9_\-\.]/i', '', $username));
            }

            $email = $this->getFieldValue($row, ['email', 'e_mail', 'surel', 'alamat_email', 'mail']);
            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $email = $username . '@simulasiku.id';
            } else {
                $email = strtolower(trim($email));
            }

            $rawPassword = $this->getFieldValue($row, ['password', 'kata_sandi', 'pass', 'sandi', 'pin']);
            $password = !empty($rawPassword) ? $rawPassword : 'password123';

            $rawRole = strtolower($this->getFieldValue($row, ['role', 'peran', 'jabatan', 'tipe', 'tipe_akun', 'jenis_akun']));
            if ($defaultRoleParam === 'murid') {
                $role = 'murid';
            } elseif ($defaultRoleParam === 'guru') {
                $role = 'guru';
            } else {
                if (str_contains($rawRole, 'guru') || str_contains($rawRole, 'teacher') || str_contains($rawRole, 'pengajar')) {
                    $role = 'guru';
                } elseif (str_contains($rawRole, 'admin')) {
                    $role = 'admin';
                } else {
                    $role = 'murid';
                }
            }

            $gradeLevel = $this->getFieldValue($row, ['grade_level', 'jenjang', 'kelas', 'tingkat', 'tingkat_kelas']);
            if (empty($gradeLevel)) {
                $gradeLevel = $defaultGradeParam ?: 'SMP Kelas 7';
            }

            // Check if user already exists by username or email
            $existingUser = User::where('username', $username)
                ->orWhere('email', $email)
                ->first();

            if ($existingUser) {
                // Update existing user information
                $existingUser->update([
                    'name' => $name,
                    'role' => $role,
                    'grade_level' => $gradeLevel,
                    'password' => Hash::make($password),
                ]);
                $updatedUsers++;
            } else {
                // Create new user
                User::create([
                    'name' => $name,
                    'username' => $username,
                    'email' => $email,
                    'password' => Hash::make($password),
                    'role' => $role,
                    'grade_level' => $gradeLevel,
                ]);

                if ($role === 'guru') {
                    $createdTeachers++;
                } else {
                    $createdStudents++;
                }
            }
        }

        $msg = "Import berhasil! Ditambahkan: {$createdStudents} Siswa, {$createdTeachers} Guru. Diperbarui: {$updatedUsers} Akun.";
        if ($skippedRows > 0) {
            $msg .= " ({$skippedRows} baris dilewati karena nama kosong).";
        }

        return redirect()->route('admin.dashboard')->with('success', $msg);
    }

    /**
     * Download Excel / CSV template for bulk user import
     */
    public function downloadTemplate(Request $request)
    {
        $type = $request->query('format', 'xls');

        if ($type === 'csv') {
            $csvData = "\xEF\xBB\xBF"; // UTF-8 BOM
            $csvData .= "Nama Lengkap,Username,Email,Password,Role,Jenjang\n";
            $csvData .= "Ahmad Rizki Pratama,nisn1001,ahmad.rizki@sekolah.sch.id,password123,murid,SMP Kelas 7\n";
            $csvData .= "Siti Nurhaliza,nisn1002,siti.nurhaliza@sekolah.sch.id,password123,murid,SMP Kelas 7\n";
            $csvData .= "Budi Santoso S.Pd,nip198501,budi.santoso@sekolah.sch.id,guru12345,guru,ALL\n";
            $csvData .= "Dewi Sartika M.Pd,nip199002,dewi.sartika@sekolah.sch.id,guru12345,guru,ALL\n";

            return response($csvData, 200, [
                'Content-Type' => 'text/csv; charset=UTF-8',
                'Content-Disposition' => 'attachment; filename="Template_Import_Pengguna.csv"',
            ]);
        }

        // Default: Formatted Excel HTML table (.xls)
        $html = "<html xmlns:o='urn:schemas-microsoft-com:office:office' xmlns:x='urn:schemas-microsoft-com:office:excel' xmlns='http://www.w3.org/TR/REC-html40'>\n" .
            "<head><meta charset='utf-8'><style>\n" .
            "table { border-collapse: collapse; width: 100%; font-family: Calibri, sans-serif; font-size: 11pt; }\n" .
            "th { background-color: #2563eb; color: #ffffff; font-weight: bold; padding: 10px; border: 1px solid #1d4ed8; text-align: left; }\n" .
            "td { padding: 8px; border: 1px solid #cbd5e1; vertical-align: middle; }\n" .
            ".role-murid { background-color: #eff6ff; color: #1d4ed8; font-weight: bold; }\n" .
            ".role-guru { background-color: #f5f3ff; color: #6d28d9; font-weight: bold; }\n" .
            "</style></head><body>\n" .
            "<h2>TEMPLATE IMPORT AKUN SISWA & GURU</h2>\n" .
            "<p>Petunjuk: Isi data akun di bawah ini. Kolom <b>Role</b> dapat diisi <i>murid</i> atau <i>guru</i>. Password jika dikosongkan akan otomatis menjadi <b>password123</b>.</p>\n" .
            "<table border='1'>\n" .
            "<thead><tr>\n" .
            "<th>Nama Lengkap</th>\n" .
            "<th>Username</th>\n" .
            "<th>Email</th>\n" .
            "<th>Password</th>\n" .
            "<th>Role</th>\n" .
            "<th>Jenjang</th>\n" .
            "</tr></thead>\n" .
            "<tbody>\n" .
            "<tr><td>Ahmad Rizki Pratama</td><td>nisn1001</td><td>ahmad.rizki@sekolah.sch.id</td><td>password123</td><td class='role-murid'>murid</td><td>SMP Kelas 7</td></tr>\n" .
            "<tr><td>Siti Nurhaliza</td><td>nisn1002</td><td>siti.nurhaliza@sekolah.sch.id</td><td>password123</td><td class='role-murid'>murid</td><td>SMP Kelas 7</td></tr>\n" .
            "<tr><td>Budi Santoso S.Pd</td><td>nip198501</td><td>budi.santoso@sekolah.sch.id</td><td>guru12345</td><td class='role-guru'>guru</td><td>ALL</td></tr>\n" .
            "<tr><td>Dewi Sartika M.Pd</td><td>nip199002</td><td>dewi.sartika@sekolah.sch.id</td><td>guru12345</td><td class='role-guru'>guru</td><td>ALL</td></tr>\n" .
            "</tbody></table></body></html>";

        return response($html, 200, [
            'Content-Type' => 'application/vnd.ms-excel; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="Template_Import_Pengguna.xls"',
        ]);
    }

    /**
     * Helper to get field value case-insensitively from multiple candidate keys
     */
    private function getFieldValue(array $row, array $candidateKeys): string
    {
        foreach ($candidateKeys as $key) {
            if (isset($row[$key]) && trim((string)$row[$key]) !== '') {
                return trim((string)$row[$key]);
            }
        }
        return '';
    }

    /**
     * Parse spreadsheet file (CSV, TSV, HTML table, TXT)
     */
    private function parseUploadedSpreadsheet(string $filePath, string $originalName): array
    {
        $content = file_get_contents($filePath);
        if ($content === false || empty($content)) {
            return [];
        }

        // Strip UTF-8 BOM
        $content = preg_replace('/^\xEF\xBB\xBF/', '', $content);
        $rows = [];

        // If file is HTML Table / XML Spreadsheet (.xls)
        if (str_contains($content, '<table') || str_contains($content, '<tr')) {
            $dom = new \DOMDocument();
            @$dom->loadHTML('<?xml encoding="UTF-8">' . $content);
            $tables = $dom->getElementsByTagName('table');
            if ($tables->length > 0) {
                $trList = $tables->item(0)->getElementsByTagName('tr');
                $headers = [];
                foreach ($trList as $tr) {
                    $cells = [];
                    foreach ($tr->childNodes as $node) {
                        if (in_array(strtolower($node->nodeName), ['td', 'th'])) {
                            $cells[] = trim($node->textContent);
                        }
                    }
                    if (empty($cells)) continue;

                    if (empty($headers)) {
                        $headers = array_map(function($h) {
                            $clean = strtolower(trim($h));
                            $clean = preg_replace('/[^a-z0-9_]/', '_', $clean);
                            return trim($clean, '_');
                        }, $cells);
                    } else {
                        $row = [];
                        foreach ($headers as $colIdx => $colName) {
                            $row[$colName] = $cells[$colIdx] ?? '';
                        }
                        if (!empty(array_filter($row))) {
                            $rows[] = $row;
                        }
                    }
                }
                if (!empty($rows)) {
                    return $rows;
                }
            }
        }

        // Delimited lines parsing (CSV, TSV, Semicolon)
        $lines = preg_split('/\r\n|\r|\n/', trim($content));
        if (empty($lines)) return [];

        $firstLine = $lines[0];
        $delimiter = ',';
        if (substr_count($firstLine, ';') > substr_count($firstLine, ',')) {
            $delimiter = ';';
        } elseif (substr_count($firstLine, "\t") > substr_count($firstLine, ',')) {
            $delimiter = "\t";
        }

        $headerLine = array_shift($lines);
        $rawHeader = str_getcsv($headerLine, $delimiter);
        $headers = array_map(function($h) {
            $clean = strtolower(trim($h));
            $clean = preg_replace('/[^a-z0-9_]/', '_', $clean);
            return trim($clean, '_');
        }, $rawHeader);

        foreach ($lines as $line) {
            $trimmed = trim($line);
            if (empty($trimmed)) continue;
            $cells = str_getcsv($trimmed, $delimiter);
            if (empty($cells) || (count($cells) === 1 && empty($cells[0]))) continue;
            $row = [];
            foreach ($headers as $idx => $headerKey) {
                $row[$headerKey] = isset($cells[$idx]) ? trim((string)$cells[$idx]) : '';
            }
            if (!empty(array_filter($row))) {
                $rows[] = $row;
            }
        }

        return $rows;
    }
}
