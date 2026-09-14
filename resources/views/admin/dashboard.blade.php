@extends('layouts.cbt')

@section('content')
<div class="max-w-7xl mx-auto space-y-8 w-full">

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-zinc-900/80 border border-zinc-800 p-6 rounded-3xl backdrop-blur-md">
        <div>
            <div class="flex items-center gap-2 text-emerald-400 font-bold text-xs mb-1">
                <span class="px-2 py-0.5 bg-emerald-950 border border-emerald-800 rounded">Admin Portal</span>
                <span>Manajemen Sistem & Pengguna</span>
            </div>
            <h1 class="text-2xl font-extrabold text-white">Kelola Akun & Mata Pelajaran</h1>
            <p class="text-xs text-zinc-400 mt-1">Tambah akun siswa (`siswa2026`), guru (`guru1`), admin, serta kelola struktur pelajaran.</p>
        </div>

        <div class="flex items-center gap-3">
            <button onclick="document.getElementById('modal-user').classList.remove('hidden')" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl transition shadow-lg shadow-blue-600/20 flex items-center gap-2">
                <span>+</span> Tambah Akun Pengguna
            </button>
            <button onclick="document.getElementById('modal-subject').classList.remove('hidden')" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl transition shadow-lg shadow-purple-600/20 flex items-center gap-2">
                <span>+</span> Tambah Mata Pelajaran
            </button>
        </div>
    </div>

    <!-- Alert Success / Error -->
    @if(session('success'))
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm font-semibold rounded-2xl flex items-center gap-2">
            <span>✓</span> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm font-semibold rounded-2xl flex items-center gap-2">
            <span>⚠</span> {{ session('error') }}
        </div>
    @endif

    <!-- System Stat Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-zinc-900/60 border border-zinc-800/80 p-5 rounded-2xl">
            <span class="text-xs font-semibold text-zinc-400 block mb-1">Total Siswa (Murid)</span>
            <div class="text-2xl font-black text-blue-400">{{ $totalStudents }}</div>
        </div>
        <div class="bg-zinc-900/60 border border-zinc-800/80 p-5 rounded-2xl">
            <span class="text-xs font-semibold text-zinc-400 block mb-1">Total Guru (Pengajar)</span>
            <div class="text-2xl font-black text-purple-400">{{ $totalTeachers }}</div>
        </div>
        <div class="bg-zinc-900/60 border border-zinc-800/80 p-5 rounded-2xl">
            <span class="text-xs font-semibold text-zinc-400 block mb-1">Total Administrator</span>
            <div class="text-2xl font-black text-emerald-400">{{ $totalAdmins }}</div>
        </div>
        <div class="bg-zinc-900/60 border border-zinc-800/80 p-5 rounded-2xl">
            <span class="text-xs font-semibold text-zinc-400 block mb-1">Total Pengerjaan Ujian</span>
            <div class="text-2xl font-black text-amber-400">{{ $attemptsCount }}</div>
        </div>
    </div>

    <!-- Main Content Tables Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- User Accounts Management Table (2 Cols) -->
        <div class="lg:col-span-2 space-y-4 bg-zinc-900/60 border border-zinc-800 p-6 rounded-3xl">
            <h2 class="text-lg font-bold text-white flex items-center justify-between">
                <span class="flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                    Daftar Akun Terdaftar dalam Sistem
                </span>
                <span class="text-xs font-normal text-zinc-400">Total: {{ $users->count() }} User</span>
            </h2>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs text-zinc-300">
                    <thead class="bg-[#141416] text-zinc-400 uppercase font-semibold text-[11px] border-b border-zinc-800">
                        <tr>
                            <th class="p-3">Nama</th>
                            <th class="p-3">Username</th>
                            <th class="p-3">Role</th>
                            <th class="p-3">Jenjang</th>
                            <th class="p-3 text-right">Aksi</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-zinc-800/60">
                        @foreach($users as $u)
                            <tr class="hover:bg-zinc-800/30 transition">
                                <td class="p-3">
                                    <div class="font-bold text-white">{{ $u->name }}</div>
                                    <div class="text-[11px] text-zinc-500">{{ $u->email }}</div>
                                </td>
                                <td class="p-3 font-mono text-blue-400 font-semibold">{{ $u->username }}</td>
                                <td class="p-3">
                                    <span class="px-2.5 py-0.5 text-[10px] font-bold rounded uppercase
                                        {{ $u->role === 'admin' ? 'bg-emerald-950 text-emerald-400 border border-emerald-800' : ($u->role === 'guru' ? 'bg-purple-950 text-purple-400 border border-purple-800' : 'bg-blue-950 text-blue-400 border border-blue-800') }}">
                                        {{ $u->role }}
                                    </span>
                                </td>
                                <td class="p-3 text-zinc-400">{{ $u->grade_level ?: '-' }}</td>
                                <td class="p-3 text-right">
                                    @if($u->id !== Auth::id())
                                        <form action="{{ route('admin.users.destroy', $u->id) }}" method="POST" class="inline" onsubmit="return confirm('Hapus akun pengguna ini?')">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-rose-400 hover:text-rose-300 font-semibold bg-rose-950/50 px-2 py-1 rounded border border-rose-900/50">
                                                Hapus
                                            </button>
                                        </form>
                                    @else
                                        <span class="text-[10px] text-zinc-500 italic">Akun Anda</span>
                                    @endif
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <!-- Subject Management Column (1 Col) -->
        <div class="space-y-4 bg-zinc-900/60 border border-zinc-800 p-6 rounded-3xl">
            <h2 class="text-lg font-bold text-white flex items-center gap-2">
                <span class="w-2 h-2 rounded-full bg-purple-500"></span>
                Daftar Pelajaran
            </h2>

            <div class="space-y-3">
                @foreach($subjects as $subj)
                    <div class="bg-[#141416] border border-zinc-800 rounded-2xl p-4 space-y-2 text-xs">
                        <div class="flex items-center justify-between">
                            <span class="font-bold text-white text-sm">{{ $subj->title }}</span>
                            <span class="px-2 py-0.5 bg-zinc-800 text-blue-400 font-mono font-bold rounded">{{ $subj->code }}</span>
                        </div>
                        <p class="text-zinc-400">{{ $subj->description }}</p>
                        <div class="pt-2 border-t border-zinc-800/80 text-[11px] text-zinc-500">
                            Pengajar: <span class="text-zinc-300 font-semibold">{{ $subj->teacher->name ?? 'Belum Ditentukan' }}</span>
                        </div>
                    </div>
                @endforeach
            </div>
        </div>

    </div>

