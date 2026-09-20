<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            $user = Auth::user();
            if ($user->role === 'admin') {
                return redirect()->route('admin.dashboard');
            } elseif ($user->role === 'guru') {
                return redirect()->route('guru.dashboard');
            }
        }

        // Direct to interactive wizard (step 1 is login modal)
        return redirect()->route('simulasi.wizard');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string',
            'password' => 'required|string',
        ]);

        $credentials = [
            'username' => $request->username,
            'password' => $request->password,
        ];

        // Also allow login with email if username matches email format
        if (filter_var($request->username, FILTER_VALIDATE_EMAIL)) {
            $credentials = [
                'email' => $request->username,
                'password' => $request->password,
            ];
        }

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $user = Auth::user();

            $redirectUrl = route('simulasi.wizard');
            if ($user->role === 'admin') {
                $redirectUrl = route('admin.dashboard');
            } elseif ($user->role === 'guru') {
                $redirectUrl = route('guru.dashboard');
            }

            if ($request->expectsJson() || $request->wantsJson() || $request->isJson() || $request->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Login berhasil',
                    'user' => $user,
                    'redirect' => $redirectUrl,
                ]);
            }

            return redirect($redirectUrl);
        }

        if ($request->expectsJson() || $request->wantsJson() || $request->isJson() || $request->ajax()) {
            return response()->json([
                'success' => false,
                'message' => 'Username atau password salah. Silakan coba lagi.',
            ], 422);
        }

        return back()->withErrors([
            'username' => 'Username atau password salah. Silakan coba lagi.',
        ])->onlyInput('username');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        if ($request->wantsJson()) {
            return response()->json(['success' => true]);
        }

        return redirect()->route('simulasi.wizard');
    }
}
