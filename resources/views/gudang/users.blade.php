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
      <div class="item" style="margin:10px 0">
        <div class="top">
          <div>
            <b>{{ $u->name }}</b>
            @if($u->id === auth()->id())
              <span class="badge selesai">Anda</span>
            @endif
          </div>
          <span class="badge {{ $u->role === 'admin' ? 'selesai' : 'aktif' }}">{{ $u->role }}</span>
        </div>
        <div class="muted">{{ $u->email }}</div>

        <div class="row-actions">
          <button type="button" class="btn sm sec" onclick="toggle('edit-{{ $u->id }}')">Edit</button>
          @if($u->id !== auth()->id())
            <form method="POST" action="/users/{{ $u->id }}" style="margin:0" onsubmit="return confirm('Hapus user {{ $u->name }}?')">
              @csrf @method('DELETE')
              <button type="submit" class="btn sm sec" style="color:var(--red);border-color:var(--red)">Hapus</button>
            </form>
          @endif
        </div>

        <form method="POST" action="/users/{{ $u->id }}" id="edit-{{ $u->id }}" style="display:none;margin-top:10px;border-top:1px solid var(--bd);padding-top:10px">
          @csrf @method('PUT')
          <label>Nama</label>
          <input name="name" value="{{ $u->name }}" required>

          <label>Email</label>
          <input name="email" type="email" value="{{ $u->email }}" required>

          <label>Role</label>
          <select name="role">
            <option value="pekerja" {{ $u->role === 'pekerja' ? 'selected' : '' }}>Pekerja (scan & lihat)</option>
            <option value="admin" {{ $u->role === 'admin' ? 'selected' : '' }}>Admin (kelola semua)</option>
          </select>

          <label>Password baru (kosongkan jika tidak diubah)</label>
          <input name="password" type="password" placeholder="min. 6 karakter">

          <button type="submit">Simpan perubahan</button>
          <button type="button" class="sec" onclick="toggle('edit-{{ $u->id }}')">Batal</button>
        </form>
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

<script>
function toggle(id){
  var el = document.getElementById(id);
  el.style.display = (el.style.display === 'none') ? 'block' : 'none';
}
</script>
@endsection