</div>

<!-- Modal Add User -->
<div id="modal-user" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div class="bg-[#141416] border border-zinc-800 w-full max-w-md rounded-3xl p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
            <h3 class="text-lg font-bold text-white">Tambah Akun Pengguna Baru</h3>
            <button onclick="document.getElementById('modal-user').classList.add('hidden')" class="text-zinc-400 hover:text-white">✕</button>
        </div>
        <form action="{{ route('admin.users.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="text-xs font-semibold text-zinc-300">Nama Lengkap</label>
                <input type="text" name="name" required placeholder="Siswa / Guru Baru" class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-zinc-300">Username</label>
                    <input type="text" name="username" required placeholder="siswa2027" class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-zinc-300">Role</label>
                    <select name="role" required class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
                        <option value="murid">Murid (Siswa)</option>
                        <option value="guru">Guru (Pengajar)</option>
                        <option value="admin">Administrator</option>
                    </select>
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-semibold text-zinc-300">Email</label>
                <input type="email" name="email" required placeholder="user@simulasiku.id" class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-zinc-300">Password</label>
                    <input type="password" name="password" required placeholder="••••••••" class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-zinc-300">Jenjang (Opsional)</label>
                    <input type="text" name="grade_level" placeholder="SD / SMP / SMA" class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
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
    <div class="bg-[#141416] border border-zinc-800 w-full max-w-md rounded-3xl p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
            <h3 class="text-lg font-bold text-white">Tambah Mata Pelajaran</h3>
            <button onclick="document.getElementById('modal-subject').classList.add('hidden')" class="text-zinc-400 hover:text-white">✕</button>
        </div>
        <form action="{{ route('admin.subjects.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="text-xs font-semibold text-zinc-300">Nama Pelajaran</label>
                <input type="text" name="title" required placeholder="Contoh: Fisika Terapan" class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-zinc-300">Kode Pelajaran</label>
                    <input type="text" name="code" required placeholder="FSK" class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-zinc-300">Guru Pengampu</label>
                    <select name="teacher_id" class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
                        <option value="">- Pilih Guru -</option>
                        @foreach($users->where('role', 'guru') as $g)
                            <option value="{{ $g->id }}">{{ $g->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-semibold text-zinc-300">Deskripsi Ringkas</label>
                <textarea name="description" rows="2" placeholder="Penjelasan singkat mata pelajaran..." class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white"></textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-purple-600/20">
                Simpan Mata Pelajaran
            </button>
        </form>
    </div>
</div>
@endsection
