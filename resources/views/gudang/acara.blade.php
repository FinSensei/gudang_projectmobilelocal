@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Acara baru — barang keluar</h2>
  <p class="hint">Isi nama acara, lalu <b>+ Tambah barang</b> satu per satu. Ketik nama, pilih dari saran, masuk daftar. Isi jumlah tiap barang, lalu Simpan.</p>

  <form method="POST" action="/acara" id="form">
    @csrf
    <label>Nama acara</label>
    <input name="nama_acara" placeholder="mis. Musrenbang" required>

    <label>Penanggung jawab</label>
    <input name="penanggung_jawab" placeholder="nama (opsional)">

    <label>Tanggal</label>
    <input name="tanggal" type="date" value="{{ now()->toDateString() }}">

    <label>Barang yang dibawa</label>
    <button type="button" class="sec" onclick="toggleAdd()">+ Tambah barang</button>

    <div id="addBox" style="display:none;margin-top:10px">
      <input id="cari" list="barang-list" placeholder="Ketik nama barang..." autocomplete="off">
      <datalist id="barang-list">
        @foreach($barang as $b)
          <option value="{{ $b->nama_barang }}"></option>
        @endforeach
      </datalist>
      <button type="button" onclick="addBarang()">Tambah ke daftar</button>
    </div>

    <div id="list"></div>

    <button type="submit">Simpan — barang keluar</button>
  </form>
</div>

<script>
const barangData = @json($barang->map(fn($b)=>['id'=>$b->id,'nama'=>$b->nama_barang,'baik'=>$b->stok_baik])->values());
let selected = [];

function toggleAdd(){
  const box = document.getElementById('addBox');
  const open = box.style.display === 'none';
  box.style.display = open ? 'block' : 'none';
  if(open) document.getElementById('cari').focus();
}

function addBarang(){
  const el = document.getElementById('cari');
  const nama = el.value.trim();
  if(!nama){ el.focus(); return; }
  let b = barangData.find(function(x){ return x.nama.toLowerCase() === nama.toLowerCase(); });
  if(!b){
    const m = barangData.filter(function(x){ return x.nama.toLowerCase().indexOf(nama.toLowerCase()) !== -1; });
    if(m.length === 1) b = m[0];
  }
  if(!b){ alert('Barang tidak ditemukan. Pilih dari saran yang muncul saat mengetik.'); return; }
  const existing = selected.find(function(x){ return x.id === b.id; });
  if(existing){ existing.jumlah += 1; }
  else { selected.push({ id: b.id, nama: b.nama, jumlah: 1, baik: b.baik }); }
  el.value = '';
  el.focus();
  renderList();
}

function renderList(){
  const list = document.getElementById('list');
  if(!selected.length){ list.innerHTML = ''; return; }
  let rows = selected.map(function(it, i){
    return '<div class="item">'
      + '<div class="top"><b>'+it.nama+'</b>'
      + '<button type="button" class="sec" style="width:auto;padding:6px 12px;margin:0;font-size:14px" onclick="removeItem('+i+')">Hapus</button></div>'
      + '<label>Jumlah <input type="number" min="1" value="'+it.jumlah+'" name="items['+i+'][jumlah]" style="max-width:110px;display:inline-block;margin-left:8px" oninput="setQty('+i+', this.value)"></label>'
      + '<span class="green" style="margin-left:10px">baik '+it.baik+'</span>'
      + '<input type="hidden" name="items['+i+'][barang_id]" value="'+it.id+'">'
      + '</div>';
  }).join('');
  list.innerHTML = '<b style="display:block;margin:12px 0 4px">Barang dibawa ('+selected.length+')</b>' + rows;
}

function setQty(i, val){ selected[i].jumlah = parseInt(val || '0', 10); }
function removeItem(i){ selected.splice(i, 1); renderList(); }

document.getElementById('cari').addEventListener('keydown', function(e){
  if(e.key === 'Enter'){ e.preventDefault(); addBarang(); }
});
renderList();
</script>
@endsection