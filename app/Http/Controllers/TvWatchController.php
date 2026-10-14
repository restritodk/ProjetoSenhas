<?php

namespace App\Http\Controllers;

use App\Services\DisplayPanelFeed;
use Illuminate\Http\JsonResponse;

class TvWatchController extends Controller
{
    /**
     * Lean public snapshot for fast TV call detection.
     * Includes relative TTS URL + announcement phrase for immediate client enqueue.
     * Does not synthesize TTS or expose credentials / personal user records.
     */
    public function __invoke(string $publicToken, DisplayPanelFeed $feed): JsonResponse
    {
        if (! config('tv_realtime.fast_watch_enabled', true)) {
            return response()->json([
                'enabled' => false,
                'cursor' => 0,
            ], 503, [
                'Cache-Control' => 'no-store',
            ]);
        }

        $panel = $feed->findByPublicIdentifier($publicToken);

        if ($panel === null) {
            abort(404);
        }

        $snapshot = $feed->watchSnapshot($panel);
        $correlation = $snapshot['correlation'] ?? 'none';

        return response()->json($snapshot, 200, [
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
            'X-Correlation-Id' => (string) $correlation,
        ]);
    }
}
