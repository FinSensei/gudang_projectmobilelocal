<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>{{ $b->nama_barang }}</title>
<style>
  body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f3f4f6;color:#111827;font-size:17px}
  .wrap{max-width:480px;margin:0 auto;padding:16px}
  .card{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:18px;margin-bottom:14px}
  h1{font-size:24px;margin:0 0 4px}
  .muted{color:#4b5563;font-size:15px}
  .big{font-size:40px;font-weight:800;line-height:1.1;margin:8px 0 2px}
  .badge{display:inline-block;padding:4px 12px;border-radius:999px;font-size:14px;font-weight:600;background:#e7f5ea;color:#1a7f37;margin-top:8px}
  .badge.keluar{background:#fff4e0;color:#9a6700}
  ul{margin:8px 0 0;padding-left:20px;line-height:1.7}
</style>
</head>
<body>
<div class="wrap">
  <div class="card">
    <h1>{{ $b->nama_barang }}</h1>
    <div class="muted">{{ $b->lokasi ?: 'tanpa lokasi' }}</div>
    <div class="big">{{ $b->stok_total }}</div>
    <div class="muted">stok asli · baik {{ $b->stok_baik }} · rusak {{ $b->stok_rusak }}</div>
    <div><span class="badge {{ $b->statusPemakaian()==='sedang digunakan'?'keluar':'' }}">{{ $b->statusPemakaian() }}</span></div>
  </div>

  @if($b->statusPemakaian()==='sedang digunakan')
  <div class="card">
    <b>Sedang dibawa ke acara:</b>
    <ul>
      @foreach($acara as $a)
        <li>{{ $a->nama_acara }} — {{ $a->detailAcara->where('barang_id',$b->id)->sum('jumlah_keluar') }} unit</li>
      @endforeach
    </ul>
  </div>
  @endif
</div>
</body>
</html>