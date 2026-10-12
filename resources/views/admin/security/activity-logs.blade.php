@extends('layouts.admin')
@section('title', 'Activity Log')
@section('content')
<div class="page-heading"><div><span class="eyebrow">SECURITY / ACCOUNT HISTORY</span><h1>Activity Log<span class="heading-dot">.</span></h1><p>A clear record of security-relevant account actions.</p></div><span class="pill"><x-icon name="lock" /> Read-only audit trail</span></div>
<x-card class="log-card">
<form method="GET" class="filter-form" action="{{ route('admin.security.activity-logs.index') }}">
<x-field name="search" label="Search activity" :value="request('search')" placeholder="Action or description" maxlength="120" />
@if($admins->isNotEmpty())<div class="field"><label for="admin_id">Administrator</label><select class="input" name="admin_id" id="admin_id"><option value="">All administrators</option>@foreach($admins as $admin)<option value="{{ $admin->id }}" @selected((string) request('admin_id') === (string) $admin->id)>{{ $admin->name }}</option>@endforeach</select></div>@endif
<x-field name="from" label="From date" type="date" :value="request('from')" /><x-field name="to" label="To date" type="date" :value="request('to')" />
<div class="filter-actions"><x-button>Filter</x-button><a class="btn btn-secondary" href="{{ route('admin.security.activity-logs.index') }}">Clear</a></div>
</form>
<div class="table-heading"><h2>Account events</h2><span class="muted">{{ $logs->total() }} {{ Str::plural('event', $logs->total()) }}</span></div>
@if($logs->isEmpty())<div class="empty-state"><span class="tile-icon"><x-icon name="activity" /></span><h3>No activity to show</h3><p>Events will appear here as administrators use the workspace.<br>Try clearing your filters if you expected a result.</p></div>
@else<x-table><thead><tr><th scope="col">Event</th><th scope="col">Administrator</th><th scope="col">IP address</th><th scope="col">Date &amp; time</th><th scope="col"><span class="sr-only">Details</span></th></tr></thead><tbody>
@foreach($logs as $log)<tr><td><span class="event-badge {{ $log->action === 'login.failed' ? 'warning' : '' }}">{{ $log->action }}</span><span class="cell-description">{{ $log->description }}</span></td><td>{{ $log->admin?->name ?? 'Unattributed' }}</td><td class="mono">{{ $log->ip_address ?? 'Unavailable' }}</td><td><time datetime="{{ $log->created_at->toIso8601String() }}">{{ $log->created_at->format('d M Y') }}<small>{{ $log->created_at->format('H:i:s') }} {{ config('app.timezone') }}</small></time></td><td><a class="icon-button" href="{{ route('admin.security.activity-logs.show', $log) }}" aria-label="View event {{ $log->id }}"><x-icon name="arrow" /></a></td></tr>@endforeach
</tbody></x-table>{{ $logs->links() }}@endif
</x-card>
<div class="workspace-note"><x-icon name="shield" /><span>Audit records cannot be edited or deleted through this workspace. Passwords and session secrets are never recorded.</span></div>
@endsection
