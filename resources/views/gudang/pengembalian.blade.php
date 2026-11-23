@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Catat barang kembali</h2>
  <p class="hint">Centang acara yang barangnya sudah tiba di gudang. Angka "kembali" sudah terisi sesuai jumlah keluar — <b>kalau semua kembali, biarkan saja</b>. Kalau ada yang kurang, ganti angkanya jadi jumlah yang benar-benar datang.</p>
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
          <label>{{ $d->barang->nama_barang }} — kembali
            <input name="kembali[{{ $d->barang_id }}]" type="number" min="0" max="{{ $d->jumlah_keluar }}" value="{{ $d->jumlah_keluar }}" style="max-width:120px;display:inline-block;margin-left:8px">
          </label>
        @endforeach
      </div>
    @endforeach
    <button type="submit" id="btn-simpan">Lanjut — lihat ringkasan</button>
  </form>
  <script>
    // Cegah double-submit: disable tombol setelah klik
    document.getElementById('form-kembali').addEventListener('submit', function(){
      var btn = document.getElementById('btn-simpan');
      btn.disabled = true;
      btn.textContent = 'Memproses...';
    });
  </script>
  @endif
</div>
@endsection