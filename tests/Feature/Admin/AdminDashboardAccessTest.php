<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AdminDashboardAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_cannot_access_admin_dashboard(): void
    {
        $this->get('/admin/dashboard')
            ->assertStatus(401);
    }

    public function test_user_without_admin_permission_is_forbidden(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get('/admin/dashboard')
            ->assertForbidden();
    }

    public function test_admin_with_dashboard_permission_can_access(): void
    {
        $this->markTestIncomplete(
            'Create an admin role and permission fixture matching the actual identity schema.'
        );
    }
}