<?php

namespace Tests\Feature;

use App\Livewire\UnitsManager;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\DisplayPanel;
use App\Models\Kiosk;
use App\Models\MediaItem;
use App\Models\Sector;
use App\Models\Ticket;
use App\Models\TicketCall;
use App\Models\TicketSequence;
use App\Models\TicketTransfer;
use App\Models\TicketType;
use App\Models\Unit;
use App\Models\User;
use App\Services\OperationalContext;
use App\TicketCallType;
use App\TicketTransferType;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class UnitManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_supervisor_cannot_open_units_manager(): void
    {
        $clinic = Clinic::factory()->create();
        $supervisor = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::SUPERVISOR,
        ]);

        $this->actingAs($supervisor)->get(route('clinic.show'))->assertForbidden();

        Livewire::actingAs($supervisor)
            ->test(UnitsManager::class)
            ->assertForbidden();
    }

    public function test_administrator_lists_own_units_only(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $ownUnit = Unit::factory()->for($clinicA)->create(['name' => 'Unidade Centro A']);
        $foreignUnit = Unit::factory()->for($clinicB)->create(['name' => 'Unidade Centro B']);
        $admin = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        $this->actingAs($admin)
            ->get(route('clinic.show'))
            ->assertOk()
            ->assertSee('Unidade Centro A')
            ->assertDontSee('Unidade Centro B');

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->assertSee('Unidade Centro A')
            ->assertDontSee('Unidade Centro B');

        $this->assertTrue($admin->can('view', $ownUnit));
        $this->assertFalse($admin->can('view', $foreignUnit));
    }

    public function test_administrator_creates_unit_in_own_clinic_and_ignores_foreign_clinic_id(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $admin = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->set('name', 'Recepção Norte')
            ->set('slug', 'recepcao-norte')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showCreateConfirmation', true);

        $this->assertDatabaseMissing('units', [
            'clinic_id' => $clinicA->id,
            'slug' => 'recepcao-norte',
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->set('name', 'Recepção Norte')
            ->set('slug', 'recepcao-norte')
            ->call('save')
            ->call('confirmCreate')
            ->assertHasNoErrors()
            ->assertSee('Unidade criada!');

        $this->assertDatabaseHas('units', [
            'clinic_id' => $clinicA->id,
            'name' => 'Recepção Norte',
            'slug' => 'recepcao-norte',
            'active' => true,
        ]);
        $this->assertDatabaseMissing('units', [
            'clinic_id' => $clinicB->id,
            'slug' => 'recepcao-norte',
        ]);
    }

    public function test_administrator_cannot_edit_or_deactivate_foreign_unit(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $foreignUnit = Unit::factory()->for($clinicB)->create(['name' => 'Unidade Centro B', 'slug' => 'centro-b']);
        $admin = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('edit', $foreignUnit->id)
            ->assertNotFound();

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('confirmDeactivation', $foreignUnit->id)
            ->assertNotFound();

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('activate', $foreignUnit->id)
            ->assertNotFound();

        $this->assertDatabaseHas('units', [
            'id' => $foreignUnit->id,
            'name' => 'Unidade Centro B',
            'active' => true,
        ]);
    }

    public function test_administrator_edits_and_toggles_own_unit(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Recepção', 'slug' => 'recepcao']);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('edit', $unit->id)
            ->set('name', 'Recepção Central')
            ->set('slug', 'recepcao-central')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Alterações salvas');

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'clinic_id' => $clinic->id,
            'name' => 'Recepção Central',
            'slug' => 'recepcao-central',
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('confirmDeactivation', $unit->id)
            ->call('deactivate')
            ->assertSee('Unidade desativada.');

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'active' => false,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('activate', $unit->id)
            ->assertSee('Unidade ativada.');

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'active' => true,
        ]);
    }

    public function test_unit_slug_is_unique_inside_clinic_and_reusable_in_another_clinic(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        Unit::factory()->for($clinicA)->create(['slug' => 'recepcao']);
        Unit::factory()->for($clinicB)->create(['slug' => 'recepcao']);
        $admin = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->set('name', 'Outra Recepção')
            ->set('slug', 'recepcao')
            ->call('save')
            ->assertHasErrors(['slug']);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->set('name', 'Triagem')
            ->set('slug', 'triagem')
            ->call('save')
            ->call('confirmCreate')
            ->assertHasNoErrors();

        $this->assertDatabaseHas('units', [
            'clinic_id' => $clinicA->id,
            'slug' => 'triagem',
        ]);
        $this->assertSame(1, Unit::query()->where('clinic_id', $clinicB->id)->where('slug', 'recepcao')->count());
    }

    public function test_empty_slug_is_generated_from_the_unit_name(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('startCreate')
            ->set('name', 'Recepção Sul')
            ->set('slug', '')
            ->call('save')
            ->assertSet('showCreateConfirmation', true)
            ->call('confirmCreate')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('Unidade criada!');

        $this->assertDatabaseHas('units', [
            'clinic_id' => $clinic->id,
            'name' => 'Recepção Sul',
            'slug' => 'recepcao-sul',
        ]);
    }

    public function test_creating_a_unit_requires_a_name_and_keeps_the_form_open(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        $existingUnits = Unit::query()->where('clinic_id', $clinic->id)->count();

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('startCreate')
            ->set('name', '')
            ->set('slug', '')
            ->call('save')
            ->assertHasErrors(['name'])
            ->assertSet('showForm', true)
            ->assertSet('showCreateConfirmation', false)
            ->assertDontSee('Unidade criada!');

        $this->assertSame($existingUnits, Unit::query()->where('clinic_id', $clinic->id)->count());
    }

    public function test_renaming_a_unit_does_not_change_its_identifier(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Recepção', 'slug' => 'recepcao']);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('edit', $unit->id)
            ->set('name', 'Recepção Central')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSee('Alterações salvas')
            ->assertDontSee('Unidade criada!');

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'name' => 'Recepção Central',
            'slug' => 'recepcao',
        ]);
    }

    public function test_deactivation_waits_for_confirmation_and_preserves_history(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Recepção', 'active' => true]);
        $ticket = Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
        ]);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('confirmDeactivation', $unit->id)
            ->assertSet('unitPendingDeactivationId', $unit->id)
            ->assertSee('Desativar unidade?')
            ->assertSee('Recepção')
            ->assertDontSee('Unidade desativada.')
            ->call('cancelDeactivation')
            ->assertSet('unitPendingDeactivationId', null);

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'active' => true,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('confirmDeactivation', $unit->id)
            ->call('deactivate')
            ->assertSet('unitPendingDeactivationId', null)
            ->assertSee('Unidade desativada.');

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'active' => false,
        ]);
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'unit_id' => $unit->id,
        ]);
    }

    public function test_activation_waits_for_confirmation(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Recepção', 'active' => false]);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('confirmActivation', $unit->id)
            ->assertSet('unitPendingActivationId', $unit->id)
            ->assertSee('Ativar unidade?')
            ->assertDontSee('Unidade ativada.')
            ->call('activateConfirmed')
            ->assertSet('unitPendingActivationId', null)
            ->assertSee('Unidade ativada.');

        $this->assertDatabaseHas('units', [
            'id' => $unit->id,
            'active' => true,
        ]);
    }

    public function test_administrator_cannot_toggle_a_unit_from_another_clinic_by_id(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $foreignUnit = Unit::factory()->for($clinicB)->create(['active' => true]);
        $admin = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('confirmActivation', $foreignUnit->id)
            ->assertNotFound();

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->set('unitPendingDeactivationId', $foreignUnit->id)
            ->call('deactivate')
            ->assertNotFound();

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->set('unitPendingActivationId', $foreignUnit->id)
            ->call('activateConfirmed')
            ->assertNotFound();

        $this->assertDatabaseHas('units', [
            'id' => $foreignUnit->id,
            'active' => true,
        ]);
    }

    public function test_unit_counters_ignore_search_and_empty_clinic_has_no_table(): void
    {
        $clinic = Clinic::factory()->create();
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
        $admin->units()->detach();
        Unit::query()->where('clinic_id', $clinic->id)->delete();

        $this->actingAs($admin)
            ->get(route('clinic.show'))
            ->assertOk()
            ->assertSee('Nenhuma unidade cadastrada')
            ->assertSee('Cadastre a primeira unidade de atendimento desta clínica.')
            ->assertDontSee('Nova unidade');

        Unit::factory()->for($clinic)->create(['name' => 'Hospital Toledo', 'slug' => 'ht', 'active' => true]);
        Unit::factory()->for($clinic)->create(['name' => 'Recepção', 'slug' => 'recepcao', 'active' => false]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->assertSee('2 unidades')
            ->assertSee('1 ativa')
            ->assertSee('1 inativa')
            ->set('search', 'toledo')
            ->assertSee('Hospital Toledo')
            ->assertDontSee('Recepção')
            ->assertSee('2 unidades')
            ->assertSee('1 ativa')
            ->assertSee('1 inativa')
            ->set('search', '')
            ->set('statusFilter', 'inactive')
            ->assertSee('Recepção')
            ->assertDontSee('Hospital Toledo');
    }

    public function test_cancelling_creation_keeps_the_typed_values(): void
    {
        $clinic = Clinic::factory()->create(['name' => 'Clínica principal']);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('startCreate')
            ->set('name', 'Clínica Toledo')
            ->set('slug', 'clinica-toledo')
            ->call('save')
            ->assertSee('Criar nova unidade?')
            ->assertSee('Clínica Toledo')
            ->assertSee('clinica-toledo')
            ->call('cancelCreateConfirmation')
            ->assertSet('showCreateConfirmation', false)
            ->assertSet('showForm', true)
            ->assertSet('name', 'Clínica Toledo')
            ->assertSet('slug', 'clinica-toledo')
            ->assertDontSee('Unidade criada!');

        $this->assertDatabaseMissing('units', [
            'clinic_id' => $clinic->id,
            'slug' => 'clinica-toledo',
        ]);
    }

    public function test_authorized_user_sees_delete_action_and_cancel_keeps_the_unit(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Hospital Toledo']);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->assertSee('Excluir unidade')
            ->call('confirmDeletion', $unit->id)
            ->assertSet('unitPendingDeletionId', $unit->id)
            ->assertSee('Excluir unidade?')
            ->assertSee('Hospital Toledo')
            ->call('cancelDeletion')
            ->assertSet('unitPendingDeletionId', null)
            ->assertDontSee('Unidade excluída');

        $this->assertDatabaseHas('units', ['id' => $unit->id]);
    }

    public function test_supervisor_cannot_delete_a_unit(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create();
        $supervisor = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::SUPERVISOR,
        ]);

        $this->assertFalse($supervisor->can('delete', $unit));

        Livewire::actingAs($supervisor)
            ->test(UnitsManager::class)
            ->assertForbidden();

        $this->assertDatabaseHas('units', ['id' => $unit->id]);
    }

    public function test_deleting_a_unit_removes_only_its_exclusive_sectors_and_updates_counters(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Unidade A', 'slug' => 'unidade-a']);
        $other = Unit::factory()->for($clinic)->create(['name' => 'Unidade B', 'slug' => 'unidade-b']);
        $ownSector = Sector::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Recepção A',
        ]);
        $otherSector = Sector::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $other->id,
            'name' => 'Recepção B',
        ]);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
        $admin->units()->detach();
        Unit::query()->where('clinic_id', $clinic->id)->whereNotIn('id', [$unit->id, $other->id])->delete();

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->assertSee('2 unidades')
            ->call('confirmDeletion', $unit->id)
            ->assertSee('Excluir mesmo assim')
            ->assertSee('1 setor')
            ->call('deleteUnit')
            ->assertSet('unitPendingDeletionId', $unit->id)
            ->assertHasErrors(['deletionConfirmation'])
            ->call('requestPermanentDeletion')
            ->assertSet('showPermanentConfirmation', true)
            ->assertSee('Confirmação permanente')
            ->set('deletionConfirmation', 'excluir')
            ->call('deleteUnit')
            ->assertHasErrors(['deletionConfirmation'])
            ->set('deletionConfirmation', ' EXCLUIR ')
            ->call('deleteUnit')
            ->assertSet('unitPendingDeletionId', null)
            ->assertSee('Unidade excluída')
            ->assertSee('foi excluída permanentemente')
            ->assertSee('1 unidade')
            ->assertDontSee('unidade-a');

        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
        $this->assertDatabaseMissing('sectors', ['id' => $ownSector->id]);
        $this->assertDatabaseHas('units', ['id' => $other->id]);
        $this->assertDatabaseHas('sectors', ['id' => $otherSector->id, 'unit_id' => $other->id]);
    }

    public function test_confirmed_deletion_removes_a_populated_unit_and_keeps_attendance_history(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Recepção', 'slug' => 'recepcao-historico']);
        $sibling = Unit::factory()->for($clinic)->create(['name' => 'Outra unidade', 'slug' => 'outra-unidade']);
        $sector = Sector::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Guichê central',
        ]);
        $siblingSector = Sector::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $sibling->id,
            'name' => 'Setor irmão',
        ]);
        $desk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'name' => 'Mesa 01',
        ]);
        $destination = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'name' => 'Mesa 02',
        ]);
        $siblingDesk = Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $sibling->id,
            'sector_id' => $siblingSector->id,
            'name' => 'Mesa irmã',
        ]);
        $type = TicketType::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Normal',
            'prefix' => 'N',
        ]);
        $unit->ticketTypes()->attach($type->id, [
            'clinic_id' => $clinic->id,
            'active' => true,
            'position' => 1,
        ]);
        $kiosk = Kiosk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'name' => 'Totem recepção',
        ]);
        $panel = DisplayPanel::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'TV recepção',
        ]);
        $panel->sectors()->attach($sector->id, ['clinic_id' => $clinic->id]);
        $media = MediaItem::factory()->create([
            'clinic_id' => $clinic->id,
            'name' => 'Aviso da clínica',
        ]);
        $panel->mediaItems()->attach($media->id, [
            'clinic_id' => $clinic->id,
            'position' => 1,
            'duration_seconds' => 8,
            'active' => true,
        ]);
        $ticket = Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $sector->id,
            'ticket_type_id' => $type->id,
            'current_desk_id' => $desk->id,
            'target_desk_id' => $destination->id,
            'kiosk_id' => $kiosk->id,
            'sequence_number' => 7,
        ]);
        $siblingTicket = Ticket::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $sibling->id,
            'sector_id' => $siblingSector->id,
            'ticket_type_id' => $type->id,
            'current_desk_id' => $siblingDesk->id,
            'sequence_number' => 3,
        ]);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
        $admin->units()->detach();
        $admin->units()->attach($unit->id, ['clinic_id' => $clinic->id]);
        Unit::query()->where('clinic_id', $clinic->id)->whereNotIn('id', [$unit->id, $sibling->id])->delete();
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
            'to_desk_id' => $destination->id,
            'transferred_by_user_id' => $admin->id,
            'transfer_type' => TicketTransferType::DESK,
            'transferred_at' => now(),
        ])->save();
        $sequence = new TicketSequence;
        $sequence->forceFill([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'ticket_type_id' => $type->id,
            'sequence_date' => now()->toDateString(),
            'last_number' => 7,
        ])->save();
        $foreignClinic = Clinic::factory()->create();
        $foreignUnit = Unit::factory()->for($foreignClinic)->create(['name' => 'Unidade externa']);
        $foreignTicket = Ticket::factory()->create([
            'clinic_id' => $foreignClinic->id,
            'unit_id' => $foreignUnit->id,
            'sequence_number' => 1,
        ]);

        session([
            OperationalContext::UNIT_SESSION_KEY => $unit->id,
            OperationalContext::DESK_SESSION_KEY => $desk->id,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('confirmDeletion', $unit->id)
            ->assertSee('Excluir mesmo assim')
            ->assertSee('atendimento histórico')
            ->call('deleteUnit')
            ->assertHasErrors(['deletionConfirmation'])
            ->call('requestPermanentDeletion')
            ->assertSee('Excluindo unidade...')
            ->assertSee('Não feche esta janela.')
            ->set('deletionConfirmation', 'excluir')
            ->call('deleteUnit')
            ->assertHasErrors(['deletionConfirmation'])
            ->set('deletionConfirmation', 'EXCLUIR')
            ->call('deleteUnit')
            ->assertSet('deletionOutcome', 'success')
            ->assertSet('unitPendingDeletionId', null)
            ->assertSee('Unidade excluída!')
            ->assertSee('foi excluída permanentemente')
            ->assertSee('Os dados vinculados foram tratados com sucesso.')
            ->assertDontSee('recepcao-historico');

        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
        $this->assertDatabaseMissing('sectors', ['id' => $sector->id]);
        $this->assertDatabaseMissing('desks', ['id' => $desk->id]);
        $this->assertDatabaseMissing('desks', ['id' => $destination->id]);
        $this->assertDatabaseMissing('kiosks', ['id' => $kiosk->id]);
        $this->assertDatabaseMissing('display_panels', ['id' => $panel->id]);
        $this->assertDatabaseMissing('display_panel_media', ['display_panel_id' => $panel->id]);
        $this->assertDatabaseMissing('unit_ticket_types', ['unit_id' => $unit->id]);
        $this->assertDatabaseMissing('ticket_sequences', ['id' => $sequence->id]);
        $this->assertDatabaseMissing('unit_user', ['unit_id' => $unit->id]);
        $this->assertDatabaseHas('tickets', [
            'id' => $ticket->id,
            'clinic_id' => $clinic->id,
            'unit_id' => null,
            'sector_id' => null,
            'current_desk_id' => null,
            'target_desk_id' => null,
            'kiosk_id' => null,
            'archived_unit_name' => 'Recepção',
            'archived_sector_name' => 'Guichê central',
            'archived_desk_name' => 'Mesa 01',
            'ticket_type_id' => $type->id,
            'sequence_number' => 7,
        ]);
        $this->assertDatabaseHas('ticket_calls', [
            'id' => $call->id,
            'ticket_id' => $ticket->id,
            'unit_id' => null,
            'desk_id' => null,
            'archived_unit_name' => 'Recepção',
            'archived_desk_name' => 'Mesa 01',
        ]);
        $this->assertDatabaseHas('ticket_transfers', [
            'id' => $transfer->id,
            'ticket_id' => $ticket->id,
            'unit_id' => null,
            'from_desk_id' => null,
            'to_desk_id' => null,
            'archived_unit_name' => 'Recepção',
            'archived_from_desk_name' => 'Mesa 01',
            'archived_to_desk_name' => 'Mesa 02',
        ]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
        $this->assertDatabaseHas('ticket_types', ['id' => $type->id]);
        $this->assertDatabaseHas('media_items', ['id' => $media->id]);
        $this->assertDatabaseHas('units', ['id' => $sibling->id]);
        $this->assertDatabaseHas('sectors', ['id' => $siblingSector->id, 'unit_id' => $sibling->id]);
        $this->assertDatabaseHas('desks', ['id' => $siblingDesk->id, 'unit_id' => $sibling->id]);
        $this->assertDatabaseHas('tickets', ['id' => $siblingTicket->id, 'unit_id' => $sibling->id]);
        $this->assertDatabaseHas('units', ['id' => $foreignUnit->id, 'clinic_id' => $foreignClinic->id]);
        $this->assertDatabaseHas('tickets', ['id' => $foreignTicket->id, 'unit_id' => $foreignUnit->id]);
        $this->assertNull(session(OperationalContext::UNIT_SESSION_KEY));
        $this->assertNull(session(OperationalContext::DESK_SESSION_KEY));
    }

    public function test_administrator_cannot_delete_a_unit_from_another_clinic(): void
    {
        $clinicA = Clinic::factory()->create();
        $clinicB = Clinic::factory()->create();
        $foreign = Unit::factory()->for($clinicB)->create();
        $admin = User::factory()->create([
            'clinic_id' => $clinicA->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('confirmDeletion', $foreign->id)
            ->assertNotFound();

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->set('unitPendingDeletionId', $foreign->id)
            ->call('deleteUnit')
            ->assertNotFound();

        $this->assertDatabaseHas('units', ['id' => $foreign->id]);
    }

    public function test_failed_deletion_rolls_back_and_does_not_show_success(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Unidade temporária']);
        $sector = Sector::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
        ]);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Unit::deleting(function (): void {
            throw new \RuntimeException('forced-failure');
        });

        try {
            Livewire::actingAs($admin)
                ->test(UnitsManager::class)
                ->call('confirmDeletion', $unit->id)
                ->set('deletionConfirmation', 'EXCLUIR')
                ->call('deleteUnit')
                ->assertSet('unitPendingDeletionId', $unit->id)
                ->assertSet('deletionOutcome', 'error')
                ->assertSee('Não foi possível concluir a exclusão.')
                ->assertSee('Nenhuma alteração parcial foi mantida.')
                ->assertSee('Tentar novamente')
                ->assertDontSee('Unidade excluída');
        } finally {
            Unit::flushEventListeners();
        }

        $this->assertDatabaseHas('units', ['id' => $unit->id]);
        $this->assertDatabaseHas('sectors', ['id' => $sector->id, 'unit_id' => $unit->id]);
    }

    public function test_permanent_confirmation_returns_to_the_warning_without_deleting(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Unidade reversível']);
        Sector::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
        ]);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('confirmDeletion', $unit->id)
            ->call('requestPermanentDeletion')
            ->assertSee('Confirmação permanente')
            ->set('deletionConfirmation', 'EXCLUIR')
            ->call('backToDeletionWarning')
            ->assertSet('showPermanentConfirmation', false)
            ->assertSet('deletionConfirmation', '')
            ->assertSet('unitPendingDeletionId', $unit->id)
            ->assertSee('Excluir mesmo assim')
            ->assertDontSee('Unidade excluída');

        $this->assertDatabaseHas('units', ['id' => $unit->id]);
    }

    public function test_unit_without_dependencies_can_be_deleted_from_the_first_dialog(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Unidade vazia', 'slug' => 'unidade-vazia']);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
        $admin->units()->detach();
        Unit::query()->where('clinic_id', $clinic->id)->whereKeyNot($unit->id)->delete();

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('confirmDeletion', $unit->id)
            ->assertSee('Sim, excluir unidade')
            ->assertDontSee('Excluir mesmo assim')
            ->call('deleteUnit')
            ->assertSee('Unidade excluída')
            ->assertDontSee('unidade-vazia');

        $this->assertDatabaseMissing('units', ['id' => $unit->id]);
    }

    public function test_successful_deletion_clears_the_dialog_and_allows_another(): void
    {
        $clinic = Clinic::factory()->create();
        $first = Unit::factory()->for($clinic)->create(['name' => 'Unidade A', 'slug' => 'unidade-a']);
        $second = Unit::factory()->for($clinic)->create(['name' => 'Unidade B', 'slug' => 'unidade-b']);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);
        $admin->units()->detach();
        Unit::query()->where('clinic_id', $clinic->id)->whereNotIn('id', [$first->id, $second->id])->delete();

        Livewire::actingAs($admin)
            ->test(UnitsManager::class)
            ->call('confirmDeletion', $first->id)
            ->assertSee('data-hc-dialog-lock', false)
            ->assertSee('this.release()', false)
            ->call('deleteUnit')
            ->assertSet('deletionOutcome', 'success')
            ->assertSet('deletionOutcomeName', 'Unidade A')
            ->call('dismissDeletionOutcome')
            ->assertSet('deletionOutcome', '')
            ->assertSet('deletionOutcomeName', '')
            ->assertSet('unitPendingDeletionId', null)
            ->assertSet('showPermanentConfirmation', false)
            ->assertSet('deletionConfirmation', '')
            ->assertSee('Adicionar unidade')
            ->assertDontSee('unidade-a')
            ->call('confirmDeletion', $second->id)
            ->assertSet('unitPendingDeletionId', $second->id)
            ->assertSet('deletionOutcome', '')
            ->call('cancelDeletion')
            ->assertSet('unitPendingDeletionId', null)
            ->assertSet('deletionConfirmation', '')
            ->assertSet('deletionOutcome', '');

        $this->assertDatabaseMissing('units', ['id' => $first->id]);
        $this->assertDatabaseHas('units', ['id' => $second->id]);
    }

    public function test_failed_deletion_can_be_dismissed_and_started_again(): void
    {
        $clinic = Clinic::factory()->create();
        $unit = Unit::factory()->for($clinic)->create(['name' => 'Unidade retentável']);
        $other = Unit::factory()->for($clinic)->create(['name' => 'Unidade seguinte']);
        Sector::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
        ]);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        Unit::deleting(function (): void {
            throw new \RuntimeException('forced-failure');
        });

        try {
            Livewire::actingAs($admin)
                ->test(UnitsManager::class)
                ->call('confirmDeletion', $unit->id)
                ->call('requestPermanentDeletion')
                ->set('deletionConfirmation', 'EXCLUIR')
                ->call('deleteUnit')
                ->assertSet('deletionOutcome', 'error')
                ->assertSet('unitPendingDeletionId', $unit->id)
                ->assertDontSee('Unidade excluída')
                ->call('cancelDeletion')
                ->assertSet('deletionOutcome', '')
                ->assertSet('unitPendingDeletionId', null)
                ->assertSet('deletionConfirmation', '')
                ->assertSet('showPermanentConfirmation', false)
                ->call('confirmDeletion', $other->id)
                ->assertSet('unitPendingDeletionId', $other->id)
                ->assertSet('deletionOutcome', '');
        } finally {
            Unit::flushEventListeners();
        }

        $this->assertDatabaseHas('units', ['id' => $unit->id]);
        $this->assertDatabaseHas('units', ['id' => $other->id]);
    }

    private function permanentDeletionButtonIsDisabled(string $html): bool
    {
        $matched = preg_match(
            '/<button\b([^>]*)>(?:(?!<\/button>).)*Excluir permanentemente/s',
            $html,
            $button,
        );

        $this->assertSame(1, $matched);

        return preg_match('/(?:^|\s)disabled(?:\s|=|>)/', $button[1]) === 1;
    }
}
