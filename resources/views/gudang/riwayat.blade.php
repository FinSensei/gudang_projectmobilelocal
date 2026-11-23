@extends('gudang.layout')

@section('content')
<div class="card">
  <div class="card-head">
    <div>
      <h2>Rekap pengembalian</h2>
      <p class="hint">Rincian tiap gelombang.</p>
    </div>
    <a href="/riwayat/pdf" class="btn sm">Unduh PDF</a>
  </div>

  @if($pengembalian->isEmpty())
    <p class="muted">Belum ada pengembalian.</p>
  @else
    @foreach($pengembalian as $p)
      @php($rows = $p->detailRows())
      <div class="item">
        <div class="top">
          <b>{{ $p->tanggal_pulang?->format('d M Y') }}</b>
          <span class="badge selesai">selesai</span>
        </div>
        <div class="muted">Acara: {{ $p->acara->pluck('nama_acara')->join(', ') }}</div>
        <div class="table-scroll cols5">
          <table>
            <tr><th>Barang</th><th>Keluar</th><th>Kembali</th><th>Hilang</th><th>Tersedia</th></tr>
            @foreach(array_slice($rows, 0, 4) as $r)
              <tr>
                <td>{{ $r['barang'] }}</td>
                <td>{{ $r['keluar'] }}</td>
                <td>{{ $r['kembali'] }}</td>
                <td class="{{ $r['hilang'] > 0 ? 'hi' : '' }}">{{ $r['hilang'] }}</td>
                <td>{{ $r['tersedia'] }}</td>
              </tr>
            @endforeach
            @if(count($rows) > 4)
              <tr><td colspan="5" style="text-align:center;color:var(--mut)">&#8943; {{ count($rows) - 4 }} barang lagi</td></tr>
            @endif
          </table>
        </div>
        <a href="/riwayat/{{ $p->id }}" class="btn sm sec" style="margin-top:10px">Detail</a>
      </div>
    @endforeach
  @endif
</div>
@endsection