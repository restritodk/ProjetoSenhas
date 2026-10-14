<?php

namespace Tests\Feature;

use App\Actions\AttachMediaToDisplayPanel;
use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\CreateMediaItem;
use App\Actions\EnsureDefaultSectorForUnit;
use App\Actions\IssueTicket;
use App\Actions\RecallTicket;
use App\Livewire\TvDisplay;
use App\Livewire\TvMediaPlayer;
use App\MediaType;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\DisplayPanel;
use App\Models\MediaItem;
use App\Models\TicketCall;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\OperationalContext;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TvMediaAudioSeparationTest extends TestCase
{
    use RefreshDatabase;

    private const SAMSUNG_TV_UA = 'Mozilla/5.0 (SMART-TV; Linux; Tizen 6.0) AppleWebKit/537.36 (KHTML, like Gecko) SamsungBrowser/4.0 Chrome/76.0.3809.146 TV Safari/537.36';

    public function test_smart_tv_keeps_effect_and_tts_on_separate_elements(): void
    {
        $panel = $this->panelWithVideo();

        $html = $this->withHeader('User-Agent', self::SAMSUNG_TV_UA)
            ->get(route('tv.panel', ['publicToken' => $panel->public_token]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('data-tv-build-family="samsung-v6-recall-media"', $html);
        $this->assertStringContainsString('prepareEffectElementForReplay', $html);
        $this->assertStringContainsString('EFFECT_REPLAY_PREP', $html);
        $this->assertStringContainsString('TTS_PREFETCH_EARLY', $html);
        $this->assertStringContainsString('data-tv-call-effect', $html);
        $this->assertStringContainsString('data-tv-call-speech', $html);
        $this->assertStringContainsString('ensureEffectSrcPinned', $html);
        $this->assertStringContainsString('Sharing one element for both caused recalls', $html);
        $this->assertStringContainsString('playSmartTvEffect', $html);
        $this->assertStringContainsString('ensureSpeechAudio', $html);
        $this->assertStringContainsString('efeitoSonoroNovo.mp3', $html);
        $this->assertStringNotContainsString('location.reload', $html);
    }

    public function test_media_player_restores_position_after_announce_without_src_rebind(): void
    {
        $panel = $this->panelWithVideo();

        $html = Livewire::test(TvMediaPlayer::class, [
            'publicToken' => $panel->public_token,
        ])->html();

        $this->assertStringContainsString('VIDEO_SEEK_RESTORE', $html);
        $this->assertStringContainsString('restoreVideoPositionIfNeeded', $html);
        $this->assertStringContainsString('recoverVideoPlayback', $html);
        $this->assertStringContainsString('VIDEO_ENDED_IGNORED_DURING_ANNOUNCE', $html);
        $this->assertStringContainsString('restartSingleItem', $html);
        $this->assertStringContainsString('Never hard-reset to t=0 when a call announcement is active', $html);
        $this->assertStringContainsString('lastVideoTime', $html);
        $this->assertStringContainsString('ANNOUNCE_BEGIN hold', $html);
        $this->assertStringContainsString('onLocalVideoEmptied', $html);
        $this->assertStringContainsString('__humanaTvMediaPersist', $html);
        $this->assertStringContainsString('Do NOT rebind src / activateCurrent here', $html);
        $this->assertStringContainsString('Never rebind the same playlist item just because readyState dropped', $html);
        $this->assertStringNotContainsString('location.reload', $html);
    }

    public function test_call_and_recall_are_distinct_call_ids_for_announce_dedupe(): void
    {
        [$panel, $unit, $desk, $attendant, $admin, $type] = $this->ready();

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $tv = Livewire::test(TvDisplay::class, ['publicToken' => $panel->public_token]);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
        app(CallNextTicket::class)->handle($attendant);

        $tv->call('refreshFeed')->assertDispatched('tv-new-call');
        $initialId = (int) $tv->get('lastAnnouncedCallId');

        app(RecallTicket::class)->handle($attendant, $ticket->fresh());
        $tv->call('refreshFeed')->assertDispatched('tv-new-call');
        $recallId = (int) $tv->get('lastAnnouncedCallId');

        $this->assertNotSame($initialId, $recallId);
        $this->assertSame(2, TicketCall::query()->where('ticket_id', $ticket->id)->count());

        $html = $tv->html();
        $this->assertStringContainsString('already played', $html);
        $this->assertStringContainsString('already queued', $html);
        $this->assertStringContainsString('playedIds', $html);
        $this->assertStringContainsString('callId', $html);
    }

    public function test_watch_and_livewire_share_call_id_dedupe_markers(): void
    {
        $panel = $this->panelWithVideo();

        $html = Livewire::test(TvDisplay::class, ['publicToken' => $panel->public_token])->html();

        $this->assertStringContainsString("source: 'watch'", $html);
        $this->assertStringContainsString('already played', $html);
        $this->assertStringContainsString('already queued', $html);
        $this->assertStringContainsString('playingCallId', $html);
    }

    public function test_playlist_refresh_skips_render_when_signature_unchanged(): void
    {
        $panel = $this->panelWithVideo();

        $component = Livewire::test(TvMediaPlayer::class, [
            'publicToken' => $panel->public_token,
        ]);

        $signature = (string) $component->get('playlistSignature');
        $this->assertNotSame('', $signature);

        $component->call('refreshPlaylist');
        $this->assertSame($signature, (string) $component->get('playlistSignature'));
    }

    public function test_recall_cooldown_still_enforced(): void
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

    public function test_effect_same_effect_guard_rejects_tts_blob_as_effect(): void
    {
        $panel = $this->panelWithVideo();

        $html = $this->get(route('tv.panel', ['publicToken' => $panel->public_token]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Never treat residual TTS/blob currentSrc as "same effect"', $html);
        $this->assertStringContainsString('isEffectFile', $html);
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
            'prefix' => 'M',
            'priority' => 10,
        ]);

        return [$panel, $unit, $desk, $attendant, $admin, $type];
    }

    private function panelWithVideo(): DisplayPanel
    {
        Storage::fake(MediaItem::DISK);
        [$panel, , , , $admin] = $this->ready();

        $this->actingAs($admin);
        $media = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Clip',
            'type' => MediaType::VIDEO->value,
            'active' => true,
            'play_with_audio' => false,
        ], UploadedFile::fake()->create('clip.mp4', 200, 'video/mp4'));
        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $media);

        return $panel->fresh(['clinic', 'unit', 'sectors']);
    }
}
