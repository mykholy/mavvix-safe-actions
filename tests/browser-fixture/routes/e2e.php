<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;

Route::get('/safe-actions-e2e', static function () {
    Auth::loginUsingId(1);
    session(['safe_actions_demo_locale' => request('lang') === 'ar' ? 'ar' : 'en']);
    app()->setLocale(session('safe_actions_demo_locale'));

    return view('safe-actions-e2e');
});

Route::get('/safe-actions-e2e-state', static function () {
    abort_unless(Auth::check(), 403);

    return response()->json([
        'rows' => DB::table('browser_orders')->orderBy('id')->get(['id', 'name', 'status', 'tenant_id']),
        'tickets' => DB::table('safe_action_preflights')->count(),
        'consumed' => DB::table('safe_action_preflights')->whereNotNull('consumed_at')->count(),
    ]);
});

// Only installed by the disposable CI fixture, never registered by the package.
Route::post('/safe-actions-e2e-expire', static function () {
    abort_unless(Auth::check(), 403);

    $changed = DB::table('safe_action_preflights')
        ->whereNull('consumed_at')
        ->update(['expires_at' => now()->subMinute()]);

    return response()->json(['expired' => $changed]);
});
