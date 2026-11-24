@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Edit barang</h2>
  <form method="POST" action="/barang/{{ $barang->id }}" enctype="multipart/form-data">
    @csrf
    @method('PUT')
    <label>Nama barang</label>
    <input name="nama_barang" value="{{ $barang->nama_barang }}" required>

    <label>Satuan</label>
    <input name="satuan" value="{{ $barang->satuan }}" required>

    <label>Ukuran</label>
    <input name="ukuran" value="{{ $barang->ukuran ?: 'N/A' }}" placeholder="mis. 21 cm atau N/A">
    <p class="hint">Isi ukuran fisik barang. Kalau tidak ada, biarkan "N/A".</p>

    <label>Lokasi</label>
    <input name="lokasi" value="{{ $barang->lokasi }}" placeholder="mis. Lantai 1 Rak A">

    <label>Kategori</label>
    <select name="kategori_id">
      <option value="">— tanpa kategori —</option>
      @foreach($kategori as $k)
        <option value="{{ $k->id }}" {{ $barang->kategori_id == $k->id ? 'selected' : '' }}>{{ $k->nama_kategori }}</option>
      @endforeach
    </select>

    <label>Foto barang</label>
    @if($barang->foto)
      <div style="margin:6px 0">
        <img src="{{ asset('storage/'.$barang->foto) }}" alt="Foto {{ $barang->nama_barang }}"
             style="width:100%;max-width:220px;border-radius:12px;border:1px solid var(--bd)">
        <div class="muted" style="margin-top:4px">Foto saat ini — upload baru untuk mengganti</div>
      </div>
    @endif
    <input name="foto" type="file" accept="image/*" capture="environment">
    <p class="hint">JPG/PNG, maks 2MB. Di HP bisa langsung ambil foto dari kamera.</p>

    <button type="submit">Simpan perubahan</button>
    <a href="/" class="back">Batal</a>
  </form>

  <hr style="border:0;border-top:1px solid var(--bd);margin:18px 0">

  <div class="card" style="margin:0 0 12px">
    <h2>Tambah stok</h2>
    <p class="hint">Kalau ada barang baru masuk, tambahkan jumlahnya di sini.</p>
    <form method="POST" action="/barang/{{ $barang->id }}/tambah">
      @csrf
      <label>Jumlah yang ditambahkan</label>
      <input name="jumlah" type="number" min="1" value="1" required>
      <button type="submit">Tambah stok</button>
    </form>
  </div>

  <div class="card" style="margin:0 0 12px">
    <h2>Kurangi jumlah</h2>
    <p class="hint">Kalau ada barang yang hilang/rusak, kurangi stoknya di sini.</p>
    <form method="POST" action="/barang/{{ $barang->id }}/kurangi">
      @csrf
      <label>Jumlah yang dikurangi</label>
      <input name="jumlah" type="number" min="1" value="1" required>
      <button type="submit">Kurangi stok</button>
    </form>
  </div>

  <div class="card" style="margin:0">
    <h2>Hapus total</h2>
    @if($barang->stokKeluar() > 0)
      <p class="hint" style="color:var(--red)">Barang ini sedang digunakan di acara aktif, tidak bisa dihapus.</p>
    @else
      <p class="hint">Hapus seluruh barang ini dari katalog.</p>
      <form method="POST" action="/barang/{{ $barang->id }}" onsubmit="return confirm('Hapus total barang ini?')">
        @csrf @method('DELETE')
        <button type="submit" class="sec" style="color:var(--red);border-color:var(--red)">Hapus total</button>
      </form>
    @endif
  </div>
</div>
@endsection