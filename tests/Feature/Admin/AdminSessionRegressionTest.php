<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Http\Request;
use Tests\TestCase;

class AdminSessionRegressionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
    }

    public function test_guest_can_open_admin_login(): void
    {
        $this->get('/admin/login')->assertOk();
    }

    public function test_authenticated_user_is_redirected_from_admin_login(): void
    {
        $this->actingAs(new User());

        $this->get('/admin/login')
            ->assertRedirect(route('admin.dashboard'));
    }

    public function test_trusted_proxy_preserves_https_scheme(): void
    {
        $this->withServerVariables([
            'REMOTE_ADDR' => '172.16.1.5',
            'HTTP_X_FORWARDED_PROTO' => 'https',
        ])->get('http://staging-api.royalis.tech/admin/dashboard')
            ->assertRedirect('https://staging-api.royalis.tech/admin/login');
    }

    public function test_route_files_do_not_emit_output_before_php(): void
    {
        foreach (['routes/b2b.php', 'routes/api_v1.php'] as $file) {
            $contents = file_get_contents(base_path($file));

            $this->assertStringStartsWith('<?php', $contents);
        }
    }
}
