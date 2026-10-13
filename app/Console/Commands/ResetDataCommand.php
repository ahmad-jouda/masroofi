<?php

namespace App\Console\Commands;

use App\Models\Envelope;
use App\Models\SavingGoal;
use App\Models\Subscription;
use App\Models\Transaction;
use App\Models\UserSetting;
use App\Models\Wishlist;
use Illuminate\Console\Command;

class ResetDataCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'masroofi:reset {--keep-envelopes : Keep standard envelope categories with zero budgets}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Reset all dummy and test financial data to zero for real user setup';

    /**
     * Execute the console command.
     */
    public function handle(): int
    {
        $this->warn('جاري مسح جميع العمليات والبيانات التجريبية لتجهيز النظام لاستقبال بياناتك الحقيقية...');

        // 1. Delete all transactions
        Transaction::query()->delete();
        $this->info('✓ تم مسح جميع سجلات العمليات والمعاملات المالية.');

        // 2. Delete all wishlist items
        Wishlist::query()->delete();
        $this->info('✓ تم تفريغ قائمة تأجيل الرغبات (48 ساعة).');

        // 3. Delete all subscriptions
        Subscription::query()->delete();
        $this->info('✓ تم مسح الاشتراكات التجريبية.');

        // 4. Delete saving goals
        SavingGoal::query()->delete();
        $this->info('✓ تم تفريغ أهداف الادخار.');

        // 5. Reset envelope budgets to zero
        Envelope::query()->update(['budget' => 0.00]);
        $this->info('✓ تم تصفير ميزانيات الأظرف المالية مع إبقاء التصنيفات الأساسية جاهزة.');

        // 6. Reset user settings numbers to zero
        $settings = UserSetting::firstOrCreate(['id' => 1]);
        $settings->salary = 0.00;
        $settings->saved_from_impulse = 0.00;
        $settings->save();
        $this->info('✓ تم تصفير الراتب والمؤشرات الحسابية.');

        $this->newLine();
        $this->info('🎉 تم تصفير كافة الأرقام بنجاح! التطبيق جاهز الآن لاستقبال راتبك وبياناتك الحقيقية.');

        return Command::SUCCESS;
    }
}
