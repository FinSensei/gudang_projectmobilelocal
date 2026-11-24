@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Edit barang</h2>
  <form method="POST" action="/barang/{{ $barang->id }}" enctype="multipart/form-data" id="form-edit">
    @csrf
    @method('PUT')
    <label>Nama barang</label>
    <input name="nama_barang" value="{{ $barang->nama_barang }}" required>

    <label>Satuan</label>
    <input name="satuan" value="{{ $barang->satuan }}" required>

    <label>Ukuran</label>
    <input name="ukuran" value="{{ $barang->ukuran ?: 'N/A' }}" placeholder="mis. 21 cm atau N/A">
    <p class="hint">Isi ukuran fisik barang. Kalau tidak ada, biarkan "N/A".</p>

    <label>Lokasi</label>
    <input name="lokasi" value="{{ $barang->lokasi }}" placeholder="mis. Lantai 1 Rak A">

    <label>Kategori</label>
    <select name="kategori_id">
      <option value="">— tanpa kategori —</option>
      @foreach($kategori as $k)
        <option value="{{ $k->id }}" {{ $barang->kategori_id == $k->id ? 'selected' : '' }}>{{ $k->nama_kategori }}</option>
      @endforeach
    </select>

    <label>Foto barang</label>

    {{-- Foto saat ini --}}
    @if($barang->foto)
      <div style="margin:6px 0">
        <img src="{{ asset('storage/'.$barang->foto) }}" alt="Foto {{ $barang->nama_barang }}"
             style="width:100%;max-width:220px;border-radius:12px;border:1px solid var(--bd)">
        <div class="muted" style="margin-top:4px">Foto saat ini</div>
      </div>
    @endif

    {{-- Dua tombol: kamera langsung / pilih dari galeri --}}
    <div class="row-actions" style="margin:10px 0">
      <label class="btn sm sec" for="foto-kamera" style="cursor:pointer;margin:0">
        <svg viewBox="0 0 24 24" style="width:18px;height:18px;vertical-align:middle;margin-right:6px;stroke:currentColor;fill:none;stroke-width:2">
          <path d="M23 19a2 2 0 0 1-2 2H3a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h4l2-3h6l2 3h4a2 2 0 0 1 2 2z"/>
          <circle cx="12" cy="13" r="4"/>
        </svg>
        Ambil Foto
      </label>

      <label class="btn sm sec" for="foto-galeri" style="cursor:pointer;margin:0">
        <svg viewBox="0 0 24 24" style="width:18px;height:18px;vertical-align:middle;margin-right:6px;stroke:currentColor;fill:none;stroke-width:2">
          <rect x="3" y="3" width="18" height="18" rx="2"/>
          <circle cx="8.5" cy="8.5" r="1.5"/>
          <path d="M21 15l-5-5L5 21"/>
        </svg>
        Pilih dari Galeri
      </label>
    </div>

    {{-- Input 1: kamera langsung (capture) --}}
    <input id="foto-kamera" name="foto" type="file" accept="image/*" capture="environment"
           style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden">

    {{-- Input 2: galeri / file --}}
    <input id="foto-galeri" name="foto_galeri" type="file" accept="image/*"
           style="position:absolute;width:1px;height:1px;opacity:0;overflow:hidden">

    {{-- Preview --}}
    <div id="preview" style="margin:10px 0;display:none">
      <img id="preview-img" src="" alt="Preview"
           style="width:100%;max-width:220px;border-radius:12px;border:1px solid var(--bd)">
      <div class="muted" style="margin-top:4px">Preview foto baru</div>
    </div>

    <p class="hint">JPG/PNG, maks 2MB. "Ambil Foto" buka kamera, "Pilih dari Galeri" buka file.</p>

    <button type="submit">Simpan perubahan</button>
    <a href="/" class="back">Batal</a>
  </form>
</div>

<script>
(function () {
  const kamera = document.getElementById('foto-kamera');
  const galeri = document.getElementById('foto-galeri');
  const preview = document.getElementById('preview');
  const img = document.getElementById('preview-img');

  function bind(input) {
    input.addEventListener('change', function () {
      const f = input.files && input.files[0];
      if (!f) return;
      if (f.size > 2 * 1024 * 1024) {
        alert('Ukuran foto maksimal 2MB. File ini ' + (f.size / 1024 / 1024).toFixed(1) + 'MB.');
        input.value = '';
        return;
      }
      // Pastikan hanya satu sumber foto yang dipakai
      if (input === kamera && galeri.value) galeri.value = '';
      if (input === galeri && kamera.value) kamera.value = '';

      const reader = new FileReader();
      reader.onload = function (e) {
        img.src = e.target.result;
        preview.style.display = 'block';
      };
      reader.readAsDataURL(f);
    });
  }

  bind(kamera);
  bind(galeri);
})();
</script>
@endsection