<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class FilamentPanelAccessTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Igual que en producción: fuera de "local" Filament exige FilamentUser::canAccessPanel().
        $this->app->detectEnvironment(fn () => 'production');
    }

    #[DataProvider('authorizedEmails')]
    public function test_agency_user_can_access_panel(string $email): void
    {
        $user = User::factory()->create(['email' => $email]);

        $this->actingAs($user)->get('/admin')->assertOk();
    }

    public static function authorizedEmails(): array
    {
        return [
            'admin' => ['a.garcia@gurumkt.com.mx'],
            'mayúsculas' => ['SEO@GuruMkt.com.mx'],
        ];
    }

    #[DataProvider('unauthorizedEmails')]
    public function test_unauthorized_email_gets_403(string $email): void
    {
        $user = User::factory()->create(['email' => $email]);

        $this->actingAs($user)->get('/admin')->assertForbidden();
    }

    public static function unauthorizedEmails(): array
    {
        return [
            'otro dominio' => ['intruso@gmail.com'],
            'subdominio falso' => ['x@gurumkt.com.mx.evil.com'],
            'sufijo sin arroba' => ['x@evilgurumkt.com.mx'],
        ];
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/admin/login');
    }
}
