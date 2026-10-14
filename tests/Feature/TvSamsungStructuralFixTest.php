<?php

namespace Tests\Feature;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\EnsureDefaultSectorForUnit;
use App\Actions\IssueTicket;
use App\Actions\RecallTicket;
use App\Livewire\TvDisplay;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\DisplayPanel;
use App\Models\TicketCall;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\OperationalContext;
use App\TicketCallType;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TvSamsungStructuralFixTest extends TestCase
{
    use RefreshDatabase;

    private const SAMSUNG_TV_UA = 'Mozilla/5.0 (SMART-TV; Linux; Tizen 6.0) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/4.0 Chrome/76.0.3809.146 TV Safari/537.36';

    public function test_smart_tv_uses_html_audio_sequence_without_audiocontext_on_unlock(): void
    {
        $panel = $this->panel();

        $html = $this->withHeader('User-Agent', self::SAMSUNG_TV_UA)
            ->get(route('tv.panel', ['publicToken' => $panel->public_token]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('playSmartTvSequence', $html);
        $this->assertStringContainsString('playHtmlAudioClip', $html);
        $this->assertStringContainsString('fetchTtsBlob', $html);
        $this->assertStringContainsString('steals the Tizen media pipeline', $html);
        $this->assertStringContainsString('data-tv-build-family="samsung-v5-split-audio"', $html);
        $this->assertStringContainsString('diagProbeEffect', $html);
        $this->assertStringContainsString('probeEffect', $html);
        $this->assertStringContainsString('sample-tts', $html);
        $this->assertStringContainsString('efeitoSonoroNovo.mp3', $html);
        $this->assertStringContainsString('Hard guard: Smart TV must not use speech', $html);
        $this->assertStringNotContainsString('location.reload', $html);
    }

    public function test_watch_snapshot_includes_announce_for_immediate_enqueue(): void
    {
        [$panel, $unit, $desk, $attendant, $admin, $type] = $this->ready();

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
        app(CallNextTicket::class)->handle($attendant);

        $call = TicketCall::query()
            ->where('ticket_id', $ticket->id)
            ->where('call_type', TicketCallType::INITIAL)
            ->firstOrFail();

        $watch = $this->getJson(route('tv.watch', ['publicToken' => $panel->public_token]))
            ->assertOk()
            ->json();

        $this->assertSame($call->id, (int) $watch['cursor']);
        $this->assertNotEmpty($watch['announcement']);
        $this->assertStringContainsString('/tts/'.$call->id, (string) $watch['audio_url']);
        $this->assertStringContainsString($ticket->display_code, (string) $watch['display_code']);
    }

    public function test_client_enqueues_from_watch_and_dedupes_livewire(): void
    {
        $panel = $this->panel();

        $html = Livewire::test(TvDisplay::class, ['publicToken' => $panel->public_token])->html();

        $this->assertStringContainsString("source: 'watch'", $html);
        $this->assertStringContainsString('already played', $html);
        $this->assertStringContainsString('already queued', $html);
        $this->assertStringContainsString('playSmartTvSequence', $html);
        $this->assertStringContainsString('TTS_PREFETCH', $html);
        $this->assertStringContainsString('restoreEffectSrc', $html);
    }

    public function test_recall_cooldown_still_rejects_rapid_repeats(): void
    {
        [, $unit, $desk, $attendant, $admin, $type] = $this->ready();

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
        $called = app(CallNextTicket::class)->handle($attendant);

        app(RecallTicket::class)->handle($attendant, $called->fresh());

        $this->expectException(ValidationException::class);
        app(RecallTicket::class)->handle($attendant, $called->fresh());
    }

    public function test_media_player_resumes_only_after_announce_end(): void
    {
        $panel = $this->panel();

        $html = $this->get(route('tv.panel', ['publicToken' => $panel->public_token]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('announceActive', $html);
        $this->assertStringContainsString("addEventListener('tv-call-audio-begin'", $html);
        $this->assertStringContainsString("addEventListener('tv-call-audio-end'", $html);
        $this->assertStringContainsString('forceResume', $html);
        $this->assertStringNotContainsString('location.reload', $html);
    }

    /**
     * @return array{0: DisplayPanel, 1: Unit, 2: Desk, 3: User, 4: User, 5: TicketType}
     */
    private function ready(): array
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $attendant = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        $attendant->units()->attach($unit->id, ['clinic_id' => $clinic->id]);
        $sector = app(EnsureDefaultSectorForUnit::class)->handle($unit);

        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'active' => true,
        ]);
        $panel->sectors()->sync([$sector->id => ['clinic_id' => $clinic->id]]);

        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
        ]);
        $type = TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'prefix' => 'S',
            'priority' => 10,
        ]);

        return [$panel, $unit, $desk, $attendant, $admin, $type];
    }

    private function panel(): DisplayPanel
    {
        [$panel] = $this->ready();

        return $panel;
    }
}
