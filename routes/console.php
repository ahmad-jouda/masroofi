<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

use Illuminate\Support\Facades\Schedule;

// تحديث أسعار الصرف تلقائياً كل ساعة من السوق المالي
Schedule::command('currency:update-rates')->hourly();

// رصد واستقطاع فوائض اليوميات تلقائياً وتوزيع النسب بين الديون وصناديق التحويش عند منتصف الليل (00:01) في بداية كل يوم جديد
Schedule::command('surplus:harvest-daily')->dailyAt('00:01');
