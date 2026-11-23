@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Dalam proses</h2>
  <p class="hint">Barang yang sedang di acara — belum kembali ke gudang.</p>
  @if($aktif->isEmpty())
    <p class="muted">Tidak ada acara aktif. Semua barang sudah kembali.</p>
  @else
    @foreach($aktif as $a)
      @php($items = $a->detailAcara)
      <div class="item">
        <div class="top">
          <b>{{ $a->nama_acara }}</b>
          <span class="badge aktif">aktif</span>
        </div>
        <div class="muted">{{ $a->tanggal?->format('d M Y') }} · {{ $a->penanggung_jawab ?: 'tanpa penanggung jawab' }}</div>
        <div class="table-scroll fit">
          <table>
            <tr><th>Barang</th><th>Keluar</th></tr>
            @foreach($items->take(4) as $d)
              <tr><td>{{ $d->barang->nama_barang }}</td><td>{{ $d->jumlah_keluar }}</td></tr>
            @endforeach
            @if($items->count() > 4)
              <tr><td colspan="2" style="text-align:center;color:var(--mut)">&#8943; {{ $items->count() - 4 }} barang lagi</td></tr>
            @endif
          </table>
        </div>
        <div class="row-actions">
          <a href="/proses/{{ $a->id }}" class="btn sm sec">Detail</a>
          <a href="/pengembalian" class="btn sm">Catat kembali</a>
        </div>
      </div>
    @endforeach
  @endif
</div>
@endsection