<?php

namespace App\Http\Controllers;

use App\Models\LogAktivitas;
use App\Models\User;
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
            'email' => 'required|email',
            'password' => 'required',
        ]);

        if (Auth::attempt($credentials, $request->boolean('remember'))) {
            $request->session()->regenerate();
            $request->session()->regenerateToken();
            LogAktivitas::create(['aksi' => 'login', 'entitas' => 'user', 'keterangan' => auth()->user()->name]);

            // Pekerja tidak punya akses dashboard -> arahkan ke halaman scan
            $target = auth()->user()->role === 'admin' ? '/' : '/scan';

            return redirect()->intended($target);
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
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role' => 'required|in:admin,pekerja',
        ]);

        User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'role' => $data['role'],
        ]);

        return back()->with('ok', "User {$data['name']} dibuat ({$data['role']}).");
    }

    /** Update user (khusus admin). Password kosong = tidak diubah. */
    public function updateUser(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => 'required|email|unique:users,email,'.$user->id,
            'role' => 'required|in:admin,pekerja',
            'password' => 'nullable|string|min:6',
        ]);

        // Admin tidak boleh menurunkan role akunnya sendiri
        if ($user->id === auth()->id() && $data['role'] !== $user->role) {
            return back()->with('err', 'Anda tidak bisa mengubah role akun sendiri.')->withInput();
        }

        $user->name = $data['name'];
        $user->email = $data['email'];
        $user->role = $data['role'];

        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        $user->save();

        return back()->with('ok', "User {$user->name} diperbarui.");
    }

    /** Hapus user (khusus admin). Admin tidak bisa menghapus dirinya sendiri. */
    public function destroyUser($id)
    {
        $user = User::findOrFail($id);

        if ($user->id === auth()->id()) {
            return back()->with('err', 'Anda tidak bisa menghapus akun Anda sendiri.');
        }

        $nama = $user->name;
        $user->delete(); // soft delete

        return back()->with('ok', "User {$nama} dihapus.");
    }
}
