@extends('layouts.cbt')

@section('content')
<div class="max-w-7xl mx-auto space-y-8 w-full">

    <!-- Header Section -->
    <div class="flex flex-col md:flex-row md:items-center justify-between gap-4 bg-zinc-900/80 border border-zinc-800 p-6 rounded-3xl backdrop-blur-md">
        <div>
            <div class="flex items-center gap-2 text-blue-400 font-bold text-xs mb-1">
                <span class="px-2 py-0.5 bg-blue-950 border border-blue-800 rounded">Guru Portal</span>
                <span>Dashboard Pengajar</span>
            </div>
            <h1 class="text-2xl font-extrabold text-white">Kelola Paket Ujian & Bank Soal</h1>
            <p class="text-xs text-zinc-400 mt-1">Buat paket simulasi, input pilihan ganda, dan atur pembahasan matematika/pelajaran.</p>
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
        <div class="p-4 bg-emerald-500/10 border border-emerald-500/20 text-emerald-400 text-sm font-semibold rounded-2xl flex items-center gap-2">
            <span>✓</span> {{ session('success') }}
        </div>
    @endif
    @if(session('error'))
        <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-sm font-semibold rounded-2xl flex items-center gap-2">
            <span>⚠️</span> {{ session('error') }}
        </div>
    @endif
    @if($errors->any())
        <div class="p-4 bg-rose-500/10 border border-rose-500/20 text-rose-400 text-xs font-semibold rounded-2xl space-y-1">
            @foreach($errors->all() as $err)
                <p>⚠️ {{ $err }}</p>
            @endforeach
        </div>
    @endif

    <!-- Metric Cards -->
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div class="bg-zinc-900/60 border border-zinc-800/80 p-5 rounded-2xl">
            <span class="text-xs font-semibold text-zinc-400 block mb-1">Total Mata Pelajaran</span>
            <div class="text-2xl font-black text-white">{{ $subjects->count() }}</div>
        </div>
        <div class="bg-zinc-900/60 border border-zinc-800/80 p-5 rounded-2xl">
            <span class="text-xs font-semibold text-zinc-400 block mb-1">Total Paket Simulasi</span>
            <div class="text-2xl font-black text-blue-400">{{ $exams->count() }}</div>
        </div>
        <div class="bg-zinc-900/60 border border-zinc-800/80 p-5 rounded-2xl">
            <span class="text-xs font-semibold text-zinc-400 block mb-1">Total Soal & Pembahasan</span>
            <div class="text-2xl font-black text-purple-400">{{ $totalQuestions }}</div>
        </div>
        <div class="bg-zinc-900/60 border border-zinc-800/80 p-5 rounded-2xl">
            <span class="text-xs font-semibold text-zinc-400 block mb-1">Rata-rata Skor Siswa</span>
            <div class="text-2xl font-black text-emerald-400">{{ $averageScore }} / 100</div>
        </div>
    </div>

    <!-- Tables Grid -->
    <div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

        <!-- Column 1 & 2: List of Exams & Questions -->
        <div class="lg:col-span-2 space-y-6">

            <!-- Exams List -->
            <div class="bg-zinc-900/60 border border-zinc-800 p-6 rounded-3xl space-y-4">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-blue-500"></span>
                    Daftar Paket Simulasi Ujian
                </h2>

                <div class="space-y-4">
                    @foreach($exams as $exam)
                        <div class="bg-[#141416] border border-zinc-800 rounded-2xl p-5 space-y-4">
                            <div class="flex items-start justify-between gap-3">
                                <div>
                                    <span class="px-2 py-0.5 bg-blue-950 text-blue-400 text-[10px] font-bold rounded uppercase">
                                        {{ $exam->subject->title ?? 'Pelajaran' }}
                                    </span>
                                    <h3 class="font-bold text-white text-base mt-1">{{ $exam->title }}</h3>
                                    <p class="text-xs text-zinc-400 mt-0.5">
                                        Durasi: {{ $exam->duration_minutes }} menit · Total Soal: {{ $exam->questions->count() }} · Diikuti: {{ $exam->attempts_count }} siswa
                                    </p>
                                </div>
                                <form action="{{ route('guru.exams.destroy', $exam->id) }}" method="POST" onsubmit="return confirm('Hapus paket ujian ini?')">
                                    @csrf
                                    @method('DELETE')
                                    <button type="submit" class="text-xs text-rose-400 hover:text-rose-300 font-semibold bg-rose-950/50 px-2.5 py-1 rounded-lg border border-rose-900/50">
                                        Hapus Paket
                                    </button>
                                </form>
                            </div>

                            <!-- Preview Questions List -->
                            <div class="space-y-2 pt-3 border-t border-zinc-800/80">
                                <span class="text-xs font-semibold text-zinc-400 block">Daftar Soal Terdaftar:</span>
                                @forelse($exam->questions as $idx => $q)
                                    <div class="bg-zinc-900/90 border border-zinc-800/60 p-3 rounded-xl flex items-start justify-between gap-3 text-xs">
                                        <div class="space-y-1">
                                            <span class="font-bold text-zinc-200">#{{ $idx + 1 }}. {{ $q->question_text }}</span>
                                            <div class="grid grid-cols-2 gap-x-4 text-[11px] text-zinc-400">
                                                <span class="{{ $q->correct_option === 'a' ? 'text-emerald-400 font-bold' : '' }}">A. {{ $q->option_a }}</span>
                                                <span class="{{ $q->correct_option === 'b' ? 'text-emerald-400 font-bold' : '' }}">B. {{ $q->option_b }}</span>
                                                <span class="{{ $q->correct_option === 'c' ? 'text-emerald-400 font-bold' : '' }}">C. {{ $q->option_c }}</span>
                                                <span class="{{ $q->correct_option === 'd' ? 'text-emerald-400 font-bold' : '' }}">D. {{ $q->option_d }}</span>
                                            </div>
                                            @if($q->explanation)
                                                <p class="text-[11px] text-blue-400 italic">Pembahasan: {{ $q->explanation }}</p>
                                            @endif
                                        </div>
                                        <form action="{{ route('guru.questions.destroy', $q->id) }}" method="POST">
                                            @csrf
                                            @method('DELETE')
                                            <button type="submit" class="text-zinc-500 hover:text-rose-400 text-sm">✕</button>
                                        </form>
                                    </div>
                                @empty
                                    <p class="text-xs text-zinc-500 italic">Belum ada soal pada paket ini. Klik tombol "Input Soal & Pembahasan" untuk menambahkan.</p>
                                @endforelse
                            </div>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <!-- Column 3: Recent Student Attempts -->
        <div class="space-y-6">
            <div class="bg-zinc-900/60 border border-zinc-800 p-6 rounded-3xl space-y-4">
                <h2 class="text-lg font-bold text-white flex items-center gap-2">
                    <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                    Hasil Ujian Siswa Terbaru
                </h2>

                <div class="space-y-3">
                    @forelse($attempts as $att)
                        <div class="bg-[#141416] border border-zinc-800 rounded-xl p-3.5 space-y-1.5 text-xs">
                            <div class="flex items-center justify-between">
                                <span class="font-bold text-white">{{ $att->user->name ?? 'Siswa' }}</span>
                                <span class="px-2 py-0.5 bg-emerald-950 text-emerald-400 border border-emerald-800 font-bold rounded">
                                    Skor: {{ $att->score }}
                                </span>
                            </div>
                            <p class="text-zinc-400">{{ $att->exam->title ?? 'Ujian' }}</p>
                            <div class="flex items-center justify-between text-[11px] text-zinc-500 pt-1">
                                <span>Waktu: {{ floor($att->time_taken_seconds / 60) }}m {{ $att->time_taken_seconds % 60 }}s</span>
                                <span>{{ $att->created_at->diffForHumans() }}</span>
                            </div>
                        </div>
                    @empty
                        <p class="text-xs text-zinc-500 italic">Belum ada siswa yang menyelesaikan simulasi.</p>
                    @endforelse
                </div>
            </div>
        </div>

    </div>

