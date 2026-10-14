<?php

namespace App\Http\Controllers;

use App\Services\DisplayPanelFeed;
use App\Services\TvTts\TvTtsService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class TvDiagSampleTtsController extends Controller
{
    /**
     * Safe Google TTS sample for ?tvdiag=1 manual probes. Fixed phrase only.
     * Does not create TicketCall rows or expose credentials.
     */
    public function __invoke(
        string $publicToken,
        Request $request,
        DisplayPanelFeed $feed,
        TvTtsService $tts,
    ): BinaryFileResponse|Response {
        $diagQuery = (string) config('tv_realtime.diag_query', 'tvdiag');
        // Explicit opt-in only — never open just because APP_DEBUG is on.
        $diagOk = $request->boolean($diagQuery) || $request->query($diagQuery) === '1';

        if (! $diagOk) {
            abort(404);
        }

        if (! $tts->isAvailable()) {
            return response('TTS unavailable', 503);
        }

        $panel = $feed->findByPublicIdentifier($publicToken);
        if ($panel === null || ! $panel->isOperationallyAvailable()) {
            abort(404);
        }

        $path = $tts->ensureCachedWav('Teste de locução do painel.');
        if ($path === null || ! $tts->isServableCachePath($path)) {
            return response('TTS sample failed', 503);
        }

        return response()->file($path, [
            'Content-Type' => $tts->contentType(),
            'Cache-Control' => 'private, max-age=300',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
