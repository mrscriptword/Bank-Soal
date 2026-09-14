<?php

namespace App\Http\Controllers;

use App\Models\Exam;
use App\Models\ExamAttempt;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminController extends Controller
{
    public function dashboard()
    {
        $users = User::latest()->get();
        $totalStudents = User::where('role', 'murid')->count();
        $totalTeachers = User::where('role', 'guru')->count();
        $totalAdmins = User::where('role', 'admin')->count();

        $subjects = Subject::with('teacher')->get();
        $exams = Exam::with(['subject', 'creator'])->get();
        $attemptsCount = ExamAttempt::count();

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
            'name' => $request->name,
            'username' => strtolower($request->username),
            'email' => strtolower($request->email),
            'password' => Hash::make($request->password),
            'role' => $request->role,
            'grade_level' => $request->grade_level ?: 'SMP',
        ]);

        return redirect()->route('admin.dashboard')->with('success', 'Pengguna baru berhasil ditambahkan!');
    }

    public function destroyUser(User $user)
    {
        if ($user->id === auth()->id()) {
            return redirect()->route('admin.dashboard')->with('error', 'Anda tidak dapat menghapus akun sendiri.');
        }

        $user->delete();
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
}
