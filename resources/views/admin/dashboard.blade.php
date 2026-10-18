@extends('layouts.cbt')

@section('content')
<div class="max-w-7xl mx-auto space-y-8 w-full">

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-zinc-900/80 border border-slate-200 dark:border-zinc-800 p-6 rounded-3xl backdrop-blur-md shadow-sm transition-colors">
        <div>
            <div class="flex items-center gap-2 text-emerald-600 dark:text-emerald-400 font-bold text-xs mb-1">
                <span class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-950 border border-emerald-300 dark:border-emerald-800 rounded">Admin Portal</span>
                <span>Manajemen Sistem & Pengguna</span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">Kelola Akun & Mata Pelajaran</h1>
            <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1">Tambah akun siswa, guru, admin, serta kelola struktur mata pelajaran.</p>
        </div>

        <div class="flex flex-wrap items-center gap-2.5">
            <button onclick="document.getElementById('modal-import-user').classList.remove('hidden')" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl transition shadow-lg shadow-emerald-600/20 flex items-center gap-2">
                <span>📥</span> Import Excel / CSV
            </button>
            <button onclick="document.getElementById('modal-user').classList.remove('hidden')" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl transition shadow-lg shadow-blue-600/20 flex items-center gap-2">
                <span>+</span> Tambah Akun
            </button>
            <button onclick="document.getElementById('modal-subject').classList.remove('hidden')" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl transition shadow-lg shadow-purple-600/20 flex items-center gap-2">
                <span>+</span> Tambah Mata Pelajaran
            </button>
        </div>
    </div>

    <!-- Alert Success / Error -->
    @if(session('success'))
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-700 dark:text-emerald-400 text-sm font-semibold rounded-2xl flex items-center gap-2">
            <span>✓</span> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-700 dark:text-rose-400 text-sm font-semibold rounded-2xl flex items-center gap-2">
            <span>⚠</span> {{ session('error') }}
        </div>
    @endif

    <!-- System Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-zinc-900/60 border border-slate-200 dark:border-zinc-800/80 p-5 rounded-2xl shadow-sm">
            <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400 block mb-1">Total Siswa (Murid)</span>
            <div class="text-2xl font-black text-blue-600 dark:text-blue-400">{{ $totalStudents }}</div>
        </div>
        <div class="bg-white dark:bg-zinc-900/60 border border-slate-200 dark:border-zinc-800/80 p-5 rounded-2xl shadow-sm">
            <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400 block mb-1">Total Guru (Pengajar)</span>
            <div class="text-2xl font-black text-purple-600 dark:text-purple-400">{{ $totalTeachers }}</div>
        </div>
        <div class="bg-white dark:bg-zinc-900/60 border border-slate-200 dark:border-zinc-800/80 p-5 rounded-2xl shadow-sm">
            <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400 block mb-1">Total Administrator</span>
            <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $totalAdmins }}</div>
        </div>
        <div class="bg-white dark:bg-zinc-900/60 border border-slate-200 dark:border-zinc-800/80 p-5 rounded-2xl shadow-sm">
            <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400 block mb-1">Total Pengerjaan Ujian</span>
            <div class="text-2xl font-black text-amber-600 dark:text-amber-400">{{ $attemptsCount }}</div>
        </div>
    </div>

    <!-- Main Content Tables Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- User Accounts Management Table (2 Cols) -->
        <div class="lg:col-span-2 space-y-4 bg-white dark:bg-zinc-900/60 border border-slate-200 dark:border-zinc-800 p-6 rounded-3xl shadow-sm">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center justify-between">
                <span class="flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                    Daftar Akun Terdaftar dalam Sistem
                </span>
                <span class="text-xs font-normal text-slate-500 dark:text-zinc-400">Total: {{ $users->total() }} User</span>
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-slate-700 dark:text-zinc-300">
                    <thead class="bg-slate-100 dark:bg-[#141416] text-slate-600 dark:text-zinc-400 uppercase font-semibold text-[11px] border-b border-slate-200 dark:border-zinc-800">
                        <tr>
                            <th class="p-3">Nama</th>
                            <th class="p-3">Username</th>
                            <th class="p-3">Role</th>
                            <th class="p-3">Jenjang</th>
                            <th class="p-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-200 dark:divide-zinc-800/60">
                        @foreach($users as $u)
                            <tr class="hover:bg-slate-50 dark:hover:bg-zinc-800/30 transition">
                                <td class="p-3">
                                    <div class="font-bold text-slate-900 dark:text-white">{{ $u->name }}</div>
                                    <div class="text-[11px] text-slate-500 dark:text-zinc-500">{{ $u->email }}</div>
                                </td>
                                <td class="p-3 font-mono text-blue-600 dark:text-blue-400 font-semibold">{{ $u->username }}</td>
                                <td class="p-3">
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold rounded uppercase
                                        {{ $u->role === 'admin' ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800' : ($u->role === 'guru' ? 'bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-400 border border-purple-300 dark:border-purple-800' : 'bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-400 border border-blue-300 dark:border-blue-800') }}">
                                        {{ $u->role }}
                                    </span>
                                </td>
                                <td class="p-3 text-slate-600 dark:text-zinc-400">{{ $u->grade_level ?: '-' }}</td>
                                <td class="p-3 text-right">
                                    @if($u->id !== Auth::id())
                                        <form action="{{ route('admin.users.destroy', $u->id) }}" method="POST" class="inline" onsubmit="return confirm('Hapus akun pengguna ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-600 dark:text-rose-400 hover:text-rose-700 font-semibold bg-rose-50 dark:bg-rose-950/50 px-2 py-1 rounded border border-rose-200 dark:border-rose-900/50">
                                                Hapus
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-[10px] text-slate-400 dark:text-zinc-500 italic">Akun Anda</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>

            {{-- Pagination (tampil hanya jika total > 50) --}}
            @if($users->hasPages())
                <div class="mt-4 flex items-center justify-between text-xs text-slate-500 dark:text-zinc-400">
                    <span>Menampilkan {{ $users->firstItem() }}–{{ $users->lastItem() }} dari {{ $users->total() }} user</span>
                    <div class="flex items-center gap-1">
                        @if($users->onFirstPage())
                            <span class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-400 dark:text-zinc-600 cursor-not-allowed">‹ Prev</span>
                        @else
                            <a href="{{ $users->previousPageUrl() }}" class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 transition font-semibold">‹ Prev</a>
                        @endif

                        <span class="px-3 py-1.5 rounded-lg bg-blue-600 text-white font-bold">{{ $users->currentPage() }}</span>

                        @if($users->hasMorePages())
                            <a href="{{ $users->nextPageUrl() }}" class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-zinc-800 hover:bg-slate-200 dark:hover:bg-zinc-700 transition font-semibold">Next ›</a>
                        @else
                            <span class="px-3 py-1.5 rounded-lg bg-slate-100 dark:bg-zinc-800 text-slate-400 dark:text-zinc-600 cursor-not-allowed">Next ›</span>
                        @endif
                    </div>
                </div>
            @endif
        </div>

        <!-- Subject Management Column (1 Col) -->
        <div class="space-y-4 bg-white dark:bg-zinc-900/60 border border-slate-200 dark:border-zinc-800 p-6 rounded-3xl shadow-sm">
            <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span>
                Daftar Pelajaran
            </h2>

            <div class="space-y-3">
                @foreach($subjects as $subj)
                    <div class="bg-slate-50 dark:bg-[#141416] border border-slate-200 dark:border-zinc-800 rounded-2xl p-4 space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-slate-900 dark:text-white text-sm">{{ $subj->title }}</span>
                            <span class="px-2 py-0.5 bg-slate-200 dark:bg-zinc-800 text-blue-600 dark:text-blue-400 font-mono font-bold rounded">{{ $subj->code }}</span>
                        </div>
                        <p class="text-slate-600 dark:text-zinc-400">{{ $subj->description }}</p>
                        <div class="pt-2 border-t border-slate-200 dark:border-zinc-800/80 text-[11px] text-slate-500 dark:text-zinc-500">
                            Pengajar: <span class="text-slate-800 dark:text-zinc-300 font-semibold">{{ $subj->teacher->name ?? 'Belum Ditentukan' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

</div>

<!-- Modal Add User -->
<div id="modal-user" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div class="bg-white dark:bg-[#141416] border border-slate-200 dark:border-zinc-800 w-full max-w-md rounded-3xl p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-zinc-800 pb-3">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Tambah Akun Pengguna Baru</h3>
            <button onclick="document.getElementById('modal-user').classList.add('hidden')" class="text-slate-400 hover:text-slate-900 dark:hover:text-white">✕</button>
        </div>
        <form action="{{ route('admin.users.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Nama Lengkap</label>
                <input type="text" name="name" required placeholder="Siswa / Guru Baru" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Username</label>
                    <input type="text" name="username" required placeholder="siswa2027" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Role</label>
                    <select name="role" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
                        <option value="murid">Murid (Siswa)</option>
                        <option value="guru">Guru (Pengajar)</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Email</label>
                <input type="email" name="email" required placeholder="user@simulasiku.id" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Password</label>
                    <input type="password" name="password" required placeholder="••••••••" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Jenjang / Kelas</label>
                    <select name="grade_level" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
                        <option value="SD Kelas 4">SD Kelas 4</option>
                        <option value="SD Kelas 5">SD Kelas 5</option>
                        <option value="SMP Kelas 7">SMP Kelas 7</option>
                        <option value="SMP Kelas 8">SMP Kelas 8</option>
                        <option value="ALL">ALL (Semua Jenjang)</option>
                    </select>
                </div>
            </div>
            <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-blue-600/20">
                Simpan Akun
            </button>
        </form>
    </div>
</div>

<!-- Modal Add Subject -->
<div id="modal-subject" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div class="bg-white dark:bg-[#141416] border border-slate-200 dark:border-zinc-800 w-full max-w-md rounded-3xl p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-zinc-800 pb-3">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Tambah Mata Pelajaran</h3>
            <button onclick="document.getElementById('modal-subject').classList.add('hidden')" class="text-slate-400 hover:text-slate-900 dark:hover:text-white">✕</button>
        </div>
        <form action="{{ route('admin.subjects.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Nama Pelajaran</label>
                <input type="text" name="title" required placeholder="Contoh: Fisika Terapan" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Kode Pelajaran</label>
                    <input type="text" name="code" required placeholder="FSK" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Guru Pengampu</label>
                    <select name="teacher_id" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
                        <option value="">- Pilih Guru -</option>
                        @foreach($users->where('role', 'guru') as $g)
                            <option value="{{ $g->id }}">{{ $g->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Deskripsi Ringkas</label>
                <textarea name="description" rows="2" placeholder="Penjelasan singkat mata pelajaran..." class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white"></textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-purple-600/20">
                Simpan Mata Pelajaran
            </button>
        </form>
    </div>
</div>

<!-- Modal Import Users (Excel / CSV) -->
<div id="modal-import-user" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div class="bg-white dark:bg-[#141416] border border-slate-200 dark:border-zinc-800 w-full max-w-lg rounded-3xl p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-zinc-800 pb-3">
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="text-emerald-600 dark:text-emerald-400">📊</span> Import Akun Siswa & Guru
                </h3>
                <p class="text-xs text-slate-500 dark:text-zinc-400">Unggah file spreadsheet (Excel / CSV) untuk menambahkan banyak pengguna sekaligus.</p>
            </div>
            <button onclick="document.getElementById('modal-import-user').classList.add('hidden')" class="text-slate-400 hover:text-slate-900 dark:hover:text-white text-lg">✕</button>
        </div>

        <!-- Download Template Options -->
        <div class="p-3.5 bg-emerald-50/60 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-900/40 rounded-2xl space-y-2">
            <span class="text-xs font-bold text-emerald-800 dark:text-emerald-300 flex items-center gap-1.5">
                <span>📄</span> Belum punya format tabel? Unduh template contoh:
            </span>
            <div class="flex items-center gap-2 pt-1">
                <a href="{{ route('admin.users.template', ['format' => 'xls']) }}" class="px-3 py-1.5 bg-emerald-600 hover:bg-emerald-500 text-white text-xs font-bold rounded-lg transition shadow-xs flex items-center gap-1.5">
                    <span>📊</span> Unduh Template Excel (.xls)
                </a>
                <a href="{{ route('admin.users.template', ['format' => 'csv']) }}" class="px-3 py-1.5 bg-slate-200 dark:bg-zinc-800 hover:bg-slate-300 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 text-xs font-bold rounded-lg transition flex items-center gap-1.5">
                    <span>📄</span> Unduh Template CSV
                </a>
            </div>
        </div>

        <form action="{{ route('admin.users.import') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            
            <div class="space-y-1.5">
                <label class="text-xs font-bold text-slate-700 dark:text-zinc-300">Pilih File Spreadsheet (Excel / CSV)</label>
                <input type="file" name="file" required accept=".csv, .xls, .xlsx, text/csv, application/vnd.ms-excel"
                       class="block w-full text-xs text-slate-600 dark:text-zinc-400 file:mr-3 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-emerald-600 file:text-white hover:file:bg-emerald-500 transition cursor-pointer border border-slate-300 dark:border-zinc-800 rounded-xl p-1 bg-slate-50 dark:bg-zinc-900">
                <span class="text-[10px] text-slate-400 dark:text-zinc-500 block">Mendukung format: .xlsx, .xls, .csv, dan .txt</span>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Peran Akun (Role)</label>
                    <select name="default_role" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white">
                        <option value="auto">Otomatis dari Kolom 'Role'</option>
                        <option value="murid">Paksa Semua Sebagai Murid</option>
                        <option value="guru">Paksa Semua Sebagai Guru</option>
                    </select>
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Default Jenjang (Jika Kosong)</label>
                    <select name="default_grade" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white">
                        <option value="SMP Kelas 7">SMP Kelas 7</option>
                        <option value="SMP Kelas 8">SMP Kelas 8</option>
                        <option value="SD Kelas 4">SD Kelas 4</option>
                        <option value="SD Kelas 5">SD Kelas 5</option>
                        <option value="ALL">ALL (Semua Jenjang)</option>
                    </select>
                </div>
            </div>

            <!-- Petunjuk Kolom -->
            <div class="p-3 bg-slate-50 dark:bg-zinc-900/60 border border-slate-200 dark:border-zinc-800 rounded-xl space-y-1.5 text-[11px] text-slate-600 dark:text-zinc-400">
                <span class="font-bold text-slate-700 dark:text-zinc-300 block">💡 Ketentuan Nama Kolom Header:</span>
                <p>• <b>Nama Lengkap</b> (atau <i>nama</i>, <i>nama_siswa</i>)</p>
                <p>• <b>Username</b> (atau <i>nisn</i>, <i>nip</i>, <i>user</i>)</p>
                <p>• <b>Email</b> (jika kosong dibuatkan otomatis <code>username@simulasiku.id</code>)</p>
                <p>• <b>Password</b> (jika kosong otomatis diisi <code>password123</code>)</p>
                <p>• <b>Role</b> (diisi <code>murid</code> atau <code>guru</code>)</p>
                <p>• <b>Jenjang</b> (contoh: <code>SMP Kelas 7</code>, <code>SD Kelas 4</code>, <code>ALL</code>)</p>
            </div>

            <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-600/20 transition flex items-center justify-center gap-2">
                <span>🚀</span> Mulai Import Pengguna
            </button>
        </form>
    </div>
</div>
@endsection
