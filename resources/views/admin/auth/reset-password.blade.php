@extends('layouts.auth')
@section('title', 'Reset Password')
@section('content')
<div class="auth-heading"><h1>A fresh start</h1><p>Choose a strong new password for your admin account.</p></div><x-alert />
<form method="POST" action="{{ route('admin.password.update') }}" class="stack">@csrf
<input type="hidden" name="token" value="{{ $token }}">
<x-field name="email" label="Email address" type="email" :value="$email" autocomplete="username" required />
<x-field name="password" label="New password" type="password" autocomplete="new-password" help="At least 12 characters, with uppercase, lowercase, a number and a symbol." required />
<x-field name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />
<x-button class="btn-full">Reset password <x-icon name="arrow" /></x-button><a class="back-link" href="{{ route('admin.login') }}">Back to sign in</a>
</form>
@endsection