</div>

<!-- Modal Create Exam -->
<div id="modal-exam" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div class="bg-[#141416] border border-zinc-800 w-full max-w-md rounded-3xl p-6 shadow-2xl space-y-5">
        <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
            <h3 class="text-lg font-bold text-white">Tambah Paket Ujian Baru</h3>
            <button onclick="document.getElementById('modal-exam').classList.add('hidden')" class="text-zinc-400 hover:text-white">✕</button>
        </div>
        <form action="{{ route('guru.exams.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="text-xs font-semibold text-zinc-300">Pilih Mata Pelajaran</label>
                <select name="subject_id" required class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
                    @foreach($subjects as $s)
                        <option value="{{ $s->id }}">{{ $s->title }} ({{ $s->code }})</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-semibold text-zinc-300">Judul Paket Ujian</label>
                <input type="text" name="title" required placeholder="Contoh: Simulasi Matematika Lanjutan" class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-zinc-300">Durasi (Menit)</label>
                    <input type="number" name="duration_minutes" value="15" required class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-zinc-300">KKM Nilai</label>
                    <input type="number" name="passing_score" value="70" required class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
                </div>
            </div>
            <button type="submit" class="w-full py-3 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-blue-600/20">
                Simpan Paket Ujian
            </button>
        </form>
    </div>
