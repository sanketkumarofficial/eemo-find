@extends('layouts.admin')
@section('title', 'My Profile')
@section('content')
@php($admin = auth('admin')->user())
<div class="page-heading"><div><span class="eyebrow">PERSONAL &amp; ACCOUNT DETAILS</span><h1>My Profile<span class="heading-dot">.</span></h1><p>A workspace that feels like you.</p></div><span class="pill"><x-icon name="shield" /> {{ $admin->is_super_admin ? 'Super Admin' : 'Administrator' }}</span></div>
<div class="profile-banner"><x-avatar :admin="$admin" :large="true" /><div><h2>{{ $admin->name }}</h2><p>{{ $admin->email }}</p><small>Administrator since {{ $admin->created_at->format('M Y') }}</small></div></div>
<div class="profile-grid d-grid">
<x-card title="Profile information" description="The details associated with your admin account.">
<form method="POST" action="{{ route('admin.profile.update') }}" class="stack">@csrf @method('PATCH')
<x-field name="name" label="Full name" :value="$admin->name" autocomplete="name" maxlength="120" required />
<x-field name="email" label="Email address" type="email" :value="$admin->email" autocomplete="email" required />
<div class="form-footer"><span class="muted">Used to sign in and recover your account.</span><x-button>Save changes</x-button></div>
</form>
</x-card>
<x-card title="Profile photo" description="A familiar face throughout your workspace.">
<div class="photo-preview"><x-avatar :admin="$admin" :large="true" /><div><strong>Your profile photo</strong><p>JPG, PNG or WebP. Up to 2 MB.<br>32–2,000 px in each dimension.</p></div></div>
<form method="POST" action="{{ route('admin.profile.photo.update') }}" enctype="multipart/form-data" class="stack">@csrf @method('PUT')
<x-field name="photo" label="Choose profile photo" type="file" accept="image/jpeg,image/png,image/webp" required />
<div class="actions"><x-button><x-icon name="upload" /> Upload photo</x-button></div>
</form>
@if($admin->photo_path)<form method="POST" action="{{ route('admin.profile.photo.delete') }}" class="remove-form" data-confirm="Remove your profile photo and restore your initials avatar?">@csrf @method('DELETE')<x-button variant="ghost">Remove photo</x-button></form>@endif
</x-card>
<x-card title="Change password" description="Keep your account secure with a strong, unique password.">
<form method="POST" action="{{ route('admin.profile.password.update') }}" class="stack">@csrf @method('PUT')
<x-field name="current_password" label="Current password" type="password" autocomplete="current-password" required />
<x-field name="password" label="New password" type="password" autocomplete="new-password" help="12+ characters with uppercase, lowercase, a number and a symbol." required />
<x-field name="password_confirmation" label="Confirm new password" type="password" autocomplete="new-password" required />
<div class="inline-note"><x-icon name="lock" /><p>Changing your password will sign out your other sessions.</p></div>
<div class="actions"><x-button>Update password</x-button></div>
</form>
</x-card>
<x-card title="Application branding" description="A shared identity for the Eemo-Find workspace.">
<div class="branding-preview"><x-brand /></div>
@can('manage-branding')
<form method="POST" action="{{ route('admin.profile.logo.update') }}" enctype="multipart/form-data" class="stack">@csrf @method('PUT')
<x-field name="logo" label="Choose application logo" type="file" accept="image/jpeg,image/png,image/webp" help="JPG, PNG or WebP. Up to 2 MB. 32–2,000 px in each dimension." required />
<div class="actions"><x-button><x-icon name="upload" /> Upload logo</x-button></div>
</form>
@if(app(App\Services\BrandingService::class)->path())<form method="POST" action="{{ route('admin.profile.logo.delete') }}" class="remove-form" data-confirm="Remove the application logo and restore default Eemo-Find branding for all administrators?">@csrf @method('DELETE')<x-button variant="ghost">Restore default logo</x-button></form>@endif
<div class="inline-note"><x-icon name="shield" /><p>Super Admin access. Changes apply to the sidebar and sign-in pages for everyone.</p></div>
@else<div class="inline-note"><x-icon name="lock" /><p>Only a Super Admin can change application branding.</p></div>@endcan
</x-card>
</div>
@endsection
