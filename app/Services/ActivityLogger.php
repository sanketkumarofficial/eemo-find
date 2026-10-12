<?php

namespace App\Services;

use App\Models\Admin;
use App\Models\AdminActivityLog;

class ActivityLogger
{
    private const EVENTS = [
        'login.success' => 'Signed in to the admin panel',
        'login.failed' => 'Unsuccessful admin sign-in attempt',
        'logout' => 'Signed out of the admin panel',
        'profile.updated' => 'Updated profile information',
        'photo.updated' => 'Updated profile photo',
        'photo.removed' => 'Removed profile photo',
        'logo.updated' => 'Updated application branding',
        'logo.removed' => 'Restored default application branding',
        'password.changed' => 'Changed password and revoked other sessions',
        'password.reset' => 'Reset password and revoked active sessions',
        'session.revoked' => 'Revoked an admin session',
        'security.updated' => 'Updated security settings',
    ];

    public function log(string $action, ?Admin $admin = null, array $metadata = []): AdminActivityLog
    {
        if (! isset(self::EVENTS[$action])) {
            throw new \InvalidArgumentException('Unsupported audit event.');
        }
        // Never accept arbitrary request bodies, identifiers or secrets as metadata.
        $safe = [];
        if (isset($metadata['changed_fields'])) {
            $safe['changed_fields'] = array_values(array_intersect($metadata['changed_fields'], ['name', 'email']));
        }
        if (isset($metadata['current_session'])) {
            $safe['current_session'] = (bool) $metadata['current_session'];
        }

        return AdminActivityLog::create([
            'admin_id' => $admin?->id, 'action' => $action, 'description' => self::EVENTS[$action],
            'ip_address' => request()->ip(), 'user_agent' => mb_substr((string) request()->userAgent(), 0, 1000),
            'metadata' => $safe ?: null, 'created_at' => now(),
        ]);
    }
}
