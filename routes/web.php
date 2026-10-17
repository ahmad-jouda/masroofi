<?php

use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AiAdvisorController;
use App\Http\Controllers\Auth\ForgotPasswordController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PinController;
use App\Http\Controllers\Auth\ResetPasswordController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\DebtController;
use App\Http\Controllers\EnvelopeController;
use App\Http\Controllers\GroceryItemController;
use App\Http\Controllers\PartnerNotificationController;
use App\Http\Controllers\PushNotificationController;
use App\Http\Controllers\SalarySourceController;
use App\Http\Controllers\SavingGoalController;
use App\Http\Controllers\SubscriptionController;
use App\Http\Controllers\SurplusAllocationController;
use App\Http\Controllers\TransactionController;
use App\Http\Controllers\UserSettingController;
use App\Http\Controllers\WishlistController;
use App\Http\Controllers\WorkspaceMemberController;
use Illuminate\Support\Facades\Route;

// ==============================================
// GUEST ROUTES (Login, Quick PIN, Password Reset)
// ==============================================
Route::middleware('guest')->group(function () {
    Route::get('/login', [LoginController::class, 'showLoginForm'])->name('login');
    Route::post('/login', [LoginController::class, 'login'])->name('login.submit');
    Route::post('/pin/login', [LoginController::class, 'loginWithPin'])->name('pin.login');
    Route::post('/device/forget', [LoginController::class, 'forgetDevice'])->name('device.forget');

    // Strict Email Password Reset
    Route::get('/forgot-password', [ForgotPasswordController::class, 'showLinkRequestForm'])->name('password.request');
    Route::post('/forgot-password', [ForgotPasswordController::class, 'sendResetLinkEmail'])->name('password.email');
    Route::get('/reset-password/{token}', [ResetPasswordController::class, 'showResetForm'])->name('password.reset');
    Route::post('/reset-password', [ResetPasswordController::class, 'reset'])->name('password.update');
});

