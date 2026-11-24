@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Dalam proses</h2>
  <p class="hint">Barang yang sudah dikonfirmasi keluar, dan reservasi yang belum dipotong stok.</p>

  <form method="GET" action="/proses" style="margin-bottom:12px">
    <div style="display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end">
      <div style="flex:1;min-width:140px">
        <label>Tanggal acara</label>
        <input type="date" name="tanggal" value="{{ $tanggal ?? '' }}">
      </div>
      <div style="flex:1;min-width:110px">
        <label>Tahun</label>
        <input type="number" name="tahun" value="{{ $tahun ?? '' }}" placeholder="mis. 2026" min="2000" max="2100">
      </div>
      <div class="row-actions" style="margin:0">
        <button type="submit" style="margin:0">Terapkan</button>
        <a href="/proses" class="btn sm sec">Reset</a>
      </div>
    </div>
  </form>

  @if($aktif->isEmpty())
    <p class="muted">{{ ($tanggal ?? '') !== '' || ($tahun ?? '') !== '' ? 'Tidak ada acara yang cocok dengan filter itu.' : 'Tidak ada acara aktif atau reservasi.' }}</p>
  @else
    @foreach($aktif as $a)
      @php($items = $a->detailAcara)
      @php($reservasi = $a->status === 'reservasi')
      <div class="item">
        <div class="top">
          <b>{{ $a->nama_acara }}</b>
          <span class="badge {{ $reservasi ? 'aktif' : 'selesai' }}">
            {{ $reservasi ? 'reservasi' : 'aktif' }}
          </span>
        </div>
        <div class="muted">
          {{ $a->tanggal?->format('d M Y') }} · {{ $a->penanggung_jawab ?: 'tanpa penanggung jawab' }}
          @if($reservasi) · <b>stok belum dipotong</b> @endif
        </div>

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
          <a href="/acara/{{ $a->id }}/edit" class="btn sm sec">Edit</a>

          @if($reservasi)
            <form method="POST" action="/acara/{{ $a->id }}/konfirmasi" style="margin:0"
                  onsubmit="return confirm('Konfirmasi reservasi ini? Stok akan dipotong sekarang.')">
              @csrf
              <button type="submit" class="btn sm">Konfirmasi</button>
            </form>
          @else
            <a href="/pengembalian" class="btn sm">Pengembalian</a>
          @endif

          <form method="POST" action="/acara/{{ $a->id }}/batalkan" style="margin:0"
                onsubmit="return confirm('Batalkan acara ini? Barang akan kembali ke stok.')">
            @csrf
            <button type="submit" class="btn sm sec" style="color:var(--red);border-color:var(--red)">Batalkan</button>
          </form>
        </div>
      </div>
    @endforeach
  @endif
</div>
@endsection