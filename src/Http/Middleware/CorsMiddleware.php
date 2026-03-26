<?php
namespace SyafiqUnijaya\AiChatbox\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CorsMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        $allowedOrigins = config('ai-chatbox.allowed_origins', [config('app.url')]);
        $origin = $request->headers->get('Origin');

        if ($origin !== null && !in_array($origin, $allowedOrigins, true)) {
            abort(403, 'Cross-origin request blocked.');
        }

        $response = $next($request);

        if ($origin !== null && in_array($origin, $allowedOrigins, true)) {
            $response->headers->set('Access-Control-Allow-Origin', $origin);
            $response->headers->set('Access-Control-Allow-Methods', 'GET, POST');
            $response->headers->set('Access-Control-Allow-Headers', 'Content-Type, X-CSRF-TOKEN, X-XSRF-TOKEN');
        }

        return $response;
    }
}
