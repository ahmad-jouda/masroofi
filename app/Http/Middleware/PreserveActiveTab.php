<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class PreserveActiveTab
{
    /**
     * The list of valid navigation tab IDs in Masroofi Pro.
     *
     * @var array<int, string>
     */
    protected const VALID_TABS = [
        'tab-dashboard',
        'tab-envelopes',
        'tab-goals',
        'tab-leaks',
        'tab-ledger',
        'tab-debts',
    ];

    /**
     * Handle an incoming request.
     *
     * Automatically flash the active tab into session if provided in form submission or query params.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $tab = $request->input('active_tab') ?? $request->input('tab');

        if ($tab && is_string($tab) && in_array($tab, self::VALID_TABS, true)) {
            session()->flash('active_tab', $tab);
        }

        return $next($request);
    }
}
