@if (session('status'))
  <p>{{ session('status') }}</p>
@endif

<h1>Dashboard Bexia</h1>
<p>Hola, {{ auth()->user()->name ?? auth()->user()->email }}.</p>

<form action="{{ route('company.switch') }}" method="POST">
  @csrf
  <select name="slug">
    @foreach(\App\Models\Company::all() as $c)
      <option value="{{ $c->slug }}">{{ $c->name }}</option>
    @endforeach
  </select>
  <button type="submit">Cambiar empresa</button>
</form>

{{-- Debug visible del tenant actual (puedes quitarlo luego) --}}
@php
  $teamId = app(\Spatie\Permission\PermissionRegistrar::class)->getPermissionsTeamId();
@endphp
<p>Empresa activa (session): {{ session('company_id') ?? '—' }}</p>
<p>Spatie team id: {{ $teamId ?? '—' }}</p>
