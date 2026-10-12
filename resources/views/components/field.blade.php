@props(['name', 'label', 'type' => 'text', 'value' => null, 'help' => null])
<div class="field">
<label for="{{ $name }}">{{ $label }}</label>
<input id="{{ $name }}" name="{{ $name }}" type="{{ $type }}" @if($type !== 'password' && $type !== 'file') value="{{ old($name, $value) }}" @endif {{ $attributes->class(['input', 'invalid' => $errors->has($name)]) }} @if($help || $errors->has($name)) aria-describedby="{{ $name }}-help" @endif @if($errors->has($name)) aria-invalid="true" @endif>
@if($errors->has($name))<small class="field-error" id="{{ $name }}-help">{{ $errors->first($name) }}</small>
@elseif($help)<small id="{{ $name }}-help">{{ $help }}</small>@endif
</div>
