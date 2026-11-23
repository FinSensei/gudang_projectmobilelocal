@extends('gudang.layout')

@section('content')
<div class="card">
  <h2>Log aktivitas</h2>
  @if($log->isEmpty())
    <p class="muted">Belum ada aktivitas.</p>
  @else
    <ul class="list">
      @foreach($log as $l)
        <li>
          <div><b>{{ $l->aksi }}</b> <span class="muted">· {{ $l->keterangan }}</span></div>
          <span class="muted">{{ $l->created_at?->format('d M H:i') }}</span>
        </li>
      @endforeach
    </ul>
  @endif
</div>
@endsection