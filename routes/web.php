<?php

use App\Http\Controllers\AdminController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\GuruController;
use App\Http\Controllers\StudentExamController;
use Illuminate\Support\Facades\Route;

// Default Route -> Interactive Simulasi Wizard (Screen 1, 2, 3, 4)
Route::get('/', [StudentExamController::class, 'wizard'])->name('simulasi.wizard');
Route::get('/simulasi', [StudentExamController::class, 'wizard']);

// Authentication Routes
Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
Route::post('/login', [AuthController::class, 'login'])->name('login.post');
Route::post('/logout', [AuthController::class, 'logout'])->name('logout');

// Public API endpoints for interactive CBT player (supports guest testing & logged in students)
Route::get('/api/subjects', [StudentExamController::class, 'getSubjects'])->name('api.subjects');
Route::get('/api/exams/{exam}', [StudentExamController::class, 'getExamDetail'])->name('api.exam.detail');
Route::post('/api/exams/submit', [StudentExamController::class, 'submitExam'])->name('api.exam.submit');
Route::get('/api/attempts/{attempt}/review', [StudentExamController::class, 'getAttemptReview'])->name('api.attempt.review');

// Role: Guru Dashboard Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/guru/dashboard', [GuruController::class, 'dashboard'])->name('guru.dashboard');
    Route::get('/guru/exams/template-download', [GuruController::class, 'downloadTemplate'])->name('guru.exams.template');
    Route::post('/guru/exams', [GuruController::class, 'storeExam'])->name('guru.exams.store');
    Route::post('/guru/exams/import-pdf', [GuruController::class, 'importExamFromPdf'])->name('guru.exams.import');
    Route::delete('/guru/exams/{exam}', [GuruController::class, 'destroyExam'])->name('guru.exams.destroy');
    Route::post('/guru/questions', [GuruController::class, 'storeQuestion'])->name('guru.questions.store');
    Route::put('/guru/questions/{question}', [GuruController::class, 'updateQuestion'])->name('guru.questions.update');
    Route::delete('/guru/questions/{question}', [GuruController::class, 'destroyQuestion'])->name('guru.questions.destroy');
});

// Role: Admin Dashboard Routes
Route::middleware(['auth'])->group(function () {
    Route::get('/admin/dashboard', [AdminController::class, 'dashboard'])->name('admin.dashboard');
    Route::post('/admin/users', [AdminController::class, 'storeUser'])->name('admin.users.store');
    Route::delete('/admin/users/{user}', [AdminController::class, 'destroyUser'])->name('admin.users.destroy');
    Route::post('/admin/subjects', [AdminController::class, 'storeSubject'])->name('admin.subjects.store');
});
