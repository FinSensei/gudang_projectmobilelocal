@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Tambah stok</h2>
  <p class="hint">Cari barang yang sudah ada lalu isi jumlah. Kalau barang belum ada, pakai "Tambah barang baru" di bawah.</p>
  <form method="POST" action="/restock">
    @csrf
    <label>Nama barang</label>
    <input id="cari_restock" list="barang_list" placeholder="Ketik nama barang..." autocomplete="off">
    <datalist id="barang_list">
      @foreach($barang as $b)
        <option value="{{ $b->nama_barang }}"></option>
      @endforeach
    </datalist>
    <input type="hidden" name="barang_id" id="restock_id">

    <label>Jumlah</label>
    <input name="jumlah" type="number" inputmode="numeric" min="1" value="10" required>

    <button type="submit">Tambah stok</button>
  </form>
</div>

<div class="card">
  <h2>Tambah barang baru</h2>
  <p class="hint">Untuk barang yang belum terdaftar. Ukuran opsional — isi kalau ada (mis. 21 cm), kalau tidak ada kosongkan.</p>

  <form method="POST" action="/barang" enctype="multipart/form-data" id="form-barang">
    @csrf
    <div id="rows"></div>
    <button type="button" class="sec" onclick="addRow()">+ Baris barang</button>
    <button type="submit">Simpan semua barang</button>
  </form>
</div>

<div class="card">
  <h2>Tandai rusak atau hilang</h2>
  <p class="hint">Pilih jenisnya: <b>rusak</b> (barang masih ada, tapi tidak layak) atau <b>hilang</b> (barang tidak ada lagi).</p>

  <form method="POST" action="/rusak" id="form-rusak">
    @csrf
    <label>Nama barang</label>
    <input id="cari_rusak" list="barang_list2" placeholder="Ketik nama barang..." autocomplete="off">
    <datalist id="barang_list2">
      @foreach($barang as $b)
        <option value="{{ $b->nama_barang }}"></option>
      @endforeach
    </datalist>
    <input type="hidden" name="barang_id" id="rusak_id">

    <label>Jenis</label>
    <select name="jenis" id="jenis" onchange="gantiJenis()">
      <option value="rusak">Rusak — barang masih ada</option>
      <option value="hilang">Hilang — barang tidak ada lagi</option>
    </select>

    <label>Jumlah</label>
    <input name="jumlah" type="number" inputmode="numeric" min="1" value="1" required>

    <div id="blok-tingkat">
      <label>Tingkat kerusakan</label>
      <select name="tingkat">
        <option value="ringan">Rusak ringan — masih bisa dipakai/diperbaiki</option>
        <option value="berat">Rusak berat — tidak bisa dipakai</option>
      </select>
    </div>

    <p class="hint" id="info-jenis" style="display:none">
      Barang hilang akan <b>mengurangi total stok</b> (baik dan total sama-sama turun).
    </p>

    <label>Keterangan (opsional)</label>
    <input name="keterangan" maxlength="255" placeholder="mis. gagang patah, jatuh dari rak">
    <p class="hint">Dicatat di Log aktivitas supaya jelas sebabnya.</p>

    <button type="submit" class="sec">Simpan</button>
  </form>
</div>

<script>
const barangMap = @json($barang->map(fn($b)=>['nama'=>$b->nama_barang,'id'=>$b->id])->values());

function setup(inpId, hidId){
  const inp = document.getElementById(inpId);
  const hid = document.getElementById(hidId);
  function sync(){
    const f = barangMap.find(x => x.nama.toLowerCase() === inp.value.trim().toLowerCase());
    hid.value = f ? f.id : '';
  }
  inp.addEventListener('input', sync);
  inp.addEventListener('change', sync);
}
setup('cari_restock','restock_id');
setup('cari_rusak','rusak_id');

// Hide/show blok tingkat sesuai jenis
window.gantiJenis = function () {
  const jenis = document.getElementById('jenis').value;
  const blok = document.getElementById('blok-tingkat');
  const info = document.getElementById('info-jenis');
  if (jenis === 'hilang') {
    blok.style.display = 'none';
    info.style.display = 'block';
  } else {
    blok.style.display = 'block';
    info.style.display = 'none';
  }
};
gantiJenis();

// Baris dinamis untuk tambah barang baru
let idx = 0;
window.addRow = function () {
  const box = document.getElementById('rows');
  box.insertAdjacentHTML('beforeend',
    '<div class="row item-row" style="margin-bottom:8px">' +
      '<div style="flex:2;min-width:130px"><label>Nama barang</label>' +
        '<input name="nama[]" placeholder="mis. Sendok" required></div>' +
      '<div style="flex:1;min-width:110px"><label>Ukuran (opsional)</label>' +
        '<input name="ukuran[]" placeholder="mis. 21 cm"></div>' +
      '<div style="flex:1;min-width:90px"><label>Jumlah</label>' +
        '<input name="jumlah[]" type="number" inputmode="numeric" min="0" value="1" required></div>' +
      '<div style="flex:1;min-width:120px"><label>Foto (opsional)</label>' +
        '<input name="foto[]" type="file" accept="image/*" style="font-size:14px"></div>' +
      '<button type="button" class="sec" style="flex:0 0 auto;width:auto;margin:0;padding:12px 14px" ' +
        'onclick="this.closest(\'.item-row\').remove()">✕</button>' +
    '</div>');
};
addRow();
</script>
@endsection