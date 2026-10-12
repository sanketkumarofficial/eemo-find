<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\ChangePasswordRequest;
use App\Http\Requests\ImageRequest;
use App\Http\Requests\ProfileRequest;
use App\Services\ActivityLogger;
use App\Services\AdminSessionService;
use App\Services\ProfileService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

class ProfileController extends Controller
{
    public function index()
    {
        return view('admin.profile');
    }

    public function update(ProfileRequest $request, ProfileService $service)
    {
        $service->update($request->user('admin'), $request->validated());

        return back()->with('status', 'Profile information saved.');
    }

    public function photo(ImageRequest $request, ProfileService $service)
    {
        $service->photo($request->user('admin'), $request->file('photo'));

        return back()->with('status', 'Profile photo updated.');
    }

    public function removePhoto(Request $request, ProfileService $service)
    {
        $service->photo($request->user('admin'), null);

        return back()->with('status', 'Profile photo removed.');
    }

    public function logo(ImageRequest $request, ProfileService $service)
    {
        Gate::authorize('manage-branding');
        $service->logo($request->user('admin'), $request->file('logo'));

        return back()->with('status', 'Application logo updated.');
    }

    public function removeLogo(Request $request, ProfileService $service)
    {
        Gate::authorize('manage-branding');
        $service->logo($request->user('admin'), null);

        return back()->with('status', 'Default application logo restored.');
    }

    public function password(ChangePasswordRequest $request, AdminSessionService $sessions, ActivityLogger $logger)
    {
        $admin = $request->user('admin');
        // logoutOtherDevices rehashes the password; auth.session invalidates stale sessions
        // even on drivers which cannot be enumerated.
        $admin->password = $request->validated('password');
        $admin->setRememberToken(Str::random(60));
        $admin->save();
        Auth::guard('admin')->logoutOtherDevices($request->validated('password'));
        $sessions->revokeOthers($admin, $request->session()->getId());
        $request->session()->put('password_hash_admin', $admin->getAuthPassword());
        $request->session()->regenerate();
        $logger->log('password.changed', $admin);

        return back()->with('status', 'Password changed. Other sessions have been revoked.');
    }
}
