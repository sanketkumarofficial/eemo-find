<?php

namespace Tests\Feature;

use App\Models\Admin;
use App\Models\AdminActivityLog;
use App\Services\ActivityLogger;
use App\Services\BrandingService;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminPanelTest extends TestCase
{
    use RefreshDatabase;

    public function test_setup_command_creates_super_admin_with_hashed_password(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Full name', 'Setup Admin')
            ->expectsQuestion('Email address', 'SETUP@EXAMPLE.COM')
            ->expectsQuestion('Password (12+ characters, uppercase, lowercase, number, symbol)', 'SetupPassword!123')
            ->expectsQuestion('Confirm password', 'SetupPassword!123')
            ->expectsOutput('Super Admin created.')
            ->assertExitCode(0);
        $admin = Admin::where('email', 'setup@example.com')->firstOrFail();
        $this->assertTrue($admin->is_super_admin);
        $this->assertTrue(Hash::check('SetupPassword!123', $admin->password));
    }

    public function test_setup_command_rejects_weak_password_without_creating_account(): void
    {
        $this->artisan('admin:create')
            ->expectsQuestion('Full name', 'Setup Admin')
            ->expectsQuestion('Email address', 'setup@example.com')
            ->expectsQuestion('Password (12+ characters, uppercase, lowercase, number, symbol)', 'weak')
            ->expectsQuestion('Confirm password', 'weak')
            ->assertExitCode(1);
        $this->assertDatabaseCount('admins', 0);
    }

    public function test_admin_only_schema_and_no_signup(): void
    {
        $this->assertFalse(Schema::hasTable('users'));
        foreach (['/register', '/admin/register', '/login', '/dashboard'] as $path) {
            $this->get($path)->assertNotFound();
        }
    }

    public function test_all_private_pages_redirect_guests(): void
    {
        foreach (['dashboard', 'profile', 'security/activity-logs', 'security/activity-logs/1', 'security/active-sessions'] as $path) {
            $this->get('/admin/'.$path)->assertRedirect(route('admin.login'));
        }
    }

    public function test_all_mutations_require_authentication(): void
    {
        foreach (['patch' => ['profile'], 'put' => ['profile/photo', 'profile/logo', 'profile/password'], 'delete' => ['profile/photo', 'profile/logo', 'security/active-sessions'], 'post' => ['logout']] as $method => $paths) {
            foreach ($paths as $path) {
                $this->$method('/admin/'.$path)->assertRedirect(route('admin.login'));
            }
        }
    }

    public function test_login_success_regenerates_session_and_logs_event(): void
    {
        $admin = Admin::factory()->create();
        $this->get('/admin/login');
        $before = session()->getId();
        $this->post('/admin/login', ['email' => strtoupper($admin->email), 'password' => 'TestPassword!123'])->assertRedirect(route('admin.dashboard'));
        $this->assertAuthenticatedAs($admin, 'admin');
        $this->assertNotSame($before, session()->getId());
        $this->assertDatabaseHas('admin_activity_logs', ['admin_id' => $admin->id, 'action' => 'login.success']);
        $this->get('/admin/login')->assertRedirect(route('admin.dashboard'));
    }

    public function test_login_failure_is_generic_and_logged_without_credentials(): void
    {
        $admin = Admin::factory()->create();
        $this->post('/admin/login', ['email' => $admin->email, 'password' => 'secret-wrong-password'])->assertSessionHasErrors('email');
        $this->assertGuest('admin');
        $log = AdminActivityLog::firstOrFail();
        $this->assertSame('login.failed', $log->action);
        $this->assertNull($log->admin_id);
        $this->assertStringNotContainsString('secret-wrong-password', $log->toJson());
        $this->assertStringNotContainsString($admin->email, $log->toJson());
    }

    public function test_login_limiter_blocks_after_five_failures(): void
    {
        $admin = Admin::factory()->create();
        for ($i = 0; $i < 5; $i++) {
            $this->post('/admin/login', ['email' => $admin->email, 'password' => 'wrong'])->assertSessionHasErrors('email');
        }
        $response = $this->post('/admin/login', ['email' => $admin->email, 'password' => 'TestPassword!123']);
        $response->assertSessionHasErrors('email');
        $this->assertStringContainsString('Too many attempts', session('errors')->first('email'));
        $this->assertGuest('admin');
    }

    public function test_login_ip_limiter_cannot_be_bypassed_by_changing_email(): void
    {
        for ($i = 0; $i < 20; $i++) {
            $this->post('/admin/login', ['email' => 'missing'.$i.'@example.com', 'password' => 'wrong']);
        }
        $this->post('/admin/login', ['email' => 'missing21@example.com', 'password' => 'wrong'])->assertStatus(429);
    }

    public function test_private_pages_render_with_exact_menu_and_real_empty_states(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin, 'admin');
        $this->get('/admin/dashboard')->assertOk()->assertSee('A clean slate')->assertSee($admin->name)->assertSee('data-clock', false);
        foreach (['profile', 'security/activity-logs', 'security/active-sessions'] as $path) {
            $this->get('/admin/'.$path)->assertOk()->assertSee('Eemo')->assertSee('My Profile')->assertSee('Activity Log')->assertSee('Active Sessions')->assertDontSee('User management');
        }
    }

    public function test_profile_update_normalizes_email_and_excludes_privilege_input(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin')->patch('/admin/profile', ['name' => 'Updated Admin', 'email' => 'UPDATED@EXAMPLE.COM', 'is_super_admin' => true])->assertSessionHasNoErrors();
        $admin->refresh();
        $this->assertSame('updated@example.com', $admin->email);
        $this->assertSame('Updated Admin', $admin->name);
        $this->assertFalse($admin->is_super_admin);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'profile.updated', 'admin_id' => $admin->id]);
    }

    public function test_profile_validation_rejects_missing_name_invalid_and_duplicate_email(): void
    {
        $admin = Admin::factory()->create();
        $other = Admin::factory()->create();
        $this->actingAs($admin, 'admin')->patch('/admin/profile', ['name' => '', 'email' => 'invalid'])->assertSessionHasErrors(['name', 'email']);
        $this->patch('/admin/profile', ['name' => $admin->name, 'email' => $other->email])->assertSessionHasErrors('email');
        $this->patch('/admin/profile', ['name' => $admin->name, 'email' => $admin->email])->assertSessionHasNoErrors();
    }

    public function test_email_change_invalidates_previously_issued_recovery_link(): void
    {
        $admin = Admin::factory()->create();
        $previousEmail = $admin->email;
        Password::broker('admins')->createToken($admin);
        $this->assertDatabaseHas('admin_password_reset_tokens', ['email' => $previousEmail]);
        $this->actingAs($admin, 'admin')->patch('/admin/profile', ['name' => $admin->name, 'email' => 'changed@example.com'])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('admin_password_reset_tokens', ['email' => $previousEmail]);
    }

    public function test_expired_recovery_link_does_not_change_password(): void
    {
        $admin = Admin::factory()->create();
        $token = Password::broker('admins')->createToken($admin);
        DB::table('admin_password_reset_tokens')->where('email', $admin->email)->update(['created_at' => now()->subMinutes(61)]);
        $this->post('/admin/reset-password', ['email' => $admin->email, 'token' => $token, 'password' => 'NewPassword!123', 'password_confirmation' => 'NewPassword!123'])->assertSessionHasErrors('email');
        $this->assertTrue(Hash::check('TestPassword!123', $admin->fresh()->password));
    }

    public function test_authenticated_mutations_require_csrf_tokens(): void
    {
        $this->actingAs(Admin::factory()->create(['is_super_admin' => true]), 'admin');
        $this->app['env'] = 'local';
        foreach (['patch' => ['profile'], 'put' => ['profile/photo', 'profile/logo', 'profile/password'], 'delete' => ['profile/photo', 'profile/logo', 'security/active-sessions'], 'post' => ['logout']] as $method => $paths) {
            foreach ($paths as $path) {
                $this->$method('/admin/'.$path)->assertStatus(419);
            }
        }
    }

    public function test_photo_upload_replace_remove_and_audit(): void
    {
        Storage::fake('public');
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin')->put('/admin/profile/photo', ['photo' => UploadedFile::fake()->image('photo.jpg', 100, 100)])->assertSessionHasNoErrors();
        $old = $admin->fresh()->photo_path;
        Storage::disk('public')->assertExists($old);
        $this->assertStringEndsWith('.png', $old);
        $this->put('/admin/profile/photo', ['photo' => UploadedFile::fake()->image('replacement.png', 120, 120)])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($old);
        $new = $admin->fresh()->photo_path;
        $this->delete('/admin/profile/photo')->assertSessionHasNoErrors();
        $this->assertNull($admin->fresh()->photo_path);
        Storage::disk('public')->assertMissing($new);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'photo.removed']);
    }

    public function test_upload_validation_rejects_non_images_oversize_small_and_large_dimensions(): void
    {
        Storage::fake('public');
        $this->actingAs(Admin::factory()->create(['is_super_admin' => true]), 'admin');
        foreach ([
            UploadedFile::fake()->create('payload.svg', 10, 'image/svg+xml'),
            UploadedFile::fake()->image('oversize.png', 100, 100)->size(3000),
            UploadedFile::fake()->image('tiny.png', 10, 10),
            UploadedFile::fake()->image('wide.png', 2001, 40),
        ] as $file) {
            $this->put('/admin/profile/photo', ['photo' => $file])->assertSessionHasErrors('photo');
        }
        $this->put('/admin/profile/logo', ['logo' => UploadedFile::fake()->image('tiny.png', 10, 10)])->assertSessionHasErrors('logo');
        $this->assertCount(0, Storage::disk('public')->allFiles());
    }

    public function test_super_admin_can_upload_replace_remove_shared_logo(): void
    {
        Storage::fake('public');
        $this->actingAs(Admin::factory()->create(['is_super_admin' => true]), 'admin');
        $this->put('/admin/profile/logo', ['logo' => UploadedFile::fake()->image('logo.png', 128, 64)])->assertSessionHasNoErrors();
        $old = app(BrandingService::class)->path();
        Storage::disk('public')->assertExists($old);
        $this->get('/admin/dashboard')->assertSee(Storage::disk('public')->url($old));
        $this->put('/admin/profile/logo', ['logo' => UploadedFile::fake()->image('logo2.png', 128, 64)])->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($old);
        $new = app(BrandingService::class)->path();
        $this->delete('/admin/profile/logo')->assertSessionHasNoErrors();
        Storage::disk('public')->assertMissing($new);
        $this->assertNull(app(BrandingService::class)->path());
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'logo.updated']);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'logo.removed']);
    }

    public function test_regular_admin_cannot_change_branding(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin');
        $this->put('/admin/profile/logo')->assertForbidden();
        $this->delete('/admin/profile/logo')->assertForbidden();
        $this->get('/admin/profile')->assertSee('Only a Super Admin');
    }

    public function test_password_change_requires_current_password_confirmation_and_strength(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin')->put('/admin/profile/password', ['current_password' => 'wrong', 'password' => 'NewPassword!123', 'password_confirmation' => 'NewPassword!123'])->assertSessionHasErrors('current_password');
        $this->put('/admin/profile/password', ['current_password' => 'TestPassword!123', 'password' => 'weak', 'password_confirmation' => 'different'])->assertSessionHasErrors('password');
        $this->assertTrue(Hash::check('TestPassword!123', $admin->fresh()->password));
        $this->assertNull(session()->getOldInput('current_password'));
    }

    public function test_password_change_hashes_and_removes_other_database_sessions(): void
    {
        config(['session.driver' => 'database']);
        $admin = Admin::factory()->create();
        $this->sessionRow($admin, 'other-session');
        $this->actingAs($admin, 'admin')->put('/admin/profile/password', ['current_password' => 'TestPassword!123', 'password' => 'NewPassword!123', 'password_confirmation' => 'NewPassword!123'])->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('NewPassword!123', $admin->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'other-session']);
        $this->get('/admin/dashboard')->assertOk();
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'password.changed']);
        $this->assertStringNotContainsString('NewPassword!123', AdminActivityLog::first()->toJson());
    }

    public function test_stale_password_hash_is_rejected_on_next_request(): void
    {
        $admin = Admin::factory()->create();
        $oldHash = $admin->password;
        $admin->update(['password' => 'ChangedPassword!123']);
        $this->actingAs($admin->fresh(), 'admin')->withSession(['password_hash_admin' => $oldHash])->get('/admin/dashboard')->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
    }

    public function test_audit_metadata_is_allowlisted_and_detail_is_read_only(): void
    {
        $admin = Admin::factory()->create();
        $log = app(ActivityLogger::class)->log('profile.updated', $admin, [
            'password' => 'do-not-record', 'token' => 'secret-token', 'session_id' => 'secret-session',
            'changed_fields' => ['name', 'email', 'password', 'is_super_admin'],
        ]);
        $this->assertSame(['changed_fields' => ['name', 'email']], $log->metadata);
        $this->assertStringNotContainsString('do-not-record', $log->toJson());
        $this->actingAs($admin, 'admin')->get('/admin/security/activity-logs/'.$log->id)->assertOk()->assertSee('Updated profile information');
        $this->delete('/admin/security/activity-logs/'.$log->id)->assertStatus(405);
        $this->patch('/admin/security/activity-logs/'.$log->id)->assertStatus(405);
    }

    public function test_regular_admin_cannot_read_other_admin_audit_records_or_bypass_search_scope(): void
    {
        $admin = Admin::factory()->create();
        $other = Admin::factory()->create();
        $log = app(ActivityLogger::class)->log('password.changed', $other);
        $this->actingAs($admin, 'admin')->get('/admin/security/activity-logs/'.$log->id)->assertForbidden();
        $this->get('/admin/security/activity-logs?search=password')->assertOk()->assertDontSee('password.changed');
        $this->get('/admin/security/activity-logs?admin_id='.$other->id)->assertOk()->assertDontSee($other->name);
    }

    public function test_super_admin_audit_filters_pagination_and_escaped_output(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true, 'name' => '<script>alert(1)</script>']);
        for ($i = 0; $i < 18; $i++) {
            app(ActivityLogger::class)->log('login.success', $admin);
        }
        $this->actingAs($admin, 'admin')->get('/admin/security/activity-logs?search=login&admin_id='.$admin->id.'&from='.now()->format('Y-m-d').'&to='.now()->format('Y-m-d'))
            ->assertOk()->assertSee('18 events')->assertSee('Page 1 of 2')->assertSee('&lt;script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
        $this->get('/admin/security/activity-logs?from=invalid')->assertSessionHasErrors('from');
    }

    public function test_date_filters_allow_either_bound_independently_and_reject_reversed_range(): void
    {
        $admin = Admin::factory()->create(['is_super_admin' => true]);
        $this->actingAs($admin, 'admin');
        $this->get('/admin/security/activity-logs?to='.now()->format('Y-m-d'))->assertOk()->assertSessionHasNoErrors();
        $this->get('/admin/security/activity-logs?from='.now()->format('Y-m-d'))->assertOk()->assertSessionHasNoErrors();
        $this->get('/admin/security/activity-logs?from=2026-10-12&to=2026-10-01')->assertSessionHasErrors('to');
    }

    public function test_sessions_are_scoped_and_ids_are_not_rendered(): void
    {
        config(['session.driver' => 'database']);
        $admin = Admin::factory()->create();
        $other = Admin::factory()->create();
        $this->sessionRow($admin, 'raw-session-secret', 'Browser A');
        $this->sessionRow($other, 'other-secret', 'Other admin browser');
        $this->sessionRow($admin, 'expired-secret', 'Expired browser', now()->subHours(3)->timestamp);
        $this->actingAs($admin, 'admin')->get('/admin/security/active-sessions')->assertOk()->assertSee('Browser A')
            ->assertDontSee('raw-session-secret')->assertDontSee('Other admin browser')->assertDontSee('Expired browser');
    }

    public function test_revoke_other_session_is_server_side_and_owner_scoped(): void
    {
        config(['session.driver' => 'database']);
        $admin = Admin::factory()->create();
        $other = Admin::factory()->create();
        $this->sessionRow($admin, 'own-device');
        $this->sessionRow($other, 'other-device');
        $this->actingAs($admin, 'admin')->delete('/admin/security/active-sessions', ['reference' => Crypt::encryptString('other-device')])->assertNotFound();
        $this->assertDatabaseHas('sessions', ['id' => 'other-device']);
        $this->delete('/admin/security/active-sessions', ['reference' => 'tampered'])->assertNotFound();
        $this->delete('/admin/security/active-sessions', ['reference' => Crypt::encryptString('own-device')])->assertSessionHasNoErrors();
        $this->assertDatabaseMissing('sessions', ['id' => 'own-device']);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'session.revoked']);
    }

    public function test_revoke_current_session_logs_out_and_rotates_session(): void
    {
        config(['session.driver' => 'database']);
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin')->get('/admin/dashboard');
        $id = session()->getId();
        session()->save();
        $this->withCookie(config('session.cookie'), $id)->delete('/admin/security/active-sessions', ['reference' => Crypt::encryptString($id)])->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
        $this->assertDatabaseMissing('sessions', ['id' => $id]);
        $this->assertNotSame($id, session()->getId());
    }

    public function test_unsupported_session_driver_has_clear_limitation_and_blocks_revocation(): void
    {
        $this->actingAs(Admin::factory()->create(), 'admin')->get('/admin/security/active-sessions')->assertOk()->assertSee('Session listing is unavailable')->assertSee('SESSION_DRIVER=database');
        $this->delete('/admin/security/active-sessions', ['reference' => Crypt::encryptString('unknown')])->assertStatus(409);
    }

    public function test_logout_invalidates_session_and_csrf_token(): void
    {
        $admin = Admin::factory()->create();
        $this->actingAs($admin, 'admin')->get('/admin/dashboard');
        $id = session()->getId();
        $token = session()->token();
        $this->post('/admin/logout')->assertRedirect(route('admin.login'));
        $this->assertGuest('admin');
        $this->assertNotSame($id, session()->getId());
        $this->assertNotSame($token, session()->token());
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'logout']);
        $this->get('/admin/dashboard')->assertRedirect(route('admin.login'));
    }

    public function test_password_reset_sends_admin_notification_with_configured_host_and_generic_response(): void
    {
        Notification::fake();
        $admin = Admin::factory()->create();
        config(['app.url' => 'https://admin.example.com']);
        $known = $this->post('/admin/forgot-password', ['email' => $admin->email]);
        $known->assertSessionHas('status', 'If an admin account matches this email, a reset link has been sent.');
        Notification::assertSentTo($admin, ResetPassword::class, function ($notification) use ($admin) {
            $this->assertStringStartsWith('https://admin.example.com/admin/reset-password/', $notification->toMail($admin)->actionUrl);

            return true;
        });
        $this->post('/admin/forgot-password', ['email' => 'unknown@example.com'])->assertSessionHas('status', session('status'));
        $this->assertDatabaseHas('admin_password_reset_tokens', ['email' => $admin->email]);
    }

    public function test_password_reset_rejects_invalid_token_and_accepts_valid_once(): void
    {
        config(['session.driver' => 'database']);
        $admin = Admin::factory()->create();
        $this->sessionRow($admin, 'old-device');
        $data = ['email' => $admin->email, 'token' => 'invalid', 'password' => 'ResetPassword!123', 'password_confirmation' => 'ResetPassword!123'];
        $this->post('/admin/reset-password', $data)->assertSessionHasErrors('email');
        $data['token'] = Password::broker('admins')->createToken($admin);
        $this->get('/admin/reset-password/'.$data['token'].'?email='.$admin->email)->assertOk();
        $this->post('/admin/reset-password', $data)->assertRedirect(route('admin.login'))->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('ResetPassword!123', $admin->fresh()->password));
        $this->assertDatabaseMissing('sessions', ['id' => 'old-device']);
        $this->assertDatabaseHas('admin_activity_logs', ['action' => 'password.reset']);
        $this->post('/admin/reset-password', $data)->assertSessionHasErrors('email');
    }

    public function test_reset_endpoints_are_rate_limited(): void
    {
        for ($i = 0; $i < 3; $i++) {
            $this->post('/admin/forgot-password', ['email' => 'unknown@example.com']);
        }
        $this->post('/admin/forgot-password', ['email' => 'unknown@example.com'])->assertStatus(429);
    }

    public function test_csrf_protects_state_changing_requests_and_headers_are_present(): void
    {
        // Laravel normally skips CSRF in tests. Switch environment for this request.
        $this->app['env'] = 'local';
        $this->post('/admin/login', ['email' => 'unknown@example.com', 'password' => 'wrong'])->assertStatus(419);
        $this->get('/admin/login')->assertOk()->assertHeader('X-Frame-Options', 'DENY')->assertHeader('X-Content-Type-Options', 'nosniff')->assertHeader('Cache-Control', 'no-store, private');
    }

    private function sessionRow(Admin $admin, string $id, string $agent = 'Test Browser', ?int $last = null): void
    {
        DB::table('sessions')->updateOrInsert(['id' => $id], [
            'user_id' => $admin->id, 'ip_address' => '127.0.0.1', 'user_agent' => $agent,
            'payload' => base64_encode(serialize([])), 'last_activity' => $last ?? now()->timestamp,
        ]);
    }
}
