<?php

use App\Http\Middleware\CaptureAffiliateAttribution;
use App\Http\Middleware\EnsureAffiliatePortalAccess;
use App\Http\Middleware\EnsureOnboardingComplete;
use App\Http\Middleware\EnsurePhoneVerified;
use App\Http\Middleware\HandleInertiaRequests;
use App\Http\Middleware\SetLocaleFromSession;
use App\Support\UploadLimit;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Middleware\HandleCors;
use Illuminate\Http\Request;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;
use Symfony\Component\HttpKernel\Exception\PostTooLargeException;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware) {
        // Ensure API preflight/response headers are always applied in local web/mobile dev.
        $middleware->append(HandleCors::class);

        $middleware->web(append: [
            SetLocaleFromSession::class,
            HandleInertiaRequests::class,
            CaptureAffiliateAttribution::class,
        ]);

        $middleware->alias([
            'role' => RoleMiddleware::class,
            'permission' => PermissionMiddleware::class,
            'role_or_permission' => RoleOrPermissionMiddleware::class,
            'onboarding.complete' => EnsureOnboardingComplete::class,
            'phone.verified' => EnsurePhoneVerified::class,
            'affiliate.access' => EnsureAffiliatePortalAccess::class,
        ]);

        // Disable CSRF for webhooks
        $middleware->validateCsrfTokens(except: [
            'webhooks/*',
            'admin/community/api/posts',
            'admin/community/api/posts/*',
            'api/community/posts/*/react',
            'api/community/posts/*/comments',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions) {
        $exceptions->render(function (PostTooLargeException $exception, Request $request) {
            $maxMb = UploadLimit::videoMaxMb();
            $message = 'The uploaded file is too large for the server request limit.';
            if ($maxMb > 0) {
                $message .= ' Try a file up to about '.$maxMb.' MB.';
            }

            if ($request->expectsJson()) {
                return response()->json([
                    'message' => $message,
                ], 413);
            }

            return back()->withErrors([
                'video_file' => $message,
            ]);
        });
    })->create();
