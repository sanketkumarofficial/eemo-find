@props(['admin', 'large' => false])
@if($admin->photo_path)
<img {{ $attributes->class(['avatar', 'avatar-large' => $large]) }} src="{{ Storage::disk('public')->url($admin->photo_path) }}" alt="{{ $admin->name }} profile photo">
@else
<span {{ $attributes->class(['avatar', 'avatar-large' => $large]) }} aria-label="{{ $admin->name }}">{{ $admin->initials }}</span>
@endif
