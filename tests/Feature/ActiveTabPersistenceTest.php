<?php

namespace Tests\Feature;

use App\Models\Envelope;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ActiveTabPersistenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_default_active_tab_is_dashboard(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertStatus(200);
        $response->assertViewHas('activeTab', 'tab-dashboard');
        $response->assertSee('<section class="tab-pane active" id="tab-dashboard">', false);
    }

    public function test_active_tab_can_be_selected_via_query_parameter(): void
    {
        $response = $this->get(route('dashboard', ['tab' => 'tab-debts']));

        $response->assertStatus(200);
        $response->assertViewHas('activeTab', 'tab-debts');
        $response->assertSee('<section class="tab-pane active" id="tab-debts">', false);
    }

    public function test_submitting_form_with_active_tab_preserves_tab_across_redirect(): void
    {
        $envelope = Envelope::create([
            'name' => 'طعام ومصروف',
            'pillar' => 'needs',
            'budget' => 500,
        ]);

        $response = $this->post(route('transactions.store'), [
            'type' => 'expense',
            'amount' => 50,
            'envelope_id' => $envelope->id,
            'date' => now()->format('Y-m-d'),
            'payment_method' => 'cash',
            'active_tab' => 'tab-ledger',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('active_tab', 'tab-ledger');

        // Follow redirect to dashboard
        $followResponse = $this->get(route('dashboard'));
        $followResponse->assertStatus(200);
        $followResponse->assertViewHas('activeTab', 'tab-ledger');
        $followResponse->assertSee('<section class="tab-pane active" id="tab-ledger">', false);
    }

    public function test_debt_form_submission_preserves_debts_tab(): void
    {
        $response = $this->post(route('debts.store'), [
            'name' => 'بقالة الأمانة',
            'type' => 'supermarket',
            'initial_amount' => 120,
            'active_tab' => 'tab-debts',
        ]);

        $response->assertRedirect(route('dashboard'));
        $response->assertSessionHas('active_tab', 'tab-debts');

        $followResponse = $this->get(route('dashboard'));
        $followResponse->assertStatus(200);
        $followResponse->assertViewHas('activeTab', 'tab-debts');
        $followResponse->assertSee('<section class="tab-pane active" id="tab-debts">', false);
    }

    public function test_invalid_active_tab_falls_back_to_dashboard(): void
    {
        $response = $this->get(route('dashboard', ['tab' => 'tab-invalid-hacker']));

        $response->assertStatus(200);
        $response->assertViewHas('activeTab', 'tab-dashboard');
        $response->assertSee('<section class="tab-pane active" id="tab-dashboard">', false);
    }
}
