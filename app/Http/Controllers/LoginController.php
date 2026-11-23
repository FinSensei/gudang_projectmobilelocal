<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Models\LogAktivitas;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class LoginController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect('/');
        }
        return view('gudang.login');
    }

    public function login(Request $request)
    {
        $credentials = $request->validate([
            'email'    => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            // Regenerate ulang token setelah session regenerate (cegah 419 di request berikutnya)
            $request->session()->regenerateToken();
            LogAktivitas::create(['aksi'=>'login','entitas'=>'user','keterangan'=>auth()->user()->name]);
            return redirect()->intended('/');
        }

        return back()->withErrors(['email' => 'Email atau password salah.'])->onlyInput('email');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect('/login');
    }

    /** Halaman kelola user (khusus admin) */
    public function indexUsers()
    {
        $users = User::orderBy('name')->get();
        return view('gudang.users', compact('users'));
    }

    /** Buat user baru (khusus admin) */
    public function storeUser(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:100',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role'     => 'required|in:admin,pekerja',
        ]);

        User::create([
            'name'     => $data['name'],
            'email'    => $data['email'],
            'password' => Hash::make($data['password']),
            'role'     => $data['role'],
        ]);

        return back()->with('ok', "User {$data['name']} dibuat ({$data['role']}).");
    }
}