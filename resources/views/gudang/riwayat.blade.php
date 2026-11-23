@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Rekap pengembalian</h2>
  <p class="hint">Rincian tiap gelombang — barang yang kembali dan yang hilang.</p>

  <div class="row-actions" style="margin:0 0 12px">
    <a href="/riwayat/pdf" class="btn sm">Unduh PDF</a>
    <a href="/riwayat/excel" class="btn sm sec">Unduh Excel</a>
  </div>

  <form method="GET" action="/riwayat" class="row" style="margin-bottom:12px">
    <input name="q" value="{{ $q ?? '' }}" placeholder="Cari nama acara / barang..." style="flex:1">
    <button type="submit" class="btn sm" style="margin:0;width:auto;padding:12px 16px;min-height:50px">Cari</button>
  </form>

  @if($pengembalian->isEmpty())
    <p class="muted">{{ ($q ?? '') !== '' ? 'Tidak ada hasil untuk pencarian itu.' : 'Belum ada pengembalian.' }}</p>
  @else
    <div class="scroll" style="max-height:60vh">
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
          <div class="row-actions">
            <a href="/riwayat/{{ $p->id }}" class="btn sm sec">Detail</a>
            <form method="POST" action="/pengembalian/{{ $p->id }}/undo" style="margin:0" onsubmit="return confirm('Batalkan pengembalian ini? Stok akan dikembalikan.')">
              @csrf
              <button type="submit" class="btn sm sec" style="color:var(--red);border-color:var(--red)">Undo</button>
            </form>
          </div>
        </div>
      @endforeach
    </div>
    <p class="hint" style="text-align:center;margin-top:10px">{{ $pengembalian->count() }} gelombang ditampilkan · scroll untuk lihat semua</p>
  @endif
</div>
@endsection