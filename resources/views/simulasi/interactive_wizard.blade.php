@extends('layouts.cbt')

@section('content')
<div x-data="simulasiWizard()" x-init="initData()" class="w-full max-w-lg mx-auto">

    <!-- Step Container / Card matching reference UI design -->
    <div class="bg-[#121214] border border-zinc-800/90 rounded-3xl p-6 sm:p-7 shadow-2xl relative overflow-hidden backdrop-blur-xl">

        <!-- ========================================== -->
        <!-- STEP 1: MASUK KE AKUN (LOGIN MODAL)        -->
        <!-- ========================================== -->
        <div x-show="currentStep === 1" x-transition.opacity.duration.300ms class="space-y-6">
            <!-- Brand Badge -->
            <div class="flex items-center gap-2 text-blue-400 font-bold text-sm">
                <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                </svg>
                <span>SimulasiKu</span>
            </div>

            <!-- Title & Subtitle -->
            <div>
                <h1 class="text-2xl font-extrabold text-white tracking-tight">Masuk ke akun</h1>
                <p class="text-sm text-zinc-400 mt-1">Masukkan username dan password untuk mulai berlatih.</p>
            </div>

            <!-- Form -->
            <form @submit.prevent="handleLogin()" class="space-y-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-zinc-300">Username</label>
                    <input type="text" x-model="loginForm.username" required placeholder="siswa2026"
                           class="w-full bg-[#1c1c1f] border border-zinc-800 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-zinc-300">Password</label>
                    <input type="password" x-model="loginForm.password" required placeholder="••••••••"
                           class="w-full bg-[#1c1c1f] border border-zinc-800 rounded-xl px-4 py-3 text-sm text-white placeholder-zinc-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                </div>

                <div x-show="errorMessage" class="text-xs text-rose-400 bg-rose-500/10 border border-rose-500/20 p-3 rounded-lg" x-text="errorMessage"></div>

                <button type="submit" :disabled="loading" class="w-full py-3.5 px-4 bg-[#2563eb] hover:bg-blue-500 text-white font-bold text-sm rounded-xl transition shadow-lg shadow-blue-600/25 flex items-center justify-center gap-2">
                    <span x-show="!loading">Masuk</span>
                    <span x-show="loading" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Memproses...
                    </span>
                </button>
            </form>

            <p class="text-xs text-zinc-500 text-center">Jika login gagal, kamu akan diminta mencoba lagi.</p>

            <!-- Quick Demo Credentials Selector -->
            <div class="pt-2 border-t border-zinc-800/80">
                <span class="text-[11px] font-semibold text-zinc-500 block mb-2">Pilih Akun Demo Uji Coba:</span>
                <div class="grid grid-cols-3 gap-2">
                    <button type="button" @click="fillCredentials('siswa2026', 'password')" class="px-2.5 py-1.5 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 rounded-lg text-xs font-medium text-blue-400 text-center">
                        Murid
                    </button>
                    <button type="button" @click="fillCredentials('guru1', 'password')" class="px-2.5 py-1.5 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 rounded-lg text-xs font-medium text-purple-400 text-center">
                        Guru
                    </button>
                    <button type="button" @click="fillCredentials('admin', 'password')" class="px-2.5 py-1.5 bg-zinc-900 hover:bg-zinc-800 border border-zinc-800 rounded-lg text-xs font-medium text-emerald-400 text-center">
                        Admin
                    </button>
                </div>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- STEP 2: PELAJARAN UNTUKMU (SUBJECT SELECT) -->
        <!-- ========================================== -->
        <div x-show="currentStep === 2" x-transition.opacity.duration.300ms class="space-y-6">
            <!-- Header with Status Pill -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-blue-400 font-bold text-sm">
                    <svg class="w-5 h-5 text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                    </svg>
                    <span>SimulasiKu</span>
                </div>
                <span class="px-2.5 py-1 bg-emerald-950/80 border border-emerald-800/80 text-emerald-400 text-xs font-semibold rounded-full flex items-center gap-1.5">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-400"></span>
                    Login berhasil
                </span>
            </div>

            <!-- Title & Subtitle -->
            <div>
                <h1 class="text-2xl font-extrabold text-white tracking-tight">Pelajaran untukmu</h1>
                <p class="text-sm text-zinc-400 mt-1">Sistem otomatis menampilkan daftar pelajaran sesuai jenjangmu.</p>
            </div>

            <!-- List of Subjects / Exams -->
            <div class="space-y-3">
                <template x-for="subj in subjects" :key="subj.id">
                    <div class="space-y-2">
                        <template x-for="exam in subj.exams" :key="exam.id">
                            <div @click="selectExam(exam, subj)"
                                 :class="selectedExam && selectedExam.id === exam.id ? 'bg-[#18181c] border-blue-500 ring-1 ring-blue-500' : 'bg-[#161618] border-zinc-800/90 hover:border-zinc-700'"
                                 class="group p-4 rounded-2xl border transition cursor-pointer flex items-center justify-between">
                                <div class="space-y-1">
                                    <h3 class="font-bold text-white text-base group-hover:text-blue-400 transition" x-text="subj.title"></h3>
                                    <p class="text-xs text-zinc-400" x-text="`${exam.questions_count || exam.total_questions || 10} soal · ${exam.duration_minutes} menit`"></p>
                                </div>
                                <svg class="w-5 h-5 text-zinc-500 group-hover:text-white transition group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                </svg>
                            </div>
                        </template>
                    </div>
                </template>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- STEP 3: EXAM PLAYER (PENGERJAAN SOAL)      -->
        <!-- ========================================== -->
        <div x-show="currentStep === 3" x-transition.opacity.duration.300ms class="space-y-6">
            <!-- Subject Title & Question Index Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-blue-400 font-bold text-sm">
                    <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                    </svg>
                    <span x-text="currentSubject ? currentSubject.title : 'Matematika'"></span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-semibold text-zinc-400" x-text="`Soal ${currentQuestionIndex + 1}/${questions.length}`"></span>
                    <span class="px-2 py-0.5 bg-blue-950 border border-blue-800 text-blue-300 text-[11px] font-mono rounded" x-text="formatTimer()"></span>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="w-full bg-zinc-800/80 rounded-full h-1.5 overflow-hidden">
                <div class="bg-blue-500 h-1.5 rounded-full transition-all duration-300" :style="`width: ${((currentQuestionIndex + 1) / questions.length) * 100}%`"></div>
            </div>

            <!-- Question Text -->
            <div class="py-2">
                <p class="text-base font-semibold text-zinc-100 leading-relaxed" x-text="currentQuestion ? currentQuestion.question_text : ''"></p>
            </div>

            <!-- Radio Options -->
            <div class="space-y-3" x-if="currentQuestion">
                <!-- Option A -->
                <label @click="selectOption('a')"
                       :class="userAnswers[currentQuestion?.id] === 'a' ? 'bg-[#0f2240] border-blue-500 text-white shadow-lg shadow-blue-950/50' : 'bg-[#18181b] border-zinc-800 text-zinc-300 hover:border-zinc-700'"
                       class="flex items-center p-4 rounded-xl border transition cursor-pointer">
                    <span class="font-semibold text-sm" x-text="currentQuestion?.option_a"></span>
                </label>

                <!-- Option B -->
                <label @click="selectOption('b')"
                       :class="userAnswers[currentQuestion?.id] === 'b' ? 'bg-[#0f2240] border-blue-500 text-white shadow-lg shadow-blue-950/50' : 'bg-[#18181b] border-zinc-800 text-zinc-300 hover:border-zinc-700'"
                       class="flex items-center p-4 rounded-xl border transition cursor-pointer">
                    <span class="font-semibold text-sm" x-text="currentQuestion?.option_b"></span>
                </label>

                <!-- Option C -->
                <label @click="selectOption('c')"
                       :class="userAnswers[currentQuestion?.id] === 'c' ? 'bg-[#0f2240] border-blue-500 text-white shadow-lg shadow-blue-950/50' : 'bg-[#18181b] border-zinc-800 text-zinc-300 hover:border-zinc-700'"
                       class="flex items-center p-4 rounded-xl border transition cursor-pointer">
                    <span class="font-semibold text-sm" x-text="currentQuestion?.option_c"></span>
                </label>

                <!-- Option D -->
                <label @click="selectOption('d')"
                       :class="userAnswers[currentQuestion?.id] === 'd' ? 'bg-[#0f2240] border-blue-500 text-white shadow-lg shadow-blue-950/50' : 'bg-[#18181b] border-zinc-800 text-zinc-300 hover:border-zinc-700'"
                       class="flex items-center p-4 rounded-xl border transition cursor-pointer">
                    <span class="font-semibold text-sm" x-text="currentQuestion?.option_d"></span>
                </label>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- STEP 4: SIMULASI SELESAI (RESULTS SUMMARY) -->
        <!-- ========================================== -->
        <div x-show="currentStep === 4" x-transition.opacity.duration.300ms class="space-y-6 text-center py-2">
            <!-- Success Checkmark Icon -->
            <div class="w-14 h-14 bg-emerald-500/20 border border-emerald-500/30 text-emerald-400 rounded-full flex items-center justify-center mx-auto animate-bounce">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>

            <!-- Title & Subtitle -->
            <div>
                <h1 class="text-2xl font-extrabold text-white tracking-tight">Simulasi Selesai</h1>
                <p class="text-sm text-zinc-400 mt-1">Kerja bagus! Hasil latihanmu sudah tersimpan.</p>
            </div>

            <!-- Main Score Badge Card (0-100 Integer Scale) -->
            <div class="bg-gradient-to-b from-blue-950/50 to-zinc-900/90 border border-blue-500/30 rounded-2xl p-5 text-center space-y-1 shadow-xl">
                <span class="text-xs font-semibold text-blue-400 uppercase tracking-wider block">Nilai Akhir (Skala 100)</span>
                <div class="text-5xl font-black py-2 flex items-baseline justify-center gap-1">
                    <span class="bg-gradient-to-r from-blue-400 via-emerald-400 to-teal-300 bg-clip-text text-transparent" x-text="examResult ? examResult.score : 0"></span>
                    <span class="text-lg font-bold text-zinc-500">/ 100</span>
                </div>
            </div>

            <!-- Stat Boxes -->
            <div class="space-y-3 text-left">
                <div class="bg-[#18181c] border border-zinc-800/90 rounded-xl p-4 flex items-center justify-between">
                    <span class="text-sm font-medium text-zinc-400">Jawaban Benar</span>
                    <span class="text-base font-bold text-emerald-400" x-text="examResult ? `${examResult.correct_answers} dari ${examResult.total_questions} Soal` : ''"></span>
                </div>

                <div class="bg-[#18181c] border border-zinc-800/90 rounded-xl p-4 flex items-center justify-between">
                    <span class="text-sm font-medium text-zinc-400">Waktu Pengerjaan</span>
                    <span class="text-base font-bold text-white" x-text="examResult?.time_taken_formatted || ''"></span>
                </div>
            </div>

            <!-- Primary Blue Button: Lihat Pembahasan -->
            <button @click="showPembahasanModal = true" class="w-full py-3.5 px-4 bg-[#2563eb] hover:bg-blue-500 text-white font-bold text-sm rounded-xl transition shadow-lg shadow-blue-600/25 flex items-center justify-center gap-2">
                Lihat pembahasan
            </button>
        </div>


        <!-- ========================================== -->
        <!-- STEPPER INDICATOR & COMMON FOOTER BUTTONS  -->
        <!-- ========================================== -->
        <div class="pt-6 mt-6 border-t border-zinc-800/80 space-y-4">
            <!-- Stepper Dots -->
            <div class="flex items-center justify-center gap-2">
                <template x-for="i in 4" :key="i">
                    <div :class="currentStep === i ? 'w-2.5 h-2.5 bg-blue-500' : 'w-2 h-2 bg-zinc-700'"
                         class="rounded-full transition-all duration-300"></div>
                </template>
            </div>

            <!-- Bottom Navigation Buttons (Kembali / Lanjut / Ulangi) -->
            <div class="flex items-center justify-between gap-3">
                <!-- Back Button -->
                <button type="button" @click="goBack()"
                        :disabled="currentStep === 1"
                        :class="currentStep === 1 ? 'opacity-40 cursor-not-allowed text-zinc-600 bg-zinc-900 border-zinc-800' : 'text-zinc-300 bg-[#1a1a1e] hover:bg-zinc-800 border-zinc-800'"
                        class="px-5 py-2.5 rounded-xl border text-sm font-semibold transition">
                    Kembali
                </button>

                <!-- Forward / Action Button -->
                <template x-if="currentStep === 1">
                    <button type="button" @click="handleLogin()" class="px-5 py-2.5 bg-[#1a1a1e] hover:bg-zinc-800 border border-zinc-800 text-zinc-200 text-sm font-semibold rounded-xl transition">
                        Lanjut
                    </button>
                </template>

                <template x-if="currentStep === 2">
                    <button type="button" @click="startExam()" :disabled="!selectedExam"
                            :class="!selectedExam ? 'opacity-40 cursor-not-allowed' : 'hover:bg-zinc-800 text-white'"
                            class="px-5 py-2.5 bg-[#1a1a1e] border border-zinc-800 text-sm font-semibold rounded-xl transition">
                        Lanjut
                    </button>
                </template>

                <template x-if="currentStep === 3">
                    <button type="button" @click="nextQuestionOrSubmit()"
                            class="px-5 py-2.5 bg-[#1a1a1e] hover:bg-zinc-800 border border-zinc-800 text-zinc-200 text-sm font-semibold rounded-xl transition">
                        <span x-text="currentQuestionIndex < questions.length - 1 ? 'Lanjut' : 'Selesai'"></span>
                    </button>
                </template>

                <template x-if="currentStep === 4">
                    <button type="button" @click="repeatExam()" class="px-5 py-2.5 bg-[#1a1a1e] hover:bg-zinc-800 border border-zinc-800 text-zinc-200 text-sm font-semibold rounded-xl transition">
                        Ulangi
                    </button>
                </template>
            </div>
        </div>

    </div>

    <!-- ======================================================= -->
    <!-- MODAL PEMBAHASAN SOAL (DETAILED ANSWER KEY & EXPLANATIONS) -->
    <!-- ======================================================= -->
    <div x-show="showPembahasanModal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm" x-transition.opacity>
        <div @click.away="showPembahasanModal = false" class="bg-[#141416] border border-zinc-800 w-full max-w-2xl rounded-3xl p-6 sm:p-7 shadow-2xl max-h-[85vh] flex flex-col">
            <div class="flex items-center justify-between pb-4 border-b border-zinc-800">
                <div>
                    <h2 class="text-xl font-bold text-white">Pembahasan & Kunci Jawaban</h2>
                    <p class="text-xs text-zinc-400">Penjelasan detail langkah demi langkah untuk setiap soal.</p>
                </div>
                <button @click="showPembahasanModal = false" class="w-8 h-8 rounded-full bg-zinc-800 text-zinc-400 hover:text-white flex items-center justify-center transition">
                    ✕
                </button>
            </div>

            <div class="flex-1 overflow-y-auto py-4 space-y-6 pr-1">
                <template x-for="item in examResult?.review" :key="item.number">
                    <div class="bg-[#1a1a1e] border rounded-2xl p-5 space-y-3"
                         :class="item.is_correct ? 'border-emerald-900/60 bg-emerald-950/10' : 'border-rose-900/60 bg-rose-950/10'">
                        <div class="flex items-start justify-between gap-3">
                            <span class="text-sm font-bold text-zinc-200" x-text="`Soal ${item.number}`"></span>
                            <span :class="item.is_correct ? 'bg-emerald-900/50 text-emerald-400 border-emerald-700/50' : 'bg-rose-900/50 text-rose-400 border-rose-700/50'"
                                  class="px-2.5 py-0.5 text-xs font-semibold rounded-full border">
                                <span x-text="item.is_correct ? 'Benar (+10)' : 'Salah (0)'"></span>
                            </span>
                        </div>

                        <p class="text-sm font-medium text-white" x-text="item.question_text"></p>

                        <div class="space-y-1.5 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="text-zinc-400">Jawabanmu:</span>
                                <span :class="item.is_correct ? 'text-emerald-400 font-bold' : 'text-rose-400 font-bold'" x-text="`${item.selected_option ? item.selected_option.toUpperCase() + '. ' : ''}${item.selected_text}`"></span>
                            </div>

                            <div x-show="!item.is_correct" class="flex items-center gap-2">
                                <span class="text-zinc-400">Jawaban Benar:</span>
                                <span class="text-emerald-400 font-bold" x-text="`${item.correct_option.toUpperCase()}. ${item.correct_text}`"></span>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-zinc-800/80 bg-zinc-900/60 -mx-5 -mb-5 p-4 rounded-b-2xl">
                            <span class="text-xs font-semibold text-blue-400 block mb-1">💡 Pembahasan:</span>
                            <p class="text-xs text-zinc-300 leading-relaxed" x-text="item.explanation"></p>
                        </div>
                    </div>
                </template>
            </div>

            <div class="pt-4 border-t border-zinc-800 text-right">
                <button @click="showPembahasanModal = false" class="px-6 py-2.5 bg-blue-600 hover:bg-blue-500 text-white font-bold text-xs rounded-xl transition">
                    Tutup Pembahasan
                </button>
            </div>
        </div>
    </div>

