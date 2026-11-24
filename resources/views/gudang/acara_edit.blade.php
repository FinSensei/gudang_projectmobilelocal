@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Edit acara</h2>
  <p class="hint">
    @if($acara->status === 'aktif')
      Barang sudah keluar dari gudang. <b>Tambah</b> barang → stok baik berkurang.
      <b>Kurangi / hapus</b> → stok baik bertambah.
    @else
      Status <b>reservasi</b> — stok belum dipotong. Perubahan di sini hanya mengubah daftar pesanan.
    @endif
  </p>

  <form method="POST" action="/acara/{{ $acara->id }}" id="form-edit-acara">
    @csrf
    @method('PUT')

    <label>Nama acara</label>
    <input name="nama_acara" value="{{ $acara->nama_acara }}" required>

    <label>Tanggal</label>
    <input name="tanggal" type="date" value="{{ $acara->tanggal?->format('Y-m-d') }}">

    <label>Penanggung jawab</label>
    <input name="penanggung_jawab" value="{{ $acara->penanggung_jawab }}">

    <label>Barang yang dibawa</label>
    <div id="items">
      @foreach($acara->detailAcara as $d)
        <div class="row item-row" style="margin-bottom:8px">
          <select name="items[{{ $loop->index }}][barang_id]" style="flex:2;min-width:0">
            @foreach($barang as $bg)
              <option value="{{ $bg->id }}" {{ $bg->id == $d->barang_id ? 'selected' : '' }}>
                {{ $bg->nama_barang }} (baik {{ $bg->stok_baik }})
              </option>
            @endforeach
          </select>
          <input name="items[{{ $loop->index }}][jumlah]" type="number" inputmode="numeric"
                 min="1" value="{{ $d->jumlah_keluar }}" style="flex:1;max-width:110px;min-width:0">
          <button type="button" class="sec" onclick="this.closest('.item-row').remove()"
                  style="flex:0 0 auto;width:auto;margin:0;padding:12px 14px">✕</button>
        </div>
      @endforeach
    </div>

    <button type="button" class="sec" onclick="tambahBaris()">+ Tambah barang</button>
    <button type="submit" style="margin-top:14px">Simpan perubahan</button>
    <a href="/proses" class="back">Batal</a>
  </form>
</div>

<script>
(function () {
  // Jumlah baris saat ini (index unik untuk name[])
  let n = {{ $acara->detailAcara->count() }};

  const opsi = @json($barang->map(fn($b) => ['id'=>$b->id,'nama'=>$b->nama_barang,'baik'=>$b->stok_baik])->values());

  window.tambahBaris = function () {
    const wrap = document.getElementById('items');
    const row = document.createElement('div');
    row.className = 'row item-row';
    row.style.marginBottom = '8px';

    const sel = document.createElement('select');
    sel.name = 'items[' + n + '][barang_id]';
    sel.style.flex = '2';
    sel.style.minWidth = '0';
    sel.innerHTML = '<option value="">— pilih barang —</option>' + opsi.map(function (o) {
      return '<option value="' + o.id + '">' + o.nama + ' (baik ' + o.baik + ')</option>';
    }).join('');

    const inp = document.createElement('input');
    inp.type = 'number';
    inp.name = 'items[' + n + '][jumlah]';
    inp.min = '1';
    inp.value = '1';
    inp.inputMode = 'numeric';
    inp.style.flex = '1';
    inp.style.maxWidth = '110px';
    inp.style.minWidth = '0';

    const del = document.createElement('button');
    del.type = 'button';
    del.className = 'sec';
    del.textContent = '✕';
    del.style.cssText = 'flex:0 0 auto;width:auto;margin:0;padding:12px 14px';
    del.onclick = function () { row.remove(); };

    row.appendChild(sel); row.appendChild(inp); row.appendChild(del);
    wrap.appendChild(row);
    n++;
  };
})();
</script>
@endsection