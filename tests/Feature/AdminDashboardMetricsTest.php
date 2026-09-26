<?php

namespace Tests\Feature;

use App\Actions\EnsureClinicRolePermissions;
use App\Actions\SaveClinicRolePermissions;
use App\Livewire\AdminDashboard;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\DisplayPanel;
use App\Models\Ticket;
use App\Models\TicketCall;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\AdminDashboardMetricsService;
use App\Services\OperationalContext;
use App\TicketCallType;
use App\TicketStatus;
use App\UserRole;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\TestCase;

class AdminDashboardMetricsTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $this->get(route('dashboard'))->assertRedirect(route('login'));
    }

    public function test_user_without_dashboard_permission_receives_forbidden(): void
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
        $supervisor = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::SUPERVISOR,
        ]);

        app(SaveClinicRolePermissions::class)->handle($admin, $clinic, UserRole::SUPERVISOR, [
            'attendant.access',
        ]);

        $this->actingAs($supervisor->fresh())
            ->get(route('dashboard'))
            ->assertForbidden();
    }

    public function test_administrator_sees_operational_dashboard(): void
    {
        [$clinic, $unit, $admin] = $this->clinicWithAdmin();

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Dashboard')
            ->assertSee('Visão geral do funcionamento da clínica em tempo real.')
            ->assertSee($clinic->name)
            ->assertSee($unit->name)
            ->assertSee('Senhas hoje')
            ->assertSee('Sistema online')
            ->assertSee('Fluxo de atendimentos')
            ->assertSee('Fila atual por tipo de senha')
            ->assertSee('Mesas / Guichês')
            ->assertSee('Painéis de TV')
            ->assertSee('Atividade recente')
            ->assertDontSee('Pausada')
            ->assertDontSee('Offline');
    }

    public function test_issued_waiting_in_service_and_completed_counts_are_real(): void
    {
        [$clinic, $unit, $admin] = $this->clinicWithAdmin();
        $normal = $this->type($clinic, 'Normal', 'N', 10);
        $tz = (string) config('app.timezone');
        $today = CarbonImmutable::now($tz);

        $this->ticket($clinic, $unit, $normal, [
            'sequence_number' => 1,
            'status' => TicketStatus::WAITING,
            'issued_at' => $today,
        ]);
        $this->ticket($clinic, $unit, $normal, [
            'sequence_number' => 2,
            'status' => TicketStatus::WAITING,
            'issued_at' => $today,
        ]);
        $this->ticket($clinic, $unit, $normal, [
            'sequence_number' => 3,
            'status' => TicketStatus::IN_SERVICE,
            'issued_at' => $today,
        ]);
        $this->ticket($clinic, $unit, $normal, [
            'sequence_number' => 4,
            'status' => TicketStatus::COMPLETED,
            'issued_at' => $today,
            'completed_at' => $today,
        ]);
        $this->ticket($clinic, $unit, $normal, [
            'sequence_number' => 5,
            'sequence_date' => $today->subDay()->toDateString(),
            'status' => TicketStatus::COMPLETED,
            'issued_at' => $today->subDay()->setTime(11, 0),
            'completed_at' => $today->subDay()->setTime(12, 0),
        ]);

        $snapshot = $this->snapshot($admin);

        $this->assertSame(4, $snapshot['issued']['value']);
        $this->assertSame(300, $snapshot['issued']['delta']);
        $this->assertSame(2, $snapshot['waiting']['value']);
        $this->assertSame(1, $snapshot['in_service']['value']);
        $this->assertSame(1, $snapshot['completed']['value']);
        $this->assertSame(0, $snapshot['completed']['delta']);
    }

    public function test_average_wait_uses_issued_at_until_first_initial_call(): void
    {
        [$clinic, $unit, $admin] = $this->clinicWithAdmin();
        $normal = $this->type($clinic, 'Normal', 'N', 10);
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Mesa 01',
        ]);
        $issued = CarbonImmutable::now((string) config('app.timezone'))->setTime(10, 0, 0);
        $ticket = $this->ticket($clinic, $unit, $normal, [
            'sequence_number' => 7,
            'status' => TicketStatus::CALLED,
            'issued_at' => $issued,
            'called_at' => $issued->addMinutes(20),
            'current_desk_id' => $desk->id,
        ]);
        $this->callTicket($ticket, $desk, $admin, $issued->addMinutes(8)->addSeconds(42), TicketCallType::INITIAL);
        $this->callTicket($ticket, $desk, $admin, $issued->addMinutes(20), TicketCallType::RECALL);

        $ignored = $this->ticket($clinic, $unit, $normal, [
            'sequence_number' => 8,
            'status' => TicketStatus::WAITING,
            'issued_at' => $issued->addHour(),
        ]);
        $this->assertNull($ignored->called_at);

        $snapshot = $this->snapshot($admin);

        $this->assertSame(1, $snapshot['wait']['samples']);
        $this->assertSame(8 * 60 + 42, $snapshot['wait']['seconds']);
        $this->assertSame('08:42', $snapshot['wait']['display']);
    }

    public function test_queue_percentages_use_real_waiting_tickets_and_empty_queue_is_safe(): void
    {
        [$clinic, $unit, $admin] = $this->clinicWithAdmin();
        $normal = $this->type($clinic, 'Normal', 'N', 10);
        $preferential = $this->type($clinic, 'Preferencial', 'P', 20);
        $emergency = $this->type($clinic, 'Emergencial', 'E', 30);

        foreach ([1, 2] as $sequence) {
            $this->ticket($clinic, $unit, $normal, ['sequence_number' => $sequence]);
        }
        $this->ticket($clinic, $unit, $preferential, ['sequence_number' => 1]);
        $this->ticket($clinic, $unit, $emergency, ['sequence_number' => 1]);

        $snapshot = $this->snapshot($admin);
        $items = collect($snapshot['queue']['items'])->keyBy('prefix');

        $this->assertSame(4, $snapshot['queue']['total']);
        $this->assertSame(2, $items['N']['waiting']);
        $this->assertSame(50, $items['N']['percent']);
        $this->assertSame(1, $items['P']['waiting']);
        $this->assertSame(25, $items['P']['percent']);
        $this->assertSame(1, $items['E']['waiting']);
        $this->assertSame(25, $items['E']['percent']);
        $this->assertSame(100, $items->sum('percent'));

        Ticket::query()->update(['status' => TicketStatus::COMPLETED, 'completed_at' => now()]);

        $empty = $this->snapshot($admin);

        $this->assertSame(0, $empty['queue']['total']);
        $this->assertSame([], $empty['queue']['items']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Nenhuma senha aguardando no momento.');
    }

    public function test_chart_groups_today_by_hour_and_real_types(): void
    {
        [$clinic, $unit, $admin] = $this->clinicWithAdmin();
        $normal = $this->type($clinic, 'Normal', 'N', 10);
        $preferential = $this->type($clinic, 'Preferencial', 'P', 20);
        $tz = (string) config('app.timezone');
        $day = CarbonImmutable::now($tz);

        $this->ticket($clinic, $unit, $normal, [
            'sequence_number' => 1,
            'issued_at' => $day->setTime(10, 10),
        ]);
        $this->ticket($clinic, $unit, $normal, [
            'sequence_number' => 2,
            'issued_at' => $day->setTime(10, 40),
        ]);
        $this->ticket($clinic, $unit, $preferential, [
            'sequence_number' => 1,
            'issued_at' => $day->setTime(11, 5),
        ]);

        $snapshot = $this->snapshot($admin);
        $buckets = collect($snapshot['chart']['buckets'])->keyBy('label');

        $this->assertFalse($snapshot['chart']['empty']);
        $this->assertSame('Quantidade de senhas por horário', $snapshot['chart']['subtitle']);
        $this->assertSame(2, $buckets['10:00']['total']);
        $this->assertSame(1, $buckets['11:00']['total']);
        $this->assertSame(0, $buckets['09:00']['total']);
        $this->assertTrue(collect($snapshot['chart']['legend'])->pluck('name')->contains('Normal'));
        $this->assertTrue(collect($snapshot['chart']['legend'])->pluck('name')->contains('Preferencial'));
    }

    public function test_period_filters_change_issued_counts(): void
    {
        [$clinic, $unit, $admin] = $this->clinicWithAdmin();
        $normal = $this->type($clinic, 'Normal', 'N', 10);
        $tz = (string) config('app.timezone');
        $now = CarbonImmutable::now($tz);

        $this->ticket($clinic, $unit, $normal, [
            'sequence_number' => 1,
            'issued_at' => $now,
        ]);
        $this->ticket($clinic, $unit, $normal, [
            'sequence_number' => 2,
            'sequence_date' => $now->subDays(3)->toDateString(),
            'issued_at' => $now->subDays(3)->setTime(9, 0),
        ]);
        $this->ticket($clinic, $unit, $normal, [
            'sequence_number' => 3,
            'sequence_date' => $now->subDays(20)->toDateString(),
            'issued_at' => $now->subDays(20)->setTime(9, 0),
        ]);
        $this->ticket($clinic, $unit, $normal, [
            'sequence_number' => 4,
            'sequence_date' => $now->subDays(40)->toDateString(),
            'issued_at' => $now->subDays(40)->setTime(9, 0),
        ]);

        $service = app(AdminDashboardMetricsService::class);

        $this->assertSame(1, $service->snapshot($admin, 'today', null, null, $unit->id)['issued']['value']);
        $this->assertSame(2, $service->snapshot($admin, '7d', null, null, $unit->id)['issued']['value']);
        $this->assertSame(3, $service->snapshot($admin, '30d', null, null, $unit->id)['issued']['value']);
        $this->assertSame(
            2,
            $service->snapshot(
                $admin,
                'custom',
                $now->subDays(4)->toDateString(),
                $now->toDateString(),
                $unit->id,
            )['issued']['value'],
        );
    }

    public function test_custom_period_rejects_inverted_or_oversized_ranges(): void
    {
        [$clinic, $unit, $admin] = $this->clinicWithAdmin();
        $service = app(AdminDashboardMetricsService::class);

        try {
            $service->snapshot($admin, 'custom', '2026-09-20', '2026-09-01', $unit->id);
            $this->fail('Intervalo invertido deveria ser rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customFrom', $exception->errors());
        }

        try {
            $service->snapshot($admin, 'custom', '2026-01-01', '2026-06-01', $unit->id);
            $this->fail('Intervalo acima de 90 dias deveria ser rejeitado.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('customFrom', $exception->errors());
        }

        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->set('customFrom', '2026-09-20')
            ->set('customTo', '2026-09-01')
            ->call('applyCustomPeriod')
            ->assertHasErrors('customFrom')
            ->assertSet('period', 'today');
    }

    public function test_active_desks_follow_the_current_unit_and_real_status(): void
    {
        [$clinic, $unit, $admin] = $this->clinicWithAdmin();
        $other = Unit::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Unidade Sul', 'active' => true]);
        $normal = $this->type($clinic, 'Normal', 'N', 10);

        $busy = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Mesa Alfa',
            'active' => true,
        ]);
        Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Mesa Beta',
            'active' => true,
        ]);
        Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Mesa Gama',
            'active' => false,
        ]);
        Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $other->id,
            'name' => 'Mesa Oculta',
            'active' => true,
        ]);

        $issued = CarbonImmutable::now((string) config('app.timezone'))->subMinutes(6)->subSeconds(24);
        $this->ticket($clinic, $unit, $normal, [
            'sequence_number' => 17,
            'status' => TicketStatus::IN_SERVICE,
            'issued_at' => $issued,
            'called_at' => $issued,
            'service_started_at' => $issued,
            'current_desk_id' => $busy->id,
        ]);

        $snapshot = $this->snapshot($admin, $unit->id);

        $this->assertSame('2 / 3', $snapshot['desks_summary']['display']);
        $this->assertSame(67, $snapshot['desks_summary']['percent']);
        $rows = collect($snapshot['desks'])->keyBy('name');
        $this->assertSame('Atendendo', $rows['Mesa Alfa']['status']);
        $this->assertSame('N017', $rows['Mesa Alfa']['ticket']);
        $this->assertSame('Disponível', $rows['Mesa Beta']['status']);
        $this->assertSame('Inativa', $rows['Mesa Gama']['status']);
        $this->assertArrayNotHasKey('Mesa Oculta', $rows->all());

        $this->actingAs($admin)
            ->withSession([OperationalContext::UNIT_SESSION_KEY => $unit->id])
            ->get(route('dashboard'))
            ->assertSee('Mesa Alfa')
            ->assertSee('N017')
            ->assertSee('Atendendo')
            ->assertDontSee('Mesa Oculta');
    }

    public function test_activity_and_metrics_never_cross_clinic_or_unauthorized_unit(): void
    {
        [$clinic, $unit, $admin] = $this->clinicWithAdmin();
        $normal = $this->type($clinic, 'Normal', 'AA', 10);
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Mesa Local',
        ]);
        $local = $this->ticket($clinic, $unit, $normal, [
            'sequence_number' => 1,
            'status' => TicketStatus::CALLED,
            'current_desk_id' => $desk->id,
            'called_at' => now(),
        ]);
        $this->callTicket($local, $desk, $admin, CarbonImmutable::now(), TicketCallType::INITIAL);

        $otherClinic = Clinic::factory()->create(['name' => 'Clínica Vizinha']);
        $otherUnit = Unit::factory()->create(['clinic_id' => $otherClinic->id, 'active' => true]);
        $otherAdmin = User::factory()->create([
            'clinic_id' => $otherClinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
        $otherType = $this->type($otherClinic, 'Emergencial', 'ZZ', 30);
        $otherDesk = Desk::factory()->create([
            'clinic_id' => $otherClinic->id,
            'unit_id' => $otherUnit->id,
            'name' => 'Guichê Estranho',
        ]);
        $foreign = $this->ticket($otherClinic, $otherUnit, $otherType, [
            'sequence_number' => 917,
            'status' => TicketStatus::WAITING,
        ]);
        $this->callTicket($foreign, $otherDesk, $otherAdmin, CarbonImmutable::now(), TicketCallType::INITIAL);

        $sibling = Unit::factory()->create(['clinic_id' => $clinic->id, 'name' => 'Unidade Restrita', 'active' => true]);
        $hiddenType = $this->type($clinic, 'Oculta', 'HH', 5);
        $this->ticket($clinic, $sibling, $hiddenType, [
            'sequence_number' => 4,
            'status' => TicketStatus::WAITING,
        ]);
        Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $sibling->id,
            'name' => 'Mesa Restrita',
        ]);

        $supervisor = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::SUPERVISOR,
        ]);
        $supervisor->units()->attach($unit->id, ['clinic_id' => $clinic->id]);

        $own = $this->snapshot($admin);
        $this->assertSame(1, $own['waiting']['value']);
        $this->assertTrue(collect($own['activity'])->pluck('title')->contains(fn (string $title): bool => str_contains($title, 'AA001')));
        $this->assertFalse(collect($own['activity'])->pluck('title')->contains(fn (string $title): bool => str_contains($title, 'ZZ917')));

        $limited = app(AdminDashboardMetricsService::class)->snapshot($supervisor, 'today', null, null, null);
        $this->assertSame(0, $limited['waiting']['value']);
        $this->assertFalse(collect($limited['desks'])->pluck('name')->contains('Mesa Restrita'));

        try {
            app(AdminDashboardMetricsService::class)->snapshot($supervisor, 'today', null, null, $sibling->id);
            $this->fail('Unidade não autorizada deveria responder 403.');
        } catch (HttpException $exception) {
            $this->assertSame(403, $exception->getStatusCode());
        }

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertSee('AA001')
            ->assertDontSee('ZZ917')
            ->assertDontSee('Guichê Estranho');

        Livewire::actingAs($supervisor)
            ->test(AdminDashboard::class)
            ->set('unitKey', (string) $sibling->id)
            ->assertForbidden();
    }

    public function test_dashboard_renders_empty_states_without_tickets_desks_or_panels(): void
    {
        [, , $admin] = $this->clinicWithAdmin();

        $snapshot = $this->snapshot($admin);

        $this->assertSame(0, $snapshot['issued']['value']);
        $this->assertSame('—', $snapshot['wait']['display']);
        $this->assertSame('—', $snapshot['desks_summary']['display']);
        $this->assertNull($snapshot['desks_summary']['percent']);
        $this->assertTrue($snapshot['chart']['empty']);
        $this->assertSame([], $snapshot['desks']);
        $this->assertSame([], $snapshot['panels']);
        $this->assertSame([], $snapshot['activity']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertSee('Nenhuma senha emitida hoje.')
            ->assertSee('Nenhuma senha aguardando no momento.')
            ->assertSee('Nenhum atendimento em andamento.')
            ->assertSee('Nenhum guichê cadastrado.')
            ->assertSee('Nenhum painel cadastrado.')
            ->assertSee('Nenhuma atividade no período.')
            ->assertDontSee('0 / 0')
            ->assertDontSee('NaN');
    }

    public function test_panels_use_active_flag_instead_of_invented_heartbeat(): void
    {
        [$clinic, $unit, $admin] = $this->clinicWithAdmin();
        DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Recepção',
            'active' => true,
        ]);
        DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Consultório 2',
            'active' => false,
        ]);

        $snapshot = $this->snapshot($admin, $unit->id);
        $panels = collect($snapshot['panels'])->keyBy('name');

        $this->assertSame('Ativo', $panels['Recepção']['status']);
        $this->assertSame('Inativo', $panels['Consultório 2']['status']);

        $this->actingAs($admin)
            ->get(route('dashboard'))
            ->assertSee('Recepção')
            ->assertSee('Inativo')
            ->assertDontSee('Offline');
    }

    public function test_seven_and_thirty_day_periods_update_the_dashboard_label(): void
    {
        [, , $admin] = $this->clinicWithAdmin();

        Livewire::actingAs($admin)
            ->test(AdminDashboard::class)
            ->assertSee('Senhas hoje')
            ->call('setPeriod', '7d')
            ->assertSee('Senhas no período')
            ->assertSee('Quantidade de senhas por dia')
            ->call('setPeriod', '30d')
            ->assertSee('Senhas no período')
            ->assertSet('period', '30d');
    }

    /**
     * @return array{0: Clinic, 1: Unit, 2: User}
     */
    private function clinicWithAdmin(): array
    {
        $clinic = Clinic::factory()->create(['name' => 'Clínica Norte']);
        $unit = Unit::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Unidade Centro',
            'active' => true,
        ]);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
            'name' => 'Ana Admin',
        ]);

        return [$clinic, $unit, $admin];
    }

    private function type(Clinic $clinic, string $name, string $prefix, int $priority): TicketType
    {
        return TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => $name,
            'prefix' => $prefix,
            'priority' => $priority,
            'active' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     */
    private function ticket(Clinic $clinic, Unit $unit, TicketType $type, array $overrides = []): Ticket
    {
        $issuedAt = $overrides['issued_at'] ?? now();

        return Ticket::factory()->create(array_merge([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'ticket_type_id' => $type->id,
            'sequence_number' => 1,
            'sequence_date' => CarbonImmutable::parse($issuedAt)->toDateString(),
            'status' => TicketStatus::WAITING,
            'issued_at' => $issuedAt,
            'queued_at' => $issuedAt,
        ], $overrides));
    }

    private function callTicket(Ticket $ticket, Desk $desk, User $user, CarbonImmutable $calledAt, TicketCallType $type): void
    {
        $call = new TicketCall;
        $call->forceFill([
            'clinic_id' => $ticket->clinic_id,
            'unit_id' => $ticket->unit_id,
            'sector_id' => $ticket->sector_id,
            'ticket_id' => $ticket->id,
            'desk_id' => $desk->id,
            'called_by_user_id' => $user->id,
            'call_type' => $type,
            'called_at' => $calledAt,
        ])->save();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(User $user, ?int $unitId = null): array
    {
        return app(AdminDashboardMetricsService::class)->snapshot($user, 'today', null, null, $unitId);
    }
}
