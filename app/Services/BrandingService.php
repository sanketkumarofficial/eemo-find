<?php

namespace App\Services;

use App\Models\ApplicationSetting;

class BrandingService
{
    public function path(): ?string
    {
        return ApplicationSetting::where('key', 'logo_path')->value('value');
    }
}
