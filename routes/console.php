<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// تحديث أسعار الصرف تلقائياً كل ساعة من السوق المالي
Schedule::command('currency:update-rates')->hourly();

// رصد واستقطاع فوائض اليوميات تلقائياً لحصالة سداد الديون عند منتصف الليل وبداية يوم جديد
Schedule::command('debts:harvest-surplus')->dailyAt('00:01');

// رصد واستقطاع فوائض اليوميات تلقائياً لصناديق التحويش عند منتصف الليل وبداية يوم جديد
Schedule::command('goals:harvest-surplus')->dailyAt('00:01');
