<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DetectMobile
{
    public function handle(Request $request, Closure $next)
    {
        if ($request->expectsJson() || $request->is('api/*') || $request->is('vendor/*') || $request->is('admin/*')) {
            return $next($request);
        }

        $userAgent = $request->header('User-Agent');
        $isAndroid = preg_match('/android/i', $userAgent);
        $isIOS = preg_match('/(iphone|ipad)/i', $userAgent);
        $isMobile = $isAndroid || $isIOS;

        view()->share([
            'isMobile' => $isMobile,
            'isAndroid' => $isAndroid,
            'isIOS' => $isIOS,
        ]);
        return $next($request);
    }
}
