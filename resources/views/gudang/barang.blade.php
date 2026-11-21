@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Tambah barang — cepat</h2>
  <p class="hint">Ketik nama barang, isi jumlah, tap <b>Tambah</b>. Ulangi untuk barang berikutnya. Kalau sudah selesai, tap <b>Simpan semua</b>.</p>

  <form method="POST" action="/barang" id="form">
    @csrf
    <label>Nama barang</label>
    <input id="nama" placeholder="mis. Sendok" autocomplete="off">

    <label>Jumlah</label>
    <input id="jumlah" type="number" min="0" value="1">

    <button type="button" onclick="addItem()">+ Tambah ke daftar</button>

    <div id="list"></div>

    <button type="submit">Simpan semua</button>
  </form>
</div>

<script>
const items = [];
function addItem(){
  const namaEl = document.getElementById('nama');
  const jumlahEl = document.getElementById('jumlah');
  const nama = namaEl.value.trim();
  const jumlah = parseInt(jumlahEl.value || '0', 10);
  if(!nama){ namaEl.focus(); return; }
  items.push({ nama: nama, jumlah: jumlah });
  namaEl.value = '';
  jumlahEl.value = '1';
  namaEl.focus();
  renderList();
}
function renderList(){
  const list = document.getElementById('list');
  if(!items.length){
    list.innerHTML = '<p class="muted" style="margin-top:14px">Belum ada barang di daftar.</p>';
    return;
  }
  let rows = items.map(function(it, i){
    return '<li><span><b>'+it.nama+'</b> <span class="muted">× '+it.jumlah+'</span></span>'
      + '<button type="button" class="sec" style="width:auto;padding:6px 12px;margin:0;font-size:14px" onclick="removeItem('+i+')">Hapus</button></li>';
  }).join('');
  list.innerHTML = '<div style="margin-top:14px"><b>Daftar barang ('+items.length+')</b><ul class="list" style="margin-top:6px">'+rows+'</ul></div>';
}
function removeItem(i){ items.splice(i,1); renderList(); }

document.getElementById('form').addEventListener('submit', function(e){
  // kalau masih ada teks di input nama, ikutkan dulu
  const namaEl = document.getElementById('nama');
  const jumlahEl = document.getElementById('jumlah');
  const nama = namaEl.value.trim();
  if(nama){
    items.push({ nama: nama, jumlah: parseInt(jumlahEl.value || '0', 10) });
  }
  // susun hidden input
  items.forEach(function(it){
    const n = document.createElement('input');
    n.type = 'hidden'; n.name = 'nama[]'; n.value = it.nama;
    e.target.appendChild(n);
    const j = document.createElement('input');
    j.type = 'hidden'; j.name = 'jumlah[]'; j.value = it.jumlah;
    e.target.appendChild(j);
  });
});
renderList();
</script>
@endsection