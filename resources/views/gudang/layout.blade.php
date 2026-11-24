<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover, interactive-widget=resizes-content">
<meta name="theme-color" media="(prefers-color-scheme: light)" content="#f3f4f6">
<meta name="theme-color" media="(prefers-color-scheme: dark)" content="#111827">
<meta name="color-scheme" content="light">
<meta name="csrf-token" content="{{ csrf_token() }}">
<title>Gudang</title>
<style>
  :root{
    --fg:#111827; --mut:#4b5563; --bd:#e5e7eb; --bg:#f3f4f6; --card:#fff;
    --acc:#1f6feb; --acc-d:#1a5fd0;
    --green:#1a7f37; --green-bg:#dcfce7;
    --amber:#b45309; --amber-bg:#fef3c7;
    --red:#cf222e;  --red-bg:#fee2e2;
    --blue:#1d4ed8; --blue-bg:#dbeafe;
    --purple:#7c3aed;--purple-bg:#ede9fe;
  }
  *{box-sizing:border-box}

  /* ===== mobile-native: platform layer ===== */
  html{
    -webkit-tap-highlight-color: transparent;   /* buang kilat abu-abu saat tap */
    -webkit-text-size-adjust: 100%;             /* cegah font membengkak di landscape */
    overscroll-behavior: none;                  /* cegah pull-to-refresh merampas scroll */
  }

  body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;
    background:var(--bg);color:var(--fg);font-size:17px;line-height:1.45;
    overscroll-behavior:none}

  /* input minimal 16px -> iOS tidak zoom saat fokus */
  input,select,textarea{font-size:17px}

  /* tap langsung diproses, tanpa delay 300ms */
  button,a,[role="button"],.btn{touch-action: manipulation}

  /* teks tombol jangan bisa diseleksi / callout saat tekan lama */
  button,[role="button"],.btn,.tab,.chip{
    user-select:none;-webkit-user-select:none;-webkit-touch-callout:none;
  }

  .wrap{max-width:640px;margin:0 auto;padding:16px;
    padding-left:calc(16px + env(safe-area-inset-left, 0px));
    padding-right:calc(16px + env(safe-area-inset-right, 0px))}

  header{display:flex;align-items:center;gap:10px;padding:14px 16px;background:var(--card);
    border-bottom:1px solid var(--bd);position:sticky;top:0;z-index:10;
    padding-top:calc(14px + env(safe-area-inset-top, 0px))}
  header .title{font-weight:700;font-size:20px}
  nav{display:grid;grid-template-columns:repeat(5,1fr);gap:6px;margin:14px 0}
  nav a{min-height:50px;display:flex;align-items:center;justify-content:center;text-align:center;
    text-decoration:none;color:var(--fg);background:var(--card);border:1px solid var(--bd);
    border-radius:12px;padding:10px 4px;font-size:13px;font-weight:600}
  nav a:active{background:var(--acc);color:#fff;border-color:var(--acc)}
  .card{background:var(--card);border:1px solid var(--bd);border-radius:14px;padding:16px;margin-bottom:14px}
  .card h2{margin:0 0 12px;font-size:18px;font-weight:700}
  label{font-size:15px;color:#374151;display:block;margin:12px 0 6px;font-weight:600}
  input,select{width:100%;padding:12px;border:1px solid var(--bd);border-radius:10px;font-size:17px;background:#fff;min-height:50px}
  button,.btn{background:var(--acc);color:#fff;border:0;border-radius:12px;padding:15px 16px;
    font-size:17px;font-weight:600;cursor:pointer;width:100%;margin-top:12px;min-height:54px}
  button.sec,.btn.sec{background:#fff;color:var(--fg);border:1px solid var(--bd)}
  button:active{background:var(--acc-d)}
  .msg{padding:12px 14px;border-radius:10px;margin-bottom:12px;font-size:15px;font-weight:500}
  .msg.ok{background:var(--green-bg);color:var(--green)}
  .msg.err{background:var(--red-bg);color:var(--red)}
  .list{list-style:none;padding:0;margin:0}
  .list li{padding:14px 0;border-bottom:1px solid var(--bd);display:flex;justify-content:space-between;align-items:center;gap:12px}
  .list li:last-child{border-bottom:0}
  .scroll{max-height:58vh;overflow-y:auto;-webkit-overflow-scrolling:touch;padding-right:4px}
  .muted{color:var(--mut);font-size:14px}
  .green{color:var(--green);font-weight:700}
  .amber{color:var(--amber);font-weight:700}
  .red{color:var(--red);font-weight:700}
  .badge{display:inline-block;padding:3px 10px;border-radius:999px;font-size:13px;font-weight:700}
  .badge.aktif{background:var(--amber-bg);color:var(--amber)}
  .badge.selesai{background:var(--green-bg);color:var(--green)}
  .badge.rusak{background:var(--red-bg);color:var(--red)}
  .num{font-size:30px;font-weight:800;line-height:1;font-variant-numeric:tabular-nums}
  .item{border:1px solid var(--bd);border-radius:12px;padding:14px;margin:10px 0}
  .item .top{display:flex;justify-content:space-between;align-items:center;font-size:16px;font-weight:600}
  table{width:100%;border-collapse:collapse;font-size:16px}
  th,td{text-align:left;padding:10px 6px;border-bottom:1px solid var(--bd)}
  th{color:var(--mut);font-size:14px;font-weight:700}
  .hint{font-size:14px;color:var(--mut);margin:6px 0 10px;line-height:1.5}
  .quick{display:grid;gap:10px;margin-bottom:14px}
  .quick a{display:flex;align-items:center;gap:14px;padding:14px 16px;border-radius:14px;background:var(--card);
    border:1px solid var(--bd);text-decoration:none;color:var(--fg);font-size:18px;font-weight:700;min-height:64px}
  .quick a:active{background:#f9fafb}
  .qa-ic{width:46px;height:46px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0}
  .qa-ic svg{width:24px;height:24px;stroke:currentColor;fill:none;stroke-width:2}
  .qa-green .qa-ic{background:var(--green-bg);color:var(--green)}
  .qa-blue .qa-ic{background:var(--blue-bg);color:var(--blue)}
  .qa-amber .qa-ic{background:var(--amber-bg);color:var(--amber)}
  .qa-purple .qa-ic{background:var(--purple-bg);color:var(--purple)}
  .stat{display:flex;gap:10px;margin-bottom:14px}
  .stat .box{flex:1;border:1px solid var(--bd);border-radius:14px;padding:14px;text-align:center}
  .stat .box .n{font-size:28px;font-weight:800;line-height:1.1;font-variant-numeric:tabular-nums}
  .stat .box .l{font-size:13px;color:var(--mut)}
  .stat .box.blue{background:var(--blue-bg);border-color:#bfdbfe}
  .stat .box.blue .n{color:var(--blue)}
  .stat .box.amber{background:var(--amber-bg);border-color:#fde68a}
  .stat .box.amber .n{color:var(--amber)}
  a.back{display:block;text-align:center;margin-top:14px;font-size:16px;color:var(--mut);text-decoration:none}
  .card-head{display:flex;justify-content:space-between;align-items:flex-start;gap:12px;margin-bottom:2px}
  .card-head h2{margin:0}
  .btn.sm{width:auto;min-height:0;padding:9px 14px;margin:0;font-size:15px;display:inline-block;text-align:center;white-space:nowrap}
  .btn.sm.sec{background:#fff;color:var(--fg);border:1px solid var(--bd)}
  a.btn{display:block;text-align:center}
  .table-scroll{overflow:auto;max-height:42vh;-webkit-overflow-scrolling:touch;margin-top:8px;border:1px solid var(--bd);border-radius:10px;
    overscroll-behavior:contain}
  .table-scroll table{width:100%;min-width:0;table-layout:fixed;margin:0}
  .table-scroll th,.table-scroll td{padding:8px 5px;font-size:14px;overflow-wrap:break-word;word-wrap:break-word}
  .table-scroll.fit th:first-child,.table-scroll.fit td:first-child{width:72%}
  .table-scroll.fit th:last-child,.table-scroll.fit td:last-child{width:28%;text-align:right}
  .table-scroll.cols5 th:nth-child(1),.table-scroll.cols5 td:nth-child(1){width:30%}
  .table-scroll.cols5 th:nth-child(2),.table-scroll.cols5 td:nth-child(2){width:15%}
  .table-scroll.cols5 th:nth-child(3),.table-scroll.cols5 td:nth-child(3){width:18%}
  .table-scroll.cols5 th:nth-child(4),.table-scroll.cols5 td:nth-child(4){width:15%}
  .table-scroll.cols5 th:nth-child(5),.table-scroll.cols5 td:nth-child(5){width:22%}
  td.hi{color:var(--red);font-weight:700}
  .row-actions{display:flex;gap:8px;margin-top:10px;flex-wrap:wrap}
  .row-actions .btn{margin-top:0}

  /* baris form: cari + kategori + tombol */
  .row{display:flex;gap:10px;flex-wrap:wrap;align-items:flex-end}
  .row > *{min-width:0}
  .row input,.row select,.row button{margin-top:0}

  /* form kembali: baris per barang */
  .retur-row{border:1px solid var(--bd);border-radius:10px;padding:10px;margin-top:8px;background:#fcfcfd}
  .retur-nama{display:flex;justify-content:space-between;gap:8px;font-size:15px}

  /* kartu stok: 4 angka kondisi barang */
  .stok-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-top:10px}
  .stok-box{background:#f8fafc;border:1px solid var(--bd);border-radius:10px;padding:10px 6px;text-align:center}
  .stok-label{display:block;font-size:11px;color:var(--mut);margin-bottom:2px;line-height:1.2}
  .stok-num{font-size:20px}
  .stok-box.baik .stok-num{color:var(--green)}
  .stok-box.ringan .stok-num{color:var(--amber)}
  .stok-box.berat .stok-num{color:var(--red)}

  /* ================= Animasi ringan ================= */
  @keyframes gudangFadeUp{from{opacity:0;transform:translateY(10px)}to{opacity:1;transform:none}}
  @keyframes gudangPop{from{opacity:0;transform:scale(.96)}to{opacity:1;transform:none}}

  /* kartu & item masuk dengan fade halus */
  .card{animation:gudangFadeUp .32s cubic-bezier(.22,1,.36,1) both}
  .item{animation:gudangFadeUp .3s cubic-bezier(.22,1,.36,1) both}
  .item:nth-child(1){animation-delay:.02s}
  .item:nth-child(2){animation-delay:.06s}
  .item:nth-child(3){animation-delay:.10s}
  .item:nth-child(4){animation-delay:.14s}
  .item:nth-child(n+5){animation-delay:.18s}

  /* tombol: hover naik, ditekan menyusut */
  button,.btn{transition:transform .12s ease, box-shadow .18s ease, background-color .18s ease, border-color .18s ease}
  button:active,.btn:active{transform:scale(.97)}
  button:focus-visible,.btn:focus-visible,input:focus-visible,select:focus-visible{
    outline:2px solid var(--acc);outline-offset:2px;
  }

  /* nav */
  nav a{transition:background-color .16s ease, color .16s ease, transform .12s ease}
  nav a:active{transform:scale(.97)}

  /* item list */
  .item{transition:box-shadow .18s ease, border-color .18s ease}

  /* SEMUA :hover di dalam sini — kalau tidak, hover "nyangkut" setelah tap di HP */
  @media (hover: hover) and (pointer: fine){
    button:hover,.btn:hover{filter:brightness(1.03)}
    nav a:hover{background:#eef2f7}
    .item:hover{border-color:#d3d9e2;box-shadow:0 2px 10px rgba(17,24,39,.06)}
  }

  /* badge & angka hilang muncul */
  .badge,.hi{animation:gudangPop .2s ease both}

  /* hormati pengaturan reduce-motion */
  @media (prefers-reduced-motion: reduce){
    *,*::before,*::after{
      animation-duration:.001ms !important;
      animation-iteration-count:1 !important;
      transition-duration:.001ms !important;
    }
  }
</style>
</head>
<body>
<header>
  <span class="title">Gudang</span>
  @auth
    <div style="margin-left:auto;display:flex;align-items:center;gap:10px">
      <span style="font-size:13px;color:var(--mut)">{{ auth()->user()->name }}</span>
      <form method="POST" action="/logout" style="margin:0">
        @csrf
        <button type="submit" class="sec" style="min-height:36px;margin:0;width:auto;padding:8px 12px;font-size:13px">Keluar</button>
      </form>
    </div>
  @endauth
</header>
<div class="wrap">
  @if(session('ok'))<div class="msg ok">{{ session('ok') }}</div>@endif
  @if(session('err'))<div class="msg err">{{ session('err') }}</div>@endif
  @if($errors->any())<div class="msg err">{{ $errors->first() }}</div>@endif

  @auth
    @if(auth()->user()->role === 'admin')
      {{-- Baris 1: menu utama --}}
      <nav>
        <a href="/">Beranda</a>
        <a href="/acara">Acara</a>
        <a href="/proses">Proses</a>
        <a href="/riwayat">Kembali</a>
        <a href="/restock">Stok</a>
      </nav>

      {{-- Baris 2: alat admin --}}
      <nav style="grid-template-columns:repeat(4,1fr);margin:0 0 14px">
        <a href="/kategori" style="min-height:42px;font-size:12px">Kategori</a>
        <a href="/log" style="min-height:42px;font-size:12px">Log</a>
        <a href="/qr" style="min-height:42px;font-size:12px">QR</a>
        <a href="/users" style="min-height:42px;font-size:12px">Pengguna</a>
      </nav>
    @else
      {{-- Pekerja: hanya scan kamera & detail hasil scan --}}
      <nav style="grid-template-columns:1fr">
        <a href="/scan">Scan QR Barang</a>
      </nav>
    @endif
  @endauth

  @yield('content')
</div>
</body>
</html>