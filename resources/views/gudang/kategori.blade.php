@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Kategori</h2>
  <p class="hint">Kelompokkan barang biar mudah difilter.</p>
  <form method="POST" action="/kategori">
    @csrf
    <label>Nama kategori</label>
    <input name="nama_kategori" placeholder="mis. Peralatan Makan" required>
    <button type="submit">Tambah kategori</button>
  </form>

  @if($kategori->isEmpty())
    <p class="muted" style="margin-top:12px">Belum ada kategori.</p>
  @else
    <ul class="list" style="margin-top:12px">
      @foreach($kategori as $k)
        <li>
          <div><b>{{ $k->nama_kategori }}</b> <span class="muted">· {{ $k->barang()->count() }} jenis</span></div>
          <form method="POST" action="/kategori/{{ $k->id }}" onsubmit="return confirm('Hapus kategori ini?')">
            @csrf @method('DELETE')
            <button class="badge rusak" style="border:0;cursor:pointer">Hapus</button>
          </form>
        </li>
      @endforeach
    </ul>
  @endif
</div>
@endsection