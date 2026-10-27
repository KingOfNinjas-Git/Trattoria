<?php

use App\Http\Controllers\QrController;
use App\Http\Controllers\TicketController;
use App\Http\Middleware\SetLocale;
use App\Livewire\Kitchen;
use App\Livewire\OrderStatus;
use App\Livewire\TableBill;
use App\Livewire\TableMenu;
use App\Models\DiningTable;
use App\Support\DemoMode;
use Illuminate\Support\Facades\Route;

Route::get('/login', fn () => redirect()->route('filament.admin.auth.login'))->name('login');

// Customer side (language-aware).
Route::middleware(SetLocale::class)->group(function () {
    Route::get('/', function () {
        $table = DiningTable::orderBy('number')->first() ?? abort(404);

        // The public demo opens on a welcome page (QR code, or a button for people who cannot scan).
        return DemoMode::enabled() ? view('demo-welcome', ['table' => $table]) : redirect()->route('table.menu', $table->token);
    });

    Route::get('/lang/{locale}', function (string $locale) {
        abort_unless(array_key_exists($locale, SetLocale::LOCALES), 404);
        session(['locale' => $locale]);

        return redirect()->back(fallback: '/');
    })->name('lang');

    Route::get('/t/{token}', TableMenu::class)->name('table.menu');
    Route::get('/t/{token}/bill', TableBill::class)->name('table.bill');
    Route::get('/t/{token}/order/{order}', OrderStatus::class)->name('table.order');

    Route::get('/credits', fn () => view('credits', [
        'credits' => json_decode(file_get_contents(resource_path('photo-credits.json')), true),
    ]))->name('credits');
});

// Staff side.
Route::middleware('auth')->group(function () {
    Route::get('/kitchen', Kitchen::class)->name('kitchen');
    Route::get('/kitchen/orders/{order}/ticket', [TicketController::class, 'show'])->name('kitchen.ticket');
    Route::get('/qr', [QrController::class, 'all'])->name('qr.all');
    Route::get('/qr/{table}', [QrController::class, 'show'])->name('qr.table');
});

// Portfolio demo only: the kitchen board and the admin panel open as a staff member without a password,
// so visitors can try the full experience. A real restaurant leaves demo mode off and keeps them behind a login.
if (config('trattoria.demo')) {
    Route::get('/demo/kitchen', function () {
        auth()->login(DemoMode::staffUser());

        return redirect()->route('kitchen');
    })->name('demo.kitchen');

    Route::get('/demo/admin', function () {
        auth()->login(DemoMode::staffUser());

        return redirect('/admin');
    })->name('demo.admin');
}
