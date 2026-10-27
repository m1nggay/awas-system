<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\URL;
use Tests\TestCase;

/** Sign-out after the idle time, and background checks that must not count as activity. */
class SessionTimeoutTest extends TestCase
{
    use DatabaseTransactions;

    private const BG = ['X-AWAS-Background' => '1', 'Accept' => 'application/json'];

    protected function setUp(): void
    {
        parent::setUp();
        config(['app.url' => 'http://localhost', 'agas.idle_timeout_minutes' => 30, 'agas.admin_2fa' => false]);
        URL::forceRootUrl('http://localhost');
    }

    private function signedIn(string $role, int $idleSeconds): User
    {
        $user = User::where('role', $role)->where('status', 'active')->firstOrFail();
        $this->actingAs($user)->withSession(['last_activity' => time() - $idleSeconds]);
        return $user;
    }

    public function test_background_checks_do_not_keep_the_session_alive(): void
    {
        $this->signedIn('admin', 20 * 60);
        $this->withHeaders(self::BG)->get(route('admin.live'))->assertOk();
        $this->withHeaders(self::BG)->get(route('session.status'))->assertOk()
            ->assertJson(fn ($json) => $json->where('remaining', fn ($s) => $s <= 10 * 60 && $s > 9 * 60));
    }

    public function test_after_the_idle_time_everything_signs_out(): void
    {
        $this->signedIn('admin', 31 * 60);
        $this->withHeaders(self::BG)->get(route('admin.live'))->assertStatus(401);
        $this->assertGuest();

        $this->flushHeaders();
        $this->signedIn('resident', 31 * 60);
        $this->get(route('resident.dashboard'))->assertRedirect(route('login', ['timeout' => 1]));
        $this->assertGuest();
    }

    public function test_stay_signed_in_restarts_the_clock(): void
    {
        $this->signedIn('staff', 29 * 60);
        $this->postJson(route('session.keep-alive'))->assertOk()
            ->assertJson(fn ($json) => $json->where('remaining', fn ($s) => $s >= 30 * 60 - 2));
    }

    public function test_signed_in_pages_load_the_timer(): void
    {
        $this->signedIn('resident', 0);
        $this->get(route('resident.dashboard'))->assertOk()->assertSee('AWAS_SESSION', false)->assertSee('session-timeout.js', false);
    }
}
