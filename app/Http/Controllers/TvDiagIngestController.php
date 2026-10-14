<?php

namespace App\Http\Controllers;

use App\Services\DisplayPanelFeed;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class TvDiagIngestController extends Controller
{
    /**
     * Optional short-lived TV diagnostic ingest (disabled by default).
     * Accepts non-sensitive timing/events only; never stores tokens or PII.
     */
    public function __invoke(string $publicToken, Request $request, DisplayPanelFeed $feed): JsonResponse
    {
        if (! config('tv_realtime.diag_ingest_enabled', false)) {
            abort(404);
        }

        $panel = $feed->findByPublicIdentifier($publicToken);
        if ($panel === null || ! $panel->isOperationallyAvailable()) {
            abort(404);
        }

        $events = $request->input('events');
        if (! is_array($events)) {
            return response()->json(['ok' => false, 'error' => 'invalid'], 422);
        }

        $clean = [];
        foreach (array_slice($events, 0, 30) as $event) {
            if (! is_string($event)) {
                continue;
            }
            $line = Str::limit(preg_replace('/\s+/', ' ', trim($event)) ?? '', 180, '');
            if ($line === '' || preg_match('/(password|token|authorization|api[_-]?key|secret)/i', $line)) {
                continue;
            }
            $clean[] = $line;
        }

        if ($clean === []) {
            return response()->json(['ok' => true, 'stored' => 0]);
        }

        $key = 'tv-diag:'.sha1((string) $panel->id.'|'.$request->ip());
        $existing = Cache::get($key, []);
        if (! is_array($existing)) {
            $existing = [];
        }

        $merged = array_slice(array_merge($existing, $clean), -80);
        Cache::put($key, $merged, now()->addMinutes(30));

        return response()->json([
            'ok' => true,
            'stored' => count($clean),
        ]);
    }
}
