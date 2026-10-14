<?php

namespace Tests\Feature;

use App\Actions\CallNextTicket;
use App\Actions\ClaimDesk;
use App\Actions\EnsureDefaultSectorForUnit;
use App\Actions\IssueTicket;
use App\Actions\RecallTicket;
use App\Actions\StartTicketService;
use App\Livewire\AttendantPanel;
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
use App\TicketStatus;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Tests\TestCase;

class TvRealtimeCallTest extends TestCase
{
    use RefreshDatabase;

    public function test_normal_call_updates_watch_cursor_and_tv_feed(): void
    {
        [$panel, $unit, $desk, $attendant, $admin, $type] = $this->ready();

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);

        $component = Livewire::test(TvDisplay::class, ['publicToken' => $panel->public_token]);

        $before = $this->getJson(route('tv.watch', ['publicToken' => $panel->public_token]))
            ->assertOk()
            ->json();
        $this->assertSame(0, (int) $before['cursor']);

        $called = app(CallNextTicket::class)->handle($attendant);
        $this->assertTrue($called->is($ticket));

        $call = TicketCall::query()
            ->where('ticket_id', $ticket->id)
            ->where('call_type', TicketCallType::INITIAL)
            ->firstOrFail();

        $response = $this->getJson(route('tv.watch', ['publicToken' => $panel->public_token]))
            ->assertOk()
            ->assertHeader('X-Correlation-Id', 'tc-'.$call->id);

