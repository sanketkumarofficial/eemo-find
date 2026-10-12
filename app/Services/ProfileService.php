<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\ApplicationSetting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Password;

class ProfileService
{
    public function __construct(private ImageService $images, private ActivityLogger $logger) {}

    public function update(Admin $admin, array $data): void
    {
        DB::transaction(function () use ($admin, $data) {
            $admin->fill($data);
            $fields = array_keys($admin->getDirty());
            if ($admin->isDirty('email')) {
                $previousEmail = $admin->getOriginal('email');
                // Invalidate recovery links for both identities during an email change.
                $admin->email = $previousEmail;
                Password::broker('admins')->deleteToken($admin);
                $admin->email = $data['email'];
                Password::broker('admins')->deleteToken($admin);
            }
            $admin->save();
            $this->logger->log('profile.updated', $admin, ['changed_fields' => $fields]);
        });
    }

    public function photo(Admin $admin, ?UploadedFile $file): void
    {
        $path = $file ? $this->images->store($file, 'admin-photos') : null;
        $old = $admin->photo_path;
        try {
            DB::transaction(function () use ($admin, $path) {
                $admin->photo_path = $path;
                $admin->save();
                $this->logger->log($path ? 'photo.updated' : 'photo.removed', $admin);
            });
        } catch (\Throwable $e) {
            $this->images->delete($path);
            throw $e;
        }
        $this->images->delete($old);
    }

    public function logo(Admin $admin, ?UploadedFile $file): void
    {
        $path = $file ? $this->images->store($file, 'branding') : null;
        $old = app(BrandingService::class)->path();
        try {
            DB::transaction(function () use ($admin, $path) {
                ApplicationSetting::updateOrCreate(['key' => 'logo_path'], ['value' => $path]);
                $this->logger->log($path ? 'logo.updated' : 'logo.removed', $admin);
            });
        } catch (\Throwable $e) {
            $this->images->delete($path);
            throw $e;
        }
        $this->images->delete($old);
    }
}
