@extends('layouts.admin')
@section('title', 'Active Sessions')
@section('content')
<div class="page-heading"><div><span class="eyebrow">SECURITY / DEVICE ACCESS</span><h1>Active Sessions<span class="heading-dot">.</span></h1><p>Know where you’re signed in. Stay in control.</p></div><span class="pill"><x-icon name="device" /> Your sessions</span></div>
@if(!$supported)
<x-card title="Session listing is unavailable"><div class="inline-note"><x-icon name="device" /><p>This application is currently using the {{ config('session.driver') }} session driver. Reliable session listing and individual revocation require the database session driver. Ask your operator to set SESSION_DRIVER=database and run the migrations.</p></div><p>You can still sign out securely using the sign-out button. Changing your password invalidates other sessions on their next request.</p></x-card>
@else
<div class="inline-note session-note"><x-icon name="shield" /><p>Only your sessions are shown here. Revoke any session you don’t recognize. Device information is reported by the browser and may be approximate.</p></div>
<x-card class="session-card" title="Signed-in devices" description="Sessions with activity within the configured session lifetime.">
@if($sessions->isEmpty())<div class="empty-state"><span class="tile-icon"><x-icon name="device" /></span><h3>No stored sessions yet</h3><p>Your current session is saved after this request. Refresh this page to see it.</p><a class="btn btn-secondary" href="{{ route('admin.security.active-sessions.index') }}">Refresh sessions</a></div>
@else
@foreach($sessions as $session)
<div class="device-row"><span class="tile-icon {{ $session['current'] ? '' : 'neutral' }}"><x-icon name="device" /></span><div class="device-info"><div class="device-heading"><h3>{{ Str::contains($session['agent'] ?? '', ['Mobile', 'Android', 'iPhone']) ? 'Mobile device' : 'Desktop / browser' }}</h3>@if($session['current'])<span class="event-badge">This device</span>@endif</div><p class="agent-text">{{ $session['agent'] ?: 'Device information unavailable' }}</p><small>{{ $session['ip'] ?? 'IP unavailable' }} <span>·</span> Last active {{ $session['last_activity']->diffForHumans() }}</small></div><form method="POST" action="{{ route('admin.security.active-sessions.destroy') }}" data-confirm="{{ $session['current'] ? 'Revoke this session and sign out of the workspace?' : 'Revoke this device’s access to your account?' }}">@csrf @method('DELETE')<input type="hidden" name="reference" value="{{ $session['reference'] }}"><x-button variant="{{ $session['current'] ? 'secondary' : 'danger' }}">{{ $session['current'] ? 'Sign out' : 'Revoke session' }}</x-button></form></div>
@endforeach
@endif
</x-card>
@endif
<div class="workspace-note"><x-icon name="lock" /><span>Session identifiers and authentication tokens are kept private. Revocation invalidates access on the server.</span></div>
@endsection
