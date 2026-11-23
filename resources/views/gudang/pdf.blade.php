<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<style>
  body { font-family: sans-serif; font-size: 12px; color: #111; }
  h1 { font-size: 18px; margin-bottom: 4px; }
  h2 { font-size: 14px; margin: 16px 0 6px; border-bottom: 1px solid #ccc; padding-bottom: 3px; }
  table { width: 100%; border-collapse: collapse; margin-bottom: 8px; }
  th, td { border: 1px solid #999; padding: 5px 6px; text-align: left; }
  th { background: #eee; font-size: 11px; }
  .hilang { color: #c00; font-weight: bold; }
  .muted { color: #666; font-size: 11px; }
</style>
</head>
<body>
  <h1>Rekap Pengembalian</h1>
  <div class="muted">Dicetak: {{ now()->format('d M Y H:i') }}</div>

  @forelse($pengembalian as $p)
    <h2>{{ $p->tanggal_pulang?->format('d M Y') }} — {{ $p->acara->pluck('nama_acara')->join(', ') }}</h2>
    <table>
      <tr><th>Barang</th><th>Keluar</th><th>Kembali</th><th>Hilang</th><th>Tersedia</th></tr>
      @foreach($p->detailRows() as $r)
        <tr>
          <td>{{ $r['barang'] }}</td>
          <td>{{ $r['keluar'] }}</td>
          <td>{{ $r['kembali'] }}</td>
          <td class="{{ $r['hilang'] > 0 ? 'hilang' : '' }}">{{ $r['hilang'] }}</td>
          <td>{{ $r['tersedia'] }}</td>
        </tr>
      @endforeach
    </table>
  @empty
    <p class="muted">Belum ada pengembalian.</p>
  @endforelse
</body>
</html>