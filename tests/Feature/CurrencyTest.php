<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CurrencyTest extends TestCase
{
    use RefreshDatabase;

    public function test_dashboard_contains_shekel_currency_option(): void
    {
        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('value="ILS"', false);
        $response->assertSee('شيكل (ILS)');
    }

    public function test_user_can_switch_currency_to_shekel(): void
    {
        $response = $this->put(route('settings.update'), [
            'currency' => 'ILS',
        ]);

        $response->assertRedirect(route('dashboard'));

        $this->assertDatabaseHas('user_settings', [
            'currency' => 'ILS',
        ]);

        $dashboardResponse = $this->get('/');
        $dashboardResponse->assertStatus(200);
        $dashboardResponse->assertSee('ILS');
    }

    public function test_currency_rates_can_be_refreshed_via_endpoint(): void
    {
        $response = $this->postJson(route('currency.refresh'));

        $response->assertStatus(200);
        $response->assertJson([
            'success' => true,
        ]);
        $response->assertJsonStructure([
            'rates' => ['USD', 'SAR', 'ILS'],
            'last_updated',
            'currencies',
        ]);
    }

    public function test_artisan_command_updates_currency_rates(): void
    {
        $this->artisan('currency:update-rates')
            ->expectsOutputToContain('تم تحديث أسعار الصرف بنجاح')
            ->assertExitCode(0);
    }
}