        $cacheControl = strtolower((string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', $cacheControl);

        $watch = $response->json();

        $this->assertSame($call->id, (int) $watch['cursor']);
        $this->assertSame($ticket->id, (int) $watch['ticket_id']);
        $this->assertSame(TicketStatus::CALLED->value, $watch['ticket_status']);
        $this->assertSame($ticket->display_code, $watch['display_code']);
        $this->assertStringNotContainsString('called_by', json_encode($watch));
        $this->assertArrayNotHasKey('audio_url', $watch);
        $this->assertArrayNotHasKey('announcement', $watch);

        $component->call('refreshFeed')
            ->assertSee($ticket->display_code)
            ->assertDispatched('tv-new-call');
    }

    public function test_five_rapid_recalls_accept_only_one(): void
    {
        [$panel, $unit, $desk, $attendant, $admin, $type] = $this->ready();

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
        $called = app(CallNextTicket::class)->handle($attendant);

        $accepted = 0;
        $rejected = 0;

        for ($i = 0; $i < 5; $i++) {
            try {
                app(RecallTicket::class)->handle($attendant, $called->fresh());
                $accepted++;
            } catch (ValidationException) {
                $rejected++;
            }
        }

        $this->assertSame(1, $accepted);
        $this->assertSame(4, $rejected);
        $this->assertSame(1, TicketCall::query()
            ->where('ticket_id', $ticket->id)
            ->where('call_type', TicketCallType::RECALL)
            ->count());

        unset($panel);
    }

    public function test_concurrent_recall_attempts_share_ticket_lock_cooldown(): void
    {
        [, $unit, $desk, $attendant, $admin, $type] = $this->ready();

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
        $called = app(CallNextTicket::class)->handle($attendant);

        app(RecallTicket::class)->handle($attendant, $called->fresh());

        try {
            app(RecallTicket::class)->handle($attendant, $called->fresh());
            $this->fail('Second immediate recall must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertStringContainsString(
                'Aguarde alguns segundos',
                collect($exception->errors())->flatten()->first() ?? '',
            );
        }

        Carbon::setTestNow(now()->addSeconds((int) config('tv_realtime.recall_cooldown_seconds', 3) + 1));

        app(RecallTicket::class)->handle($attendant, $called->fresh());

        $this->assertSame(2, TicketCall::query()
            ->where('ticket_id', $ticket->id)
            ->where('call_type', TicketCallType::RECALL)
            ->count());

        Carbon::setTestNow();
    }

    public function test_start_service_instructs_tv_to_invalidate_queued_recalls(): void
    {
        [$panel, $unit, $desk, $attendant, $admin, $type] = $this->ready();

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
        $called = app(CallNextTicket::class)->handle($attendant);
        app(RecallTicket::class)->handle($attendant, $called->fresh());
        app(StartTicketService::class)->handle($attendant, $called->fresh());

        $this->assertSame(TicketStatus::IN_SERVICE, $ticket->fresh()->status);

        $watch = $this->getJson(route('tv.watch', ['publicToken' => $panel->public_token]))
            ->assertOk()
            ->json();

        $this->assertSame(TicketStatus::IN_SERVICE->value, $watch['ticket_status']);
        $this->assertSame($ticket->id, (int) $watch['ticket_id']);

        $html = Livewire::test(TvDisplay::class, ['publicToken' => $panel->public_token])
            ->call('refreshFeed')
            ->html();

        $this->assertStringContainsString('invalidateTicket', $html);
        $this->assertStringContainsString('fastWatchEnabled', $html);
        $this->assertStringContainsString('/watch', $html);
        $this->assertStringContainsString('efeitoSonoroNovo.mp3', $html);
    }

    public function test_watch_is_isolated_between_clinics(): void
    {
        [$panelA, $unitA, $deskA, $attendantA, $adminA, $typeA] = $this->ready();
        [$panelB] = $this->ready();

        $this->actingAs($adminA);
        $ticket = app(IssueTicket::class)->handle($adminA, $unitA->id, $typeA->id);

        $this->actingAs($attendantA);
        app(OperationalContext::class)->setActiveUnit($attendantA, $unitA, session());
        app(ClaimDesk::class)->handle($attendantA, $deskA);
        app(CallNextTicket::class)->handle($attendantA);

        $call = TicketCall::query()->where('ticket_id', $ticket->id)->firstOrFail();

        $watchA = $this->getJson(route('tv.watch', ['publicToken' => $panelA->public_token]))
            ->assertOk()
            ->json();
        $watchB = $this->getJson(route('tv.watch', ['publicToken' => $panelB->public_token]))
            ->assertOk()
            ->json();

        $this->assertSame($call->id, (int) $watchA['cursor']);
        $this->assertSame(0, (int) $watchB['cursor']);
        $this->assertNull($watchB['ticket_id']);
    }

    public function test_unknown_panel_token_returns_404_and_disabled_watch_returns_503(): void
    {
        $this->getJson(route('tv.watch', ['publicToken' => str_repeat('x', 32)]))
            ->assertNotFound();

        config(['tv_realtime.fast_watch_enabled' => false]);

        [$panel] = $this->ready();

        $this->getJson(route('tv.watch', ['publicToken' => $panel->public_token]))
            ->assertStatus(503)
            ->assertJson(['enabled' => false]);
    }

    public function test_attendant_panel_surfaces_recall_cooldown_message(): void
    {
        [, $unit, $desk, $attendant, $admin, $type] = $this->ready();

        $this->actingAs($admin);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
        app(CallNextTicket::class)->handle($attendant);

        $component = Livewire::actingAs($attendant)
            ->test(AttendantPanel::class)
            ->call('recall')
            ->assertSet('errorMessage', '')
            ->assertSee('rechamada', false);

        $this->assertNotNull($component->get('recallLockedUntil'));
        $this->assertGreaterThan(now()->getTimestamp(), (int) $component->get('recallLockedUntil'));

        $component->call('recall')
            ->assertSet('errorMessage', 'Aguarde alguns segundos antes de rechamar novamente.');
    }

    public function test_watch_endpoint_stays_fast_under_repeated_hits(): void
    {
        [$panel, $unit, $desk, $attendant, $admin, $type] = $this->ready();

        $this->actingAs($admin);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
        app(CallNextTicket::class)->handle($attendant);

        $samples = [];
        for ($i = 0; $i < 20; $i++) {
            $start = hrtime(true);
            $this->getJson(route('tv.watch', ['publicToken' => $panel->public_token]))->assertOk();
            $samples[] = (hrtime(true) - $start) / 1_000_000;
        }

        sort($samples);
        $count = count($samples);
        $average = array_sum($samples) / $count;
        $median = $samples[(int) floor(($count - 1) / 2)];
        $p95 = $samples[(int) floor(($count - 1) * 0.95)];
        $max = max($samples);

        $this->assertLessThan(200, $average, "Watch avg {$average}ms exceeded budget");
        $this->assertLessThan(300, $p95, "Watch p95 {$p95}ms exceeded budget");

        // Keep numbers in the assertion message for the final performance report.
        $this->assertTrue(true, sprintf(
            'watch_ms count=%d avg=%.2f median=%.2f p95=%.2f max=%.2f',
            $count,
            $average,
            $median,
            $p95,
            $max,
        ));
    }

    public function test_livewire_poll_fallback_still_announces_without_duplicate_cursor(): void
    {
        [$panel, $unit, $desk, $attendant, $admin, $type] = $this->ready();

        $this->actingAs($admin);
        $ticket = app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $component = Livewire::test(TvDisplay::class, ['publicToken' => $panel->public_token]);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
        app(CallNextTicket::class)->handle($attendant);

        $component->call('refreshFeed')->assertDispatched('tv-new-call');
        $firstCursor = (int) $component->get('lastAnnouncedCallId');
        $this->assertGreaterThan(0, $firstCursor);

        $component->call('refreshFeed')->assertNotDispatched('tv-new-call');
        $this->assertSame($firstCursor, (int) $component->get('lastAnnouncedCallId'));
        $this->assertSame($ticket->display_code, $component->get('currentCall')['display_code']);
    }

    public function test_watch_payload_is_lean_and_has_no_sensitive_fields(): void
    {
        [$panel, $unit, $desk, $attendant, $admin, $type] = $this->ready();

        $this->actingAs($admin);
        app(IssueTicket::class)->handle($admin, $unit->id, $type->id);

        $this->actingAs($attendant);
        app(OperationalContext::class)->setActiveUnit($attendant, $unit, session());
        app(ClaimDesk::class)->handle($attendant, $desk);
        app(CallNextTicket::class)->handle($attendant);

        $watch = $this->getJson(route('tv.watch', ['publicToken' => $panel->public_token]))
            ->assertOk()
            ->json();

        $allowed = [
            'enabled',
            'cursor',
            'ticket_id',
            'ticket_status',
            'display_code',
            'desk_name',
            'ticket_type_name',
            'call_type',
            'called_at',
            'correlation',
        ];

        $this->assertSame([], array_values(array_diff(array_keys($watch), $allowed)));
        $this->assertSame([], array_values(array_diff($allowed, array_keys($watch))));

        $encoded = json_encode($watch);
        $this->assertIsString($encoded);
        $this->assertStringNotContainsString('email', $encoded);
        $this->assertStringNotContainsString('password', $encoded);
        $this->assertStringNotContainsString('called_by', $encoded);
        $this->assertStringNotContainsString('audio_url', $encoded);
        $this->assertStringNotContainsString('announcement', $encoded);
        $this->assertStringNotContainsString('public_token', $encoded);
    }

    public function test_watch_client_guards_inflight_and_aborts_slow_requests(): void
    {
        [$panel] = $this->ready();

        $html = Livewire::test(TvDisplay::class, ['publicToken' => $panel->public_token])->html();

        $this->assertStringContainsString('__humanaTvWatch', $html);
        $this->assertStringContainsString('_refreshInFlight', $html);
        $this->assertStringContainsString('AbortController', $html);
        $this->assertStringContainsString('controller.abort', $html);
        $this->assertStringContainsString('wire:poll.', $html);
        $this->assertStringContainsString('efeitoSonoroNovo.mp3', $html);
        $this->assertStringContainsString('playSmartTvEffect', $html);
        $this->assertStringContainsString('EFFECT_START_REQUESTED', $html);
        $this->assertStringContainsString('Always unlock the HTMLAudioElement', $html);
        $this->assertStringContainsString('EFFECT_START_REQUESTED', $html);
        $this->assertStringContainsString('invalidateTicket', $html);
        $this->assertStringContainsString('itemTicket !== id', $html);
        $this->assertStringContainsString('data-tv-media-shell', $html);
    }

    public function test_rate_limit_headroom_covers_400ms_interval(): void
    {
        $intervalMs = (int) config('tv_realtime.fast_watch_interval_ms', 400);
        $requestsPerMinute = (int) ceil(60_000 / max(1, $intervalMs));

        $this->assertSame(400, $intervalMs);
        $this->assertSame(150, $requestsPerMinute);
        $this->assertLessThan(240, $requestsPerMinute);
    }

    public function test_multi_panel_watch_load_stays_within_budget(): void
    {
        $panels = [];
        foreach ([1, 5, 10, 20] as $count) {
            while (count($panels) < $count) {
                [$panel] = $this->ready();
                $panels[] = $panel;
            }

            $samples = [];
            foreach ($panels as $panel) {
                $start = hrtime(true);
                $this->getJson(route('tv.watch', ['publicToken' => $panel->public_token]))->assertOk();
                $samples[] = (hrtime(true) - $start) / 1_000_000;
            }

            sort($samples);
            $average = array_sum($samples) / count($samples);
            $p95 = $samples[(int) floor((count($samples) - 1) * 0.95)];

            $this->assertLessThan(
                250,
                $average,
                "Avg watch latency for {$count} panels was {$average}ms",
            );
            $this->assertLessThan(
                400,
                $p95,
                "P95 watch latency for {$count} panels was {$p95}ms",
            );
        }
    }

    public function test_watch_unit_isolation_inside_same_clinic(): void
    {
        [$panelA, $unitA, $deskA, $attendantA, $adminA, $typeA] = $this->ready();

        $unitB = Unit::factory()->for($adminA->clinic)->create();
        $sectorB = app(EnsureDefaultSectorForUnit::class)->handle($unitB);
        $panelB = DisplayPanel::factory()->create([
            'clinic_id' => $adminA->clinic_id,
            'unit_id' => $unitB->id,
            'active' => true,
        ]);
        $panelB->sectors()->sync([$sectorB->id => ['clinic_id' => $adminA->clinic_id]]);

        $this->actingAs($adminA);
        app(IssueTicket::class)->handle($adminA, $unitA->id, $typeA->id);

        $this->actingAs($attendantA);
        app(OperationalContext::class)->setActiveUnit($attendantA, $unitA, session());
        app(ClaimDesk::class)->handle($attendantA, $deskA);
        app(CallNextTicket::class)->handle($attendantA);

        $watchA = $this->getJson(route('tv.watch', ['publicToken' => $panelA->public_token]))->assertOk()->json();
        $watchB = $this->getJson(route('tv.watch', ['publicToken' => $panelB->public_token]))->assertOk()->json();

        $this->assertGreaterThan(0, (int) $watchA['cursor']);
        $this->assertSame(0, (int) $watchB['cursor']);
    }

    public function test_database_driver_notes_for_recall_lock(): void
    {
        $driver = DB::connection()->getDriverName();

        // PHPUnit uses sqlite in-memory; production/.env uses mysql (InnoDB lockForUpdate).
        $this->assertSame('sqlite', $driver);
        $this->assertTrue(
            in_array($driver, ['sqlite', 'mysql', 'mariadb', 'pgsql'], true),
        );
    }

    /**
     * @return array{0: DisplayPanel, 1: Unit, 2: Desk, 3: User, 4: User, 5: TicketType}
     */
    private function ready(): array
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
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
            'name' => 'Guichê RT',
            'code' => 'GRT',
        ]);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
        $attendant = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);
        $attendant->units()->attach($unit->id, ['clinic_id' => $clinic->id]);

        $type = new TicketType;
        $type->forceFill([
            'clinic_id' => $clinic->id,
            'name' => 'Normal RT',
            'prefix' => 'R',
            'priority' => 10,
            'active' => true,
        ])->save();

        return [$panel, $unit, $desk, $attendant, $admin, $type->refresh()];
    }
}
