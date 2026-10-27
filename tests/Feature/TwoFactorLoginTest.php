<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Mailer;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Administrator two-factor login: password, then an emailed code.
 * Runs inside a transaction (nothing is saved) and captures emails instead of sending them.
 */
class TwoFactorLoginTest extends TestCase
{
    use DatabaseTransactions;

    private array $sent = [];

    protected function setUp(): void
    {
        parent::setUp();
        $sent = &$this->sent;
        $this->app->instance(Mailer::class, new class($sent) extends Mailer {
            public function __construct(private array &$sent) {}
            public function send(string $toEmail, string $toName, string $subject, string $view, array $data = []): bool
            {
                $this->sent[] = ['to' => $toEmail, 'subject' => $subject, 'code' => $data['code'] ?? null];
                return true;
            }
        });
        config(['agas.admin_2fa' => true]);
        // On XAMPP the app URL has a /awas-system/public folder the test client can't route through.
        config(['app.url' => 'http://localhost']);
        \Illuminate\Support\Facades\URL::forceRootUrl('http://localhost');
    }

    private function user(string $role, array $extra = []): User
    {
        $user = User::where('role', $role)->where('status', 'active')->firstOrFail();
        $user->forceFill(['password_hash' => Hash::make('Secret-123'), 'failed_login_attempts' => 0, 'locked_at' => null] + $extra)->save();
        return $user;
    }

    private function lastCode(): string
    {
        return (string)end($this->sent)['code'];
    }

    public function test_admin_needs_the_emailed_code(): void
    {
        $admin = $this->user('admin', ['email' => 'admin-2fa-test@example.com']);

        $this->post('/login', ['username' => $admin->username, 'password' => 'Secret-123'])->assertRedirect(route('two-factor'));
        $this->assertGuest();
        $this->assertSame('admin-2fa-test@example.com', end($this->sent)['to']);
        $this->get(route('two-factor'))->assertOk()->assertSee('ad*****');

        $wrong = $this->lastCode() === '000000' ? '111111' : '000000';
        $this->post(route('two-factor.verify'), ['otp' => $wrong])->assertSessionHas('error');
        $this->assertGuest();

        $this->post(route('two-factor.verify'), ['otp' => $this->lastCode()])->assertRedirect(route($admin->dashboardRoute()));
        $this->assertAuthenticatedAs($admin);
    }

    public function test_too_many_wrong_codes_start_over(): void
    {
        $admin = $this->user('admin', ['email' => 'admin-2fa-test@example.com']);
        $this->post('/login', ['username' => $admin->username, 'password' => 'Secret-123']);
        $wrong = $this->lastCode() === '000000' ? '111111' : '000000';

        for ($i = 0; $i < 4; $i++) {
            $this->post(route('two-factor.verify'), ['otp' => $wrong])->assertSessionHas('error');
        }
        $this->post(route('two-factor.verify'), ['otp' => $wrong])->assertRedirect(route('login'));
        // Even the right code no longer works: the sign-in has to start again.
        $this->post(route('two-factor.verify'), ['otp' => $this->lastCode()])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_code_page_needs_the_password_first(): void
    {
        $this->get(route('two-factor'))->assertRedirect(route('login'));
        $this->post(route('two-factor.verify'), ['otp' => '123456'])->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_meter_reader_signs_in_without_a_code(): void
    {
        $reader = $this->user('staff');
        $this->post('/login', ['username' => $reader->username, 'password' => 'Secret-123'])->assertRedirect(route($reader->dashboardRoute()));
        $this->assertAuthenticatedAs($reader);
        $this->assertSame([], $this->sent);
    }
}
