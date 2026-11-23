@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>{{ $a->nama_acara }}</h2>
  <p class="hint">{{ $a->tanggal?->format('d M Y') }} · {{ $a->penanggung_jawab ?: 'tanpa penanggung jawab' }}</p>
  <div class="table-scroll fit">
    <table>
      <tr><th>Barang</th><th>Keluar</th></tr>
      @foreach($a->detailAcara as $d)
        <tr><td>{{ $d->barang->nama_barang }}</td><td>{{ $d->jumlah_keluar }}</td></tr>
      @endforeach
    </table>
  </div>
  <a href="/proses" class="btn sec" style="margin-top:12px">Kembali</a>
  <a href="/pengembalian" class="btn" style="margin-top:10px">Catat barang kembali</a>
</div>
@endsection