<?php

use App\Models\Admin;
use App\Models\AdminActivityLog;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

// Disposable QA workflow; creates and removes its own randomized admin only.
// Run against php artisan serve and the same isolated local database.
require dirname(__DIR__).'/vendor/autoload.php';
$app = require dirname(__DIR__).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
if (! $app->environment('local')) {
    fwrite(STDERR, "Local environments only.\n");
    exit(1);
}

$base = 'http://127.0.0.1:8000';
$password = bin2hex(random_bytes(16)).'Aa!9';
$admin = Admin::create(['name' => 'HTTP Verification', 'email' => 'qa-'.bin2hex(random_bytes(8)).'@example.test', 'password' => $password]);
$cookie = tempnam(sys_get_temp_dir(), 'eemo-qa-');
$curl = curl_init();
curl_setopt_array($curl, [CURLOPT_COOKIEJAR => $cookie, CURLOPT_COOKIEFILE => $cookie, CURLOPT_RETURNTRANSFER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_TIMEOUT => 20, CURLOPT_PROXY => '']);
$assertions = 0;
function check(bool $condition, string $message): void
{
    global $assertions;
    if (! $condition) {
        throw new RuntimeException($message);
    }
    $assertions++;
}
function requestPage(string $path, ?array $data = null): array
{
    global $curl, $base;
    curl_setopt($curl, CURLOPT_URL, $base.$path);
    curl_setopt($curl, CURLOPT_POST, $data !== null);
    if ($data !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($data));
    }
    $body = curl_exec($curl);
    if ($body === false) {
        throw new RuntimeException(curl_error($curl));
    }

    return [curl_getinfo($curl, CURLINFO_RESPONSE_CODE), $body];
}
function csrf(string $body): string
{
    if (! preg_match('/name="_token" value="([^"]+)"/', $body, $matches)) {
        throw new RuntimeException('Missing CSRF token');
    }

    return html_entity_decode($matches[1]);
}
try {
    [$code] = requestPage('/admin/dashboard');
    check($code === 302, 'Guest must redirect');
    [$code, $login] = requestPage('/admin/login');
    check($code === 200, 'Login must render');
    [$code] = requestPage('/admin/login', ['_token' => csrf($login), 'email' => $admin->email, 'password' => $password]);
    check($code === 302, 'Login must redirect');
    [$code, $dashboard] = requestPage('/admin/dashboard');
    check($code === 200 && str_contains($dashboard, 'HTTP Verification'), 'Authenticated dashboard must render');
    foreach (['profile', 'security/activity-logs'] as $path) {
        [$code] = requestPage('/admin/'.$path);
        check($code === 200, 'Admin page must render');
    }
    [$code, $sessions] = requestPage('/admin/security/active-sessions');
    check($code === 200 && str_contains($sessions, 'This device'), 'Current database session must be identified');
    $row = DB::table('sessions')->where('user_id', $admin->id)->first();
    check($row !== null, 'Database session must be associated with admin');
    check(! str_contains($sessions, $row->id), 'Raw session ID must not be rendered');
    check(! str_contains($row->payload, $password), 'Session must not contain plaintext password');
    if (! preg_match('/name="reference" value="([^"]+)"/', $sessions, $matches)) {
        throw new RuntimeException('Missing session reference');
    }
    [$code] = requestPage('/admin/security/active-sessions', ['_token' => csrf($sessions), '_method' => 'DELETE', 'reference' => html_entity_decode($matches[1])]);
    check($code === 302, 'Current revocation must redirect');
    check(! DB::table('sessions')->where('id', $row->id)->exists(), 'Current session must be deleted server-side');
    [$code] = requestPage('/admin/dashboard');
    check($code === 302, 'Revoked session must lose authentication');
    [$code, $login] = requestPage('/admin/login');
    requestPage('/admin/login', ['_token' => csrf($login), 'email' => $admin->email, 'password' => $password]);
    [$code, $dashboard] = requestPage('/admin/dashboard');
    check($code === 200, 'Admin must be able to sign in again');
    [$code] = requestPage('/admin/logout', ['_token' => csrf($dashboard)]);
    check($code === 302, 'Logout must redirect');
    [$code] = requestPage('/admin/dashboard');
    check($code === 302, 'Logged-out session must lose authentication');
    echo 'HTTP smoke passed: '.$assertions." checks.\n";
} finally {
    AdminActivityLog::where('admin_id', $admin->id)->delete();
    $admin->delete();
    curl_close($curl);
    if (is_file($cookie)) {
        unlink($cookie);
    }
}
