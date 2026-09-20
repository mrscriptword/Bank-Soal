@extends('layouts.cbt')

@section('content')
<div class="max-w-7xl mx-auto space-y-8 w-full">

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-white dark:bg-zinc-900/80 border border-slate-200 dark:border-zinc-800 p-6 rounded-3xl backdrop-blur-md shadow-sm transition-colors">
        <div>
            <div class="flex items-center gap-2 text-blue-600 dark:text-blue-400 font-bold text-xs mb-1">
                <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-950 border border-blue-200 dark:border-blue-800 rounded">Guru Portal</span>
                <span>Dashboard Pengajar</span>
            </div>
            <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white">Kelola Paket Ujian & Monitoring Siswa</h1>
            <p class="text-xs text-slate-500 dark:text-zinc-400 mt-1">Buat paket simulasi, pantau siswa yang sudah/belum mengerjakan, dan kelola bank soal.</p>
        </div>

        <div class="flex flex-wrap items-center gap-3">
            <a href="#modal-pdf-exam" onclick="document.getElementById('modal-pdf-exam').classList.remove('hidden')" class="px-4 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl transition shadow-lg shadow-purple-600/20 flex items-center gap-2">
                <span>📄</span> Upload PDF Ujian
            </a>
            <a href="#modal-exam" onclick="document.getElementById('modal-exam').classList.remove('hidden')" class="px-4 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl transition shadow-lg shadow-blue-600/20 flex items-center gap-2">
                <span>+</span> Paket Manual
            </a>
            <a href="#modal-question" onclick="document.getElementById('modal-question').classList.remove('hidden')" class="px-4 py-2.5 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl transition shadow-lg shadow-emerald-600/20 flex items-center gap-2">
                <span>+</span> Input Soal
            </a>
        </div>
    </div>

    <!-- Alert Success & Error -->
    @if(session('success'))
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-600 dark:text-emerald-400 text-sm font-semibold rounded-2xl flex items-center gap-2">
            <span>✓</span> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-sm font-semibold rounded-2xl flex items-center gap-2">
            <span>⚠️</span> {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-600 dark:text-rose-400 text-xs font-semibold rounded-2xl space-y-1">
            @foreach($errors->all() as $err)
                <p>⚠️ {{ $err }}</p>
            @endforeach
        </div>
    @endif

    <!-- Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-white dark:bg-zinc-900/60 border border-slate-200 dark:border-zinc-800/80 p-5 rounded-2xl shadow-sm">
            <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400 block mb-1">Total Mata Pelajaran</span>
            <div class="text-2xl font-black text-slate-900 dark:text-white">{{ $subjects->count() }}</div>
        </div>
        <div class="bg-white dark:bg-zinc-900/60 border border-slate-200 dark:border-zinc-800/80 p-5 rounded-2xl shadow-sm">
            <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400 block mb-1">Total Paket Simulasi</span>
            <div class="text-2xl font-black text-blue-600 dark:text-blue-400">{{ $exams->count() }}</div>
        </div>
        <div class="bg-white dark:bg-zinc-900/60 border border-slate-200 dark:border-zinc-800/80 p-5 rounded-2xl shadow-sm">
            <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400 block mb-1">Total Soal Terdaftar</span>
            <div class="text-2xl font-black text-purple-600 dark:text-purple-400">{{ $totalQuestions }}</div>
        </div>
        <div class="bg-white dark:bg-zinc-900/60 border border-slate-200 dark:border-zinc-800/80 p-5 rounded-2xl shadow-sm">
            <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400 block mb-1">Rata-rata Skor Siswa</span>
            <div class="text-2xl font-black text-emerald-600 dark:text-emerald-400">{{ $averageScore }} / 100</div>
        </div>
    </div>

    <!-- Main Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Column 1 & 2: List of Exams with Student Attempt Monitoring -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Exams List -->
            <div class="bg-white dark:bg-zinc-900/60 border border-slate-200 dark:border-zinc-800 p-6 rounded-3xl space-y-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-blue-500"></span>
                        Daftar Paket Simulasi Ujian & Status Pengerjaan
                    </h2>
                    <span class="text-xs text-slate-500 dark:text-zinc-400 font-medium">Monitoring Real-time</span>
                </div>

                <div class="space-y-5">
                    @foreach($exams as $exam)
                        <div x-data="{ open: false, activeTab: 'soal' }" class="bg-slate-50 dark:bg-[#141416] border border-slate-200 dark:border-zinc-800 rounded-2xl p-5 space-y-4 transition-all hover:border-slate-300 dark:hover:border-zinc-700/80">
                            <!-- Card Header -->
                            <div class="flex items-start justify-between gap-3">
                                <div class="cursor-pointer group flex-1" @click="open = !open">
                                    <div class="flex items-center flex-wrap gap-2 mb-1">
                                        <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-950 text-blue-700 dark:text-blue-400 text-[10px] font-bold rounded uppercase border border-blue-200 dark:border-blue-900/50">
                                            {{ $exam->subject->title ?? 'Pelajaran' }}
                                        </span>
                                        <span class="px-2 py-0.5 bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 text-[10px] font-bold rounded uppercase border border-purple-200 dark:border-purple-900/50">
                                            🎓 {{ $exam->grade_level ?? 'SD Kelas 4' }}
                                        </span>
                                        <span class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 text-[10px] font-bold rounded border border-emerald-200 dark:border-emerald-900/50">
                                            ✅ Sudah: {{ $exam->completed_students->count() }}
                                        </span>
                                        <span class="px-2 py-0.5 bg-amber-100 dark:bg-amber-950 text-amber-700 dark:text-amber-400 text-[10px] font-bold rounded border border-amber-200 dark:border-amber-900/50">
                                            ⏳ Belum: {{ $exam->pending_students->count() }}
                                        </span>
                                    </div>

                                    <h3 class="font-bold text-slate-900 dark:text-white text-base group-hover:text-blue-600 dark:group-hover:text-blue-400 transition flex items-center gap-2">
                                        {{ $exam->title }}
                                    </h3>
                                    <p class="text-xs text-slate-500 dark:text-zinc-400 mt-0.5">
                                        Durasi: {{ $exam->duration_minutes }} menit · Total Soal: {{ $exam->questions->count() }} · Diikuti: {{ $exam->attempts_count }} kali
                                    </p>
                                </div>

                                <div class="flex items-center gap-2 shrink-0">
                                    <button type="button" @click="open = !open" class="px-3 py-1.5 bg-white dark:bg-zinc-900 hover:bg-slate-100 dark:hover:bg-zinc-800 border border-slate-200 dark:border-zinc-800 rounded-xl text-xs font-semibold text-slate-700 dark:text-zinc-300 flex items-center gap-1.5 transition">
                                        <span x-text="open ? '📂 Sembunyikan' : '📁 Detail & Soal (' + {{ $exam->questions->count() }} + ')'"></span>
                                    </button>
                                    <form action="{{ route('guru.exams.destroy', $exam->id) }}" method="POST" onsubmit="return confirm('Hapus paket ujian ini?')">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="text-xs text-rose-600 dark:text-rose-400 hover:text-rose-700 font-semibold bg-rose-50 dark:bg-rose-950/50 px-2.5 py-1.5 rounded-xl border border-rose-200 dark:border-rose-900/50 transition">
                                            Hapus
                                        </button>
                                    </form>
                                </div>
                            </div>

                            <!-- Expanded Section (Tabs for Soal vs Siswa Sudah/Belum) -->
                            <div x-show="open" x-cloak class="space-y-4 pt-4 border-t border-slate-200 dark:border-zinc-800/80">
                                <!-- Tab Buttons -->
                                <div class="flex items-center gap-2 border-b border-slate-200 dark:border-zinc-800 pb-2 text-xs">
                                    <button type="button" @click="activeTab = 'soal'"
                                            :class="activeTab === 'soal' ? 'bg-blue-600 text-white font-bold' : 'bg-slate-200 dark:bg-zinc-900 text-slate-700 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white'"
                                            class="px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                                        <span>📝 Soal Terdaftar ({{ $exam->questions->count() }})</span>
                                    </button>
                                    <button type="button" @click="activeTab = 'sudah'"
                                            :class="activeTab === 'sudah' ? 'bg-emerald-600 text-white font-bold' : 'bg-slate-200 dark:bg-zinc-900 text-slate-700 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white'"
                                            class="px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                                        <span>✅ Sudah Mengerjakan ({{ $exam->completed_students->count() }})</span>
                                    </button>
                                    <button type="button" @click="activeTab = 'belum'"
                                            :class="activeTab === 'belum' ? 'bg-amber-600 text-white font-bold' : 'bg-slate-200 dark:bg-zinc-900 text-slate-700 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white'"
                                            class="px-3 py-1.5 rounded-lg transition flex items-center gap-1">
                                        <span>⏳ Belum Mengerjakan ({{ $exam->pending_students->count() }})</span>
                                    </button>
                                </div>

                                <!-- TAB 1: SOAL TERDAFTAR -->
                                <div x-show="activeTab === 'soal'" class="space-y-2">
                                    <div class="flex items-center justify-between text-xs">
                                        <span class="font-semibold text-slate-600 dark:text-zinc-400">Daftar Soal Pilihan Ganda:</span>
                                        <span class="text-[11px] text-slate-500 dark:text-zinc-500 italic">Klik "Edit" untuk mengubah soal & pembahasan</span>
                                    </div>
                                    @forelse($exam->questions as $idx => $q)
                                        <div class="bg-white dark:bg-zinc-900/90 border border-slate-200 dark:border-zinc-800/60 p-3.5 rounded-xl flex items-start justify-between gap-3 text-xs hover:border-slate-300 dark:hover:border-zinc-700/60 transition">
                                            <div class="space-y-1">
                                                <div class="flex items-center flex-wrap gap-2">
                                                    <span class="font-bold text-slate-800 dark:text-zinc-200">#{{ $idx + 1 }}. {{ $q->question_text }}</span>
                                                    @if($q->type === 'matching')
                                                        <span class="px-2 py-0.5 bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 border border-purple-300 dark:border-purple-800 text-[10px] font-bold rounded-md inline-flex items-center gap-1">
                                                            🧩 Pasangan
                                                        </span>
                                                    @elseif($q->type === 'ordering')
                                                        <span class="px-2 py-0.5 bg-indigo-100 dark:bg-indigo-950 text-indigo-700 dark:text-indigo-300 border border-indigo-300 dark:border-indigo-800 text-[10px] font-bold rounded-md inline-flex items-center gap-1">
                                                            🔢 Susun Urutan
                                                        </span>
                                                    @endif
                                                    @if(str_contains($q->explanation ?? '', 'KUNCI DEFAULT'))
                                                        <span class="px-2 py-0.5 bg-amber-100 dark:bg-amber-950/80 text-amber-700 dark:text-amber-400 border border-amber-300 dark:border-amber-800/80 text-[10px] font-bold rounded-md inline-flex items-center gap-1">
                                                            ⚠️ Kunci Default (Perlu Dicek)
                                                        </span>
                                                    @endif
                                                </div>

                                                @if($q->type === 'matching' && is_array($q->pair_data))
                                                    <div class="flex flex-wrap gap-2 text-[11px] text-purple-700 dark:text-purple-300 pt-1 font-medium">
                                                        @foreach($q->pair_data as $p)
                                                            <span class="bg-purple-50 dark:bg-purple-950/40 px-2 py-0.5 rounded border border-purple-200 dark:border-purple-900/50">
                                                                {{ $p['left'] ?? '' }} ➔ {{ $p['right'] ?? '' }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @elseif($q->type === 'ordering' && is_array($q->sequence_data))
                                                    <div class="flex flex-wrap gap-1.5 text-[11px] text-indigo-700 dark:text-indigo-300 pt-1 font-medium">
                                                        @foreach($q->sequence_data as $sIdx => $sVal)
                                                            <span class="bg-indigo-50 dark:bg-indigo-950/40 px-2 py-0.5 rounded border border-indigo-200 dark:border-indigo-900/50">
                                                                {{ $sIdx + 1 }}. {{ $sVal }}
                                                            </span>
                                                        @endforeach
                                                    </div>
                                                @else
                                                    <div class="grid grid-cols-2 gap-x-4 text-[11px] text-slate-600 dark:text-zinc-400 pt-1">
                                                        <span class="{{ $q->correct_option === 'a' ? 'text-emerald-600 dark:text-emerald-400 font-bold' : '' }}">A. {{ $q->option_a }}</span>
                                                        <span class="{{ $q->correct_option === 'b' ? 'text-emerald-600 dark:text-emerald-400 font-bold' : '' }}">B. {{ $q->option_b }}</span>
                                                        <span class="{{ $q->correct_option === 'c' ? 'text-emerald-600 dark:text-emerald-400 font-bold' : '' }}">C. {{ $q->option_c }}</span>
                                                        <span class="{{ $q->correct_option === 'd' ? 'text-emerald-600 dark:text-emerald-400 font-bold' : '' }}">D. {{ $q->option_d }}</span>
                                                    </div>
                                                @endif

                                                @if($q->explanation)
                                                    <p class="text-[11px] text-blue-600 dark:text-blue-400 italic pt-0.5">Pembahasan: {{ $q->explanation }}</p>
                                                @endif
                                            </div>
                                            <div class="flex items-center gap-2 shrink-0">
                                                <button type="button" 
                                                        onclick="openEditQuestionModal({{ json_encode($q) }})" 
                                                        title="Edit Soal & Pembahasan"
                                                        class="text-xs text-blue-600 dark:text-blue-400 font-semibold bg-blue-50 dark:bg-blue-950/60 hover:bg-blue-100 dark:hover:bg-blue-900/60 px-2.5 py-1 rounded-lg border border-blue-200 dark:border-blue-800/60 flex items-center gap-1 transition">
                                                    <span>✏️</span> Edit
                                                </button>
                                                <form action="{{ route('guru.questions.destroy', $q->id) }}" method="POST" onsubmit="return confirm('Hapus soal ini?')">
                                                    @csrf
                                                    @method('DELETE')
                                                    <button type="submit" title="Hapus Soal" class="text-slate-400 hover:text-rose-600 p-1 text-sm font-bold">✕</button>
                                                </form>
                                            </div>
                                        </div>
                                    @empty
                                        <p class="text-xs text-slate-500 dark:text-zinc-500 italic">Belum ada soal pada paket ini. Klik tombol "Input Soal & Pembahasan" untuk menambahkan.</p>
                                    @endforelse
                                </div>

                                <!-- TAB 2: SUDAH MENGERJAKAN -->
                                <div x-show="activeTab === 'sudah'" class="space-y-2">
                                    <div class="flex items-center justify-between text-xs pb-1">
                                        <span class="font-semibold text-slate-700 dark:text-zinc-300">Siswa yang Telah Menyelesaikan Ujian Ini:</span>
                                        <span class="text-[11px] text-emerald-600 dark:text-emerald-400 font-bold">Total: {{ $exam->completed_students->count() }} siswa</span>
                                    </div>
                                    <div class="space-y-2">
                                        @forelse($exam->completed_students as $att)
                                            <div class="bg-white dark:bg-zinc-900/90 border border-slate-200 dark:border-zinc-800 p-3.5 rounded-xl flex items-center justify-between text-xs shadow-sm">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded-full bg-emerald-100 dark:bg-emerald-950 border border-emerald-300 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 font-bold flex items-center justify-center text-xs">
                                                        ✓
                                                    </div>
                                                    <div>
                                                        <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                                            <span>{{ $att->user->name ?? 'Siswa' }}</span>
                                                            <span class="text-[10px] text-slate-500 dark:text-zinc-400 font-normal">({{ $att->user->username ?? '-' }})</span>
                                                        </div>
                                                        <div class="text-[11px] text-slate-500 dark:text-zinc-400 flex items-center gap-3">
                                                            <span>Waktu: {{ floor($att->time_taken_seconds / 60) }}m {{ $att->time_taken_seconds % 60 }}s</span>
                                                            <span>Tanggal: {{ $att->created_at ? $att->created_at->format('d M Y H:i') : '-' }}</span>
                                                        </div>
                                                    </div>
                                                </div>
                                                <div class="text-right">
                                                    <span class="px-3 py-1 bg-emerald-100 dark:bg-emerald-950/90 border border-emerald-300 dark:border-emerald-800 text-emerald-700 dark:text-emerald-400 text-xs font-black rounded-lg inline-block">
                                                        Skor: {{ $att->score }} / 100
                                                    </span>
                                                    <span class="text-[10px] text-slate-500 dark:text-zinc-400 block mt-0.5">
                                                        Benar: {{ $att->correct_answers }} / {{ $att->total_questions }}
                                                    </span>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="p-4 bg-white dark:bg-zinc-900/60 border border-slate-200 dark:border-zinc-800 rounded-xl text-center text-xs text-slate-500 dark:text-zinc-500">
                                                Belum ada siswa yang menyelesaikan ujian paket ini.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>

                                <!-- TAB 3: BELUM MENGERJAKAN -->
                                <div x-show="activeTab === 'belum'" class="space-y-2">
                                    <div class="flex items-center justify-between text-xs pb-1">
                                        <span class="font-semibold text-slate-700 dark:text-zinc-300">Siswa Target ({{ $exam->grade_level }}) yang Belum Memulai Ujian:</span>
                                        <span class="text-[11px] text-amber-600 dark:text-amber-400 font-bold">Total: {{ $exam->pending_students->count() }} siswa</span>
                                    </div>
                                    <div class="space-y-2">
                                        @forelse($exam->pending_students as $st)
                                            <div class="bg-white dark:bg-zinc-900/90 border border-slate-200 dark:border-zinc-800 p-3.5 rounded-xl flex items-center justify-between text-xs shadow-sm">
                                                <div class="flex items-center gap-3">
                                                    <div class="w-8 h-8 rounded-full bg-amber-100 dark:bg-amber-950 border border-amber-300 dark:border-amber-800 text-amber-700 dark:text-amber-400 font-bold flex items-center justify-center text-xs">
                                                        ⏳
                                                    </div>
                                                    <div>
                                                        <div class="font-bold text-slate-900 dark:text-white flex items-center gap-2">
                                                            <span>{{ $st->name }}</span>
                                                            <span class="text-[10px] text-slate-500 dark:text-zinc-400 font-normal">({{ $st->username }})</span>
                                                        </div>
                                                        <div class="text-[11px] text-slate-500 dark:text-zinc-400">
                                                            Jenjang: {{ $st->grade_level }}
                                                        </div>
                                                    </div>
                                                </div>
                                                <div>
                                                    <span class="px-2.5 py-1 bg-amber-50 dark:bg-amber-950/60 border border-amber-200 dark:border-amber-800/60 text-amber-700 dark:text-amber-400 text-xs font-semibold rounded-lg">
                                                        Belum Memulai
                                                    </span>
                                                </div>
                                            </div>
                                        @empty
                                            <div class="p-4 bg-emerald-50 dark:bg-emerald-950/20 border border-emerald-200 dark:border-emerald-800/40 rounded-xl text-center text-xs text-emerald-700 dark:text-emerald-400 font-semibold">
                                                🎉 Luar biasa! Semua siswa di kelas ini telah menyelesaikan ujian.
                                            </div>
                                        @endforelse
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Column 3: Overall Student Directory & Progress -->
        <div class="space-y-6">

            <!-- Directory Siswa Card -->
            <div class="bg-white dark:bg-zinc-900/60 border border-slate-200 dark:border-zinc-800 p-6 rounded-3xl space-y-4 shadow-sm">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                        <span class="w-2.5 h-2.5 rounded-full bg-purple-500"></span>
                        Status Siswa Terdaftar
                    </h2>
                    <span class="text-xs text-slate-500 dark:text-zinc-400 font-semibold">{{ $students->count() }} Murid</span>
                </div>

                <div class="space-y-2.5">
                    @forelse($students as $m)
                        @php
                            $mAttemptsCount = $m->examAttempts->count();
                        @endphp
                        <div class="bg-slate-50 dark:bg-[#141416] border border-slate-200 dark:border-zinc-800 rounded-xl p-3.5 flex items-center justify-between text-xs">
                            <div class="space-y-0.5">
                                <span class="font-bold text-slate-900 dark:text-white block">{{ $m->name }}</span>
                                <span class="text-[11px] text-slate-500 dark:text-zinc-400 block">{{ $m->username }} · {{ $m->grade_level }}</span>
                            </div>
                            <span class="{{ $mAttemptsCount > 0 ? 'bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 border-emerald-200 dark:border-emerald-800' : 'bg-slate-200 dark:bg-zinc-800 text-slate-600 dark:text-zinc-400 border-slate-300 dark:border-zinc-700' }} border text-[10px] font-bold px-2 py-1 rounded-lg">
                                {{ $mAttemptsCount }} Ujian Selesai
                            </span>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500 dark:text-zinc-500 italic">Belum ada murid terdaftar di sistem.</p>
                    @endforelse
                </div>
            </div>

            <!-- Recent Attempts Log -->
            <div class="bg-white dark:bg-zinc-900/60 border border-slate-200 dark:border-zinc-800 p-6 rounded-3xl space-y-4 shadow-sm">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="w-2.5 h-2.5 rounded-full bg-emerald-500"></span>
                    Riwayat Submit Terbaru
                </h2>

                <div class="space-y-3">
                    @forelse($attempts as $att)
                        <div class="bg-slate-50 dark:bg-[#141416] border border-slate-200 dark:border-zinc-800 rounded-xl p-3.5 space-y-1.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-slate-900 dark:text-white">{{ $att->user->name ?? 'Siswa' }}</span>
                                <span class="px-2 py-0.5 bg-emerald-100 dark:bg-emerald-950 text-emerald-700 dark:text-emerald-400 border border-emerald-300 dark:border-emerald-800 font-bold rounded">
                                    Skor: {{ $att->score }}
                                </span>
                            </div>
                            <p class="text-slate-600 dark:text-zinc-400">{{ $att->exam->title ?? 'Ujian' }}</p>
                            <div class="flex items-center justify-between text-[11px] text-slate-500 dark:text-zinc-500 pt-1">
                                <span>Waktu: {{ floor($att->time_taken_seconds / 60) }}m {{ $att->time_taken_seconds % 60 }}s</span>
                                <span>{{ $att->created_at ? $att->created_at->diffForHumans() : '-' }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-slate-500 dark:text-zinc-500 italic">Belum ada riwayat pengerjaan simulasi.</p>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Modal Create Exam -->
<div id="modal-exam" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div class="bg-white dark:bg-[#141416] border border-slate-200 dark:border-zinc-800 w-full max-w-md rounded-3xl p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-zinc-800 pb-3">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Tambah Paket Ujian Baru</h3>
            <button onclick="document.getElementById('modal-exam').classList.add('hidden')" class="text-slate-400 hover:text-slate-900 dark:hover:text-white">✕</button>
        </div>
        <form action="{{ route('guru.exams.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Pilih Mata Pelajaran</label>
                <select name="subject_id" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
                    @foreach($subjects as $s)
                        <option value="{{ $s->id }}">{{ $s->title }} ({{ $s->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Target Jenjang Kelas</label>
                <select name="grade_level" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
                    <option value="SD Kelas 4">SD Kelas 4</option>
                    <option value="SD Kelas 5">SD Kelas 5</option>
                    <option value="SMP Kelas 7">SMP Kelas 7</option>
                    <option value="SMP Kelas 8">SMP Kelas 8</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Judul Paket Ujian</label>
                <input type="text" name="title" required placeholder="Contoh: Simulasi Matematika Lanjutan" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Durasi (Menit)</label>
                    <input type="number" name="duration_minutes" value="15" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">KKM Nilai</label>
                    <input type="number" name="passing_score" value="70" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
                </div>
            </div>
            <div class="flex items-center gap-2 pt-1">
                <input type="checkbox" name="allow_repeat" id="allow_repeat" value="1" class="w-4 h-4 text-blue-600 rounded border-slate-300 dark:border-zinc-800 focus:ring-blue-500">
                <label for="allow_repeat" class="text-xs font-semibold text-slate-700 dark:text-zinc-300 cursor-pointer">Izinkan Siswa Mengulangi Ujian (Fitur Ulangi)</label>
            </div>
            <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-blue-600/20">
                Simpan Paket Ujian
            </button>
        </form>
    </div>
</div>

<!-- Modal Input Question -->
<div id="modal-question" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div class="bg-white dark:bg-[#141416] border border-slate-200 dark:border-zinc-800 w-full max-w-xl rounded-3xl p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-zinc-800 pb-3">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Input Soal & Pembahasan</h3>
            <button onclick="document.getElementById('modal-question').classList.add('hidden')" class="text-slate-400 hover:text-slate-900 dark:hover:text-white">✕</button>
        </div>
        <form action="{{ route('guru.questions.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Pilih Paket Ujian Target</label>
                <select name="exam_id" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
                    @foreach($exams as $e)
                        <option value="{{ $e->id }}">{{ $e->title }} ({{ $e->subject->title ?? 'Pelajaran' }})</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Tipe Soal</label>
                <select name="type" id="create_question_type" onchange="toggleQuestionTypeFields('create')" class="w-full bg-slate-50 dark:bg-zinc-900 border border-purple-500/40 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-900 dark:text-white">
                    <option value="multiple_choice">Pilihan Ganda (Standard A, B, C, D)</option>
                    <option value="matching">🧩 Pasangan (Menjodohkan Kiri & Kanan)</option>
                    <option value="ordering">🔢 Susun Urutan (Urutan Langkah/Kronologis)</option>
                </select>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Pertanyaan / Instruksi Soal</label>
                <textarea name="question_text" rows="3" required placeholder="Ketik kalimat soal atau instruksi di sini..." class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white"></textarea>
            </div>

            <!-- Fieldset: Pilihan Ganda -->
            <div id="create-field-mc" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Pilihan A</label>
                        <input type="text" name="option_a" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Pilihan B</label>
                        <input type="text" name="option_b" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Pilihan C</label>
                        <input type="text" name="option_c" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Pilihan D</label>
                        <input type="text" name="option_d" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Kunci Jawaban Benar</label>
                    <select name="correct_option" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
                        <option value="a">A</option>
                        <option value="b">B</option>
                        <option value="c">C</option>
                        <option value="d">D</option>
                    </select>
                </div>
            </div>

            <!-- Fieldset: Pasangan (Matching) -->
            <div id="create-field-matching" class="hidden space-y-3 bg-purple-50/50 dark:bg-purple-950/20 p-4 border border-purple-200 dark:border-purple-900/40 rounded-2xl">
                <span class="text-xs font-bold text-purple-700 dark:text-purple-300 block">🧩 Pasangan Kiri & Kanan (Kunci Benar):</span>
                @for($i = 0; $i < 4; $i++)
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" name="pair_data[{{ $i }}][left]" placeholder="Kiri {{ $i + 1 }} (Misal: Persegi)" class="bg-white dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs">
                        <input type="text" name="pair_data[{{ $i }}][right]" placeholder="Kanan {{ $i + 1 }} (Misal: s x s)" class="bg-white dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs">
                    </div>
                @endfor
            </div>

            <!-- Fieldset: Susun Urutan (Ordering) -->
            <div id="create-field-ordering" class="hidden space-y-3 bg-indigo-50/50 dark:bg-indigo-950/20 p-4 border border-indigo-200 dark:border-indigo-900/40 rounded-2xl">
                <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300 block">🔢 Urutan Langkah yang Benar (Top to Bottom):</span>
                @for($i = 0; $i < 5; $i++)
                    <div class="flex items-center gap-2">
                        <span class="w-5 h-5 rounded bg-indigo-600 text-white text-[10px] font-extrabold flex items-center justify-center shrink-0">{{ $i + 1 }}</span>
                        <input type="text" name="sequence_data[{{ $i }}]" placeholder="Langkah {{ $i + 1 }}" class="w-full bg-white dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs">
                    </div>
                @endfor
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Pembahasan (Langkah & Rumus)</label>
                <textarea name="explanation" rows="3" placeholder="Tuliskan rumus dan cara penyelesaiannya..." class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white"></textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-600/20">
                Simpan Soal & Pembahasan
            </button>
        </form>
    </div>
</div>

<!-- Modal Edit Question -->
<div id="modal-edit-question" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div class="bg-white dark:bg-[#141416] border border-slate-200 dark:border-zinc-800 w-full max-w-xl rounded-3xl p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-zinc-800 pb-3">
            <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <span class="text-blue-500">✏️</span> Edit Soal & Pembahasan
            </h3>
            <button onclick="document.getElementById('modal-edit-question').classList.add('hidden')" class="text-slate-400 hover:text-slate-900 dark:hover:text-white text-lg">✕</button>
        </div>
        <form id="form-edit-question" action="" method="POST" class="space-y-4">
            @csrf
            @method('PUT')
            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Pilih Paket Ujian Target</label>
                <select id="edit_exam_id" name="exam_id" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
                    @foreach($exams as $e)
                        <option value="{{ $e->id }}">{{ $e->title }} ({{ $e->subject->title ?? 'Pelajaran' }})</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Tipe Soal</label>
                <select id="edit_type" name="type" onchange="toggleQuestionTypeFields('edit')" class="w-full bg-slate-50 dark:bg-zinc-900 border border-purple-500/40 rounded-xl px-3 py-2.5 text-xs font-bold text-slate-900 dark:text-white">
                    <option value="multiple_choice">Pilihan Ganda (Standard A, B, C, D)</option>
                    <option value="matching">🧩 Pasangan (Menjodohkan Kiri & Kanan)</option>
                    <option value="ordering">🔢 Susun Urutan (Urutan Langkah/Kronologis)</option>
                </select>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Pertanyaan / Instruksi Soal</label>
                <textarea id="edit_question_text" name="question_text" rows="3" required placeholder="Ketik kalimat soal di sini..." class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white"></textarea>
            </div>

            <!-- Fieldset Edit: Multiple Choice -->
            <div id="edit-field-mc" class="space-y-4">
                <div class="grid grid-cols-2 gap-3">
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Pilihan A</label>
                        <input type="text" id="edit_option_a" name="option_a" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Pilihan B</label>
                        <input type="text" id="edit_option_b" name="option_b" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Pilihan C</label>
                        <input type="text" id="edit_option_c" name="option_c" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                    <div class="space-y-1">
                        <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Pilihan D</label>
                        <input type="text" id="edit_option_d" name="option_d" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs text-slate-900 dark:text-white">
                    </div>
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Kunci Jawaban Benar</label>
                    <select id="edit_correct_option" name="correct_option" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
                        <option value="a">A</option>
                        <option value="b">B</option>
                        <option value="c">C</option>
                        <option value="d">D</option>
                    </select>
                </div>
            </div>

            <!-- Fieldset Edit: Pasangan (Matching) -->
            <div id="edit-field-matching" class="hidden space-y-3 bg-purple-50/50 dark:bg-purple-950/20 p-4 border border-purple-200 dark:border-purple-900/40 rounded-2xl">
                <span class="text-xs font-bold text-purple-700 dark:text-purple-300 block">🧩 Pasangan Kiri & Kanan (Kunci Benar):</span>
                @for($i = 0; $i < 4; $i++)
                    <div class="grid grid-cols-2 gap-2">
                        <input type="text" id="edit_pair_left_{{ $i }}" name="pair_data[{{ $i }}][left]" placeholder="Kiri {{ $i + 1 }}" class="bg-white dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs">
                        <input type="text" id="edit_pair_right_{{ $i }}" name="pair_data[{{ $i }}][right]" placeholder="Kanan {{ $i + 1 }}" class="bg-white dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs">
                    </div>
                @endfor
            </div>

            <!-- Fieldset Edit: Susun Urutan (Ordering) -->
            <div id="edit-field-ordering" class="hidden space-y-3 bg-indigo-50/50 dark:bg-indigo-950/20 p-4 border border-indigo-200 dark:border-indigo-900/40 rounded-2xl">
                <span class="text-xs font-bold text-indigo-700 dark:text-indigo-300 block">🔢 Urutan Langkah yang Benar (Top to Bottom):</span>
                @for($i = 0; $i < 5; $i++)
                    <div class="flex items-center gap-2">
                        <span class="w-5 h-5 rounded bg-indigo-600 text-white text-[10px] font-extrabold flex items-center justify-center shrink-0">{{ $i + 1 }}</span>
                        <input type="text" id="edit_seq_{{ $i }}" name="sequence_data[{{ $i }}]" placeholder="Langkah {{ $i + 1 }}" class="w-full bg-white dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2 text-xs">
                    </div>
                @endfor
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Pembahasan (Langkah & Rumus)</label>
                <textarea id="edit_explanation" name="explanation" rows="3" placeholder="Tuliskan rumus dan cara penyelesaiannya..." class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white"></textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-blue-600/20">
                Simpan Perubahan Soal
            </button>
        </form>
    </div>
</div>

<script>
function toggleQuestionTypeFields(prefix) {
    const typeSelect = document.getElementById(prefix === 'create' ? 'create_question_type' : 'edit_type');
    const type = typeSelect ? typeSelect.value : 'multiple_choice';

    const mcContainer = document.getElementById(prefix + '-field-mc');
    const matchingContainer = document.getElementById(prefix + '-field-matching');
    const orderingContainer = document.getElementById(prefix + '-field-ordering');

    if (mcContainer) mcContainer.classList.toggle('hidden', type !== 'multiple_choice');
    if (matchingContainer) matchingContainer.classList.toggle('hidden', type !== 'matching');
    if (orderingContainer) orderingContainer.classList.toggle('hidden', type !== 'ordering');
}

function openEditQuestionModal(question) {
    const form = document.getElementById('form-edit-question');
    form.action = '/guru/questions/' + question.id;
    
    document.getElementById('edit_exam_id').value = question.exam_id;
    document.getElementById('edit_type').value = question.type || 'multiple_choice';
    document.getElementById('edit_question_text').value = question.question_text || '';
    document.getElementById('edit_option_a').value = question.option_a || '';
    document.getElementById('edit_option_b').value = question.option_b || '';
    document.getElementById('edit_option_c').value = question.option_c || '';
    document.getElementById('edit_option_d').value = question.option_d || '';
    document.getElementById('edit_correct_option').value = question.correct_option || 'a';
    document.getElementById('edit_explanation').value = question.explanation || '';

    // Populate pair_data
    const pairs = question.pair_data || [];
    for (let i = 0; i < 4; i++) {
        const leftInput = document.getElementById('edit_pair_left_' + i);
        const rightInput = document.getElementById('edit_pair_right_' + i);
        if (leftInput) leftInput.value = (pairs[i] && pairs[i].left) ? pairs[i].left : '';
        if (rightInput) rightInput.value = (pairs[i] && pairs[i].right) ? pairs[i].right : '';
    }

    // Populate sequence_data
    const seq = question.sequence_data || [];
    for (let i = 0; i < 5; i++) {
        const seqInput = document.getElementById('edit_seq_' + i);
        if (seqInput) seqInput.value = seq[i] || '';
    }

    toggleQuestionTypeFields('edit');
    document.getElementById('modal-edit-question').classList.remove('hidden');
}
</script>

<!-- Modal Upload PDF Exam -->
<div id="modal-pdf-exam" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div class="bg-white dark:bg-[#141416] border border-slate-200 dark:border-zinc-800 w-full max-w-lg rounded-3xl p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-zinc-800 pb-3">
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="text-purple-600 dark:text-purple-400">📄</span> Import Paket Ujian dari PDF
                </h3>
                <p class="text-xs text-slate-500 dark:text-zinc-400">Unggah file PDF bank soal untuk membuat paket ujian secara otomatis.</p>
            </div>
            <button onclick="document.getElementById('modal-pdf-exam').classList.add('hidden')" class="text-slate-400 hover:text-slate-900 dark:hover:text-white text-lg">✕</button>
        </div>

        <form action="{{ route('guru.exams.import') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Pilih Mata Pelajaran</label>
                <select name="subject_id" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
                    @foreach($subjects as $s)
                        <option value="{{ $s->id }}">{{ $s->title }} ({{ $s->code }})</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Target Jenjang Kelas</label>
                <select name="grade_level" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
                    <option value="SD Kelas 4">SD Kelas 4</option>
                    <option value="SD Kelas 5">SD Kelas 5</option>
                    <option value="SMP Kelas 7">SMP Kelas 7</option>
                    <option value="SMP Kelas 8">SMP Kelas 8</option>
                </select>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Judul Paket Ujian</label>
                <input type="text" name="title" required placeholder="Contoh: Paket Tryout UN Matematika 2026 (PDF)" class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Durasi (Menit)</label>
                    <input type="number" name="duration_minutes" value="15" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">KKM Nilai</label>
                    <input type="number" name="passing_score" value="70" required class="w-full bg-slate-50 dark:bg-zinc-900 border border-slate-300 dark:border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-slate-900 dark:text-white">
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="text-xs font-semibold text-slate-700 dark:text-zinc-300">Pilih File PDF Naskah Soal</label>
                <div class="border-2 border-dashed border-purple-500/30 hover:border-purple-500/60 bg-purple-50 dark:bg-purple-950/10 p-5 rounded-2xl text-center space-y-2 transition">
                    <svg class="w-8 h-8 text-purple-600 dark:text-purple-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <input type="file" name="pdf_file" accept=".pdf" required class="block w-full text-xs text-slate-600 dark:text-zinc-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-purple-600 file:text-white hover:file:bg-purple-500 transition cursor-pointer">
            <div class="flex items-center gap-2 pt-1">
                <input type="checkbox" name="allow_repeat" id="pdf_allow_repeat" value="1" class="w-4 h-4 text-purple-600 rounded border-slate-300 dark:border-zinc-800 focus:ring-purple-500">
                <label for="pdf_allow_repeat" class="text-xs font-semibold text-slate-700 dark:text-zinc-300 cursor-pointer">Izinkan Siswa Mengulangi Ujian (Fitur Ulangi)</label>
            </div>

            <!-- Guidelines Box -->
            <div class="bg-slate-50 dark:bg-zinc-900/90 border border-slate-200 dark:border-zinc-800 p-3.5 rounded-2xl text-[11px] text-slate-700 dark:text-zinc-400 space-y-2">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-purple-600 dark:text-purple-400 block">💡 Panduan Format Naskah PDF:</span>
                    <a href="{{ route('guru.exams.template') }}" class="px-2.5 py-1 bg-purple-100 dark:bg-purple-950 hover:bg-purple-200 dark:hover:bg-purple-900 border border-purple-300 dark:border-purple-800 text-purple-700 dark:text-purple-300 font-bold rounded-lg text-[10px] flex items-center gap-1 transition">
                        <span>📥</span> Unduh Template Word (.doc)
                    </a>
                </div>
                <p>• Gunakan nomor soal standar (misal: <code>1. Pertanyaan...</code>)</p>
                <p>• Sertakan pilihan ganda <code>A.</code>, <code>B.</code>, <code>C.</code>, <code>D.</code></p>
                <p>• (Opsional) Kunci jawaban per soal <code>Kunci: B</code> atau di akhir dokumen <code>KUNCI JAWABAN: 1.B 2.C</code></p>
                <p>• (Opsional) Pembahasan: <code>Pembahasan: ...</code></p>
                
                <button type="button" onclick="document.getElementById('modal-template-guide').classList.remove('hidden')" class="w-full mt-1 py-1.5 bg-slate-200 dark:bg-zinc-800 hover:bg-slate-300 dark:hover:bg-zinc-700 text-slate-800 dark:text-zinc-200 font-semibold rounded-xl text-[11px] flex items-center justify-center gap-1 transition">
                    <span>📋</span> Lihat Contoh Template Naskah Lengkap
                </button>
            </div>

            <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-purple-600/25 flex items-center justify-center gap-2">
                <span>📄</span> Import & Ekstrak Soal PDF
            </button>
        </form>
    </div>
</div>

<!-- Modal Guide Template Naskah PDF -->
<div id="modal-template-guide" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div class="bg-white dark:bg-[#141416] border border-slate-200 dark:border-zinc-800 w-full max-w-2xl rounded-3xl p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-slate-200 dark:border-zinc-800 pb-3">
            <div>
                <h3 class="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                    <span class="text-purple-600 dark:text-purple-400">📋</span> Contoh Template Naskah Bank Soal
                </h3>
                <p class="text-xs text-slate-500 dark:text-zinc-400">Salin format di bawah atau unduh dokumen Word (.doc) lalu Simpan/Export sebagai PDF.</p>
            </div>
            <button onclick="document.getElementById('modal-template-guide').classList.add('hidden')" class="text-slate-400 hover:text-slate-900 dark:hover:text-white text-lg">✕</button>
        </div>

        <div class="space-y-4 text-xs">
            <div class="space-y-1.5">
                <div class="flex items-center justify-between">
                    <span class="font-bold text-slate-800 dark:text-zinc-200">Format 1: Kunci & Pembahasan Inline (Per Soal)</span>
                    <a href="{{ route('guru.exams.template') }}" class="text-purple-600 dark:text-purple-400 hover:underline text-[11px]">📥 Unduh File Template Word (.doc)</a>
                </div>
                <pre class="bg-slate-100 dark:bg-zinc-950 border border-slate-300 dark:border-zinc-800/80 p-4 rounded-2xl text-slate-800 dark:text-zinc-300 font-mono text-[11px] leading-relaxed overflow-x-auto whitespace-pre-wrap">
1. Hasil dari 15 + 25 x 2 adalah...
a. 80
b. 65
c. 55
d. 70
Kunci: B
Pembahasan: Dahulukan perkalian 25 x 2 = 50. Lalu 15 + 50 = 65.

2. Sebuah persegi memiliki panjang sisi 8 cm. Berapa luasnya?
a. 32 cm²
b. 64 cm²
c. 16 cm²
d. 48 cm²
Kunci: B
Pembahasan: Luas persegi = 8 x 8 = 64 cm².
</pre>
            </div>

            <div class="space-y-1.5">
                <span class="font-bold text-slate-800 dark:text-zinc-200 block">Format 2: Daftar Kunci Terpisah di Akhir Dokumen</span>
                <pre class="bg-slate-100 dark:bg-zinc-950 border border-slate-300 dark:border-zinc-800/80 p-4 rounded-2xl text-slate-800 dark:text-zinc-300 font-mono text-[11px] leading-relaxed overflow-x-auto whitespace-pre-wrap">
1. Soal nomor satu...
a. Opsi A
b. Opsi B
c. Opsi C
d. Opsi D

2. Soal nomor dua...
a. Opsi A
b. Opsi B
c. Opsi C
d. Opsi D

KUNCI JAWABAN:
1. B
2. C
</pre>
            </div>
        </div>

        <div class="flex items-center justify-between border-t border-slate-200 dark:border-zinc-800 pt-4">
            <a href="{{ route('guru.exams.template') }}" class="px-4 py-2.5 bg-slate-100 dark:bg-zinc-900 hover:bg-slate-200 dark:hover:bg-zinc-800 text-purple-700 dark:text-purple-400 border border-purple-300 dark:border-purple-800/60 font-bold text-xs rounded-xl flex items-center gap-2 transition">
                <span>📥</span> Unduh Template Word (.doc)
            </a>
            <div class="flex items-center gap-3">
                <button onclick="document.getElementById('modal-template-guide').classList.add('hidden')" class="px-4 py-2.5 bg-slate-200 dark:bg-zinc-800 hover:bg-slate-300 dark:hover:bg-zinc-700 text-slate-700 dark:text-zinc-300 font-bold text-xs rounded-xl transition">
                    Batal
                </button>
                <button onclick="document.getElementById('modal-template-guide').classList.add('hidden'); document.getElementById('modal-pdf-exam').classList.remove('hidden');" class="px-5 py-2.5 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-purple-600/25 flex items-center gap-2 transition">
                    <span>Lanjutkan ke Upload PDF</span> ➔
                </button>
            </div>
        </div>
    </div>
</div>
@endsection
