<?php

use App\Http\Controllers\Admin\FloorplanUploadController;
use App\Http\Controllers\Admin\SeatApiController;
use App\Http\Controllers\Admin\SeatingLayoutController;
use App\Http\Controllers\Admin\StaffController;
use App\Http\Controllers\Admin\TwoFactorController;
use App\Http\Controllers\AdminController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Auth\PasswordChangeController;
use App\Http\Controllers\PayMongoWebhookController;
use App\Http\Controllers\Staff\StaffProfileController;
use App\Http\Middleware\AdminNotFound;
use App\Http\Middleware\AdminRole;
use App\Http\Middleware\EnsureAdminOnly;
use App\Models\BlogPost;
use App\Models\MenuCategory;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public Routes
|--------------------------------------------------------------------------
*/

Route::get('/', fn () => view('home'))->name('home');
Route::get('/menu', function () {
    $categories = MenuCategory::orderBy('sort_order')
        ->with(['items' => fn ($q) => $q->where('is_available', true)->orderBy('sort_order')])
        ->get();

    return view('pages.menu', ['categories' => $categories]);
})->name('menu');
Route::get('/reservation', fn () => view('pages.reservation'))->name('reservation');
Route::get('/reservation/success', fn () => view('pages.reservation-success'))->name('reservation.success');
Route::get('/reservation/failed', fn () => view('pages.reservation-failed'))->name('reservation.failed');
Route::get('/reservation/payment-guide', fn () => view('pages.reservation-payment-guide'))->name('reservation.payment-guide');
Route::redirect('/payment', '/reservation/payment-guide', 301)->name('payment');
Route::get('/about', fn () => view('pages.about'))->name('about');
Route::get('/contact', fn () => view('pages.contact'))->name('contact');
Route::get('/blog', function () {
    $posts = BlogPost::orderByDesc('published_at')->get();

    return view('pages.blog', ['posts' => $posts]);
})->name('blog');
Route::get('/blog/{id}', function (int $id) {
    $post = BlogPost::findOrFail($id);

    return view('pages.blog-single', ['post' => $post]);
})->name('blog.show');

/*
|--------------------------------------------------------------------------
| Mobile experience (/mobile/*)
|--------------------------------------------------------------------------
*/

Route::get('/mobile', fn () => redirect('/'));
Route::get('/mobile/queue', fn () => redirect('/'));
Route::get('/mobile/reservation', fn () => redirect('/reservation'));
Route::get('/mobile/lookup', fn () => redirect('/reservation'));
Route::get('/mobile/{any}', fn () => redirect('/'))->where('any', '.*');

/*
|--------------------------------------------------------------------------
| Kiosk experience (/kiosk/*)
|--------------------------------------------------------------------------
*/

Route::get('/kiosk', fn () => redirect('/'))->name('kiosk.home');
Route::get('/kiosk/{any}', fn () => redirect('/'))->where('any', '.*');

/*
|--------------------------------------------------------------------------
| Staff — walk-in queue registration (auth required)
|--------------------------------------------------------------------------
*/

Route::middleware(['auth', 'staff'])->prefix('staff')->name('staff.')->group(function () {
    Route::get('/queue', fn () => redirect()->route('admin.waitlist'))->name('queue');
    Route::get('/admin/profile', [StaffProfileController::class, 'index'])->name('profile');
    Route::post('/admin/profile', [StaffProfileController::class, 'update'])->name('profile.update');
});

/*
|--------------------------------------------------------------------------
| Webhooks (CSRF excluded via bootstrap/app.php)
|--------------------------------------------------------------------------
*/

Route::post('/webhook/paymongo', [PayMongoWebhookController::class, 'handle'])->name('webhook.paymongo');

/*
|--------------------------------------------------------------------------
| Admin — convenience redirect (there is no standalone /admin page)
|--------------------------------------------------------------------------
*/

Route::redirect('/admin', '/admin/login');

/*
|--------------------------------------------------------------------------
| Admin Routes — guests redirect to /admin/login?redirect=…; wrong role → 403
|--------------------------------------------------------------------------
*/

