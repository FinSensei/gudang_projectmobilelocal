@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Kode QR barang</h2>
  <p class="hint">Cetak halaman ini, lalu tempel QR di rak atau barang. Operator scan pakai HP → langsung lihat stok.</p>
  <button type="button" onclick="window.print()">Cetak halaman ini</button>

  <div class="qr-grid">
    @foreach($barang as $b)
      <div class="qr-item">
        <div class="qr-box">{!! QrCode::size(130)->generate(url('/b/'.$b->kode_qr)) !!}</div>
        <b>{{ $b->nama_barang }}</b>
        <div class="muted">{{ $b->lokasi ?: 'tanpa lokasi' }}</div>
        <div class="muted">kode: {{ $b->kode_qr }}</div>
      </div>
    @endforeach
    @if($barang->isEmpty())
      <p class="muted">Belum ada barang.</p>
    @endif
  </div>
</div>

<style>
  .qr-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(150px,1fr));gap:14px;margin-top:16px}
  .qr-item{border:1px solid var(--bd);border-radius:12px;padding:12px;text-align:center;background:#fff}
  .qr-box{display:flex;justify-content:center;margin-bottom:8px}
  .qr-box svg{width:130px;height:130px}
  .qr-item b{font-size:15px;display:block;margin-bottom:2px}
  .qr-item .muted{font-size:12px}
  @media print{
    header,nav,.hint,header,.card>h2,button{display:none !important}
    .qr-grid{gap:10px}
    .qr-item{page-break-inside:avoid}
    body{background:#fff}
  }
</style>
@endsection