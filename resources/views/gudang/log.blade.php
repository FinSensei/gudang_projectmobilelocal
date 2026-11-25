@extends('gudang.layout')

@section('content')
<div class="card">
  <div class="card-head">
    <div>
      <h2>Log aktivitas</h2>
      <p class="hint">Riwayat semua perubahan data — siapa, kapan, apa.</p>
    </div>
    <span class="badge selesai">{{ $log->count() }} aktivitas</span>
  </div>

  <p class="hint">Menyimpan {{ $log->count() }} aktivitas terbaru — yang lama otomatis dibuang.</p>

  <div class="row-actions" style="margin:0 0 12px">
    <a href="/log/export" class="btn sm">Unduh Excel</a>
  </div>

  @if($log->isEmpty())
    <p class="muted">Belum ada aktivitas.</p>
  @else
    <div class="log-list">
      @foreach($log as $l)
        <div class="log-row">
          <div class="log-when">
            <b>{{ $l->created_at?->format('d M') }}</b>
            <span>{{ $l->created_at?->format('H:i:s') }}</span>
          </div>
          <div class="log-body">
            <span class="badge {{ $l->aksi === 'hapus_barang' ? 'rusak' : ($l->aksi === 'pengembalian' ? 'aktif' : 'selesai') }}">{{ $l->aksi }}</span>
            <span class="log-ket">{{ $l->keterangan }}</span>
          </div>
        </div>
      @endforeach
    </div>
  @endif
</div>

<style>
  .log-list{margin-top:10px}
  .log-row{display:flex;gap:12px;padding:10px 0;border-bottom:1px solid var(--bd);align-items:flex-start}
  .log-row:last-child{border-bottom:0}
  .log-when{min-width:64px;display:flex;flex-direction:column;font-size:13px;color:var(--mut)}
  .log-when b{color:var(--fg);font-size:14px}
  .log-body{flex:1;display:flex;flex-direction:column;gap:4px}
  .log-ket{font-size:15px;color:var(--fg)}
</style>
@endsection