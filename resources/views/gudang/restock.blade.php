@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Tambah stok</h2>
  <p class="hint">Pilih barang yang sudah ada, atau isi nama barang baru.</p>
  <form method="POST" action="/restock">
    @csrf
    <label>Barang yang sudah ada</label>
    <select name="barang_id">
      <option value="">— pilih —</option>
      @foreach($barang as $b)
        <option value="{{ $b->id }}">{{ $b->nama_barang }} (baik {{ $b->stok_baik }})</option>
      @endforeach
    </select>

    <label>Atau nama barang baru</label>
    <input name="nama_baru" placeholder="mis. Gelas">

    <label>Jumlah</label>
    <input name="jumlah" type="number" min="1" value="10" required>

    <button type="submit">Tambah stok</button>
  </form>
</div>

<div class="card">
  <h2>Tandai rusak</h2>
  <p class="hint">Barang yang kembali tapi rusak dipindah ke stok rusak (stok asli tidak berkurang).</p>
  <form method="POST" action="/rusak">
    @csrf
    <label>Barang</label>
    <select name="barang_id">
      @foreach($barang as $b)
        <option value="{{ $b->id }}">{{ $b->nama_barang }} (baik {{ $b->stok_baik }})</option>
      @endforeach
    </select>
    <label>Jumlah rusak</label>
    <input name="jumlah" type="number" min="1" value="1" required>
    <button type="submit" class="sec">Tandai rusak</button>
  </form>
</div>
@endsection