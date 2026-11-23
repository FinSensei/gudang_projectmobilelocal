@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Scan QR barang</h2>
  <p class="hint">Arahkan kamera ke QR pada rak/barang. Detail stok akan muncul otomatis.</p>

  <video id="cam" autoplay playsinline muted style="width:100%;border-radius:12px;background:#000;max-height:320px"></video>
  <div id="status" class="hint" style="text-align:center;margin-top:10px">Menyiapkan kamera...</div>

  <canvas id="canvas" style="display:none"></canvas>

  <div class="row-actions" style="justify-content:center">
    <button type="button" id="btn-start" class="btn sm" onclick="startCam()">Nyalakan kamera</button>
    <button type="button" id="btn-stop" class="btn sm sec" onclick="stopCam()" style="display:none">Matikan</button>
  </div>

  <div class="card" style="margin:14px 0 0;background:var(--bg)">
    <h2 style="font-size:16px">Atau masukkan kode manual</h2>
    <div style="display:flex;gap:8px;align-items:flex-end">
      <input id="kode" placeholder="mis. QR-GY58J1NU" style="flex:1">
      <button type="button" class="btn sm" onclick="byCode()">Lihat</button>
    </div>
  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/jsqr@1.4.0/dist/jsQR.js"></script>
<script>
const video = document.getElementById('cam');
const canvas = document.getElementById('canvas');
const ctx = canvas.getContext('2d');
const statusEl = document.getElementById('status');
let stream = null, timer = null, lastCode = '';

function setStatus(t){ statusEl.textContent = t; }

function startCam(){
  if(!navigator.mediaDevices || !navigator.mediaDevices.getUserMedia){
    setStatus('Browser tidak mendukung kamera. Gunakan input kode manual di bawah.'); return;
  }
  navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } })
    .then(function(s){
      stream = s;
      video.srcObject = s;
      video.play();
      document.getElementById('btn-start').style.display = 'none';
      document.getElementById('btn-stop').style.display = 'inline-block';
      setStatus('Kamera aktif — arahkan ke QR');
      timer = setInterval(scanFrame, 300);
    })
    .catch(function(e){
      setStatus('Tidak bisa akses kamera (' + e.name + '). Gunakan input kode manual.');
    });
}

function stopCam(){
  if(timer){ clearInterval(timer); timer = null; }
  if(stream){ stream.getTracks().forEach(function(t){ t.stop(); }); stream = null; }
  video.srcObject = null;
  document.getElementById('btn-start').style.display = 'inline-block';
  document.getElementById('btn-stop').style.display = 'none';
  setStatus('Kamera mati.');
}

function scanFrame(){
  if(!stream || video.readyState !== video.HAVE_ENOUGH_DATA) return;
  canvas.width = video.videoWidth;
  canvas.height = video.videoHeight;
  ctx.drawImage(video, 0, 0, canvas.width, canvas.height);
  const img = ctx.getImageData(0, 0, canvas.width, canvas.height);
  const code = jsQR(img.data, img.width, img.height, { inversionAttempts: 'dontInvert' });
  if(code && code.data && code.data !== lastCode){
    lastCode = code.data;
    go(code.data);
  }
}

function byCode(){
  const v = document.getElementById('kode').value.trim();
  if(v) go(v);
}

function go(data){
  // QR berisi URL penuh (mis. http://.../b/KODE) atau kode saja
  let path = data;
  try {
    const u = new URL(data);
    path = u.pathname;            // ambil /b/KODE
  } catch(e) {
    path = '/b/' + data.replace(/^\/?b\//, '');  // kode saja
  }
  setStatus('Membuka ' + path + ' ...');
  window.location.href = path;
}
</script>
@endsection