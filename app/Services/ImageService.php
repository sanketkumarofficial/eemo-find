<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ImageService
{
    public function store(UploadedFile $file, string $directory): string
    {
        // Strip metadata and appended content by decoding and encoding a fresh PNG.
        $image = imagecreatefromstring($file->get());
        if ($image === false) {
            throw new \RuntimeException('Unable to decode image.');
        }
        imagealphablending($image, false);
        imagesavealpha($image, true);
        ob_start();
        imagepng($image);
        $bytes = ob_get_clean();
        imagedestroy($image);
        $path = $directory.'/'.Str::uuid().'.png';
        if (! Storage::disk('public')->put($path, $bytes)) {
            throw new \RuntimeException('Unable to store image.');
        }

        return $path;
    }

    public function delete(?string $path): void
    {
        if ($path && preg_match('#^(admin-photos|branding)/[a-f0-9-]+\.png$#', $path)) {
            Storage::disk('public')->delete($path);
        }
    }
}
