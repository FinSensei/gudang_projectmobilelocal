@extends('gudang.layout')

@section('content')
<div class="card">
  <div class="card-head">
    <div>
      <h2>Pengguna</h2>
      <p class="hint">Kelola akun admin & pekerja.</p>
    </div>
    <span class="badge selesai">{{ $users->count() }} user</span>
  </div>

  <div class="list">
    @foreach($users as $u)
      <div class="item" style="display:flex;justify-content:space-between;align-items:center">
        <div>
          <b>{{ $u->name }}</b>
          <div class="muted">{{ $u->email }}</div>
        </div>
        <span class="badge {{ $u->role === 'admin' ? 'selesai' : 'aktif' }}">{{ $u->role }}</span>
      </div>
    @endforeach
  </div>
</div>

<div class="card">
  <h2>Tambah pengguna</h2>
  <form method="POST" action="/users">
    @csrf
    <label>Nama</label>
    <input name="name" placeholder="Nama lengkap" required>

    <label>Email</label>
    <input name="email" type="email" placeholder="nama@gudang.id" required>

    <label>Password</label>
    <input name="password" type="password" placeholder="min. 6 karakter" required>

    <label>Role</label>
    <select name="role" required>
      <option value="pekerja">Pekerja (scan & lihat stok)</option>
      <option value="admin">Admin (kelola semua)</option>
    </select>

    <button type="submit">Tambah pengguna</button>
  </form>
</div>
@endsection