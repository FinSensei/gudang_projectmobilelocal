@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Catat barang kembali</h2>
  <p class="hint">
    Centang acara yang barangnya sudah tiba. Untuk tiap barang isi
    <b>Rusak Ringan</b>, <b>Rusak Berat</b>, dan <b>Hilang</b>.
    Kolom <b>Baik</b> terisi otomatis (sisanya).
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
          @php($keluar = (int) $d->jumlah_keluar)
          <div class="retur-row" data-keluar="{{ $keluar }}">
            <div class="retur-nama">
              <b>{{ $d->barang->nama_barang }}</b>
              <span class="muted">keluar {{ $keluar }}</span>
            </div>

            <div class="row" style="margin-top:6px">
              <div style="flex:1;min-width:92px">
                <label>Baik <span class="muted" style="font-weight:400">(otomatis)</span></label>
                <input data-role="baik" type="number" readonly tabindex="-1"
                       value="{{ $keluar }}" class="baik-input">
              </div>
              <div style="flex:1;min-width:92px">
                <label>Rusak Ringan</label>
                <input name="rusak_ringan[{{ $d->barang_id }}]" type="number" inputmode="numeric"
                       min="0" max="{{ $keluar }}" value="0" data-role="ringan">
              </div>
              <div style="flex:1;min-width:92px">
                <label>Rusak Berat</label>
                <input name="rusak_berat[{{ $d->barang_id }}]" type="number" inputmode="numeric"
                       min="0" max="{{ $keluar }}" value="0" data-role="berat">
              </div>
              <div style="flex:1;min-width:92px">
                <label>Hilang</label>
                <input name="hilang[{{ $d->barang_id }}]" type="number" inputmode="numeric"
                       min="0" max="{{ $keluar }}" value="0" data-role="hilang">
              </div>
            </div>

            <p class="baik-info muted" data-role="info" style="margin:6px 0 0">dari {{ $keluar }} barang keluar</p>
          </div>
        @endforeach
      </div>
    @endforeach
    <button type="submit" id="btn-simpan">Lanjut — lihat ringkasan</button>
  </form>

  <script>
  (function () {
    function hitung(row) {
      const max     = parseInt(row.dataset.keluar, 10) || 0;
      const ringan  = parseInt(row.querySelector('[data-role=ringan]').value, 10) || 0;
      const berat   = parseInt(row.querySelector('[data-role=berat]').value, 10) || 0;
      const hilang  = parseInt(row.querySelector('[data-role=hilang]').value, 10) || 0;

      const total   = ringan + berat + hilang;
      const baik    = Math.max(0, max - total);
      const out     = row.querySelector('[data-role=baik]');
      const info    = row.querySelector('[data-role=info]');

      out.value = baik;
      out.style.color = total > max ? 'var(--red)' : '';

      if (total > max) {
        info.textContent = 'Lebih ' + (total - max) + ' dari jumlah keluar!';
        info.style.color = 'var(--red)';
      } else if (hilang > 0) {
        info.textContent = 'Baik ' + baik + ' · hilang ' + hilang;
        info.style.color = 'var(--red)';
      } else {
        info.textContent = 'Baik ' + baik + ' dari ' + max + ' barang keluar';
        info.style.color = '';
      }
    }

    document.querySelectorAll('.retur-row').forEach(function (row) {
      row.querySelectorAll('input').forEach(function (inp) {
        inp.addEventListener('input', function () { hitung(row); });
      });
      hitung(row);
    });

    // Cegah double-submit
    document.getElementById('form-kembali').addEventListener('submit', function () {
      var btn = document.getElementById('btn-simpan');
      btn.disabled = true;
      btn.textContent = 'Memproses...';
    });
  })();
  </script>
  @endif
</div>
@endsection