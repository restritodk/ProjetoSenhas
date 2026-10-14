<?php

namespace Tests\Feature;

use App\Actions\EnsureDefaultSectorForUnit;
use App\Contracts\TvSpeechSynthesizer;
use App\Models\Clinic;
use App\Models\DisplayPanel;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\FakeTvSpeechSynthesizer;
use Tests\TestCase;

class TvDiagProbeTest extends TestCase
{
    use RefreshDatabase;

    public function test_diag_hud_exposes_manual_audio_probes(): void
    {
        $panel = $this->panel();

        $html = $this->get(route('tv.panel', [
            'publicToken' => $panel->public_token,
            'tvdiag' => 1,
        ]))
            ->assertOk()
            ->getContent();

        foreach ([
            'Testar efeito',
            'Testar TTS',
            'Sequência',
            'diagProbeEffect',
            'diagProbeTts',
            'diagProbeSequence',
            'probeEffect',
            'legacy-playSmartTvEffect',
            'WATCH_ERR',
            'watchSnapshot',
            'sample-tts',
            'samsung-v6-recall-media',
        ] as $needle) {
            $this->assertTrue(
                str_contains($html, $needle),
                'Missing diagnostic marker: '.$needle
            );
        }
    }

    public function test_sample_tts_requires_diag_flag_and_serves_fixed_phrase(): void
    {
        $panel = $this->panel();
        config([
            'tv_tts.enabled' => true,
            'tv_tts.driver' => 'google',
            'app.debug' => false,
            'tv_realtime.player_diagnostics' => false,
        ]);
        $this->app->instance(TvSpeechSynthesizer::class, new FakeTvSpeechSynthesizer);

        // Without diag flag (and with debug/diagnostics off) the sample endpoint stays closed.
        $this->get(route('tv.diag.sample-tts', ['publicToken' => $panel->public_token]))
            ->assertNotFound();

        $ok = $this->get(route('tv.diag.sample-tts', [
            'publicToken' => $panel->public_token,
            'tvdiag' => 1,
        ]));
        $ok->assertOk();
        $this->assertStringContainsString('audio/', (string) $ok->headers->get('Content-Type'));
    }

    private function panel(): DisplayPanel
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $sector = app(EnsureDefaultSectorForUnit::class)->handle($unit);

        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'active' => true,
        ]);
        $panel->sectors()->sync([$sector->id => ['clinic_id' => $clinic->id]]);

        return $panel;
    }
}
