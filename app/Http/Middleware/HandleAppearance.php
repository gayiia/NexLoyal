<?php

// This middleware shares UI appearance settings with Blade views.
namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

// This class reads the appearance cookie and exposes it to views.
class HandleAppearance
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // This exposes the appearance preference so layouts can set theme classes.
        View::share('appearance', $request->cookie('appearance') ?? 'system');

        return $next($request);
    }
}
