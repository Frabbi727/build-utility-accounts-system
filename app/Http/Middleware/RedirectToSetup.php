<?php

namespace App\Http\Middleware;

use App\Models\Building;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A fresh install has a chart of accounts and an administrator and nothing else.
 * Rather than dropping that administrator on an empty dashboard, send them to the
 * wizard until a building exists.
 *
 * Only administrators are redirected: nobody else can create a building anyway.
 */
class RedirectToSetup
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user instanceof User && $user->isAdmin() && ! Building::query()->exists()) {
            return redirect()->route('setup');
        }

        return $next($request);
    }
}
