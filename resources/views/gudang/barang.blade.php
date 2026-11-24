@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Tambah barang (cepat)</h2>
  <p class="hint">Tambahkan beberapa barang sekaligus. Ukuran opsional (mis. 21 cm, 26 cm, S/M/L).</p>
  <form method="POST" action="/barang">
    @csrf
    <div id="rows"></div>
    <button type="button" class="sec" onclick="addRow()">+ Baris barang</button>
    <button type="submit">Simpan semua barang</button>
  </form>
</div>

<script>
let idx = 0;
function addRow(){
  const box = document.getElementById('rows');
  const i = idx++;
  box.insertAdjacentHTML('beforeend',
    '<div class="item" style="display:flex;gap:8px;flex-wrap:wrap;align-items:flex-end">' +
      '<div style="flex:2;min-width:130px"><label>Nama barang</label>' +
        '<input name="nama[]" placeholder="mis. Sendok" required></div>' +
      '<div style="flex:1;min-width:110px"><label>Ukuran (opsional)</label>' +
        '<input name="ukuran[]" placeholder="mis. 21 cm"></div>' +
      '<div style="flex:1;min-width:90px"><label>Jumlah</label>' +
        '<input name="jumlah[]" type="number" min="0" value="1" required></div>' +
      '<button type="button" class="sec" style="width:auto;padding:10px 12px;margin:0" onclick="this.parentNode.remove()">×</button>' +
    '</div>');
}
addRow();
</script>
@endsection