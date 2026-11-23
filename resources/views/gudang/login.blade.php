<!DOCTYPE html>
<html lang="id">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Masuk — Gudang</title>
<style>
  *{box-sizing:border-box}
  body{margin:0;font-family:system-ui,-apple-system,"Segoe UI",Roboto,sans-serif;background:#f3f4f6;color:#111827;font-size:17px;display:flex;align-items:center;justify-content:center;min-height:100vh;padding:16px}
  .box{background:#fff;border:1px solid #e5e7eb;border-radius:14px;padding:28px;max-width:380px;width:100%}
  h1{font-size:22px;margin:0 0 4px}
  .hint{font-size:14px;color:#4b5563;margin:0 0 18px}
  label{font-size:15px;color:#374151;display:block;margin:12px 0 6px;font-weight:600}
  input{width:100%;padding:12px;border:1px solid #e5e7eb;border-radius:10px;font-size:17px;min-height:50px}
  button{width:100%;background:#1f6feb;color:#fff;border:0;border-radius:12px;padding:15px;font-size:17px;font-weight:600;cursor:pointer;margin-top:18px;min-height:54px}
  .err{background:#fee2e2;color:#cf222e;border-radius:10px;padding:10px 12px;font-size:15px;margin-bottom:12px}
  .remember{display:flex;align-items:center;gap:8px;font-size:15px;color:#4b5563;margin-top:12px}
  .remember input{width:auto;min-height:auto}
</style>
</head>
<body>
<div class="box">
  <h1>Gudang</h1>
  <p class="hint">Masuk untuk mengelola data gudang.</p>

  @if($errors->any())
    <div class="err">{{ $errors->first() }}</div>
  @endif

  <form method="POST" action="/login">
    @csrf
    <label>Email</label>
    <input name="email" type="email" value="{{ old('email') }}" required autofocus>

    <label>Password</label>
    <input name="password" type="password" required>

    <label class="remember">
      <input name="remember" type="checkbox" value="1"> Ingat saya
    </label>

    <button type="submit">Masuk</button>
  </form>
</div>
</body>
</html>