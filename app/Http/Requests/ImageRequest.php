<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class ImageRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth('admin')->check() && (! $this->routeIs('admin.profile.logo.update') || $this->user('admin')->can('manage-branding'));
    }

    public function rules(): array
    {
        $field = $this->routeIs('admin.profile.logo.update') ? 'logo' : 'photo';

        return [$field => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:2048', 'dimensions:min_width=32,min_height=32,max_width=2000,max_height=2000']];
    }
}
