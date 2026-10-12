<?php

namespace App\Services;

use App\Models\Admin;
use Illuminate\Support\Facades\DB;

class AdminSessionService
{
    public function supported(): bool
    {
        return config('session.driver') === 'database';
    }

    public function query(Admin $admin)
    {
        return DB::connection(config('session.connection'))->table(config('session.table', 'sessions'))->where('user_id', $admin->id);
    }

    public function revokeOthers(Admin $admin, ?string $except = null): void
    {
        if ($this->supported()) {
            $query = $this->query($admin);
            if ($except) {
                $query->where('id', '!=', $except);
            }
            $query->delete();
        }
    }
}
