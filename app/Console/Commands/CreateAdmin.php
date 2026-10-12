<?php

namespace App\Console\Commands;

use App\Models\Admin;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rules\Password;

class CreateAdmin extends Command
{
    protected $signature = 'admin:create';

    protected $description = 'Securely create a Super Admin using interactive prompts';

    public function handle(): int
    {
        $data = ['name' => $this->ask('Full name'), 'email' => mb_strtolower(trim((string) $this->ask('Email address'))),
            'password' => $this->secret('Password (12+ characters, uppercase, lowercase, number, symbol)'),
            'password_confirmation' => $this->secret('Confirm password')];
        $validator = Validator::make($data, [
            'name' => ['required', 'string', 'max:120'], 'email' => ['required', 'email', 'unique:admins', 'max:255'],
            'password' => ['required', 'confirmed', Password::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }
        $admin = new Admin(collect($data)->except('password_confirmation')->all());
        $admin->is_super_admin = true;
        $admin->save();
        $this->info('Super Admin created.');

        return self::SUCCESS;
    }
}