Route::middleware([AdminNotFound::class, AdminRole::class, 'admin.timeout', 'force.password.change', 'require.2fa'])
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/ping', function () {
            session(['admin_last_activity' => now()]);

            return response()->json(['ok' => true]);
        })->name('ping');

        Route::get('/2fa/verify', [TwoFactorController::class, 'showVerifyForm'])->name('2fa.verify');
        Route::post('/2fa/verify', [TwoFactorController::class, 'verify'])->name('2fa.verify.submit');
        Route::get('/2fa/setup', [TwoFactorController::class, 'setup'])->name('2fa.setup');
        Route::post('/2fa/enable', [TwoFactorController::class, 'enable'])->name('2fa.enable');
        Route::post('/2fa/disable', [TwoFactorController::class, 'disable'])->name('2fa.disable');

        Route::get('/password/change', [PasswordChangeController::class, 'showChangeForm'])->name('password.change');
        Route::post('/password/change', [PasswordChangeController::class, 'updatePassword'])->name('password.change.update');

        Route::get('/dashboard', function () {
            if (auth()->user()->role === 'staff') {
                return redirect()->route('admin.tables');
            }

            return app(AdminController::class)->index();
        })->name('dashboard');
        Route::get('/focus', fn () => view('staff.focus'))->name('focus');
        Route::get('/tables', [AdminController::class, 'tables'])->name('tables');
        Route::get('/waitlist', [AdminController::class, 'waitlist'])->name('waitlist');
        Route::get('/bookings', [AdminController::class, 'bookings'])->name('bookings');
        Route::post('/bookings/{booking}/verify-payment', [AdminController::class, 'verifyPayment'])->name('bookings.verify-payment');
        Route::post('/bookings/{booking}/reject-payment', [AdminController::class, 'rejectPayment'])->name('bookings.reject-payment');
        Route::get('/menu', [AdminController::class, 'menu'])->name('menu');
        Route::get('/settings', [AdminController::class, 'settings'])->name('settings');
        Route::post('/settings/qr-upload', [AdminController::class, 'uploadQr'])->name('settings.qr-upload');
        Route::post('/settings/qr-crop', [AdminController::class, 'saveQrCrop'])->name('settings.qr-crop');
        Route::get('/logs', [AdminController::class, 'logs'])->name('logs');

        Route::get('/api/seats', [SeatApiController::class, 'index'])->name('api.seats');
        Route::post('/api/seats/update', [SeatApiController::class, 'update'])->name('api.seats.update');
        Route::get('/api/tables/operations', [SeatApiController::class, 'plannerIndex'])->name('api.tables.operations');
        Route::post('/api/tables/operations/status', [SeatApiController::class, 'plannerStatus'])->name('api.tables.operations.status');
        Route::post('/api/tables/operations/merge-groups', [SeatApiController::class, 'plannerMergeGroups'])->name('api.tables.operations.merge-groups');

        Route::middleware(EnsureAdminOnly::class)->group(function () {
            Route::get('/system-logs', [AdminController::class, 'systemLogs'])->name('system-logs');
            Route::redirect('/qa-proof', '/admin/system-logs')->name('qa-proof');
            Route::get('/seating-analytics', [AdminController::class, 'seatingAnalytics'])->name('seating-analytics');
            Route::get('/seating-layout', SeatingLayoutController::class)->name('seating-layout');
            Route::post('/seating-layout/floorplan', FloorplanUploadController::class)->name('seating-layout.floorplan');
            Route::post('/api/seats/group', [SeatApiController::class, 'group'])->name('api.seats.group');
            Route::post('/api/seats/unmerge', [SeatApiController::class, 'unmerge'])->name('api.seats.unmerge');
            Route::post('/api/seats/place', [SeatApiController::class, 'place'])->name('api.seats.place');
            Route::post('/api/seats/delete', [SeatApiController::class, 'destroy'])->name('api.seats.delete');
            Route::get('/api/seats/planner', [SeatApiController::class, 'plannerIndex'])->name('api.seats.planner');
            Route::post('/api/seats/planner/table', [SeatApiController::class, 'plannerStore'])->name('api.seats.planner.store');
            Route::post('/api/seats/planner/save', [SeatApiController::class, 'plannerSave'])->name('api.seats.planner.save');
            Route::post('/api/seats/planner/status', [SeatApiController::class, 'plannerStatus'])->name('api.seats.planner.status');
            Route::post('/api/seats/planner/merge-groups', [SeatApiController::class, 'plannerMergeGroups'])->name('api.seats.planner.merge-groups');
            Route::post('/api/seats/planner/delete', [SeatApiController::class, 'plannerDelete'])->name('api.seats.planner.delete');

            Route::get('/staff', [StaffController::class, 'index'])->name('staff.index');
            Route::get('/staff/create', [StaffController::class, 'create'])->name('staff.create');
            Route::post('/staff', [StaffController::class, 'store'])->name('staff.store');
            Route::post('/staff/{user}/deactivate', [StaffController::class, 'deactivate'])->name('staff.deactivate');
            Route::post('/staff/{user}/activate', [StaffController::class, 'activate'])->name('staff.activate');
            Route::post('/staff/{user}/force-logout', [StaffController::class, 'forceLogout'])->name('staff.force-logout');
        });
    });

/*
|--------------------------------------------------------------------------
| Auth Routes — rate limited login
|--------------------------------------------------------------------------
*/

Route::get('/admin/login', [LoginController::class, 'showLogin'])->name('login');
Route::post('/admin/login', [LoginController::class, 'login'])->middleware('throttle:admin-login');
if (app()->environment(['local', 'testing'])) {
    Route::post('/admin/dev-login', [LoginController::class, 'devLogin'])->name('login.dev');
}
Route::post('/admin/logout', [LoginController::class, 'logout'])->name('logout');

Route::middleware('guest')->prefix('admin')->group(function () {
    Route::get('/forgot-password', [PasswordResetController::class, 'showForgotForm'])->name('admin.password.forgot');
    Route::post('/forgot-password', [PasswordResetController::class, 'sendResetLink'])->name('admin.password.forgot.send');
    Route::get('/reset-password/{token}', [PasswordResetController::class, 'showResetForm'])->name('admin.password.reset');
    Route::post('/reset-password', [PasswordResetController::class, 'resetPassword'])->name('admin.password.reset.update');
});
