@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Detail pengembalian</h2>
  <p class="hint">{{ $p->tanggal_pulang?->format('d M Y') }} · {{ $p->acara->pluck('nama_acara')->join(', ') }}</p>
  <div class="table-scroll cols5">
    <table>
      <tr><th>Barang</th><th>Keluar</th><th>Kembali</th><th>Hilang</th><th>Tersedia</th></tr>
      @foreach($p->detailRows() as $r)
        <tr>
          <td>{{ $r['barang'] }}</td>
          <td>{{ $r['keluar'] }}</td>
          <td>{{ $r['kembali'] }}</td>
          <td class="{{ $r['hilang'] > 0 ? 'hi' : '' }}">{{ $r['hilang'] }}</td>
          <td>{{ $r['tersedia'] }}</td>
        </tr>
      @endforeach
    </table>
  </div>
  <a href="/riwayat" class="btn sec" style="margin-top:12px">Kembali ke rekap</a>
</div>
@endsection