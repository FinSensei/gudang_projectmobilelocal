@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Catat barang kembali</h2>
  <p class="hint">
    Centang acara yang barangnya sudah tiba di gudang. Untuk setiap barang isi:
    <b>Baik</b> (bisa dipakai lagi), <b>Rusak Ringan</b>, <b>Rusak Berat</b>.
    Kalau semua kembali dalam kondisi baik, biarkan angka ringan/berat = 0.
  </p>

  @if($aktif->isEmpty())
    <p class="muted">Tidak ada acara aktif.</p>
  @else
  <form method="POST" action="/pengembalian/preview" id="form-kembali">
    @csrf
    @foreach($aktif as $a)
      <div class="item">
        <label style="display:flex;align-items:center;gap:10px;font-size:17px;color:var(--fg);font-weight:700">
          <input type="checkbox" name="acara_ids[]" value="{{ $a->id }}" style="width:auto;min-height:auto">
          {{ $a->nama_acara }}
          <span class="badge aktif">aktif</span>
        </label>
        <div class="muted" style="margin:6px 0 0">
          @foreach($a->detailAcara as $d)
            {{ $d->barang->nama_barang }} {{ $d->jumlah_keluar }} ·
          @endforeach
        </div>

        @foreach($a->detailAcara as $d)
          <div class="retur-row">
            <div class="retur-nama">
              <b>{{ $d->barang->nama_barang }}</b>
              <span class="muted">keluar {{ $d->jumlah_keluar }}</span>
            </div>
            <div class="row" style="margin-top:6px">
              <div style="flex:1;min-width:92px">
                <label>Baik</label>
                <input name="kembali[{{ $d->barang_id }}]" type="number" inputmode="numeric"
                       min="0" max="{{ $d->jumlah_keluar }}" value="{{ $d->jumlah_keluar }}">
              </div>
              <div style="flex:1;min-width:92px">
                <label>Rusak Ringan</label>
                <input name="rusak_ringan[{{ $d->barang_id }}]" type="number" inputmode="numeric"
                       min="0" max="{{ $d->jumlah_keluar }}" value="0">
              </div>
              <div style="flex:1;min-width:92px">
                <label>Rusak Berat</label>
                <input name="rusak_berat[{{ $d->barang_id }}]" type="number" inputmode="numeric"
                       min="0" max="{{ $d->jumlah_keluar }}" value="0">
              </div>
            </div>
          </div>
        @endforeach
      </div>
    @endforeach
    <button type="submit" id="btn-simpan">Lanjut — lihat ringkasan</button>
  </form>
  <script>
    // Cegah double-submit
    document.getElementById('form-kembali').addEventListener('submit', function(){
      var btn = document.getElementById('btn-simpan');
      btn.disabled = true;
      btn.textContent = 'Memproses...';
    });
  </script>
  @endif
</div>
@endsection