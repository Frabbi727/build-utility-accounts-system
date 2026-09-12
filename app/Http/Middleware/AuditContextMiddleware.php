<?php

namespace App\Http\Middleware;

use App\Services\Audit\AuditContext;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class AuditContextMiddleware
{
    public function __construct(private readonly AuditContext $context) {}

    /**
     * Handle an incoming request.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $request->header('X-Request-Id')
            ?? $request->header('X-Correlation-Id')
            ?? (string) Str::uuid();

        $clientPlatform = strtolower((string) $request->header('X-Client-Platform', ''));
        $appVersion = $request->header('X-App-Version');
        $isApi = $request->is('api/*');

        if ($clientPlatform !== '') {
            $source = Str::startsWith($clientPlatform, 'flutter') ? $clientPlatform : 'flutter_'.$clientPlatform;
            $platform = $clientPlatform;
        } elseif ($isApi) {
            $source = 'api';
            $platform = 'mobile_api';
        } else {
            $source = 'web_admin';
            $platform = 'browser';
        }

        $routeName = $request->route() !== null ? $request->route()->getName() : null;
        $route = $routeName ?? $request->path();

        $this->context->setFromRequest(
            requestId: $requestId,
            source: $source,
            platform: $platform,
            appVersion: $appVersion,
            ipAddress: $request->ip(),
            userAgent: $request->userAgent(),
            route: $route,
            httpMethod: $request->method(),
            userId: Auth::id(),
        );

        $response = $next($request);

        // Propagate request ID back to client in response header
        $response->headers->set('X-Request-Id', $requestId);

        return $response;
    }
}