// ==============================================
// AUTHENTICATED ROUTES
// ==============================================
Route::middleware('auth')->group(function () {
    // Logout
    Route::post('/logout', [LoginController::class, 'logout'])->name('logout');

    // Quick PIN Setup & Lock/Unlock
    Route::get('/pin/setup', [PinController::class, 'showSetupForm'])->name('pin.setup');
    Route::post('/pin/setup', [PinController::class, 'setup'])->name('pin.setup.store');
    Route::post('/pin/change', [PinController::class, 'changePin'])->name('pin.change');
    Route::post('/pin/lock', [PinController::class, 'lock'])->name('pin.lock');
    Route::get('/pin/unlock', [PinController::class, 'showUnlockForm'])->name('pin.unlock');
    Route::post('/pin/unlock', [PinController::class, 'unlock'])->name('pin.unlock.submit');

    // Dashboard Main Route
    Route::get('/', [DashboardController::class, 'index'])->name('dashboard');

    // Transactions
    Route::get('/ledger/export/pdf', [TransactionController::class, 'exportPdf'])->name('transactions.exportPdf');
    Route::get('/ledger/export/excel', [TransactionController::class, 'exportExcel'])->name('transactions.exportExcel');
    Route::post('/transactions', [TransactionController::class, 'store'])->name('transactions.store');
    Route::put('/transactions/{transaction}', [TransactionController::class, 'update'])->name('transactions.update');
    Route::post('/transactions/sync', [TransactionController::class, 'syncOffline'])->name('transactions.sync');
    Route::delete('/transactions/{transaction}', [TransactionController::class, 'destroy'])->name('transactions.destroy');

    // Envelopes & 50/30/20
    Route::post('/envelopes', [EnvelopeController::class, 'store'])->name('envelopes.store');
    Route::put('/envelopes/{envelope}', [EnvelopeController::class, 'update'])->name('envelopes.update');
    Route::delete('/envelopes/{envelope}', [EnvelopeController::class, 'destroy'])->name('envelopes.destroy');
    Route::post('/envelopes/auto-allocate', [EnvelopeController::class, 'autoAllocate'])->name('envelopes.autoAllocate');

    // Saving Goals
    Route::post('/goals', [SavingGoalController::class, 'store'])->name('goals.store');
    Route::post('/goals/{goal}/deposit', [SavingGoalController::class, 'deposit'])->name('goals.deposit');
    Route::post('/goals/{goal}/harvest-surplus', [SavingGoalController::class, 'harvestSurplus'])->name('goals.harvestSurplus');
    Route::post('/goals/{goal}/harvest/activate', [SavingGoalController::class, 'activateHarvest'])->name('goals.harvest.activate');
    Route::post('/goals/{goal}/harvest/toggle', [SavingGoalController::class, 'toggleHarvest'])->name('goals.harvest.toggle');
    Route::get('/goals/{goal}/harvest/logs', [SavingGoalController::class, 'getHarvestLogs'])->name('goals.harvest.logs');
    Route::post('/surplus/allocate', [SurplusAllocationController::class, 'allocate'])->name('surplus.allocate');
    Route::delete('/goals/{goal}', [SavingGoalController::class, 'destroy'])->name('goals.destroy');

    // 48-Hour Wishlist
    Route::post('/wishlist', [WishlistController::class, 'store'])->name('wishlist.store');
    Route::patch('/wishlist/{wishlist}', [WishlistController::class, 'update'])->name('wishlist.update');
    Route::delete('/wishlist/{wishlist}', [WishlistController::class, 'destroy'])->name('wishlist.destroy');

    // Recurring Subscriptions
    Route::post('/subscriptions', [SubscriptionController::class, 'store'])->name('subscriptions.store');
    Route::delete('/subscriptions/{subscription}', [SubscriptionController::class, 'destroy'])->name('subscriptions.destroy');

    // Debts & Payables Tracker (الديون والذمم المؤجلة)
    Route::post('/debts', [DebtController::class, 'store'])->name('debts.store');
    Route::post('/debts/{debt}/add', [DebtController::class, 'addRecord'])->name('debts.add');
    Route::post('/debts/{debt}/repay', [DebtController::class, 'repay'])->name('debts.repay');
    Route::delete('/debts/{debt}', [DebtController::class, 'destroy'])->name('debts.destroy');
    Route::post('/debts/{debt}/harvest/activate', [DebtController::class, 'activateHarvest'])->name('debts.harvest.activate');
    Route::post('/debts/{debt}/harvest/toggle', [DebtController::class, 'toggleHarvest'])->name('debts.harvest.toggle');
    Route::post('/debts/{debt}/harvest/repay', [DebtController::class, 'repayFromHarvest'])->name('debts.harvest.repay');
    Route::post('/debts/{debt}/harvest/reset', [DebtController::class, 'resetHarvest'])->name('debts.harvest.reset');
    Route::get('/debts/{debt}/harvest/logs', [DebtController::class, 'getHarvestLogs'])->name('debts.harvest.logs');
    Route::delete('/debts/records/{record}', [DebtController::class, 'destroyRecord'])->name('debts.records.destroy');

    // Salary Sources & Multi-Salary Management
    Route::post('/salaries', [SalarySourceController::class, 'store'])->name('salaries.store');
    Route::put('/salaries/{salary}', [SalarySourceController::class, 'update'])->name('salaries.update');
    Route::delete('/salaries/{salary}', [SalarySourceController::class, 'destroy'])->name('salaries.destroy');

    // User Settings, Reset & Currency
    Route::put('/settings', [UserSettingController::class, 'update'])->name('settings.update');
    Route::post('/settings/start-new-cycle', [UserSettingController::class, 'startNewCycleNow'])->name('settings.startNewCycle');
    Route::post('/settings/reset', [UserSettingController::class, 'resetData'])->name('settings.reset');
    Route::post('/currency/refresh', [UserSettingController::class, 'refreshCurrencyRates'])->name('currency.refresh');

    // Workspace Family Members (مشاركة المحفظة مع الزوجة أو الشريك)
    Route::post('/workspace/members', [WorkspaceMemberController::class, 'store'])->name('workspace.members.store');
    Route::delete('/workspace/members/{user}', [WorkspaceMemberController::class, 'destroy'])->name('workspace.members.destroy');
    Route::post('/workspace/members/{user}/reset-pin', [WorkspaceMemberController::class, 'resetPin'])->name('workspace.members.resetPin');

    // Shared Smart Grocery List (قائمة المقاضي والمشتريات المشتركة)
    Route::get('/grocery-items', [GroceryItemController::class, 'index'])->name('grocery-items.index');
    Route::post('/grocery-items', [GroceryItemController::class, 'store'])->name('grocery-items.store');
    Route::post('/grocery-items/{groceryItem}/toggle', [GroceryItemController::class, 'toggle'])->name('grocery-items.toggle');
    Route::post('/grocery-items/{groceryItem}/buy', [GroceryItemController::class, 'buy'])->name('grocery-items.buy');
    Route::delete('/grocery-items/{groceryItem}', [GroceryItemController::class, 'destroy'])->name('grocery-items.destroy');
    Route::post('/grocery-items/convert-expense', [GroceryItemController::class, 'convertToExpense'])->name('grocery-items.convert-expense');
    Route::post('/grocery-items/sync', [GroceryItemController::class, 'syncOffline'])->name('grocery-items.sync');

    // Instant Partner Notifications (إشعارات فورية بين الحسابين)
    Route::get('/partner/notifications/poll', [PartnerNotificationController::class, 'poll'])->name('partner.notifications.poll');
    Route::post('/partner/notifications/mark-read', [PartnerNotificationController::class, 'markAllRead'])->name('partner.notifications.mark-read');

    // Web Push Notifications (إشعارات الهاتف الفورية)
    Route::get('/push/public-key', [PushNotificationController::class, 'publicKey'])->name('push.public-key');
    Route::post('/push/subscribe', [PushNotificationController::class, 'subscribe'])->name('push.subscribe');
    Route::post('/push/unsubscribe', [PushNotificationController::class, 'unsubscribe'])->name('push.unsubscribe');
    Route::post('/push/test', [PushNotificationController::class, 'sendTestPush'])->name('push.test');
    Route::post('/push/evening-reminder', [PushNotificationController::class, 'checkEveningSurplusReminder'])->name('push.evening-reminder');

    // AI Financial Advisor Chat (مستشار الذكاء الاصطناعي التفاعلي)
    Route::get('/ai-advisor/messages', [AiAdvisorController::class, 'getMessages'])->name('ai-advisor.messages');
    Route::post('/ai-advisor/chat', [AiAdvisorController::class, 'sendMessage'])->name('ai-advisor.chat');
    Route::post('/ai-advisor/clear', [AiAdvisorController::class, 'clearChat'])->name('ai-advisor.clear');

    // ==============================================
    // ADMIN ONLY ROUTES (User & Workspace Management)
    // ==============================================
    Route::middleware('admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/users', [UserController::class, 'index'])->name('users.index');
        Route::post('/users', [UserController::class, 'store'])->name('users.store');
        Route::put('/users/{user}', [UserController::class, 'update'])->name('users.update');
        Route::post('/users/{user}/reset-pin', [UserController::class, 'resetPin'])->name('users.resetPin');
        Route::delete('/users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
    });
});