</div>
@endsection

@section('scripts')
<script>
    function simulasiWizard() {
        return {
            currentStep: 1, // 1: Login, 2: Pelajaran, 3: Exam Player, 4: Result
            loading: false,
            errorMessage: '',
            subjects: @json($subjects),
            selectedExam: null,
            currentSubject: null,
            questions: [],
            currentQuestionIndex: 0,
            userAnswers: {},
            examResult: null,
            timerSeconds: 0,
            timerInterval: null,
            timeTakenSeconds: 0,
            showPembahasanModal: false,

            loginForm: {
                username: 'siswa2026',
                password: 'password'
            },

            initData() {
                // Auto select first subject exam if available
                if (this.subjects.length > 0 && this.subjects[0].exams.length > 0) {
                    this.selectedExam = this.subjects[0].exams[0];
                    this.currentSubject = this.subjects[0];
                }
            },

            fillCredentials(username, password) {
                this.loginForm.username = username;
                this.loginForm.password = password;
                this.handleLogin();
            },

            async handleLogin() {
                this.loading = true;
                this.errorMessage = '';

                try {
                    const response = await fetch("{{ route('login.post') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify(this.loginForm)
                    });

                    const data = await response.json();
                    this.loading = false;

                    if (data.success) {
                        if (data.user.role === 'admin') {
                            window.location.href = "{{ route('admin.dashboard') }}";
                        } else if (data.user.role === 'guru') {
                            window.location.href = "{{ route('guru.dashboard') }}";
                        } else {
                            this.currentStep = 2;
                        }
                    } else {
                        this.errorMessage = data.message || 'Login gagal. Coba lagi.';
                    }
                } catch (err) {
                    this.loading = false;
                    // Fallback to offline mode for testing step 2
                    this.currentStep = 2;
                }
            },

            selectExam(exam, subject) {
                this.selectedExam = exam;
                this.currentSubject = subject;
                this.startExam();
            },

            async startExam() {
                if (!this.selectedExam) return;

                this.loading = true;
                try {
                    const res = await fetch(`/api/exams/${this.selectedExam.id}`);
                    const data = await res.json();
                    if (data.success) {
                        this.questions = data.exam.questions;
                        this.currentQuestionIndex = 0;
                        this.userAnswers = {};
                        this.timeTakenSeconds = 0;
                        this.timerSeconds = (this.selectedExam.duration_minutes || 15) * 60;
                        this.startTimer();
                        this.currentStep = 3;
                    }
                } catch (e) {
                    console.error(e);
                } finally {
                    this.loading = false;
                }
            },

            get currentQuestion() {
                return this.questions[this.currentQuestionIndex] || null;
            },

            selectOption(optionKey) {
                if (this.currentQuestion) {
                    this.userAnswers[this.currentQuestion.id] = optionKey;
                }
            },

            nextQuestionOrSubmit() {
                if (this.currentQuestionIndex < this.questions.length - 1) {
                    this.currentQuestionIndex++;
                } else {
                    this.submitExam();
                }
            },

            startTimer() {
                if (this.timerInterval) clearInterval(this.timerInterval);
                this.timerInterval = setInterval(() => {
                    this.timeTakenSeconds++;
                    if (this.timerSeconds > 0) {
                        this.timerSeconds--;
                    } else {
                        clearInterval(this.timerInterval);
                        this.submitExam();
                    }
                }, 1000);
            },

            formatTimer() {
                const m = Math.floor(this.timerSeconds / 60);
                const s = this.timerSeconds % 60;
                return `${m.toString().padStart(2, '0')}:${s.toString().padStart(2, '0')}`;
            },

            async submitExam() {
                if (this.timerInterval) clearInterval(this.timerInterval);

                try {
                    const response = await fetch("{{ route('api.exam.submit') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify({
                            exam_id: this.selectedExam.id,
                            answers: this.userAnswers,
                            time_taken_seconds: this.timeTakenSeconds
                        })
                    });

                    const data = await response.json();
                    if (data.success) {
                        this.examResult = data;
                        this.currentStep = 4;
                    }
                } catch (e) {
                    console.error(e);
                }
            },

            goBack() {
                if (this.currentStep > 1) {
                    if (this.currentStep === 3) {
                        if (this.currentQuestionIndex > 0) {
                            this.currentQuestionIndex--;
                            return;
                        } else {
                            if (confirm('Kembali ke daftar pelajaran? Kemajuan soal akan direset.')) {
                                if (this.timerInterval) clearInterval(this.timerInterval);
                                this.currentStep = 2;
                            }
                            return;
                        }
                    }
                    this.currentStep--;
                }
            },

            repeatExam() {
                this.userAnswers = {};
                this.examResult = null;
                this.startExam();
            }
        }
    }
</script>
@endsection
