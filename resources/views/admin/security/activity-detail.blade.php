@extends('layouts.admin')
@section('title', 'Event Details')
@section('content')
<div class="page-heading"><div><span class="eyebrow">SECURITY / ACTIVITY LOG</span><h1>Event details<span class="heading-dot">.</span></h1><p>A closer look at this account action.</p></div><a class="btn btn-secondary" href="{{ route('admin.security.activity-logs.index') }}">Back to activity log</a></div>
<x-card title="Audit event"><dl class="detail-list">
<div><dt>Event type</dt><dd><span class="event-badge">{{ $log->action }}</span></dd></div>
<div><dt>Description</dt><dd>{{ $log->description }}</dd></div>
<div><dt>Administrator</dt><dd>{{ $log->admin?->name ?? 'Unattributed' }}</dd></div>
<div><dt>IP address</dt><dd class="mono">{{ $log->ip_address ?? 'Unavailable' }}</dd></div>
<div><dt>Recorded at</dt><dd>{{ $log->created_at->format('d M Y, H:i:s') }} {{ config('app.timezone') }}</dd></div>
<div><dt>User agent</dt><dd class="agent-text">{{ $log->user_agent ?: 'Unavailable' }}</dd></div>
<div><dt>Additional details</dt><dd>@if($log->metadata)<pre>{{ json_encode($log->metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES) }}</pre>@else<span class="muted">No additional metadata.</span>@endif</dd></div>
</dl></x-card>
@endsection
