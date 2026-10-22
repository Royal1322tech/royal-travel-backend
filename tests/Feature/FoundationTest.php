<?php

namespace Tests\Feature;

use Tests\TestCase;

class FoundationTest extends TestCase
{
    public function test_api_supports_arabic(): void
    {
        $this->withHeader('X-Locale', 'ar')
            ->getJson('/api/v1/foundation-check')
            ->assertOk()
            ->assertJsonPath('locale', 'ar')
            ->assertJsonPath('direction', 'rtl');
    }

    public function test_api_falls_back_to_english(): void
    {
        $this->withHeader('X-Locale', 'fr')
            ->getJson('/api/v1/foundation-check')
            ->assertOk()
            ->assertJsonPath('locale', 'en');
    }

    public function test_blade_portal_uses_rtl(): void
    {
        $this->get('/admin/foundation-check?lang=ar')
            ->assertOk()
            ->assertSee('dir="rtl"', false);
    }
}
