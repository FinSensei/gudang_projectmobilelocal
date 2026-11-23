@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Tambah stok</h2>
  <p class="hint">Ketik nama barang untuk mencari dari ratusan barang, lalu isi jumlah. Kalau barang belum ada, isi nama di kolom "barang baru".</p>
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

    <label>Atau nama barang baru</label>
    <input name="nama_baru" placeholder="mis. Gelas">

    <label>Jumlah</label>
    <input name="jumlah" type="number" min="1" value="10" required>

    <button type="submit">Tambah stok</button>
  </form>
</div>

<div class="card">
  <h2>Tandai rusak</h2>
  <form method="POST" action="/rusak">
    @csrf
    <label>Nama barang</label>
    <input id="cari_rusak" list="barang_list2" placeholder="Ketik nama barang..." autocomplete="off">
    <datalist id="barang_list2">
      @foreach($barang as $b)
        <option value="{{ $b->nama_barang }}"></option>
      @endforeach
    </datalist>
    <input type="hidden" name="barang_id" id="rusak_id">

    <label>Jumlah rusak</label>
    <input name="jumlah" type="number" min="1" value="1" required>
    <button type="submit" class="sec">Tandai rusak</button>
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
</script>
@endsection