<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\ApplicantStatusController;
use App\Http\Controllers\Auth;
use App\Http\Controllers\BillPrintController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\GcashQrController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\MembershipApplicationController;
use App\Http\Controllers\Resident;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public pages
|--------------------------------------------------------------------------
*/
Route::get('/', HomeController::class)->name('home');

Route::middleware('guest')->group(function () {
    Route::get('/login', [Auth\LoginController::class, 'show'])->name('login');
    Route::post('/login', [Auth\LoginController::class, 'login'])->name('login.attempt');

    Route::get('/register', [Auth\RegisterController::class, 'show'])->name('register');
    Route::post('/register', [Auth\RegisterController::class, 'store'])->name('register.store');

    Route::get('/verify-email', [Auth\EmailVerificationController::class, 'show'])->name('verify-email');
    Route::post('/verify-email', [Auth\EmailVerificationController::class, 'verify'])->name('verify-email.verify');
    Route::post('/verify-email/resend', [Auth\EmailVerificationController::class, 'resend'])->name('verify-email.resend');

    Route::get('/forgot-password', [Auth\PasswordResetController::class, 'showRequest'])->name('password.forgot');
    Route::post('/forgot-password', [Auth\PasswordResetController::class, 'sendCode'])->name('password.send-code');
    Route::get('/reset-password', [Auth\PasswordResetController::class, 'showReset'])->name('password.reset');
    Route::post('/reset-password', [Auth\PasswordResetController::class, 'reset'])->name('password.update');

    Route::get('/apply', [MembershipApplicationController::class, 'create'])->name('apply');
    Route::post('/apply', [MembershipApplicationController::class, 'store'])->name('apply.store');
});

Route::post('/logout', [Auth\LoginController::class, 'logout'])->name('logout');

