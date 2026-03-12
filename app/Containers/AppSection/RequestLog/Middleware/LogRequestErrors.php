<?php

namespace App\Containers\AppSection\RequestLog\Middleware;

use App\Containers\AppSection\RequestLog\Models\RequestLog;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

final class LogRequestErrors
{
    public function handle(Request $request, \Closure $next): Response
    {
        $response = $next($request);

        try {
            if ($request->getMethod() === 'OPTIONS') {
                return $response;
            }

            $status = $response->getStatusCode();
            if ($status < 400) {
                return $response;
            }

            $url = $request->fullUrl();
            if ($url && Str::contains($url, '.js.map')) {
                return $response;
            }

            $this->pruneIfNeeded();

            $requestLog = RequestLog::query()->firstOrNew([
                'url' => Str::limit((string) $url, 120),
                'status_code' => $status,
            ]);

            $referrer = $request->header('referer') ?: $request->header('referrer');
            if ($referrer) {
                $requestLog->referrer = $this->mergeUnique((array) $requestLog->referrer, [$referrer]);
            }

            $userId = Auth::guard()->id()
                ?: Auth::guard('api')->id();
            if ($userId) {
                $requestLog->user_id = $this->mergeUnique((array) $requestLog->user_id, [$userId]);
            }

            $requestLog->count = $requestLog->exists ? $requestLog->count + 1 : 1;
            $requestLog->save();
        } catch (Throwable) {
            // Swallow logging failures to avoid impacting API responses.
        }

        return $response;
    }

    private function pruneIfNeeded(): void
    {
        if (Cache::has('pruned_request_logs_table')) {
            return;
        }

        (new RequestLog())->pruneAll();

        Cache::put('pruned_request_logs_table', 1, now()->addDay());
    }

    /**
     * @param array<int, mixed> $current
     * @param array<int, mixed> $incoming
     * @return array<int, mixed>
     */
    private function mergeUnique(array $current, array $incoming): array
    {
        return array_values(array_unique(array_merge($current, $incoming)));
    }
}
