@extends('layouts.cbt')

@section('content')
<div x-data="simulasiWizard()" x-init="initData()" class="w-full max-w-lg mx-auto">

    <!-- Step Container / Card matching reference UI design -->
    <div class="bg-white dark:bg-[#121214] border border-slate-200 dark:border-zinc-800/90 rounded-3xl p-6 sm:p-7 shadow-xl dark:shadow-2xl relative overflow-hidden backdrop-blur-xl transition-colors">

        <!-- ========================================== -->
        <!-- STEP 1: MASUK KE AKUN (LOGIN MODAL)        -->
        <!-- ========================================== -->
        <div x-show="currentStep === 1" x-transition.opacity.duration.300ms class="space-y-6">
            <!-- Brand Badge -->
            <div class="flex items-center gap-2 text-blue-600 dark:text-blue-400 font-bold text-sm">
                <svg class="w-5 h-5 text-blue-600 dark:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                </svg>
                <span>SimulasiKu</span>
            </div>

            <!-- Title & Subtitle -->
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Masuk ke akun</h1>
                <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1">Masukkan username dan password untuk mulai berlatih.</p>
            </div>

            <!-- Form -->
            <form @submit.prevent="handleLogin()" class="space-y-4">
                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300">Username</label>
                    <input type="text" x-model="loginForm.username" required placeholder="Masukkan username"
                           class="w-full bg-slate-50 dark:bg-[#1c1c1f] border border-slate-300 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                </div>

                <div class="space-y-1.5">
                    <label class="block text-xs font-semibold text-slate-700 dark:text-zinc-300">Password</label>
                    <input type="password" x-model="loginForm.password" required placeholder="Masukkan password"
                           class="w-full bg-slate-50 dark:bg-[#1c1c1f] border border-slate-300 dark:border-zinc-800 rounded-xl px-4 py-3 text-sm text-slate-900 dark:text-white placeholder-slate-400 dark:placeholder-zinc-500 focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition">
                </div>

                <div x-show="errorMessage" class="text-xs text-rose-600 dark:text-rose-400 bg-rose-500/10 border border-rose-500/20 p-3 rounded-lg" x-text="errorMessage"></div>

                <button type="submit" :disabled="loading" class="w-full py-3.5 px-4 bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm rounded-xl transition shadow-lg shadow-blue-600/25 flex items-center justify-center gap-2">
                    <span x-show="!loading">Masuk</span>
                    <span x-show="loading" class="flex items-center gap-2">
                        <svg class="animate-spin h-4 w-4 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path></svg>
                        Memproses...
                    </span>
                </button>
            </form>

            <p class="text-xs text-slate-500 dark:text-zinc-500 text-center">Jika login gagal, kamu akan diminta mencoba lagi.</p>
        </div>

        <!-- ========================================== -->
        <!-- STEP 2: DASHBOARD MURID (SPACE UTAMA SISWA) -->
        <!-- ========================================== -->
        <div x-show="currentStep === 2" x-transition.opacity.duration.300ms class="space-y-6">
            <!-- Header with Status Pill & Logout Button -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-blue-600 dark:text-blue-400 font-bold text-sm">
                    <svg class="w-5 h-5 text-blue-600 dark:text-blue-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l9-5-9-5-9 5 9 5z"/>
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 14l6.16-3.422a12.083 12.083 0 01.665 6.479A11.952 11.952 0 0012 20.055a11.952 11.952 0 00-6.824-2.998 12.078 12.078 0 01.665-6.479L12 14z"/>
                    </svg>
                    <span>SimulasiKu</span>
                </div>
                <div class="flex items-center gap-2">
                    <span class="px-2.5 py-1 bg-emerald-100 dark:bg-emerald-950/80 border border-emerald-300 dark:border-emerald-800/80 text-emerald-700 dark:text-emerald-400 text-xs font-semibold rounded-full flex items-center gap-1.5">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 animate-pulse"></span>
                        Dashboard Murid
                    </span>
                    <form action="{{ route('logout') }}" method="POST" class="inline">
                        @csrf
                        <button type="submit" class="px-2.5 py-1 text-xs font-bold text-rose-600 dark:text-rose-400 hover:text-rose-700 bg-rose-50 dark:bg-rose-950/50 hover:bg-rose-100 dark:hover:bg-rose-900/50 border border-rose-200 dark:border-rose-900/50 rounded-full transition flex items-center gap-1">
                            <span>🚪</span> Logout
                        </button>
                    </form>
                </div>
            </div>

            <!-- Student Welcome Card -->
            <div class="bg-gradient-to-r from-blue-50 via-indigo-50 to-purple-50 dark:from-blue-950/40 dark:via-zinc-900 dark:to-indigo-950/30 border border-blue-200 dark:border-blue-500/20 rounded-2xl p-5 space-y-3">
                <div class="flex items-center justify-between">
                    <div>
                        <span class="text-xs text-slate-500 dark:text-zinc-400 font-medium">Ruang Ujian Siswa</span>
                        <h1 class="text-xl font-extrabold text-slate-900 dark:text-white tracking-tight flex items-center gap-2">
                            <span>Selamat Datang,</span>
                            <span class="text-blue-600 dark:text-blue-400" x-text="userName"></span>
                        </h1>
                    </div>
                    <span class="px-3 py-1 bg-purple-100 dark:bg-purple-950/90 text-purple-700 dark:text-purple-300 text-xs font-bold rounded-lg border border-purple-300 dark:border-purple-800/50" x-text="userGrade || 'SD Kelas 4'"></span>
                </div>
                <p class="text-xs text-slate-600 dark:text-zinc-300 leading-relaxed">
                    Ini adalah area ruang ujianmu. Kamu bebas menentukan kapan mulai pengerjaan ujian mata pelajaran. Siapkan mental, alat tulis jika diperlukan, dan koneksi internet yang stabil.
                </p>
            </div>

            <!-- Status & Guidelines Cards -->
            <div class="grid grid-cols-2 gap-3 text-xs">
                <div class="bg-slate-50 dark:bg-[#161618] border border-slate-200 dark:border-zinc-800/90 rounded-xl p-3.5 space-y-1">
                    <span class="text-slate-500 dark:text-zinc-400 font-medium block">Status Akses</span>
                    <div class="text-emerald-600 dark:text-emerald-400 font-bold flex items-center gap-1.5">
                        <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                        <span x-text="totalAvailableExams > 0 ? 'Siap Mengikuti Ujian' : 'Belum Ada Ujian'"></span>
                    </div>
                </div>
                <div class="bg-slate-50 dark:bg-[#161618] border border-slate-200 dark:border-zinc-800/90 rounded-xl p-3.5 space-y-1">
                    <span class="text-slate-500 dark:text-zinc-400 font-medium block">Pilihan Waktu</span>
                    <div class="text-blue-600 dark:text-blue-400 font-bold flex items-center gap-1.5">
                        <span>⏱️ Mandiri / Bebas</span>
                    </div>
                </div>
            </div>

            <!-- Info Box -->
            <div class="bg-slate-100 dark:bg-zinc-900/80 border border-slate-200 dark:border-zinc-800 p-4 rounded-xl space-y-2">
                <span class="text-xs font-bold text-slate-800 dark:text-zinc-200 block flex items-center gap-1.5">
                    <span>📌</span> Petunjuk Sebelum Memulai:
                </span>
                <ul class="text-xs text-slate-600 dark:text-zinc-400 space-y-1 list-disc list-inside">
                    <li>Klik tombol "Pilih Pelajaran" di bawah untuk melihat daftar ujian.</li>
                    <li>Timer ujian akan berjalan setelah soal pertama muncul.</li>
                    <li>Nilai dan pembahasan langsung dapat dilihat setelah submit.</li>
                </ul>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- STEP 3: PELAJARAN UNTUKMU (SUBJECT SELECT) -->
        <!-- ========================================== -->
        <div x-show="currentStep === 3" x-transition.opacity.duration.300ms class="space-y-6">
            <!-- Header with Status Pill -->
            <div class="flex items-center justify-between">
                <button @click="currentStep = 2" class="text-xs font-semibold text-slate-600 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white flex items-center gap-1 bg-slate-100 dark:bg-zinc-900 border border-slate-200 dark:border-zinc-800 px-3 py-1.5 rounded-lg transition">
                    <span>← Kembali ke Dashboard</span>
                </button>
                <span class="px-2.5 py-1 bg-purple-100 dark:bg-purple-950/80 border border-purple-300 dark:border-purple-800/80 text-purple-700 dark:text-purple-300 text-xs font-semibold rounded-full flex items-center gap-1.5" x-text="userGrade || 'SD Kelas 4'">
                </span>
            </div>

            <!-- Title & Subtitle -->
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Pelajaran untukmu</h1>
                <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1">Sistem otomatis menampilkan daftar pelajaran sesuai jenjangmu.</p>
            </div>

            <!-- List of Subjects / Exams (Filtered by Grade Level) -->
            <div class="space-y-3">
                <template x-if="totalAvailableExams > 0">
                    <div class="space-y-3">
                        <template x-for="subj in filteredSubjects" :key="subj.id">
                            <div class="space-y-2">
                                <template x-for="exam in subj.exams" :key="exam.id">
                                    <div @click="selectExam(exam, subj)"
                                         :class="selectedExam && selectedExam.id === exam.id ? 'bg-blue-50 dark:bg-[#18181c] border-blue-500 ring-1 ring-blue-500' : 'bg-slate-50 dark:bg-[#161618] border-slate-200 dark:border-zinc-800/90 hover:border-slate-300 dark:hover:border-zinc-700'"
                                         class="group p-4 rounded-2xl border transition cursor-pointer flex items-center justify-between shadow-sm">
                                        <div class="space-y-1">
                                            <div class="flex items-center gap-2">
                                                <h3 class="font-bold text-slate-900 dark:text-white text-base group-hover:text-blue-600 dark:group-hover:text-blue-400 transition" x-text="subj.title"></h3>
                                                <span class="px-2 py-0.5 bg-purple-100 dark:bg-purple-950 text-purple-700 dark:text-purple-300 text-[10px] font-bold rounded border border-purple-300 dark:border-purple-900/50" x-text="exam.grade_level || 'SD Kelas 4'"></span>
                                            </div>
                                            <p class="text-xs text-slate-500 dark:text-zinc-400" x-text="`${exam.questions_count || exam.total_questions || 10} soal · ${exam.duration_minutes} menit`"></p>
                                        </div>
                                        <div class="flex items-center gap-2">
                                            <span class="text-xs font-bold text-blue-600 dark:text-blue-400 group-hover:underline">Mulai</span>
                                            <svg class="w-5 h-5 text-slate-400 dark:text-zinc-500 group-hover:text-slate-800 dark:group-hover:text-white transition group-hover:translate-x-1" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                                            </svg>
                                        </div>
                                    </div>
                                </template>
                            </div>
                        </template>
                    </div>
                </template>

                <!-- Empty State: No Exams Available for Student's Grade Level -->
                <template x-if="totalAvailableExams === 0">
                    <div class="bg-slate-50 dark:bg-[#161618] border border-slate-200 dark:border-zinc-800/90 rounded-2xl p-8 text-center space-y-4 shadow-sm">
                        <div class="w-16 h-16 bg-amber-100 dark:bg-amber-950/60 border border-amber-300 dark:border-amber-800/60 text-amber-600 dark:text-amber-400 rounded-full flex items-center justify-center mx-auto text-2xl">
                            📭
                        </div>
                        <div class="space-y-1.5">
                            <h3 class="text-lg font-bold text-slate-900 dark:text-white">Belum ada ujian</h3>
                            <p class="text-xs text-slate-600 dark:text-zinc-300 max-w-sm mx-auto leading-relaxed">
                                Belum ada ujian yang tersedia untuk jenjang <span class="font-bold text-purple-600 dark:text-purple-400" x-text="userGrade || 'kelas ini'"></span>. Silakan hubungi guru Anda.
                            </p>
                        </div>
                        <div class="pt-2">
                            <button @click="currentStep = 2" class="px-4 py-2 bg-slate-200 dark:bg-zinc-800 hover:bg-slate-300 dark:hover:bg-zinc-700 text-slate-800 dark:text-zinc-200 text-xs font-semibold rounded-xl transition">
                                ← Kembali ke Dashboard
                            </button>
                        </div>
                    </div>
                </template>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- STEP 4: EXAM PLAYER (PENGERJAAN SOAL)      -->
        <!-- ========================================== -->
        <div x-show="currentStep === 4" x-transition.opacity.duration.300ms class="space-y-6">
            <!-- Subject Title & Question Index Header -->
            <div class="flex items-center justify-between">
                <div class="flex items-center gap-2 text-blue-600 dark:text-blue-400 font-bold text-sm">
                    <svg class="w-4 h-4 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.232 5.232l3.536 3.536m-2.036-5.036a2.5 2.5 0 113.536 3.536L6.5 21.036H3v-3.572L16.732 3.732z"/>
                    </svg>
                    <span x-text="currentSubject ? currentSubject.title : 'Matematika'"></span>
                </div>
                <div class="flex items-center gap-3">
                    <span class="text-xs font-semibold text-slate-500 dark:text-zinc-400" x-text="`Soal ${currentQuestionIndex + 1}/${questions.length}`"></span>
                    <span class="px-2 py-0.5 bg-blue-100 dark:bg-blue-950 border border-blue-300 dark:border-blue-800 text-blue-700 dark:text-blue-300 text-[11px] font-mono rounded" x-text="formatTimer()"></span>
                </div>
            </div>

            <!-- Progress Bar -->
            <div class="w-full bg-slate-200 dark:bg-zinc-800/80 rounded-full h-1.5 overflow-hidden">
                <div class="bg-blue-600 dark:bg-blue-500 h-1.5 rounded-full transition-all duration-300" :style="`width: ${((currentQuestionIndex + 1) / questions.length) * 100}%`"></div>
            </div>

            <!-- Question Text -->
            <div class="py-2">
                <p class="text-base font-semibold text-slate-900 dark:text-zinc-100 leading-relaxed" x-text="currentQuestion ? currentQuestion.question_text : ''"></p>
            </div>

            <!-- Radio Options -->
            <div class="space-y-3" x-if="currentQuestion">
                <!-- Option A -->
                <label @click="selectOption('a')"
                       :class="userAnswers[currentQuestion?.id] === 'a' ? 'bg-blue-50 dark:bg-[#0f2240] border-blue-500 text-slate-900 dark:text-white shadow-md' : 'bg-slate-50 dark:bg-[#18181b] border-slate-200 dark:border-zinc-800 text-slate-700 dark:text-zinc-300 hover:border-slate-300 dark:hover:border-zinc-700'"
                       class="flex items-center p-4 rounded-xl border transition cursor-pointer">
                    <span class="font-semibold text-sm" x-text="currentQuestion?.option_a"></span>
                </label>

                <!-- Option B -->
                <label @click="selectOption('b')"
                       :class="userAnswers[currentQuestion?.id] === 'b' ? 'bg-blue-50 dark:bg-[#0f2240] border-blue-500 text-slate-900 dark:text-white shadow-md' : 'bg-slate-50 dark:bg-[#18181b] border-slate-200 dark:border-zinc-800 text-slate-700 dark:text-zinc-300 hover:border-slate-300 dark:hover:border-zinc-700'"
                       class="flex items-center p-4 rounded-xl border transition cursor-pointer">
                    <span class="font-semibold text-sm" x-text="currentQuestion?.option_b"></span>
                </label>

                <!-- Option C -->
                <label @click="selectOption('c')"
                       :class="userAnswers[currentQuestion?.id] === 'c' ? 'bg-blue-50 dark:bg-[#0f2240] border-blue-500 text-slate-900 dark:text-white shadow-md' : 'bg-slate-50 dark:bg-[#18181b] border-slate-200 dark:border-zinc-800 text-slate-700 dark:text-zinc-300 hover:border-slate-300 dark:hover:border-zinc-700'"
                       class="flex items-center p-4 rounded-xl border transition cursor-pointer">
                    <span class="font-semibold text-sm" x-text="currentQuestion?.option_c"></span>
                </label>

                <!-- Option D -->
                <label @click="selectOption('d')"
                       :class="userAnswers[currentQuestion?.id] === 'd' ? 'bg-blue-50 dark:bg-[#0f2240] border-blue-500 text-slate-900 dark:text-white shadow-md' : 'bg-slate-50 dark:bg-[#18181b] border-slate-200 dark:border-zinc-800 text-slate-700 dark:text-zinc-300 hover:border-slate-300 dark:hover:border-zinc-700'"
                       class="flex items-center p-4 rounded-xl border transition cursor-pointer">
                    <span class="font-semibold text-sm" x-text="currentQuestion?.option_d"></span>
                </label>
            </div>
        </div>

        <!-- ========================================== -->
        <!-- STEP 5: SIMULASI SELESAI (RESULTS SUMMARY) -->
        <!-- ========================================== -->
        <div x-show="currentStep === 5" x-transition.opacity.duration.300ms class="space-y-6 text-center py-2">
            <!-- Success Checkmark Icon -->
            <div class="w-14 h-14 bg-emerald-100 dark:bg-emerald-500/20 border border-emerald-300 dark:border-emerald-500/30 text-emerald-600 dark:text-emerald-400 rounded-full flex items-center justify-center mx-auto animate-bounce">
                <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/>
                </svg>
            </div>

            <!-- Title & Subtitle -->
            <div>
                <h1 class="text-2xl font-extrabold text-slate-900 dark:text-white tracking-tight">Simulasi Selesai</h1>
                <p class="text-sm text-slate-500 dark:text-zinc-400 mt-1">Kerja bagus! Hasil latihanmu sudah tersimpan.</p>
            </div>

            <!-- Main Score Badge Card (0-100 Integer Scale) -->
            <div class="bg-gradient-to-b from-blue-50 to-indigo-50 dark:from-blue-950/50 dark:to-zinc-900/90 border border-blue-200 dark:border-blue-500/30 rounded-2xl p-5 text-center space-y-1 shadow-md">
                <span class="text-xs font-semibold text-blue-600 dark:text-blue-400 uppercase tracking-wider block">Nilai Akhir (Skala 100)</span>
                <div class="text-5xl font-black py-2 flex items-baseline justify-center gap-1">
                    <span class="bg-gradient-to-r from-blue-600 via-emerald-600 to-teal-500 dark:from-blue-400 dark:via-emerald-400 dark:to-teal-300 bg-clip-text text-transparent" x-text="examResult ? examResult.score : 0"></span>
                    <span class="text-lg font-bold text-slate-400 dark:text-zinc-500">/ 100</span>
                </div>
            </div>

            <!-- Stat Boxes -->
            <div class="space-y-3 text-left">
                <div class="bg-slate-50 dark:bg-[#18181c] border border-slate-200 dark:border-zinc-800/90 rounded-xl p-4 flex items-center justify-between">
                    <span class="text-sm font-medium text-slate-600 dark:text-zinc-400">Jawaban Benar</span>
                    <span class="text-base font-bold text-emerald-600 dark:text-emerald-400" x-text="examResult ? `${examResult.correct_answers} dari ${examResult.total_questions} Soal` : ''"></span>
                </div>

                <div class="bg-slate-50 dark:bg-[#18181c] border border-slate-200 dark:border-zinc-800/90 rounded-xl p-4 flex items-center justify-between">
                    <span class="text-sm font-medium text-slate-600 dark:text-zinc-400">Waktu Pengerjaan</span>
                    <span class="text-base font-bold text-slate-900 dark:text-white" x-text="examResult?.time_taken_formatted || ''"></span>
                </div>
            </div>

            <!-- Primary Blue Button: Lihat Pembahasan -->
            <button @click="showPembahasanModal = true" class="w-full py-3.5 px-4 bg-blue-600 hover:bg-blue-500 text-white font-bold text-sm rounded-xl transition shadow-lg shadow-blue-600/25 flex items-center justify-center gap-2">
                Lihat pembahasan
            </button>
        </div>


        <!-- ========================================== -->
        <!-- COMMON FOOTER BUTTONS                      -->
        <!-- ========================================== -->
        <div class="pt-6 mt-6 border-t border-slate-200 dark:border-zinc-800/80">
            <!-- Bottom Navigation Buttons (Kembali / Lanjut / Ulangi) -->
            <div class="flex items-center justify-between gap-3">
                <!-- Back Button -->
                <button type="button" @click="goBack()"
                        :disabled="currentStep === 1"
                        :class="currentStep === 1 ? 'opacity-40 cursor-not-allowed text-slate-400 dark:text-zinc-600 bg-slate-100 dark:bg-zinc-900 border-slate-200 dark:border-zinc-800' : 'text-slate-700 dark:text-zinc-300 bg-slate-100 dark:bg-[#1a1a1e] hover:bg-slate-200 dark:hover:bg-zinc-800 border-slate-300 dark:border-zinc-800'"
                        class="px-5 py-2.5 rounded-xl border text-sm font-semibold transition">
                    Kembali
                </button>

                <!-- Forward / Action Button -->
                <template x-if="currentStep === 1">
                    <button type="button" @click="handleLogin()" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-xl transition shadow-md">
                        Lanjut
                    </button>
                </template>

                <template x-if="currentStep === 2">
                    <button type="button" @click="currentStep = 3" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-xl transition shadow-md">
                        Pilih Pelajaran
                    </button>
                </template>

                <template x-if="currentStep === 3">
                    <button type="button" @click="startExam()" :disabled="!selectedExam || totalAvailableExams === 0"
                            :class="!selectedExam || totalAvailableExams === 0 ? 'opacity-40 cursor-not-allowed bg-slate-200 dark:bg-zinc-900 text-slate-400 dark:text-zinc-600' : 'bg-blue-600 hover:bg-blue-500 text-white shadow-md'"
                            class="px-5 py-2.5 border border-slate-200 dark:border-zinc-800 text-sm font-semibold rounded-xl transition">
                        Mulai Ujian
                    </button>
                </template>

                <template x-if="currentStep === 4">
                    <button type="button" @click="nextQuestionOrSubmit()"
                            class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-xl transition shadow-md">
                        <span x-text="currentQuestionIndex < questions.length - 1 ? 'Lanjut' : 'Selesai'"></span>
                    </button>
                </template>

                <template x-if="currentStep === 5">
                    <button type="button" @click="repeatExam()" class="px-5 py-2.5 bg-blue-600 hover:bg-blue-500 text-white text-sm font-semibold rounded-xl transition shadow-md">
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
        <div @click.away="showPembahasanModal = false" class="bg-white dark:bg-[#141416] border border-slate-200 dark:border-zinc-800 w-full max-w-2xl rounded-3xl p-6 sm:p-7 shadow-2xl max-h-[85vh] flex flex-col">
            <div class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-zinc-800">
                <div>
                    <h2 class="text-xl font-bold text-slate-900 dark:text-white">Pembahasan & Kunci Jawaban</h2>
                    <p class="text-xs text-slate-500 dark:text-zinc-400">Penjelasan detail langkah demi langkah untuk setiap soal.</p>
                </div>
                <button @click="showPembahasanModal = false" class="w-8 h-8 rounded-full bg-slate-100 dark:bg-zinc-800 text-slate-500 dark:text-zinc-400 hover:text-slate-900 dark:hover:text-white flex items-center justify-center transition">
                    ✕
                </button>
            </div>

            <div class="flex-1 overflow-y-auto py-4 space-y-6 pr-1">
                <template x-for="item in examResult?.review" :key="item.number">
                    <div class="bg-slate-50 dark:bg-[#1a1a1e] border rounded-2xl p-5 space-y-3"
                         :class="item.is_correct ? 'border-emerald-300 dark:border-emerald-900/60 bg-emerald-50 dark:bg-emerald-950/10' : 'border-rose-300 dark:border-rose-900/60 bg-rose-50 dark:bg-rose-950/10'">
                        <div class="flex items-start justify-between gap-3">
                            <span class="text-sm font-bold text-slate-800 dark:text-zinc-200" x-text="`Soal ${item.number}`"></span>
                            <span :class="item.is_correct ? 'bg-emerald-100 dark:bg-emerald-900/50 text-emerald-700 dark:text-emerald-400 border-emerald-300 dark:border-emerald-700/50' : 'bg-rose-100 dark:bg-rose-900/50 text-rose-700 dark:text-rose-400 border-rose-300 dark:border-rose-700/50'"
                                  class="px-2.5 py-0.5 text-xs font-semibold rounded-full border">
                                <span x-text="item.is_correct ? 'Benar (+10)' : 'Salah (0)'"></span>
                            </span>
                        </div>

                        <p class="text-sm font-medium text-slate-900 dark:text-white" x-text="item.question_text"></p>

                        <div class="space-y-1.5 text-xs">
                            <div class="flex items-center gap-2">
                                <span class="text-slate-500 dark:text-zinc-400">Jawabanmu:</span>
                                <span :class="item.is_correct ? 'text-emerald-700 dark:text-emerald-400 font-bold' : 'text-rose-700 dark:text-rose-400 font-bold'" x-text="`${item.selected_option ? item.selected_option.toUpperCase() + '. ' : ''}${item.selected_text}`"></span>
                            </div>

                            <div x-show="!item.is_correct" class="flex items-center gap-2">
                                <span class="text-slate-500 dark:text-zinc-400">Jawaban Benar:</span>
                                <span class="text-emerald-700 dark:text-emerald-400 font-bold" x-text="`${item.correct_option.toUpperCase()}. ${item.correct_text}`"></span>
                            </div>
                        </div>

                        <div class="pt-3 border-t border-slate-200 dark:border-zinc-800/80 bg-slate-100 dark:bg-zinc-900/60 -mx-5 -mb-5 p-4 rounded-b-2xl">
                            <span class="text-xs font-semibold text-blue-600 dark:text-blue-400 block mb-1">💡 Pembahasan:</span>
                            <p class="text-xs text-slate-700 dark:text-zinc-300 leading-relaxed" x-text="item.explanation"></p>
                        </div>
                    </div>
                </template>
            </div>

            <div class="pt-4 border-t border-slate-200 dark:border-zinc-800 text-right">
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
            currentStep: @json(Auth::check() && Auth::user()->role === 'murid' ? 2 : 1),
            loading: false,
            errorMessage: '',
            isLoggedIn: @json(Auth::check()),
            userRole: @json(Auth::check() ? Auth::user()->role : null),
            userName: @json(Auth::check() ? Auth::user()->name : 'Siswa'),
            userGrade: @json(Auth::check() ? Auth::user()->grade_level : null),
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
                username: '',
                password: ''
            },

            get filteredSubjects() {
                if (!this.userGrade) return this.subjects;
                return this.subjects.map(subj => {
                    const filteredExams = (subj.exams || []).filter(e => e.grade_level === this.userGrade);
                    return {
                        ...subj,
                        exams: filteredExams
                    };
                }).filter(subj => subj.exams && subj.exams.length > 0);
            },

            get totalAvailableExams() {
                return this.filteredSubjects.reduce((total, subj) => total + (subj.exams ? subj.exams.length : 0), 0);
            },

            async fetchSubjects() {
                try {
                    const url = this.userGrade ? `/api/subjects?grade_level=${encodeURIComponent(this.userGrade)}` : '/api/subjects';
                    const res = await fetch(url);
                    const data = await res.json();
                    if (data.success && data.subjects) {
                        this.subjects = data.subjects;
                        if (data.target_grade) {
                            this.userGrade = data.target_grade;
                        }
                    }
                } catch (e) {
                    console.error('Fetch subjects error:', e);
                }
            },

            async initData() {
                if (this.isLoggedIn && this.userRole === 'murid') {
                    await this.fetchSubjects();
                    this.currentStep = 2; // Dashboard Murid
                }
                this.autoSelectFirstExam();
            },

            autoSelectFirstExam() {
                const available = this.filteredSubjects;
                if (available.length > 0 && available[0].exams.length > 0) {
                    this.selectedExam = available[0].exams[0];
                    this.currentSubject = available[0];
                } else {
                    this.selectedExam = null;
                    this.currentSubject = null;
                }
            },

            async handleLogin() {
                this.loading = true;
                this.errorMessage = '';

                try {
                    const response = await fetch("{{ route('login.post') }}", {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name="csrf-token"]').getAttribute('content')
                        },
                        body: JSON.stringify(this.loginForm)
                    });

                    const data = await response.json();
                    this.loading = false;

                    if (data.success) {
                        if (data.user && data.user.role === 'admin') {
                            window.location.href = data.redirect || "{{ route('admin.dashboard') }}";
                        } else if (data.user && data.user.role === 'guru') {
                            window.location.href = data.redirect || "{{ route('guru.dashboard') }}";
                        } else {
                            window.location.href = "{{ route('simulasi.wizard') }}";
                        }
                    } else {
                        this.errorMessage = data.message || 'Login gagal. Coba lagi.';
                    }
                } catch (err) {
                    this.loading = false;
                    this.errorMessage = 'Terjadi kesalahan saat memproses login.';
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
                        this.currentStep = 4; // Step 4 is CBT Exam Player
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
                            'Accept': 'application/json',
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
                        this.currentStep = 5; // Step 5 is Exam Result
                    }
                } catch (e) {
                    console.error(e);
                }
            },

            goBack() {
                if (this.currentStep === 3) {
                    this.currentStep = 2; // From Pelajaran back to Dashboard Murid
                } else if (this.currentStep === 4) {
                    if (this.currentQuestionIndex > 0) {
                        this.currentQuestionIndex--;
                    } else {
                        if (confirm('Kembali ke daftar pelajaran? Kemajuan soal akan direset.')) {
                            if (this.timerInterval) clearInterval(this.timerInterval);
                            this.currentStep = 3;
                        }
                    }
                } else if (this.currentStep === 5) {
                    this.currentStep = 2;
                } else if (this.currentStep === 2 && !this.isLoggedIn) {
                    this.currentStep = 1;
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
