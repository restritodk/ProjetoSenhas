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
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\OperationalContext;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

/**
 * Behavioral contracts for TV media isolation.
 * Real Chromium timing evidence is produced separately via the browser harness
 * (see test_tv_panel_exposes_media_probe_for_browser_verification).
 */
class TvMediaBrowserPlaybackTest extends TestCase
{
    use RefreshDatabase;

    public function test_media_player_has_persistent_video_element_not_x_if(): void
    {
        $panel = $this->panelWithVideo();

        $html = Livewire::test(TvMediaPlayer::class, [
            'publicToken' => $panel->public_token,
        ])->html();

        $this->assertStringContainsString('data-tv-media-video', $html);
        $this->assertStringContainsString('bindVideoSrc', $html);
        $this->assertStringContainsString('x-show="hasItems && current && current.type === \'video\'"', $html);
        $this->assertStringNotContainsString('template x-if="hasItems && current && current.type === \'video\'"', $html);
        $this->assertStringNotContainsString("addEventListener('tv-call-audio-begin'", $html);
        $this->assertStringNotContainsString('callAudioActive', $html);
        $this->assertStringNotContainsString('video.load()', $html);
    }

    public function test_call_and_recall_do_not_dispatch_playlist_updates(): void
    {
        $panel = $this->panelWithVideo();
        $clinic = $panel->clinic;
        $unit = $panel->unit;
        $sector = app(EnsureDefaultSectorForUnit::class)->handle($unit);
        $panel->sectors()->sync([$sector->id => ['clinic_id' => $clinic->id]]);

        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $attendant = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ATTENDANT]);
        $attendant->units()->attach($unit->id, ['clinic_id' => $clinic->id]);

        $type = TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'prefix' => 'N',
            'priority' => 10,
        ]);
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
        ]);

        $this->actingAs($admin);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $tv = Livewire::test(TvDisplay::class, ['publicToken' => $panel->public_token]);
        $media = Livewire::test(TvMediaPlayer::class, ['publicToken' => $panel->public_token]);
        $signature = $media->get('playlistSignature');
        $items = $media->get('items');

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
        $ticket = app(CallNextTicket::class)->handle($attendant);

        $tv->call('refreshFeed')->assertDispatched('tv-new-call');

        $media->call('refreshPlaylist')
            ->assertSet('playlistSignature', $signature)
            ->assertSet('items', $items)
            ->assertNotDispatched('tv-playlist-updated');

        app(RecallTicket::class)->handle($attendant, $ticket);

        $tv->call('refreshFeed')->assertDispatched('tv-new-call');

        $media->call('refreshPlaylist')
            ->assertSet('playlistSignature', $signature)
            ->assertSet('items', $items)
            ->assertNotDispatched('tv-playlist-updated');
    }

    public function test_desktop_enable_unlocks_html_audio_effect_element(): void
    {
        $panel = $this->panelWithVideo();

        $html = $this->get(route('tv.panel', ['publicToken' => $panel->public_token]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('Always unlock the HTMLAudioElement', $html);
        $this->assertStringContainsString('unlockSmartTvEffect(panel)', $html);
        $this->assertStringContainsString('EFFECT_START_REQUESTED', $html);
        $this->assertStringContainsString('EFFECT_PLAY_RESOLVED', $html);
        $this->assertStringContainsString('EFFECT_ENDED', $html);
        $this->assertStringContainsString('TTS_START_REQUESTED', $html);
        $this->assertStringContainsString('data-tv-call-effect', $html);
        $this->assertStringContainsString('/sond/efeitoSonoroNovo.mp3', $html);
        $this->assertStringContainsString('playEffectBuffer', $html);
        $this->assertStringContainsString('?v=', $html);
        $this->assertStringContainsString('Retomar mídia', $html);
    }

    public function test_tv_panel_exposes_media_probe_for_browser_verification(): void
    {
        $panel = $this->panelWithVideo();

        $html = $this->get(route('tv.panel', ['publicToken' => $panel->public_token]))
            ->assertOk()
            ->getContent();

        $this->assertStringContainsString('__humanaTvMediaPlayer', $html);
        $this->assertStringContainsString('playbackSnapshot', $html);
        $this->assertStringContainsString('data-tv-media-video', $html);
        $this->assertStringContainsString('data-tv-media-shell', $html);
    }

    public function test_sem_audio_config_is_applied_without_call_duck(): void
    {
        $panel = $this->panelWithVideo(playWithAudio: false);

        $html = Livewire::test(TvMediaPlayer::class, [
            'publicToken' => $panel->public_token,
        ])->html();

        $this->assertStringContainsString('itemWantsAudio', $html);
        $this->assertStringContainsString('enforceConfiguredMute', $html);
        $this->assertStringNotContainsString('callDucked', $html);
        $this->assertStringContainsString('play_with_audio === true', $html);
    }

    private function panelWithVideo(bool $playWithAudio = false): DisplayPanel
    {
        Storage::fake(MediaItem::DISK);
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $admin = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::ADMINISTRATOR]);

        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'active' => true,
        ]);

        $this->actingAs($admin);
        $video = app(CreateMediaItem::class)->handle($admin, [
            'name' => 'Browser Clip',
            'type' => MediaType::VIDEO->value,
            'active' => true,
            'play_with_audio' => $playWithAudio,
        ], UploadedFile::fake()->create('clip.mp4', 200, 'video/mp4'));

        app(AttachMediaToDisplayPanel::class)->handle($admin, $panel, $video);

        return $panel->fresh(['clinic', 'unit']);
    }
}
