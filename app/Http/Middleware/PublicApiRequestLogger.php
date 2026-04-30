<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

class PublicApiRequestLogger
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $startedAt = microtime(true);
        $requestId = (string) str()->ulid();
        $context = [
            'request_id' => $requestId,
            'path' => $request->path(),
            'method' => $request->method(),
            'full_url' => $request->fullUrl(),
            'scheme' => $request->getScheme(),
            'host' => $request->getHost(),
            'ip' => $request->ip(),
            'user_agent' => $request->userAgent(),
            'referer' => $request->headers->get('referer'),
            'origin' => $request->headers->get('origin'),
            'x_forwarded_for' => $request->headers->get('x-forwarded-for'),
            'x_forwarded_proto' => $request->headers->get('x-forwarded-proto'),
            'query' => $request->query(),
            'payload' => $request->except(['password', 'password_confirmation']),
        ];

        Log::info('Public API request started', $context);

        try {
            $response = $next($request);

            Log::info('Public API request completed', array_merge($context, [
                'status_code' => $response->getStatusCode(),
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]));

            return $response;
        } catch (Throwable $exception) {
            Log::error('Public API request failed', array_merge($context, [
                'error_message' => $exception->getMessage(),
                'error_class' => $exception::class,
                'duration_ms' => (int) round((microtime(true) - $startedAt) * 1000),
            ]));

            throw $exception;
        }
    }
}
