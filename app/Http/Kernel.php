<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;

class Kernel extends HttpKernel
{
    // Global & groups omitted for brevity ...

    /**
     * Route middleware aliases.
     * (Laravel 10/11 prefers $middlewareAliases)
     */
    protected $middlewareAliases = [
        'auth'              => \App\Http\Middleware\Authenticate::class,
        'guest'             => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'verified'          => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,
        'throttle'          => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'signed'            => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'cache.headers'     => \Illuminate\Http\Middleware\SetCacheHeaders::class,
        'password.confirm'  => \Illuminate\Auth\Middleware\RequirePassword::class,
        'auth.basic'        => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'can'               => \Illuminate\Auth\Middleware\Authorize::class,

        // ✅ Your alias
        'role'              => \App\Http\Middleware\RoleMiddleware::class,
    ];
}
