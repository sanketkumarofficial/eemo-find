@extends('layouts.auth')
@section('title', 'Forgot Password')
@section('content')
<div class="auth-heading"><h1>Forgot your password?</h1><p>Enter your admin email and we’ll send you a secure reset link.</p></div><x-alert />
<form method="POST" action="{{ route('admin.password.email') }}" class="stack">@csrf
<x-field name="email" label="Email address" type="email" autocomplete="username" placeholder="you@example.com" required autofocus />
<x-button class="btn-full">Send reset link <x-icon name="arrow" /></x-button><a class="back-link" href="{{ route('admin.login') }}">Back to sign in</a>
</form>
@endsection