</div>

<!-- Modal Input Question -->
<div id="modal-question" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div class="bg-[#141416] border border-zinc-800 w-full max-w-xl rounded-3xl p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
            <h3 class="text-lg font-bold text-white">Input Soal & Pembahasan</h3>
            <button onclick="document.getElementById('modal-question').classList.add('hidden')" class="text-zinc-400 hover:text-white">✕</button>
        </div>
        <form action="{{ route('guru.questions.store') }}" method="POST" class="space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="text-xs font-semibold text-zinc-300">Pilih Paket Ujian Target</label>
                <select name="exam_id" required class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
                    @foreach($exams as $e)
                        <option value="{{ $e->id }}">{{ $e->title }} ({{ $e->subject->title ?? 'Pelajaran' }})</option>
                    @endforeach
                </select>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-semibold text-zinc-300">Pertanyaan / Soal</label>
                <textarea name="question_text" rows="3" required placeholder="Ketik kalimat soal di sini..." class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white"></textarea>
            </div>
            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-zinc-300">Pilihan A</label>
                    <input type="text" name="option_a" required class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white">
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-zinc-300">Pilihan B</label>
                    <input type="text" name="option_b" required class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white">
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-zinc-300">Pilihan C</label>
                    <input type="text" name="option_c" required class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white">
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-zinc-300">Pilihan D</label>
                    <input type="text" name="option_d" required class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2 text-xs text-white">
                </div>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-semibold text-zinc-300">Kunci Jawaban Benar</label>
                <select name="correct_option" required class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
                    <option value="a">A</option>
                    <option value="b">B</option>
                    <option value="c">C</option>
                    <option value="d">D</option>
                </select>
            </div>
            <div class="space-y-1">
                <label class="text-xs font-semibold text-zinc-300">Pembahasan (Langkah & Rumus)</label>
                <textarea name="explanation" rows="3" placeholder="Tuliskan rumus dan cara penyelesaiannya..." class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white"></textarea>
            </div>
            <button type="submit" class="w-full py-3 bg-emerald-600 hover:bg-emerald-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-emerald-600/20">
                Simpan Soal & Pembahasan
            </button>
        </form>
    </div>
</div>

