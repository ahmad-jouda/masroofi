<?php

namespace Tests\Feature;

use App\Models\SalarySource;
use App\Models\UserSetting;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SalarySourceTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_create_salary_source_with_custom_day(): void
    {
        $response = $this->post('/salaries', [
            'name' => 'راتب الوظيفة الأساسية',
            'amount' => 1800.00,
            'currency' => 'ILS',
            'payment_method' => 'transfer',
            'day_of_month' => 5,
            'notes' => 'يُقبض أول الشهر عبر التطبيق',
        ]);

        $response->assertRedirect('/');
        $this->assertDatabaseHas('salary_sources', [
            'name' => 'راتب الوظيفة الأساسية',
            'amount' => 1800.00,
            'payment_method' => 'transfer',
            'day_of_month' => 5,
        ]);
    }

    public function test_user_can_update_salary_source(): void
    {
        $source = SalarySource::create([
            'name' => 'راتب الكاش',
            'amount' => 1600.00,
            'currency' => 'ILS',
            'payment_method' => 'cash',
            'day_of_month' => 15,
            'is_active' => true,
        ]);

        $response = $this->put("/salaries/{$source->id}", [
            'name' => 'راتب الكاش المعدل',
            'amount' => 1700.00,
            'currency' => 'ILS',
            'payment_method' => 'cash',
            'day_of_month' => 18,
            'notes' => 'تعديل موعد الاستلام',
        ]);

        $response->assertRedirect('/');
        $this->assertDatabaseHas('salary_sources', [
            'id' => $source->id,
            'name' => 'راتب الكاش المعدل',
            'amount' => 1700.00,
            'day_of_month' => 18,
        ]);
    }

    public function test_user_can_delete_salary_source(): void
    {
        $source = SalarySource::create([
            'name' => 'دخل مؤقت',
            'amount' => 500.00,
            'currency' => 'ILS',
            'payment_method' => 'transfer',
            'day_of_month' => 20,
        ]);

        $response = $this->delete("/salaries/{$source->id}");

        $response->assertRedirect('/');
        $this->assertDatabaseMissing('salary_sources', [
            'id' => $source->id,
        ]);
    }

    public function test_multiple_salaries_sync_total_to_settings(): void
    {
        SalarySource::create([
            'name' => 'راتب 1',
            'amount' => 1800.00,
            'currency' => 'ILS',
            'payment_method' => 'transfer',
            'day_of_month' => 1,
            'is_active' => true,
        ]);

        SalarySource::create([
            'name' => 'راتب 2',
            'amount' => 1600.00,
            'currency' => 'ILS',
            'payment_method' => 'cash',
            'day_of_month' => 15,
            'is_active' => true,
        ]);

        // Post an update or trigger sync
        $this->post('/salaries', [
            'name' => 'عمل إضافي',
            'amount' => 400.00,
            'currency' => 'ILS',
            'payment_method' => 'transfer',
            'day_of_month' => 28,
        ]);

        $setting = UserSetting::first();
        $this->assertEquals(3800.00, (float) $setting->salary);
    }

    public function test_dashboard_displays_salaries_and_no_work_hours(): void
    {
        SalarySource::create([
            'name' => 'راتب التطبيق الذكي',
            'amount' => 1800.00,
            'currency' => 'ILS',
            'payment_method' => 'transfer',
            'day_of_month' => 1,
            'is_active' => true,
        ]);

        $response = $this->get('/');

        $response->assertStatus(200);
        $response->assertSee('راتب التطبيق الذكي');
        $response->assertSee('جدول مواعيد وتدفق الرواتب الشهرية');
        $response->assertDontSee('ساعات العمل أسبوعياً');
    }
}
