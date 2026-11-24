@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Langkah 2 — cek ringkasan</h2>
  <p class="hint">Pastikan angka di bawah benar sebelum disimpan. Angka <span class="hi">merah</span> = hilang.</p>
  <table>
    <tr><th>Barang</th><th>Keluar</th><th>Baik</th><th>Ringan</th><th>Berat</th><th>Hilang</th></tr>
    @foreach($rows as $r)
      <tr>
        <td>{{ $r['nama'] }}</td>
        <td>{{ $r['keluar'] }}</td>
        <td>{{ $r['kembali'] }}</td>
        <td>{{ $r['ringan'] ?? 0 }}</td>
        <td>{{ $r['berat'] ?? 0 }}</td>
        <td class="{{ $r['hilang']>0 ? 'hi' : '' }}">{{ $r['hilang'] }}</td>
      </tr>
    @endforeach
  </table>
  <p style="font-size:18px;margin-top:12px">Total hilang: <b class="{{ $totalHilang>0 ? 'hi' : '' }}" style="font-size:22px">{{ $totalHilang }}</b></p>

  <form method="POST" action="/pengembalian" id="form-final">
    @csrf
    @foreach($acara_ids as $id)
      <input type="hidden" name="acara_ids[]" value="{{ $id }}">
    @endforeach
    @foreach($rows as $r)
      <input type="hidden" name="kembali[{{ $r['barang_id'] }}]" value="{{ $r['kembali'] }}">
      <input type="hidden" name="rusak_ringan[{{ $r['barang_id'] }}]" value="{{ $r['ringan'] ?? 0 }}">
      <input type="hidden" name="rusak_berat[{{ $r['barang_id'] }}]" value="{{ $r['berat'] ?? 0 }}">
    @endforeach
    <input type="hidden" name="tanggal" value="{{ $tanggal }}">

    <button type="submit" id="btn-simpan">Simpan pengembalian</button>
    <a href="/pengembalian" class="back">Batal</a>
  </form>
  <script>
    // Cegah double-submit: disable tombol setelah klik
    document.getElementById('form-final').addEventListener('submit', function(){
      var btn = document.getElementById('btn-simpan');
      btn.disabled = true;
      btn.textContent = 'Menyimpan...';
    });
  </script>
</div>
@endsection