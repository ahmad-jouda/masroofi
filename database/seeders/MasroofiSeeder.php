<?php

namespace Database\Seeders;

use App\Models\Envelope;
use App\Models\SavingGoal;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\UserSetting;
use App\Models\Wishlist;
use Illuminate\Database\Seeder;

class MasroofiSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1. Settings
        UserSetting::updateOrCreate(
            ['id' => 1],
            [
                'salary' => 7500.00,
                'salary_day' => 27,
                'work_hours_per_week' => 40,
                'savings_target_percent' => 20,
                'currency' => 'SAR',
                'theme' => 'dark',
                'sound_enabled' => true,
                'saved_from_impulse' => 280.00,
            ]
        );

        // 2. Envelopes
        $envelopes = [
            ['id' => 1, 'name' => 'سكن وفواتير', 'icon' => '🏠', 'budget' => 2500.00, 'pillar' => 'needs', 'color' => '#3b82f6'],
            ['id' => 2, 'name' => 'بقالة وتموين', 'icon' => '🛒', 'budget' => 1500.00, 'pillar' => 'needs', 'color' => '#10b981'],
            ['id' => 3, 'name' => 'بنزين ومواصلات', 'icon' => '🚗', 'budget' => 600.00, 'pillar' => 'needs', 'color' => '#06b6d4'],
            ['id' => 4, 'name' => 'صحة وصيدلية', 'icon' => '💊', 'budget' => 300.00, 'pillar' => 'needs', 'color' => '#ec4899'],
            ['id' => 5, 'name' => 'مطاعم وتوصيل', 'icon' => '🍔', 'budget' => 700.00, 'pillar' => 'wants', 'color' => '#f59e0b'],
            ['id' => 6, 'name' => 'تسوق وملابس', 'icon' => '🛍️', 'budget' => 500.00, 'pillar' => 'wants', 'color' => '#8b5cf6'],
            ['id' => 7, 'name' => 'ترفيه واشتراكات', 'icon' => '✈️', 'budget' => 400.00, 'pillar' => 'wants', 'color' => '#f97316'],
            ['id' => 8, 'name' => 'تحويش للطوارئ', 'icon' => '🛡️', 'budget' => 1000.00, 'pillar' => 'savings', 'color' => '#10b981'],
        ];

        foreach ($envelopes as $env) {
            Envelope::updateOrCreate(['id' => $env['id']], $env);
        }

        // 3. Saving Goals
        $goals = [
            ['id' => 1, 'name' => 'صندوق الطوارئ (3 شهور)', 'icon' => '🛡️', 'target_amount' => 15000.00, 'current_amount' => 8500.00, 'deadline' => '2026-12-31'],
            ['id' => 2, 'name' => 'صيانة وتأمين السيارة', 'icon' => '🚗', 'target_amount' => 2400.00, 'current_amount' => 1600.00, 'deadline' => '2026-11-30'],
            ['id' => 3, 'name' => 'عطلة وسفرة الصيف', 'icon' => '✈️', 'target_amount' => 6000.00, 'current_amount' => 2200.00, 'deadline' => '2027-06-15'],
        ];

        foreach ($goals as $goal) {
            SavingGoal::updateOrCreate(['id' => $goal['id']], $goal);
        }

        // 4. Subscriptions
        $subs = [
            ['id' => 1, 'name' => 'اشتراك نتفلكس العائلي', 'cost' => 65.00, 'period' => 'monthly'],
            ['id' => 2, 'name' => 'فاتورة إنترنت منزلي فايبر', 'cost' => 287.00, 'period' => 'monthly'],
            ['id' => 3, 'name' => 'اشتراك النادي الرياضي (Gym)', 'cost' => 1200.00, 'period' => 'yearly'],
            ['id' => 4, 'name' => 'مساحة تخزين سحابية Google One', 'cost' => 11.00, 'period' => 'monthly'],
        ];

        foreach ($subs as $sub) {
            Subscription::updateOrCreate(['id' => $sub['id']], $sub);
        }

        // 5. Wishlist
        $wishes = [
            ['id' => 1, 'title' => 'ساعة ذكية رياضية', 'price' => 499.00, 'duration_hours' => 48, 'status' => 'pending'],
            ['id' => 2, 'title' => 'حذاء كلاسيكي إضافي', 'price' => 280.00, 'duration_hours' => 48, 'status' => 'saved'],
        ];

        foreach ($wishes as $wish) {
            Wishlist::updateOrCreate(['id' => $wish['id']], $wish);
        }

        // 6. Transactions
        if (Transaction::count() === 0) {
            Transaction::create(['type' => 'expense', 'amount' => 320.00, 'envelope_id' => 2, 'date' => '2026-10-09', 'payment_method' => 'card', 'note' => 'مقاضي الأسبوع من السوبرماركت']);
            Transaction::create(['type' => 'expense', 'amount' => 140.00, 'envelope_id' => 3, 'date' => '2026-10-08', 'payment_method' => 'card', 'note' => 'تعبئة بنزين للسيارة']);
            Transaction::create(['type' => 'expense', 'amount' => 85.00, 'envelope_id' => 5, 'date' => '2026-10-08', 'payment_method' => 'transfer', 'note' => 'عشاء ومشروبات مع الأصدقاء']);
            Transaction::create(['type' => 'savings', 'amount' => 500.00, 'envelope_id' => 8, 'goal_id' => 1, 'date' => '2026-10-05', 'payment_method' => 'transfer', 'note' => 'إيداع شهري في صندوق الطوارئ']);
            Transaction::create(['type' => 'expense', 'amount' => 120.00, 'envelope_id' => 4, 'date' => '2026-10-03', 'payment_method' => 'card', 'note' => 'فيتامينات ومستلزمات صيدلية']);
            Transaction::create(['type' => 'expense', 'amount' => 450.00, 'envelope_id' => 2, 'date' => '2026-10-01', 'payment_method' => 'card', 'note' => 'لحوم وخضروات طازجة']);
        }
    }
}