<!-- Modal Upload PDF Exam -->
<div id="modal-pdf-exam" class="hidden fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
    <div class="bg-[#141416] border border-zinc-800 w-full max-w-lg rounded-3xl p-6 shadow-2xl space-y-5 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between border-b border-zinc-800 pb-3">
            <div>
                <h3 class="text-lg font-bold text-white flex items-center gap-2">
                    <span class="text-purple-400">📄</span> Import Paket Ujian dari PDF
                </h3>
                <p class="text-xs text-zinc-400">Unggah file PDF bank soal untuk membuat paket ujian secara otomatis.</p>
            </div>
            <button onclick="document.getElementById('modal-pdf-exam').classList.add('hidden')" class="text-zinc-400 hover:text-white text-lg">✕</button>
        </div>

        <form action="{{ route('guru.exams.import') }}" method="POST" enctype="multipart/form-data" class="space-y-4">
            @csrf
            <div class="space-y-1">
                <label class="text-xs font-semibold text-zinc-300">Pilih Mata Pelajaran</label>
                <select name="subject_id" required class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
                    @foreach($subjects as $s)
                        <option value="{{ $s->id }}">{{ $s->title }} ({{ $s->code }})</option>
                    @endforeach
                </select>
            </div>

            <div class="space-y-1">
                <label class="text-xs font-semibold text-zinc-300">Judul Paket Ujian</label>
                <input type="text" name="title" required placeholder="Contoh: Paket Tryout UN Matematika 2026 (PDF)" class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-zinc-300">Durasi (Menit)</label>
                    <input type="number" name="duration_minutes" value="15" required class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
                </div>
                <div class="space-y-1">
                    <label class="text-xs font-semibold text-zinc-300">KKM Nilai</label>
                    <input type="number" name="passing_score" value="70" required class="w-full bg-zinc-900 border border-zinc-800 rounded-xl px-3 py-2.5 text-xs text-white">
                </div>
            </div>

            <div class="space-y-1.5">
                <label class="text-xs font-semibold text-zinc-300">Pilih File PDF Naskah Soal</label>
                <div class="border-2 border-dashed border-purple-500/30 hover:border-purple-500/60 bg-purple-950/10 p-5 rounded-2xl text-center space-y-2 transition">
                    <svg class="w-8 h-8 text-purple-400 mx-auto" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16a4 4 0 01-.88-7.903A5 5 0 1115.9 6L16 6a5 5 0 011 9.9M15 13l-3-3m0 0l-3 3m3-3v12"/>
                    </svg>
                    <input type="file" name="pdf_file" accept=".pdf" required class="block w-full text-xs text-zinc-400 file:mr-4 file:py-2 file:px-4 file:rounded-xl file:border-0 file:text-xs file:font-semibold file:bg-purple-600 file:text-white hover:file:bg-purple-500 transition cursor-pointer">
                    <span class="text-[11px] text-zinc-500 block">Format didukung: PDF (Maks. 10MB)</span>
                </div>
            </div>

            <!-- Guidelines Box -->
            <div class="bg-zinc-900/90 border border-zinc-800 p-3.5 rounded-2xl text-[11px] text-zinc-400 space-y-1">
                <span class="font-bold text-purple-400 block mb-0.5">💡 Panduan Format Naskah PDF:</span>
                <p>• Gunakan nomor soal standar (misal: <code>1. Pertanyaan...</code>)</p>
                <p>• Sertakan pilihan ganda <code>A.</code>, <code>B.</code>, <code>C.</code>, <code>D.</code></p>
                <p>• (Opsional) Kunci jawaban: <code>Kunci: B</code> & Pembahasan: <code>Pembahasan: ...</code></p>
            </div>

            <button type="submit" class="w-full py-3 bg-purple-600 hover:bg-purple-500 text-white font-bold text-xs rounded-xl shadow-lg shadow-purple-600/25 flex items-center justify-center gap-2">
                <span>📄</span> Import & Ekstrak Soal PDF
            </button>
        </form>
    </div>
</div>
@endsection
