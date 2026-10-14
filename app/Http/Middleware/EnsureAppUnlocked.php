<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAppUnlocked
{
    /**
     * Handle an incoming request.
     *
     * Check if the authenticated user has manually locked their app session.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (auth()->check() && $request->session()->get('masroofi_locked') === true) {
            if (! $request->routeIs('pin.unlock*') && ! $request->routeIs('logout') && ! $request->routeIs('transactions.sync')) {
                return redirect()->route('pin.unlock');
            }
        }

        return $next($request);
    }
}
