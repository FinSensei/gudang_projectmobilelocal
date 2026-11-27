@extends('gudang.layout')

@section('content')
<div class="stat">
  <div class="box blue"><div class="n">{{ $barang->count() }}</div><div class="l">jenis barang</div></div>
  <div class="box amber"><div class="n">{{ $acaraAktif->count() }}</div><div class="l">acara aktif</div></div>
</div>

<div class="card">
  <h2>Ringkasan</h2>
  <div class="stat" style="margin-bottom:0">
    <div class="box blue"><div class="n">{{ $totalDimiliki }}</div><div class="l">Total barang dimiliki</div></div>
    <div class="box {{ $totalHilang > 0 ? 'red' : 'amber' }}"><div class="n">{{ $totalHilang }}</div><div class="l">Total barang hilang</div></div>
  </div>
</div>

{{-- Perlu perhatian: reservasi menunggu + stok habis --}}
@php($perlu = $reservasiPending->count() + $stokHabis->count())
<div class="card {{ $perlu > 0 ? 'amber' : '' }}">
  <h2 {{ $perlu > 0 ? 'class="amber-text"' : '' }}>
    Perlu perhatian
    @if($perlu > 0)<span class="badge aktif">{{ $perlu }}</span>@endif
  </h2>

  @if($perlu === 0)
    <p class="muted" style="margin:0">Semua aman — tidak ada reservasi menunggu, tidak ada stok habis.</p>
  @endif

  @if($reservasiPending->isNotEmpty())
    <p class="hint-sm">Reservasi menunggu konfirmasi — stok belum dipotong:</p>
    @foreach($reservasiPending as $r)
      <div class="item">
        <div class="top">
          <b>{{ $r->nama_acara }}</b>
          <span class="badge aktif">reservasi</span>
        </div>
        <div class="muted">{{ $r->tanggal?->format('d M Y') }} · {{ $r->detailAcara->count() }} jenis barang</div>
        <div class="row-actions">
          <form method="POST" action="/acara/{{ $r->id }}/konfirmasi" style="margin:0"
                onsubmit="return confirm('Konfirmasi reservasi ini? Stok akan dipotong.')">
            @csrf
            <button type="submit" class="btn sm">Konfirmasi</button>
          </form>
          <a href="/acara/{{ $r->id }}/edit" class="btn sm sec">Edit</a>
        </div>
      </div>
    @endforeach
  @endif

  @if($stokHabis->isNotEmpty())
    <p class="hint-sm">Stok baik habis:</p>
    @foreach($stokHabis as $s)
      <div class="item">
        <div class="top">
          <b>{{ $s->nama_barang }}</b>
          <span class="badge rusak">habis</span>
        </div>
        <div class="muted">kode {{ $s->kode_qr }} · {{ $s->lokasi ?: 'tanpa lokasi' }}</div>
        <div class="row-actions">
          <a href="/barang/{{ $s->id }}/edit" class="btn sm sec">Tambah stok</a>
        </div>
      </div>
    @endforeach
  @endif
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
  <a href="/restock" class="qa-amber">
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

<div class="card">
  <div class="card-head">
    <div>
      <h2>Stok barang</h2>
      <p class="hint">Kelola stok, tambah/kurangi, atau unduh daftar.</p>
    </div>
    <a href="/stok/excel" class="btn sm sec">Unduh Excel</a>
  </div>
  <form method="GET" action="/" class="row" style="margin-bottom:14px">
    <input name="q" value="{{ $q ?? '' }}" placeholder="Cari nama barang..." style="flex:1 1 220px;min-width:0">
    <select name="kategori" style="flex:1 1 180px;min-width:0">
      <option value="">Semua kategori</option>
      @foreach($kategori as $k)
        <option value="{{ $k->id }}" {{ request('kategori') == $k->id ? 'selected' : '' }}>{{ $k->nama_kategori }}</option>
      @endforeach
    </select>
    <button type="submit" style="flex:0 0 auto;width:auto;margin:0">Cari</button>
  </form>
  @if($barang->isEmpty())
    <p class="muted">Belum ada barang. Tambah lewat menu Stok.</p>
  @else
    <div class="scroll">
    <ul class="list">
      @foreach($barang as $b)
        @php($st = ($keluarMap[$b->id] ?? 0) > 0 ? 'sedang digunakan' : 'tersedia')
        <li class="barang-item" data-id="{{ $b->id }}">
          <div class="barang-head" onclick="toggleBarang('{{ $b->id }}')">
            <div style="display:flex;align-items:center;gap:8px">
              <span class="barang-toggle">▸</span>
              <b style="font-size:17px">{{ $b->nama_barang }}</b>
            </div>
            <div class="muted">{{ $b->lokasi ?: 'tanpa lokasi' }} · baik <span class="green">{{ $b->stok_baik }}</span></div>
          </div>
          <div class="barang-body" id="barang-body-{{ $b->id }}" style="display:none">
            <div class="aksi-barang" style="margin-top:6px">
              <a href="/barang/{{ $b->id }}/edit" class="aksi-barang selesai">Edit</a>
              <a href="/barang/{{ $b->id }}/riwayat" class="aksi-barang selesai">Riwayat</a>
              <form method="POST" action="/barang/{{ $b->id }}" style="display:inline;margin:0" onsubmit="return confirm('Hapus barang ini?')">
                @csrf @method('DELETE')
                <button type="submit" class="aksi-barang rusak">Hapus</button>
              </form>
            </div>
          </div>
          <div style="text-align:right;margin-top:6px">
            <div class="num {{ $st==='sedang digunakan' ? 'amber' : 'green' }}">{{ $b->stok_total }}</div>
            <span class="badge {{ $st==='sedang digunakan' ? 'aktif' : 'selesai' }}">{{ $st }}</span>
          </div>
        </li>
      @endforeach
    </ul>
    </div>
  @endif
</div>
<script>
  function toggleBarang(id) {
    var body = document.getElementById('barang-body-' + id);
    var toggle = document.querySelector('[data-id="' + id + '"] .barang-toggle');
    var hidden = body.style.display === 'none';
    body.style.display = hidden ? 'block' : 'none';
    toggle.textContent = hidden ? '▾' : '▸';
    try {
      localStorage.setItem('gudang_barang_' + id, hidden ? '1' : '0');
    } catch (e) {}
  }

  document.querySelectorAll('[data-id]').forEach(function (li) {
    var id = li.getAttribute('data-id');
    try {
      var saved = localStorage.getItem('gudang_barang_' + id);
      if (saved === '0') {
        document.getElementById('barang-body-' + id).style.display = 'none';
        li.querySelector('.barang-toggle').textContent = '▸';
      }
    } catch (e) {}
  });
</script>
@endsection