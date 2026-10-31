<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountProfileController;
use App\Http\Controllers\Admin;
use App\Http\Controllers\ApplicantStatusController;
use App\Http\Controllers\Auth;
use App\Http\Controllers\BillPrintController;
use App\Http\Controllers\ChatbotController;
use App\Http\Controllers\GcashQrController;
use App\Http\Controllers\PaymentReceiptController;
use App\Http\Controllers\NotificationController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\LegalController;
use App\Http\Controllers\MembershipApplicationController;
use App\Http\Controllers\Resident;
use App\Http\Controllers\SessionController;
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
    // Administrators: second sign-in step, a code emailed after the password.
    Route::get('/login/code', [Auth\TwoFactorController::class, 'show'])->name('two-factor');
    Route::post('/login/code', [Auth\TwoFactorController::class, 'verify'])->name('two-factor.verify');
    Route::post('/login/code/resend', [Auth\TwoFactorController::class, 'resend'])->name('two-factor.resend');
    Route::post('/login/code/cancel', [Auth\TwoFactorController::class, 'cancel'])->name('two-factor.cancel');

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

// Public Privacy Policy and Terms of Service (also linked on Google's sign-in consent screen).
Route::get('/privacy', [LegalController::class, 'privacy'])->name('privacy');
Route::get('/terms', [LegalController::class, 'terms'])->name('terms');

// "Continue with Google / Facebook": sign in, or (from Profile, ?link=1) connect it to the signed-in account.
Route::get('/auth/{provider}/redirect', [Auth\SocialLoginController::class, 'redirect'])->whereIn('provider', ['google', 'facebook'])->name('social.redirect');
Route::get('/auth/{provider}/callback', [Auth\SocialLoginController::class, 'callback'])->whereIn('provider', ['google', 'facebook'])->name('social.callback');
Route::post('/auth/{provider}/unlink', [Auth\SocialLoginController::class, 'unlink'])->whereIn('provider', ['google', 'facebook'])
    ->middleware('auth')->name('social.unlink');

