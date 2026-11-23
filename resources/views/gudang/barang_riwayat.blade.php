@extends('gudang.layout')

@section('content')
<div class="card">
  <div class="card-head">
    <div>
      <h2>{{ $barang->nama_barang }}</h2>
      <p class="hint">Riwayat transaksi barang ini.</p>
    </div>
    <span class="badge {{ $barang->statusStok() === 'habis' ? 'rusak' : ($barang->statusStok() === 'menipis' ? 'aktif' : 'selesai') }}">{{ $barang->statusStok() }}</span>
  </div>

  <div class="row-actions" style="margin:0 0 12px">
    <div><span class="muted">Total</span> <b>{{ $barang->stok_total }}</b></div>
    <div><span class="muted">Baik</span> <b class="green">{{ $barang->stok_baik }}</b></div>
    <div><span class="muted">Rusak</span> <b class="amber">{{ $barang->stok_rusak }}</b></div>
    <div><span class="muted">Keluar</span> <b class="red">{{ $barang->stokKeluar() }}</b></div>
  </div>

  @if($barang->ukuran)
    <p class="hint">Ukuran: <b>{{ $barang->ukuran }}</b> · {{ $barang->lokasi ?: 'tanpa lokasi' }}</p>
  @endif

  @if(empty($rows))
    <p class="muted">Belum ada riwayat.</p>
  @else
    <div class="table-scroll">
      <table>
        <tr><th>Tanggal</th><th>Tipe</th><th>Acara</th><th>Jumlah</th><th>Hilang</th></tr>
        @foreach($rows as $r)
          <tr>
            <td>{{ $r['tanggal']?->format('d/m/Y') ?: '-' }}</td>
            <td>
              <span class="badge {{ $r['tipe'] === 'keluar' ? 'aktif' : 'selesai' }}">{{ $r['tipe'] }}</span>
            </td>
            <td>{{ $r['acara'] }}</td>
            <td>{{ $r['jumlah'] }}</td>
            <td class="{{ ($r['hilang'] ?? 0) > 0 ? 'hi' : '' }}">{{ $r['hilang'] ?? '-' }}</td>
          </tr>
        @endforeach
      </table>
    </div>
  @endif

  <div class="row-actions">
    <a href="/barang/{{ $barang->id }}/edit" class="btn sm sec">Edit barang</a>
    <a href="/" class="btn sm sec">Kembali</a>
  </div>
</div>
@endsection