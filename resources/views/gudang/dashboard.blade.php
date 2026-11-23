@extends('gudang.layout')

@section('content')
<div class="stat">
  <div class="box blue"><div class="n">{{ $barang->count() }}</div><div class="l">jenis barang</div></div>
  <div class="box amber"><div class="n">{{ $acaraAktif->count() }}</div><div class="l">acara aktif</div></div>
</div>

<div class="card">
  <h2>Ringkasan</h2>
  <div class="stat" style="margin-bottom:0">
    <div class="box blue"><div class="n">{{ $totalKeluar }}</div><div class="l">unit keluar</div></div>
    <div class="box amber"><div class="n">{{ $totalKembali }}</div><div class="l">unit kembali</div></div>
  </div>
  <p style="font-size:18px;margin:14px 0 0">Total hilang: <b class="{{ $totalHilang > 0 ? 'hi' : '' }}" style="font-size:24px">{{ $totalHilang }}</b> unit</p>
</div>

@if($lowStock->isNotEmpty())
<div class="card" style="border-color:var(--red)">
  <h2 style="color:var(--red)">Stok habis / menipis</h2>
  <p class="hint">Stok baik 0 (habis) atau kurang dari 10% (menipis) — segera restock.</p>
  <div class="scroll">
    <ul class="list">
      @foreach($lowStock as $b)
        <li>
          <div><b>{{ $b->nama_barang }}</b><div class="muted">{{ $b->lokasi ?: 'tanpa lokasi' }}</div></div>
          <div style="text-align:right">
            <span class="badge {{ $b->statusStok()==='habis' ? 'rusak' : 'aktif' }}">{{ $b->statusStok() }}</span>
            <div class="muted">baik {{ $b->stok_baik }} / {{ $b->stok_total }}</div>
          </div>
        </li>
      @endforeach
    </ul>
  </div>
</div>
@endif

<div class="quick">
  <a href="/barang" class="qa-green">
    <span class="qa-ic"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg></span>
    Tambah barang
  </a>
  <a href="/pengembalian" class="qa-blue">
    <span class="qa-ic"><svg viewBox="0 0 24 24"><path d="M9 14 4 9l5-5"/><path d="M4 9h11a5 5 0 0 1 0 10h-3"/></svg></span>
    Catat barang kembali
  </a>
  <a href="/acara" class="qa-amber">
    <span class="qa-ic"><svg viewBox="0 0 24 24"><rect x="3" y="4" width="18" height="17" rx="2"/><path d="M3 9h18M8 2v4M16 2v4"/></svg></span>
    Buat acara (barang keluar)
  </a>
  <a href="/restock" class="qa-purple">
    <span class="qa-ic"><svg viewBox="0 0 24 24"><path d="M21 8l-9-5-9 5 9 5 9-5zM3 8v8l9 5 9-5V8"/></svg></span>
    Tambah stok
  </a>
</div>

<div class="card">
  <h2>Acara aktif <span class="muted" style="font-weight:400">(barang sedang keluar)</span></h2>
  @if($acaraAktif->isEmpty())
    <p class="muted">Tidak ada acara aktif.</p>
  @else
    <div class="scroll">
    <ul class="list">
      @foreach($acaraAktif as $a)
        <li>
          <div>
            <b style="font-size:17px">{{ $a->nama_acara }}</b>
            <div class="muted">{{ $a->tanggal?->format('d M Y') }} · {{ $a->detailAcara->sum('jumlah_keluar') }} unit keluar</div>
          </div>
          <span class="badge aktif">aktif</span>
        </li>
      @endforeach
    </ul>
    </div>
  @endif
</div>

  <div class="row-actions" style="margin:0 0 12px">
    <a href="/stok/excel" class="btn sm sec">Unduh Excel</a>
  </div>

<div class="card">
  <h2>Stok barang</h2>
  <form method="GET" action="/" class="row" style="margin-bottom:12px">
    <input name="q" value="{{ $q ?? '' }}" placeholder="Cari nama barang..." style="flex:1">
    <select name="kategori" style="flex:1">
      <option value="">Semua kategori</option>
      @foreach($kategori as $k)
        <option value="{{ $k->id }}" {{ request('kategori') == $k->id ? 'selected' : '' }}>{{ $k->nama_kategori }}</option>
      @endforeach
    </select>
    <button type="submit" class="sec" style="width:auto;flex:0;margin:0">Cari</button>
  </form>
  @if($barang->isEmpty())
    <p class="muted">Belum ada barang. Tambah lewat menu Stok.</p>
  @else
    <div class="scroll">
    <ul class="list">
      @foreach($barang as $b)
        @php($st = $b->statusPemakaian())
        <li>
          <div>
            <b style="font-size:17px">{{ $b->nama_barang }}</b>
            <div class="muted">{{ $b->lokasi ?: 'tanpa lokasi' }} · baik <span class="green">{{ $b->stok_baik }}</span></div>
            <div style="margin-top:6px;display:flex;gap:6px">
              <a href="/barang/{{ $b->id }}/edit" class="badge selesai" style="text-decoration:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;min-width:64px;padding:6px 10px">Edit</a>
              <a href="/barang/{{ $b->id }}/riwayat" class="badge selesai" style="text-decoration:none;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;min-width:64px;padding:6px 10px">Riwayat</a>
              <form method="POST" action="/barang/{{ $b->id }}" style="display:inline;margin:0" onsubmit="return confirm('Hapus barang ini?')">
                @csrf @method('DELETE')
                <button type="submit" class="badge rusak" style="border:0;cursor:pointer;display:inline-flex;align-items:center;justify-content:center;min-width:64px;padding:6px 10px;font-size:13px">Hapus</button>
              </form>
            </div>
          </div>
          <div style="text-align:right">
            <div class="num {{ $st==='sedang digunakan' ? 'amber' : 'green' }}">{{ $b->stok_total }}</div>
            <span class="badge {{ $st==='sedang digunakan' ? 'aktif' : 'selesai' }}">{{ $st }}</span>
          </div>
        </li>
      @endforeach
    </ul>
    </div>
  @endif
</div>
@endsection