/*
|--------------------------------------------------------------------------
| Signed-in pages
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'idle', 'reminders'])->group(function () {
    // Inactivity timer on every signed-in page: time left, and "Stay signed in".
    Route::get('/session/status', [SessionController::class, 'status'])->name('session.status');
    Route::post('/session/keep-alive', [SessionController::class, 'keepAlive'])->name('session.keep-alive');


    Route::get('/applicant/status', ApplicantStatusController::class)->name('applicant.status');

    // Opening the notification bell marks notifications as read — every signed-in role.
    Route::post('/notifications/read', [NotificationController::class, 'markRead'])->name('notifications.read');
    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');

    // Change password with an emailed code — every signed-in role.
    Route::get('/account/password', [AccountController::class, 'show'])->name('account.password');
    Route::post('/account/password/send-code', [AccountController::class, 'sendCode'])->name('account.password.send-code');
    Route::post('/account/password/verify', [AccountController::class, 'verifyCode'])->name('account.password.verify');
    Route::post('/account/password', [AccountController::class, 'update'])->name('account.password.update');

    // Name and profile picture — every signed-in role.
    Route::post('/account/name', [AccountProfileController::class, 'updateName'])->name('account.name');
    Route::get('/account/photo', [AccountProfileController::class, 'show'])->name('account.photo');
    Route::post('/account/photo', [AccountProfileController::class, 'update'])->name('account.photo.update');
    Route::delete('/account/photo', [AccountProfileController::class, 'destroy'])->name('account.photo.remove');

    // Printable bill — administrators can print any bill, consumers only their own.
    Route::get('/bills/{bill}/print', BillPrintController::class)
        ->middleware('role:admin,resident')->name('bills.print');
    // Payment receipt (view / download) — administrators: any payment; consumers: only their own.
    Route::get('/payments/{payment}/receipt', PaymentReceiptController::class)
        ->whereNumber('payment')->middleware('role:admin,resident')->name('payments.receipt');

    /* ---- Administrator & Meter Reader -------------------------------- */
    Route::prefix('admin')->name('admin.')->middleware('role:admin,staff')->group(function () {
        Route::get('/dashboard', Admin\DashboardController::class)->name('dashboard');
        // Profile for administrators and meter readers (consumers have their own).
        Route::get('/profile', [Admin\ProfileController::class, 'show'])->name('profile');
        Route::post('/profile/contact', [Admin\ProfileController::class, 'updateContact'])->name('profile.contact');

        Route::get('/meter-readings', [Admin\MeterReadingController::class, 'index'])->name('readings.index');
        Route::get('/meter-readings/lookup', [Admin\MeterReadingController::class, 'lookup'])->name('readings.lookup');
        Route::get('/meter-readings/search', [Admin\MeterReadingController::class, 'search'])->name('readings.search');
        // The Meter Reader's own readings (Recorded Readings).
        Route::get('/meter-readings/history', [Admin\MeterReadingController::class, 'history'])->middleware('role:staff')->name('readings.history');
        // Only the Meter Reader records readings; the bill is generated automatically.
        Route::post('/meter-readings', [Admin\MeterReadingController::class, 'store'])->middleware('role:staff')->name('readings.store');
        // Take Photo of Meter: the meter number and the reading are read from the photo.
        Route::post('/meter-readings/scan', [Admin\MeterReadingController::class, 'scan'])->middleware(['role:staff', 'throttle:30,1'])->name('readings.scan');
        // Live camera: is a meter with readable numbers inside the square? (green / red)
        Route::post('/meter-readings/check', [Admin\MeterReadingController::class, 'check'])->middleware(['role:staff', 'throttle:60,1'])->name('readings.check');
        // The meter photo a reading was read from.
        Route::get('/meter-readings/{reading}/photo', [Admin\MeterReadingController::class, 'photo'])->whereNumber('reading')->name('readings.photo');
        // Offline recording: the phone downloads its puroks, then sends saved readings one by one.
        Route::get('/meter-readings/offline-data', [Admin\MeterReadingController::class, 'offlineData'])->middleware('role:staff')->name('readings.offline-data');
        Route::post('/meter-readings/sync', [Admin\MeterReadingController::class, 'sync'])->middleware('role:staff')->name('readings.sync');
        // A reading typed without internet that its photo didn't confirm (Needs review).
        Route::post('/meter-readings/{reading}/review', [Admin\MeterReadingController::class, 'review'])->whereNumber('reading')->middleware('role:admin')->name('readings.review');
        Route::put('/meter-readings/{reading}',[Admin\MeterReadingController::class, 'update'])->middleware('role:staff')->name('readings.update');
        Route::delete('/meter-readings/{reading}', [Admin\MeterReadingController::class, 'destroy'])->middleware('role:staff')->name('readings.destroy');

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
            Route::post('/consumers/{consumer}/deactivate', [Admin\ConsumerController::class, 'deactivate'])->name('consumers.deactivate');
            Route::get('/consumers/{consumer}/history', [Admin\ConsumerController::class, 'history'])->name('consumers.history');

            Route::get('/bills', [Admin\WaterBillController::class, 'index'])->name('bills.index');

            // Database Backup & Restore.
            Route::get('/backups', [Admin\BackupController::class, 'index'])->name('backups.index');
            Route::post('/backups', [Admin\BackupController::class, 'store'])->name('backups.store');
            Route::post('/backups/upload', [Admin\BackupController::class, 'upload'])->name('backups.upload');
            Route::get('/backups/{name}/download', [Admin\BackupController::class, 'download'])->name('backups.download');
            Route::post('/backups/{name}/restore', [Admin\BackupController::class, 'restore'])->name('backups.restore');
            Route::delete('/backups/{name}', [Admin\BackupController::class, 'destroy'])->name('backups.destroy');

            // Dashboard Collection Monitoring: today / this week / this month, printable or CSV.
            Route::get('/collections/print', [Admin\CollectionController::class, 'print'])->name('collections.print');
            Route::get('/collections/export', [Admin\CollectionController::class, 'export'])->name('collections.export');

            // Payments: record Cash / GCash QR at the barangay; verify or reject online GCash payments.
            Route::get('/payments', [Admin\PaymentController::class, 'index'])->name('payments.index');
            Route::post('/payments', [Admin\PaymentController::class, 'store'])->name('payments.store');
            Route::post('/payments/{payment}/verify', [Admin\PaymentController::class, 'verify'])->name('payments.verify');
            Route::post('/payments/{payment}/reject', [Admin\PaymentController::class, 'reject'])->name('payments.reject');
            Route::post('/payments/{payment}/paymongo-check', [Admin\PaymentController::class, 'checkPayMongo'])->name('payments.paymongo-check');
            // GCash QR at the barangay (QR Ph via PayMongo), confirmed automatically.
            Route::post('/payments/qr', [Admin\PaymentController::class, 'startQr'])->name('payments.qr');
            Route::get('/payments/{payment}/qr-status', [Admin\PaymentController::class, 'qrStatus'])->whereNumber('payment')->name('payments.qr-status');
            Route::post('/payments/{payment}/qr-cancel', [Admin\PaymentController::class, 'cancelQr'])->whereNumber('payment')->name('payments.qr-cancel');
            Route::get('/payments/{payment}/receipt', [Admin\PaymentController::class, 'receipt'])->name('payments.receipt');

            Route::get('/applications', [Admin\MembershipReviewController::class, 'index'])->name('applications.index');
            Route::delete('/applications/accounts/{user}', [Admin\MembershipReviewController::class, 'deleteAccount'])->whereNumber('user')->name('applications.accounts.destroy');
            Route::get('/applications/{application}', [Admin\MembershipReviewController::class, 'show'])->name('applications.show');
            Route::delete('/applications/{application}', [Admin\MembershipReviewController::class, 'destroy'])->name('applications.destroy');
            Route::post('/applications/{application}/verification', [Admin\MembershipReviewController::class, 'verification'])->name('applications.verification');
            Route::post('/applications/{application}/approve', [Admin\MembershipReviewController::class, 'approve'])->name('applications.approve');
            Route::post('/applications/{application}/reject', [Admin\MembershipReviewController::class, 'reject'])->name('applications.reject');
            Route::post('/applications/{application}/assign-meter', [Admin\MembershipReviewController::class, 'assignMeter'])->name('applications.assign-meter');
            Route::get('/applications/{application}/file/{type}', [Admin\MembershipReviewController::class, 'file'])
                ->whereIn('type', ['id', 'id_back', 'face'])->name('applications.file');

            // Billing Rates moved into System Settings (water rate tiers); old links land there.
            Route::redirect('/billing-rates', '/admin/settings')->name('rates.index');

            Route::get('/announcements', [Admin\AnnouncementController::class, 'index'])->name('announcements.index');
            Route::post('/announcements', [Admin\AnnouncementController::class, 'store'])->name('announcements.store');

            Route::get('/puroks', [Admin\PurokController::class, 'index'])->name('puroks.index');
            Route::post('/puroks', [Admin\PurokController::class, 'store'])->name('puroks.store');
            Route::put('/puroks/{purok}', [Admin\PurokController::class, 'update'])->name('puroks.update');
            Route::delete('/puroks/{purok}', [Admin\PurokController::class, 'destroy'])->name('puroks.destroy');

            // Automatic checking for new readings / notifications (polled by live.js).
            Route::get('/live-updates', Admin\LiveUpdateController::class)->name('live');
            Route::get('/users', [Admin\UserController::class, 'index'])->name('users.index');
            Route::post('/users', [Admin\UserController::class, 'store'])->name('users.store');
            Route::post('/users/{user}/toggle-status', [Admin\UserController::class, 'toggleStatus'])->name('users.toggle');
            Route::delete('/users/{user}', [Admin\UserController::class, 'destroy'])->name('users.destroy');
            Route::post('/users/{user}/reset-password', [Admin\UserController::class, 'resetPassword'])->name('users.reset-password');
            Route::patch('/users/{user}/unlock', [Admin\UserController::class, 'unlock'])->name('users.unlock');
            Route::post('/users/{user}/puroks', [Admin\UserController::class, 'updatePuroks'])->name('users.puroks');

            Route::get('/chatbot-faqs', [Admin\ChatbotFaqController::class, 'index'])->name('faqs.index');
            Route::post('/chatbot-faqs', [Admin\ChatbotFaqController::class, 'store'])->name('faqs.store');
            Route::put('/chatbot-faqs/{faq}', [Admin\ChatbotFaqController::class, 'update'])->name('faqs.update');
            Route::post('/chatbot-faqs/{faq}/toggle', [Admin\ChatbotFaqController::class, 'toggle'])->name('faqs.toggle');
            Route::delete('/chatbot-faqs/{faq}', [Admin\ChatbotFaqController::class, 'destroy'])->name('faqs.destroy');
            Route::delete('/chatbot-unanswered/{id}', [Admin\ChatbotFaqController::class, 'dismissUnanswered'])->name('faqs.dismiss');
            Route::get('/settings', [Admin\SettingController::class, 'edit'])->name('settings.edit');
            Route::post('/settings', [Admin\SettingController::class, 'update'])->name('settings.update');
            Route::post('/settings/maintenance', [Admin\SettingController::class, 'updateMaintenance'])->name('settings.maintenance');        });
    });

    /* ---- Residents -------------------------------------------------- */
    Route::prefix('resident')->name('resident.')->middleware('role:resident')->group(function () {
        Route::get('/dashboard', Resident\DashboardController::class)->name('dashboard');
        Route::get('/bill', [Resident\BillController::class, 'index'])->name('bill');
        // Online payment: GCash via PayMongo (confirmed automatically), or the
        // barangay GCash QR (waits for administrator verification).
        Route::get('/bill/{bill}/pay', [Resident\BillController::class, 'pay'])->whereNumber('bill')->name('bill.pay');
        Route::post('/bill/{bill}/pay', [Resident\BillController::class, 'submitPayment'])->whereNumber('bill')->name('bill.pay.submit');
        Route::post('/bill/{bill}/paymongo', [Resident\BillController::class, 'payWithPayMongo'])->whereNumber('bill')->name('bill.paymongo');
        Route::get('/bill/{bill}/paymongo/return', [Resident\BillController::class, 'payMongoReturn'])->whereNumber('bill')->name('bill.paymongo.return');
        // QR Ph made by PayMongo for the bill's exact balance — scanned with GCash or a bank app, confirmed automatically.
        Route::post('/bill/{bill}/qr', [Resident\BillController::class, 'startQr'])->whereNumber('bill')->name('bill.qr');
        Route::get('/bill/qr/{payment}/status', [Resident\BillController::class, 'qrStatus'])->whereNumber('payment')->name('bill.qr.status');
        Route::post('/bill/qr/{payment}/cancel', [Resident\BillController::class, 'cancelQr'])->whereNumber('payment')->name('bill.qr.cancel');
        Route::get('/billing-history', [Resident\HistoryController::class, 'bills'])->name('billing-history');
        Route::get('/payment-history', [Resident\HistoryController::class, 'payments'])->name('payment-history');

        Route::get('/profile', [Resident\ProfileController::class, 'show'])->name('profile');
        Route::post('/profile/contact', [Resident\ProfileController::class, 'updateContact'])->name('profile.contact');
        // A new email is confirmed with a code sent to the current one.
        Route::post('/profile/contact/confirm', [Resident\ProfileController::class, 'confirmEmail'])->name('profile.contact.confirm');
        Route::post('/profile/contact/resend', [Resident\ProfileController::class, 'resendEmailCode'])->name('profile.contact.resend');
        Route::post('/profile/contact/cancel', [Resident\ProfileController::class, 'cancelEmailChange'])->name('profile.contact.cancel');
    });

    // The barangay's official GCash QR image (payment pages).
    Route::get('/gcash-qr', GcashQrController::class)->middleware('role:admin,resident')->name('gcash.qr');

    Route::post('/chatbot', ChatbotController::class)->middleware('role:resident')->name('chatbot');
});
