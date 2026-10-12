@extends('layouts.admin')
@section('title', 'Dashboard')
@section('content')
<div class="page-heading"><div><span class="eyebrow">YOUR WORKSPACE AT A GLANCE</span><h1>Dashboard<span class="heading-dot">.</span></h1><p>A little clarity. A lot of control.</p></div><div class="date-chip"><x-icon name="clock" /><time data-clock data-timezone="{{ config('app.timezone') }}" datetime="{{ now()->toIso8601String() }}">{{ now()->format('D, d M Y · H:i') }} {{ config('app.timezone') }}</time></div></div>
<div class="dashboard-overview">
<section class="welcome-card">
<div class="welcome-copy"><span class="pill"><span class="status-dot"></span> You’re securely signed in</span><h2>Welcome back, {{ auth('admin')->user()->name }}.</h2><p>This is your space to manage your profile, review your activity,<br class="desktop-break"> and keep your account secure.</p><a class="text-link" href="{{ route('admin.profile') }}">Make yourself at home <x-icon name="arrow" /></a></div>
<div class="welcome-profile"><div class="avatar-orbit"><x-avatar :admin="auth('admin')->user()" :large="true" /></div><strong>{{ auth('admin')->user()->is_super_admin ? 'Super Admin' : 'Administrator' }}</strong><small>YOUR EEMO-FIND ACCOUNT</small></div>
</section>
<a class="quick-card" href="{{ route('admin.profile') }}"><span class="tile-icon"><x-icon name="user" /></span><span class="quick-number">01</span><h3>My Profile</h3><p>Update your personal details, profile photo,<br class="desktop-break"> and account password.</p><span class="quick-link">Manage profile <x-icon name="arrow" /></span></a>
<a class="quick-card" href="{{ route('admin.security.active-sessions.index') }}"><span class="tile-icon teal"><x-icon name="shield" /></span><span class="quick-number">02</span><h3>Security</h3><p>Stay in control of your active sessions<br class="desktop-break"> and review your account activity.</p><span class="quick-link">Review security <x-icon name="arrow" /></span></a>
</div>
<div class="dashboard-lower">
<section class="card activity-card"><div class="section-heading"><div><h2>Recent activity</h2><p>A record of your latest account actions.</p></div><a class="text-link" href="{{ route('admin.security.activity-logs.index') }}">View activity log <x-icon name="arrow" /></a></div>
@if($activities->isEmpty())<div class="empty-state"><span class="tile-icon"><x-icon name="activity" /></span><h3>A clean slate</h3><p>Your recent account activity will appear here.</p></div>
@else<div class="activity-list">@foreach($activities as $activity)<div class="activity-row"><span class="activity-icon"><x-icon :name="str_starts_with($activity->action, 'login') ? 'lock' : 'activity'" /></span><div><strong>{{ $activity->description }}</strong><small>{{ $activity->ip_address ?? 'IP unavailable' }}</small></div><time datetime="{{ $activity->created_at->toIso8601String() }}">{{ $activity->created_at->diffForHumans() }}</time></div>@endforeach</div>@endif
</section>
<x-card class="account-card" title="Your account" description="Your personal admin workspace.">
    <div class="account-identity"><x-avatar :admin="auth('admin')->user()" /><div><strong>{{ auth('admin')->user()->name }}</strong><small>{{ auth('admin')->user()->email }}</small></div></div>
    <dl class="account-details">
        <div><dt>Account role</dt><dd>{{ auth('admin')->user()->is_super_admin ? 'Super Admin' : 'Administrator' }}</dd></div>
        <div><dt>Administrator since</dt><dd>{{ auth('admin')->user()->created_at->format('M Y') }}</dd></div>
        <div><dt>Access</dt><dd><span class="status-dot"></span> Admin workspace</dd></div>
    </dl>
    <a class="btn btn-primary" href="{{ route('admin.security.active-sessions.index') }}"><x-icon name="shield" /> Review your sessions</a>
</x-card>
</div>
<div class="workspace-note"><x-icon name="shield" /><span>Your account. Your control. Keep your password private and review unfamiliar sessions.</span></div>
@endsection
