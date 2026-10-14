<?php

namespace Tests\Feature;

use App\Actions\CreateDesk;
use App\Livewire\DesksManager;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\DeskAssignment;
use App\Models\Sector;
use App\Models\Ticket;
use App\Models\TicketCall;
use App\Models\TicketTransfer;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\TicketCallType;
use App\TicketStatus;
use App\TicketTransferType;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class DeskScreenTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_search_and_filters_stay_inside_the_clinic(): void
    {
        $clinic = Clinic::factory()->create();
        $other = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $unitA = Unit::factory()->for($clinic)->create(['name' => 'Hospital Toledo']);
        $unitB = Unit::factory()->for($clinic)->create(['name' => 'Unidade Centro']);
        $foreignUnit = Unit::factory()->for($other)->create(['name' => 'Outra Clínica']);
        $reception = $this->sector($unitA, 'Geral', 'GERAL');
        $imaging = $this->sector($unitB, 'Imagem', 'IMG');
        $foreignSector = $this->sector($foreignUnit, 'Externo', 'EXT');

        Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unitA->id,
            'sector_id' => $reception->id,
            'name' => 'Guichê Alfa',
            'code' => 'GA1',
            'active' => true,
        ]);
        Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unitB->id,
            'sector_id' => $imaging->id,
            'name' => 'Guichê Beta',
            'code' => 'GB2',
            'active' => false,
        ]);
        Desk::factory()->create([
            'clinic_id' => $other->id,
            'unit_id' => $foreignUnit->id,
            'sector_id' => $foreignSector->id,
            'name' => 'Mesa Externa',
            'code' => 'XX9',
        ]);

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->assertSee('Guichê Alfa')
            ->assertSee('Guichê Beta')
            ->assertDontSee('Mesa Externa')
            ->assertSee('2 mesas')
            ->assertSee('1 ativa')
            ->assertSee('1 inativa')
            ->assertSee('0 em atendimento')
            ->set('search', 'alfa')
            ->assertSee('Guichê Alfa')
            ->assertDontSee('Guichê Beta')
            ->set('search', 'gb2')
            ->assertSee('Guichê Beta')
            ->assertDontSee('Guichê Alfa')
            ->set('search', 'nenhuma')
            ->assertSee('Nenhuma mesa encontrada')
            ->call('clearFilters')
            ->set('unitFilter', (string) $unitA->id)
            ->assertSee('Guichê Alfa')
            ->assertDontSee('Guichê Beta')
            ->assertSee('Geral')
            ->assertDontSee('Imagem')
            ->set('sectorFilter', (string) $reception->id)
            ->set('unitFilter', (string) $unitB->id)
            ->assertSet('sectorFilter', '')
            ->assertSee('Guichê Beta')
            ->set('sectorFilter', (string) $imaging->id)
            ->assertSee('Guichê Beta')
            ->assertDontSee('Guichê Alfa')
            ->set('sectorFilter', '')
            ->set('unitFilter', '')
            ->set('statusFilter', 'inactive')
            ->assertSee('Guichê Beta')
            ->assertDontSee('Guichê Alfa');
    }

    public function test_create_waits_for_confirmation_and_rejects_foreign_unit_or_sector(): void
    {
        $clinic = Clinic::factory()->create();
        $other = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $unitA = Unit::factory()->for($clinic)->create(['name' => 'Hospital Toledo']);
        $unitB = Unit::factory()->for($clinic)->create(['name' => 'Unidade Centro']);
        $foreignUnit = Unit::factory()->for($other)->create();
        $reception = $this->sector($unitA, 'Geral', 'GERAL');
        $imaging = $this->sector($unitB, 'Imagem', 'IMG');
        $foreignSector = $this->sector($foreignUnit, 'Externo', 'EXT');

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('startCreate')
            ->set('name', 'Guichê 05')
            ->set('code', 'g5')
            ->set('unitId', $unitA->id)
            ->set('sectorId', $reception->id)
            ->set('active', true)
            ->set('search', 'Guichê')
            ->set('unitFilter', (string) $unitA->id)
            ->call('review')
            ->assertSet('showCreateConfirmation', true)
            ->assertSet('name', 'Guichê 05')
            ->assertSet('code', 'G5')
            ->call('backToDeskForm')
            ->assertSet('showCreateConfirmation', false)
            ->assertSet('name', 'Guichê 05')
            ->assertSet('code', 'G5')
            ->assertSet('sectorId', $reception->id)
            ->call('review')
            ->call('confirmCreate')
            ->call('confirmCreate')
            ->assertSee('Mesa criada!')
            ->assertSet('search', 'Guichê')
            ->assertSet('unitFilter', (string) $unitA->id)
            ->call('dismissCreationOutcome')
            ->assertSet('showForm', false)
            ->assertSet('creationOutcome', '')
            ->call('startCreate')
            ->assertSet('showForm', true)
            ->assertSet('name', '');

        $this->assertSame(1, Desk::query()->where('code', 'G5')->count());
        $this->assertDatabaseHas('desks', [
            'clinic_id' => $clinic->id,
            'unit_id' => $unitA->id,
            'sector_id' => $reception->id,
            'name' => 'Guichê 05',
            'code' => 'G5',
        ]);

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('startCreate')
            ->set('name', 'Invasora')
            ->set('code', 'INV')
            ->set('unitId', $foreignUnit->id)
            ->set('sectorId', $foreignSector->id)
            ->call('review')
            ->assertHasErrors(['unitId'])
            ->assertSet('showCreateConfirmation', false);

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('startCreate')
            ->set('name', 'Setor Cruzado')
            ->set('code', 'CRZ')
            ->set('unitId', $unitA->id)
            ->set('sectorId', $imaging->id)
            ->call('review')
            ->assertHasErrors(['sectorId']);

        $this->assertDatabaseMissing('desks', ['name' => 'Invasora']);
        $this->assertDatabaseMissing('desks', ['name' => 'Setor Cruzado']);

        $this->expectException(ValidationException::class);
        app(CreateDesk::class)->handle($admin, [
            'name' => 'Ação direta',
            'code' => 'DIR',
            'unit_id' => $unitA->id,
            'sector_id' => $foreignSector->id,
            'active' => true,
        ]);
    }

    public function test_changing_unit_clears_a_sector_from_another_unit(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $unitA = Unit::factory()->for($clinic)->create();
        $unitB = Unit::factory()->for($clinic)->create();
        $reception = $this->sector($unitA, 'Geral', 'GERAL');
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unitA->id,
            'sector_id' => $reception->id,
            'name' => 'Guichê 02',
            'code' => 'G2',
        ]);

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('edit', $desk->id)
            ->set('unitId', $unitB->id)
            ->assertSet('sectorId', null)
            ->call('save')
            ->assertHasErrors(['sectorId']);

        $this->assertSame($unitA->id, $desk->fresh()->unit_id);
        $this->assertSame($reception->id, $desk->fresh()->sector_id);
    }

    public function test_open_attendance_blocks_deactivation_and_structural_move(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $unitA = Unit::factory()->for($clinic)->create(['name' => 'Hospital Toledo']);
        $unitB = Unit::factory()->for($clinic)->create(['name' => 'Unidade Centro']);
        $reception = $this->sector($unitA, 'Geral', 'GERAL');
        $imaging = $this->sector($unitB, 'Imagem', 'IMG');
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unitA->id,
            'sector_id' => $reception->id,
            'name' => 'Guichê 02',
            'code' => 'G2',
            'active' => true,
        ]);
        $ticket = Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unitA->id,
            'sector_id' => $reception->id,
            'current_desk_id' => $desk->id,
            'status' => TicketStatus::IN_SERVICE,
            'sequence_number' => 8,
        ]);

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->assertSee('1 em atendimento')
            ->assertSee('Em atendimento')
            ->call('confirmDeactivation', $desk->id)
            ->assertSet('deactivationOutcome', 'blocked')
            ->assertSee('Não é possível desativar')
            ->assertSee('atendimento em andamento');

        $this->assertTrue($desk->fresh()->active);
        $this->assertSame(TicketStatus::IN_SERVICE, $ticket->fresh()->status);

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('edit', $desk->id)
            ->set('unitId', $unitB->id)
            ->set('sectorId', $imaging->id)
            ->call('save')
            ->call('confirmUpdate')
            ->assertHasErrors(['unitId'])
            ->assertSee('Finalize o atendimento antes de alterar sua unidade ou setor.');

        $this->assertSame($unitA->id, $desk->fresh()->unit_id);
        $this->assertSame($reception->id, $desk->fresh()->sector_id);

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('confirmDeletion', $desk->id)
            ->call('requestPermanentDeletion')
            ->set('deletionConfirmation', 'EXCLUIR')
            ->call('deleteDesk')
            ->assertSet('deletionOutcome', 'blocked')
            ->assertDontSee('SQLSTATE');

        $this->assertDatabaseHas('desks', ['id' => $desk->id]);
        $this->assertSame(TicketStatus::IN_SERVICE, $ticket->fresh()->status);
        $this->assertSame($desk->id, $ticket->fresh()->current_desk_id);
    }

    public function test_waiting_targeted_tickets_still_block_deactivation(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $unit = Unit::factory()->for($clinic)->create();
        $sector = $this->sector($unit, 'Geral', 'GERAL');
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'active' => true,
        ]);
        Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'target_desk_id' => $desk->id,
            'status' => TicketStatus::WAITING,
            'sequence_number' => 3,
        ]);

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('confirmDeactivation', $desk->id)
            ->assertSet('deactivationOutcome', 'blocked')
            ->assertSee('senhas aguardando direcionadas');

        $this->assertTrue($desk->fresh()->active);
    }

    public function test_deletion_requires_typed_confirmation_preserves_history_and_rolls_back(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $attendant = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ATTENDANT,
        ]);
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Hospital Toledo']);
        $sector = $this->sector($unit, 'Geral', 'GERAL');
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'name' => 'Guichê 02',
            'code' => 'G2',
        ]);
        $otherDesk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'name' => 'Guichê 03',
            'code' => 'G3',
        ]);
        $ticketType = TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'prefix' => 'H',
            'priority' => 10,
        ]);
        $ticket = Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'ticket_type_id' => $ticketType->id,
            'current_desk_id' => $desk->id,
            'status' => TicketStatus::COMPLETED,
            'sequence_number' => 17,
        ]);
        $call = new TicketCall;
        $call->forceFill([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'ticket_id' => $ticket->id,
            'desk_id' => $desk->id,
            'called_by_user_id' => $admin->id,
            'call_type' => TicketCallType::INITIAL,
            'called_at' => now(),
        ])->save();
        $transfer = new TicketTransfer;
        $transfer->forceFill([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'ticket_id' => $ticket->id,
            'from_desk_id' => $desk->id,
            'to_desk_id' => $otherDesk->id,
            'transferred_by_user_id' => $admin->id,
            'transfer_type' => TicketTransferType::DESK,
            'transferred_at' => now(),
        ])->save();
        $assignment = new DeskAssignment;
        $assignment->forceFill([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'desk_id' => $desk->id,
            'user_id' => $attendant->id,
            'claimed_at' => now(),
            'last_seen_at' => now(),
        ])->save();

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('confirmDeletion', $desk->id)
            ->assertSee('1 atendente vinculado')
            ->assertSee('1 atendimento histórico')
            ->assertSee('1 chamada registrada')
            ->assertSee('1 transferência')
            ->call('deleteDesk')
            ->assertHasErrors(['deletionConfirmation'])
            ->set('deletionConfirmation', 'excluir')
            ->call('requestPermanentDeletion')
            ->call('deleteDesk')
            ->assertHasErrors(['deletionConfirmation']);

        $this->assertDatabaseHas('desks', ['id' => $desk->id]);
        $this->assertSame($desk->id, $ticket->fresh()->current_desk_id);

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('confirmDeletion', $desk->id)
            ->call('requestPermanentDeletion')
            ->set('deletionConfirmation', 'EXCLUIR')
            ->call('deleteDesk')
            ->assertSee('Mesa excluída!');

        $this->assertDatabaseMissing('desks', ['id' => $desk->id]);
        $this->assertDatabaseMissing('desk_assignments', ['id' => $assignment->id]);
        $this->assertDatabaseHas('tickets', ['id' => $ticket->id]);
        $this->assertNull($ticket->fresh()->current_desk_id);
        $this->assertSame('Guichê 02', $ticket->fresh()->archived_desk_name);
        $this->assertDatabaseHas('ticket_calls', ['id' => $call->id]);
        $this->assertNull($call->fresh()->desk_id);
        $this->assertSame('Guichê 02', $call->fresh()->archived_desk_name);
        $this->assertDatabaseHas('ticket_transfers', ['id' => $transfer->id]);
        $this->assertNull($transfer->fresh()->from_desk_id);
        $this->assertSame($otherDesk->id, $transfer->fresh()->to_desk_id);
        $this->assertSame('Guichê 02', $transfer->fresh()->archived_from_desk_name);
        $this->assertDatabaseHas('desks', ['id' => $otherDesk->id]);

        $kept = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'name' => 'Guichê Mantido',
            'code' => 'GM',
        ]);
        $keptTicket = Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'ticket_type_id' => $ticketType->id,
            'current_desk_id' => $kept->id,
            'status' => TicketStatus::COMPLETED,
            'sequence_number' => 18,
        ]);

        Desk::deleting(function (): void {
            throw new RuntimeException('forced-failure');
        });

        try {
            Livewire::actingAs($admin)
                ->test(DesksManager::class)
                ->call('confirmDeletion', $kept->id)
                ->call('requestPermanentDeletion')
                ->set('deletionConfirmation', 'EXCLUIR')
                ->call('deleteDesk')
                ->assertSet('deletionOutcome', 'error')
                ->assertSee('Os dados foram preservados. Tente novamente.')
                ->assertDontSee('forced-failure')
                ->assertDontSee('SQLSTATE');
        } finally {
            Desk::flushEventListeners();
        }

        $this->assertDatabaseHas('desks', ['id' => $kept->id]);
        $this->assertSame($kept->id, $keptTicket->fresh()->current_desk_id);
        $this->assertNull($keptTicket->fresh()->archived_desk_name);
    }

    public function test_cross_tenant_delete_is_rejected(): void
    {
        $clinic = Clinic::factory()->create();
        $other = Clinic::factory()->create();
        $admin = $this->administrator($clinic);
        $foreign = Desk::factory()->create([
            'clinic_id' => $other->id,
            'name' => 'Mesa Alheia',
        ]);

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->call('confirmDeletion', $foreign->id)
            ->assertNotFound();

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->set('deskPendingDeletionId', $foreign->id)
            ->set('deletionConfirmation', 'EXCLUIR')
            ->call('deleteDesk')
            ->assertNotFound();

        $this->assertDatabaseHas('desks', ['id' => $foreign->id]);
    }

    public function test_empty_clinic_offers_the_first_desk(): void
    {
        $admin = $this->administrator();

        Livewire::actingAs($admin)
            ->test(DesksManager::class)
            ->assertSee('Nenhuma mesa cadastrada')
            ->assertSee('Cadastre um ponto de atendimento para começar.')
            ->assertSee('0 mesas')
            ->assertSee('0 em atendimento');
    }

    private function sector(Unit $unit, string $name, string $code): Sector
    {
        return Sector::factory()->create([
            'clinic_id' => $unit->clinic_id,
            'unit_id' => $unit->id,
            'name' => $name,
            'code' => $code,
        ]);
    }

    private function administrator(?Clinic $clinic = null): User
    {
        $clinic ??= Clinic::factory()->create();

        return User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
    }
}
