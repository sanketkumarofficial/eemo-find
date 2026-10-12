@extends('layouts.auth')
@section('title', 'Admin Login')
@section('content')
<div class="auth-heading"><h1>Welcome back</h1><p>Sign in to your Eemo-Find admin workspace.</p></div>
<x-alert />
<form method="POST" action="{{ route('admin.login.store') }}" class="stack">@csrf
<x-field name="email" label="Email address" type="email" placeholder="you@example.com" autocomplete="username" required autofocus />
<x-field name="password" label="Password" type="password" placeholder="Enter your password" autocomplete="current-password" required />
<div class="auth-options"><span class="muted"><x-icon name="lock" /> Secure sign-in</span><a href="{{ route('admin.password.request') }}">Forgot password?</a></div>
<x-button class="btn-full">Sign in to workspace <x-icon name="arrow" /></x-button>
</form>
@endsection