/*
|--------------------------------------------------------------------------
| Signed-in pages
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'idle'])->group(function () {

    Route::get('/applicant/status', ApplicantStatusController::class)->name('applicant.status');

    // Change password with an emailed code — every signed-in role.
    Route::get('/account/password', [AccountController::class, 'show'])->name('account.password');
    Route::post('/account/password/send-code', [AccountController::class, 'sendCode'])->name('account.password.send-code');
    Route::post('/account/password/verify', [AccountController::class, 'verifyCode'])->name('account.password.verify');
    Route::post('/account/password', [AccountController::class, 'update'])->name('account.password.update');

    // Printable bill — administrators can print any bill, consumers only their own.
    Route::get('/bills/{bill}/print', BillPrintController::class)
        ->middleware('role:admin,resident')->name('bills.print');
    // Payment receipt (view / download) — administrators: any payment; consumers: only their own.
    Route::get('/payments/{payment}/receipt', PaymentReceiptController::class)
        ->whereNumber('payment')->middleware('role:admin,resident')->name('payments.receipt');

    /* ---- Administrator & Meter Reader -------------------------------- */
    Route::prefix('admin')->name('admin.')->middleware('role:admin,staff')->group(function () {
        Route::get('/dashboard', Admin\DashboardController::class)->name('dashboard');

        Route::get('/meter-readings', [Admin\MeterReadingController::class, 'index'])->name('readings.index');
        Route::get('/meter-readings/lookup', [Admin\MeterReadingController::class, 'lookup'])->name('readings.lookup');
        // Only the Meter Reader records readings; the bill is generated automatically.
        Route::post('/meter-readings', [Admin\MeterReadingController::class, 'store'])->middleware('role:staff')->name('readings.store');
        Route::put('/meter-readings/{reading}', [Admin\MeterReadingController::class, 'update'])->middleware('role:admin')->name('readings.update');

        Route::get('/reports', [Admin\ReportController::class, 'index'])->name('reports.index');
        Route::get('/reports/export', [Admin\ReportController::class, 'export'])->name('reports.export');

        // Administrators manage consumers; the Meter Reader can only view the list.
        Route::get('/consumers', [Admin\ConsumerController::class, 'index'])->name('consumers.index');

        // Administrators: everyone's activity; Meter Reader: their own.
        Route::get('/activity-logs', [Admin\UserController::class, 'logs'])->name('users.logs');

        /* Administrator only */
        Route::middleware('role:admin')->group(function () {
            Route::post('/consumers', [Admin\ConsumerController::class, 'store'])->name('consumers.store');
            Route::put('/consumers/{consumer}', [Admin\ConsumerController::class, 'update'])->name('consumers.update');
            Route::delete('/consumers/{consumer}', [Admin\ConsumerController::class, 'destroy'])->name('consumers.destroy');
            Route::get('/consumers/{consumer}/history', [Admin\ConsumerController::class, 'history'])->name('consumers.history');

            Route::get('/bills', [Admin\WaterBillController::class, 'index'])->name('bills.index');

            // Payments: record Cash / GCash QR at the barangay; verify or reject online GCash payments.
            Route::get('/payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
            Route::post('/payments', [Admin\PaymentController::class, 'store'])->name('payments.store');
            Route::post('/payments/{payment}/verify', [Admin\PaymentController::class, 'verify'])->name('payments.verify');
            Route::post('/payments/{payment}/reject', [Admin\PaymentController::class, 'reject'])->name('payments.reject');
            Route::get('/payments/{payment}/receipt', [Admin\PaymentController::class, 'receipt'])->name('payments.receipt');

            Route::get('/applications', [Admin\MembershipReviewController::class, 'index'])->name('applications.index');
            Route::get('/applications/{application}', [Admin\MembershipReviewController::class, 'show'])->name('applications.show');
            Route::post('/applications/{application}/verification', [Admin\MembershipReviewController::class, 'verification'])->name('applications.verification');
            Route::post('/applications/{application}/approve', [Admin\MembershipReviewController::class, 'approve'])->name('applications.approve');
            Route::post('/applications/{application}/reject', [Admin\MembershipReviewController::class, 'reject'])->name('applications.reject');
            Route::post('/applications/{application}/assign-meter', [Admin\MembershipReviewController::class, 'assignMeter'])->name('applications.assign-meter');
            Route::get('/applications/{application}/file/{type}', [Admin\MembershipReviewController::class, 'file'])
                ->whereIn('type', ['id', 'id_back', 'face'])->name('applications.file');

            Route::get('/billing-rates', [Admin\BillingRateController::class, 'index'])->name('rates.index');
            Route::post('/billing-rates', [Admin\BillingRateController::class, 'store'])->name('rates.store');
            Route::put('/billing-rates/{rate}', [Admin\BillingRateController::class, 'update'])->name('rates.update');
            Route::delete('/billing-rates/{rate}', [Admin\BillingRateController::class, 'destroy'])->name('rates.destroy');

            Route::get('/puroks', [Admin\PurokController::class, 'index'])->name('puroks.index');
            Route::post('/puroks', [Admin\PurokController::class, 'store'])->name('puroks.store');
            Route::put('/puroks/{purok}', [Admin\PurokController::class, 'update'])->name('puroks.update');
            Route::delete('/puroks/{purok}', [Admin\PurokController::class, 'destroy'])->name('puroks.destroy');

            Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
            Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
            Route::post('/users/{user}/toggle-status', [Admin\UserController::class, 'toggleStatus'])->name('users.toggle');
            Route::post('/users/{user}/reset-password', [Admin\UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::post('/users/{user}/puroks', [Admin\UserController::class, 'updatePuroks'])->name('users.puroks');

            Route::get('/chatbot-faqs', [Admin\ChatbotFaqController::class, 'index'])->name('faqs.index');
            Route::post('/chatbot-faqs', [Admin\ChatbotFaqController::class, 'store'])->name('faqs.store');
            Route::put('/chatbot-faqs/{faq}', [Admin\ChatbotFaqController::class, 'update'])->name('faqs.update');
            Route::post('/chatbot-faqs/{faq}/toggle', [Admin\ChatbotFaqController::class, 'toggle'])->name('faqs.toggle');
            Route::delete('/chatbot-faqs/{faq}', [Admin\ChatbotFaqController::class, 'destroy'])->name('faqs.destroy');
            Route::delete('/chatbot-unanswered/{id}', [Admin\ChatbotFaqController::class, 'dismissUnanswered'])->name('faqs.dismiss');
            Route::post('/chatbot-ai-settings', [Admin\ChatbotFaqController::class, 'updateAiSettings'])->name('faqs.ai-settings');

            Route::get('/settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
            Route::post('/settings', [Admin\SettingController::class, 'update'])->name('settings.update');
            Route::post('/settings/gcash', [Admin\SettingController::class, 'updateGcash'])->name('settings.gcash');
        });
    });

    /* ---- Residents -------------------------------------------------- */
    Route::prefix('resident')->name('resident.')->middleware('role:resident')->group(function () {
        Route::get('/dashboard', Resident\DashboardController::class)->name('dashboard');
        Route::get('/bill', [Resident\BillController::class, 'index'])->name('bill');
        // Online payment: GCash QR only — the payment waits for administrator verification.
        Route::get('/bill/{bill}/pay', [Resident\BillController::class, 'pay'])->whereNumber('bill')->name('bill.pay');
        Route::post('/bill/{bill}/pay', [Resident\BillController::class, 'submitPayment'])->whereNumber('bill')->name('bill.pay.submit');
        Route::get('/billing-history', [Resident\HistoryController::class, 'bills'])->name('billing-history');
        Route::get('/consumption', [Resident\HistoryController::class, 'consumption'])->name('consumption');
        Route::get('/payment-history', [Resident\HistoryController::class, 'payments'])->name('payment-history');

        Route::get('/profile', [Resident\ProfileController::class, 'show'])->name('profile');
        Route::post('/profile/contact', [Resident\ProfileController::class, 'updateContact'])->name('profile.contact');
    });

    // The barangay's official GCash QR image (payment pages).
    Route::get('/gcash-qr', GcashQrController::class)->middleware('role:admin,resident')->name('gcash.qr');

    Route::post('/chatbot', ChatbotController::class)->middleware('role:resident')->name('chatbot');
});
