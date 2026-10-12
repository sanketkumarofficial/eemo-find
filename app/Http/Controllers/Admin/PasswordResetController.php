<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Admin;
use App\Services\ActivityLogger;
use App\Services\AdminSessionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password as PasswordRule;
use Illuminate\Validation\ValidationException;

class PasswordResetController extends Controller
{
    public function create()
    {
        return view('admin.auth.forgot-password');
    }

    public function email(Request $request)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate(['email' => ['required', 'email', 'max:255']]);
        Password::broker('admins')->sendResetLink($data);

        return back()->with('status', 'If an admin account matches this email, a reset link has been sent.');
    }

    public function edit(Request $request, string $token)
    {
        return view('admin.auth.reset-password', ['token' => $token, 'email' => $request->query('email', '')]);
    }

    public function update(Request $request, AdminSessionService $sessions, ActivityLogger $logger)
    {
        $request->merge(['email' => mb_strtolower(trim((string) $request->input('email')))]);
        $data = $request->validate([
            'token' => ['required', 'string'], 'email' => ['required', 'email'],
            'password' => ['required', 'confirmed', PasswordRule::min(12)->mixedCase()->numbers()->symbols()],
        ]);
        $status = Password::broker('admins')->reset($data, function (Admin $admin, string $password) use ($sessions, $logger) {
            $admin->password = $password;
            $admin->setRememberToken(Str::random(60));
            $admin->save();
            $sessions->revokeOthers($admin);
            $logger->log('password.reset', $admin);
        });
        if ($status !== Password::PASSWORD_RESET) {
            throw ValidationException::withMessages(['email' => 'The reset link is invalid or expired. Request a new link.']);
        }

        return redirect()->route('admin.login')->with('status', 'Password reset. Sign in with your new password.');
    }
}
