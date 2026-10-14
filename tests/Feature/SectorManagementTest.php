<?php

namespace Tests\Feature;

use App\Actions\CreateSector;
use App\Actions\EnsureClinicRolePermissions;
use App\Livewire\SectorsManager;
use App\Models\Clinic;
use App\Models\Desk;
use App\Models\Sector;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use RuntimeException;
use Tests\TestCase;

class SectorManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_listing_search_and_filters_stay_inside_the_clinic(): void
    {
        [$clinic, $unit, $admin] = $this->clinicWithAdmin('Hospital Toledo');
        $otherUnit = Unit::factory()->for($clinic)->create(['name' => 'Unidade Centro']);
        $otherClinic = Clinic::factory()->create();
        $foreignUnit = Unit::factory()->for($otherClinic)->create(['name' => 'Unidade Alheia']);
        $foreignAdmin = User::factory()->create(['clinic_id' => $otherClinic->id, 'role' => UserRole::ADMINISTRATOR]);

        $reception = $this->sector($admin, $unit, 'Recepção', 'REC');
        $inactive = $this->sector($admin, $otherUnit, 'Arquivo', 'ARQ', false);
        $this->sector($foreignAdmin, $foreignUnit, 'Setor Alheio', 'ALH');

        Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $reception->id,
            'name' => 'Mesa 1',
            'code' => 'M1',
        ]);

        Livewire::actingAs($admin)
            ->test(SectorsManager::class)
            ->assertSee('Recepção')
            ->assertSee('Arquivo')
            ->assertDontSee('Setor Alheio')
            ->assertSee('2 setores')
            ->assertSee('1 ativo')
            ->assertSee('1 inativo')
            ->set('search', 'recep')
            ->assertSee('Recepção')
            ->assertDontSee('Arquivo')
            ->set('search', '')
            ->set('unitFilter', (string) $otherUnit->id)
            ->assertSee('Arquivo')
            ->assertDontSee('Recepção')
            ->set('unitFilter', '')
            ->set('statusFilter', 'inactive')
            ->assertSee('Arquivo')
            ->assertDontSee('Recepção')
            ->set('statusFilter', 'active')
            ->set('unitFilter', (string) $unit->id)
            ->set('search', 'REC')
            ->assertSee('Recepção')
            ->assertDontSee('Arquivo')
            ->assertSee('1 mesa vinculada');

        $this->assertSame(2, Sector::query()->where('clinic_id', $clinic->id)->count());
        $this->assertFalse($inactive->active);
    }

    public function test_unauthorized_user_cannot_open_sectors(): void
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $supervisor = User::factory()->create(['clinic_id' => $clinic->id, 'role' => UserRole::SUPERVISOR]);

        $this->actingAs($supervisor)
            ->get(route('sectors.index'))
            ->assertForbidden();

        Livewire::actingAs($supervisor)
            ->test(SectorsManager::class)
            ->assertForbidden();
    }

    public function test_create_waits_for_confirmation_and_rejects_invalid_or_foreign_units(): void
    {
        [$clinic, $unit, $admin] = $this->clinicWithAdmin('Hospital Toledo');
        $foreignUnit = Unit::factory()->for(Clinic::factory())->create();

        Livewire::actingAs($admin)
            ->test(SectorsManager::class)
            ->call('startCreate')
            ->set('name', 'Geral')
            ->set('code', 'padrao')
            ->set('unitId', $unit->id)
            ->set('active', true)
            ->call('review')
            ->assertSet('showCreateConfirmation', true)
            ->assertSet('name', 'Geral')
            ->assertSet('code', 'PADRAO')
            ->assertHasNoErrors();

        $this->assertDatabaseMissing('sectors', ['clinic_id' => $clinic->id, 'code' => 'PADRAO']);

        Livewire::actingAs($admin)
            ->test(SectorsManager::class)
            ->call('startCreate')
            ->set('name', 'Geral')
            ->set('code', 'PADRAO')
            ->set('unitId', $unit->id)
            ->call('review')
            ->call('backToSectorForm')
            ->assertSet('showCreateConfirmation', false)
            ->assertSet('showForm', true)
            ->assertSet('name', 'Geral')
            ->assertSet('unitId', $unit->id)
            ->assertSet('code', 'PADRAO')
            ->call('cancel');

        $this->assertSame(0, Sector::query()->where('clinic_id', $clinic->id)->where('code', 'PADRAO')->count());

        Livewire::actingAs($admin)
            ->test(SectorsManager::class)
            ->call('startCreate')
            ->set('name', '')
            ->set('code', 'PADRAO')
            ->set('unitId', $unit->id)
            ->call('review')
            ->assertHasErrors(['name'])
            ->assertSet('showCreateConfirmation', false);

        Livewire::actingAs($admin)
            ->test(SectorsManager::class)
            ->call('startCreate')
            ->set('name', 'Geral')
            ->set('code', 'PADRAO')
            ->set('unitId', $foreignUnit->id)
            ->call('review')
            ->assertHasErrors(['unitId'])
            ->assertSet('showCreateConfirmation', false);

        $this->assertSame(0, Sector::query()->where('code', 'PADRAO')->count());
    }

    public function test_confirmed_create_inserts_one_sector_and_ignores_a_second_submit(): void
    {
        [$clinic, $unit, $admin] = $this->clinicWithAdmin('Hospital Toledo');

        $component = Livewire::actingAs($admin)
            ->test(SectorsManager::class)
            ->call('startCreate')
            ->set('name', 'Geral')
            ->set('code', 'PADRAO')
            ->set('unitId', $unit->id)
            ->set('description', 'Área geral')
            ->call('review')
            ->call('confirmCreate')
            ->assertSet('creationOutcome', 'success')
            ->assertSee('Setor criado!')
            ->call('confirmCreate')
            ->assertSet('search', '');

        $this->assertSame(1, Sector::query()->where('clinic_id', $clinic->id)->where('code', 'PADRAO')->count());
        $this->assertDatabaseHas('sectors', [
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'name' => 'Geral',
            'description' => 'Área geral',
            'active' => true,
        ]);

        $component
            ->call('dismissCreationOutcome')
            ->assertSet('showForm', false)
            ->assertSet('creationOutcome', '')
            ->assertSet('showCreateConfirmation', false)
            ->call('startCreate')
            ->assertSet('showForm', true)
            ->assertSee('1 setor')
            ->assertSee('1 ativo');
    }

    public function test_edit_deactivate_and_activate_require_confirmation(): void
    {
        [, $unit, $admin] = $this->clinicWithAdmin('Hospital Toledo');
        $sector = $this->sector($admin, $unit, 'Geral', 'PADRAO');

        Livewire::actingAs($admin)
            ->test(SectorsManager::class)
            ->call('edit', $sector->id)
            ->set('name', 'Geral atualizado')
            ->call('save')
            ->assertSet('showEditConfirmation', true)
            ->assertSet('name', 'Geral atualizado')
            ->call('backToSectorForm')
            ->assertSet('showForm', true)
            ->assertSet('name', 'Geral atualizado')
            ->call('save')
            ->call('confirmUpdate')
            ->assertSet('editOutcome', 'success')
            ->assertSee('Alterações salvas!');

        $this->assertDatabaseHas('sectors', ['id' => $sector->id, 'name' => 'Geral atualizado', 'active' => true]);

        Livewire::actingAs($admin)
            ->test(SectorsManager::class)
            ->call('confirmDeactivation', $sector->id)
            ->assertSee('Desativar setor?')
            ->assertSet('sectorPendingDeactivationId', $sector->id);

        $this->assertTrue($sector->refresh()->active);

        Livewire::actingAs($admin)
            ->test(SectorsManager::class)
            ->call('confirmDeactivation', $sector->id)
            ->call('deactivate')
            ->assertSet('deactivationOutcome', 'success')
            ->call('dismissDeactivationOutcome')
            ->assertSet('sectorPendingDeactivationId', null)
            ->assertSee('Inativo')
            ->call('confirmActivation', $sector->id)
            ->assertSee('Ativar setor?');

        $this->assertFalse($sector->refresh()->active);

        Livewire::actingAs($admin)
            ->test(SectorsManager::class)
            ->call('activate', $sector->id)
            ->assertSet('activationOutcome', 'success')
            ->call('dismissActivationOutcome')
            ->assertSet('sectorPendingActivationId', null)
            ->assertSee('Ativo');

        $this->assertTrue($sector->refresh()->active);
    }

    public function test_deletion_requires_typed_confirmation_and_blocks_cross_tenant(): void
    {
        [$clinic, $unit, $admin] = $this->clinicWithAdmin('Hospital Toledo');
        $unused = $this->sector($admin, $unit, 'Temporário', 'TMP');
        $foreignClinic = Clinic::factory()->create();
        $foreignAdmin = User::factory()->create(['clinic_id' => $foreignClinic->id, 'role' => UserRole::ADMINISTRATOR]);
        $foreignSector = $this->sector($foreignAdmin, Unit::factory()->for($foreignClinic)->create(), 'Alheio', 'ALH');

        Livewire::actingAs($admin)
            ->test(SectorsManager::class)
            ->call('confirmDeletion', $unused->id)
            ->assertSee('Excluir setor?')
            ->call('requestPermanentDeletion')
            ->assertSee('Confirmação permanente')
            ->set('deletionConfirmation', 'excluir')
            ->call('deleteSector')
            ->assertHasErrors(['deletionConfirmation']);

        $this->assertDatabaseHas('sectors', ['id' => $unused->id]);

        Livewire::actingAs($admin)
            ->test(SectorsManager::class)
            ->call('confirmDeletion', $unused->id)
            ->call('requestPermanentDeletion')
            ->set('deletionConfirmation', 'EXCLUIR')
            ->call('deleteSector')
            ->assertSet('deletionOutcome', 'success')
            ->assertSee('Setor excluído!')
            ->call('dismissDeletionOutcome')
            ->assertSet('sectorPendingDeletionId', null)
            ->assertSet('deletionOutcome', '')
            ->assertSee('Nenhum setor cadastrado');

        $this->assertDatabaseMissing('sectors', ['id' => $unused->id]);
        $this->assertDatabaseHas('sectors', ['id' => $foreignSector->id]);

        Livewire::actingAs($admin)
            ->test(SectorsManager::class)
            ->call('confirmDeletion', $foreignSector->id)
            ->assertNotFound();

        $this->assertSame($clinic->id, $admin->clinic_id);
    }

    public function test_sector_with_desks_is_kept_and_a_failed_delete_rolls_back(): void
    {
        [$clinic, $unit, $admin] = $this->clinicWithAdmin('Hospital Toledo');
        $linked = $this->sector($admin, $unit, 'Recepção', 'REC');
        Desk::factory()->create([
            'clinic_id' => $clinic->id,
            'unit_id' => $unit->id,
            'sector_id' => $linked->id,
        ]);

        Livewire::actingAs($admin)
            ->test(SectorsManager::class)
            ->call('confirmDeletion', $linked->id)
            ->assertSee('1 mesa/guichê')
            ->call('requestPermanentDeletion')
            ->set('deletionConfirmation', 'EXCLUIR')
            ->call('deleteSector')
            ->assertSet('deletionOutcome', 'blocked')
            ->assertSee('Os dados foram preservados.')
            ->assertDontSee('SQLSTATE');

        $this->assertDatabaseHas('sectors', ['id' => $linked->id]);
        $this->assertDatabaseHas('desks', ['sector_id' => $linked->id]);

        $unused = $this->sector($admin, $unit, 'Vazio', 'VAZ');
        Sector::deleting(function (): void {
            throw new RuntimeException('forced-sector-delete');
        });

        Livewire::actingAs($admin)
            ->test(SectorsManager::class)
            ->set('search', 'Vazio')
            ->call('confirmDeletion', $unused->id)
            ->call('requestPermanentDeletion')
            ->set('deletionConfirmation', 'EXCLUIR')
            ->call('deleteSector')
            ->assertSet('deletionOutcome', 'error')
            ->assertSet('search', 'Vazio')
            ->assertSee('Os dados foram preservados.')
            ->assertDontSee('forced-sector-delete')
            ->assertDontSee('SQLSTATE');

        $this->assertDatabaseHas('sectors', ['id' => $unused->id, 'clinic_id' => $clinic->id]);
    }

    public function test_filters_remain_after_creating_and_the_dialog_can_reopen(): void
    {
        [, $unit, $admin] = $this->clinicWithAdmin('Hospital Toledo');
        $this->sector($admin, $unit, 'Arquivo', 'ARQ', false);

        Livewire::actingAs($admin)
            ->test(SectorsManager::class)
            ->set('statusFilter', 'inactive')
            ->set('search', 'arq')
            ->call('startCreate')
            ->set('name', 'Novo filtro')
            ->set('code', 'NF')
            ->set('unitId', $unit->id)
            ->call('review')
            ->call('backToSectorForm')
            ->assertSet('statusFilter', 'inactive')
            ->assertSet('search', 'arq')
            ->assertSet('name', 'Novo filtro')
            ->call('cancel')
            ->assertSet('showForm', false)
            ->assertSet('statusFilter', 'inactive')
            ->call('startCreate')
            ->assertSet('showForm', true)
            ->call('cancel')
            ->assertSee('Arquivo')
            ->assertDontSee('Novo filtro');
    }

    /**
     * @return array{0: Clinic, 1: Unit, 2: User}
     */
    private function clinicWithAdmin(string $unitName): array
    {
        $clinic = Clinic::factory()->create();
        app(EnsureClinicRolePermissions::class)->handle($clinic);
        $unit = Unit::factory()->for($clinic)->create(['name' => $unitName]);
        $admin = User::factory()->create([
            'clinic_id' => $clinic->id,
            'role' => UserRole::ADMINISTRATOR,
        ]);

        return [$clinic, $unit, $admin];
    }

    private function sector(User $admin, Unit $unit, string $name, string $code, bool $active = true): Sector
    {
        return app(CreateSector::class)->handle($admin, [
            'name' => $name,
            'code' => $code,
            'unit_id' => $unit->id,
            'active' => $active,
        ]);
    }
}
