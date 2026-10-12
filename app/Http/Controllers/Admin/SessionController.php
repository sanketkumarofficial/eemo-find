<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
use App\Services\AdminSessionService;
use Carbon\Carbon;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Crypt;

class SessionController extends Controller
{
    public function index(Request $request, AdminSessionService $service)
    {
        $supported = $service->supported();
        $sessions = $supported ? $service->query($request->user('admin'))
            ->where('last_activity', '>=', now()->subMinutes(config('session.lifetime'))->timestamp)
            ->orderByDesc('last_activity')->get()->map(function ($session) use ($request) {
                return [
                    'reference' => Crypt::encryptString($session->id),
                    'current' => hash_equals($request->session()->getId(), $session->id),
                    'ip' => $session->ip_address, 'agent' => $session->user_agent,
                    'last_activity' => Carbon::createFromTimestamp($session->last_activity),
                ];
            }) : collect();

        return view('admin.security.active-sessions', compact('supported', 'sessions'));
    }

    public function destroy(Request $request, AdminSessionService $service, ActivityLogger $logger)
    {
        $data = $request->validate(['reference' => ['required', 'string', 'max:2048']]);
        abort_unless($service->supported(), 409, 'Database sessions must be enabled.');
        try {
            $id = Crypt::decryptString($data['reference']);
        } catch (DecryptException $e) {
            abort(404);
        }
        $query = $service->query($request->user('admin'))->where('id', $id);
        abort_unless($query->exists(), 404);
        $current = hash_equals($request->session()->getId(), $id);
        $logger->log('session.revoked', $request->user('admin'), ['current_session' => $current]);
        $query->delete();
        if ($current) {
            Auth::guard('admin')->logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('admin.login')->with('status', 'Session revoked. You have been signed out.');
        }

        return back()->with('status', 'Session revoked.');
    }
}
