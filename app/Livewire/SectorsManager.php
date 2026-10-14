<?php

namespace App\Livewire;

use App\Actions\CreateSector;
use App\Actions\DeleteSector;
use App\Actions\UpdateSector;
use App\Models\Desk;
use App\Models\DisplayPanel;
use App\Models\Kiosk;
use App\Models\Sector;
use App\Models\Ticket;
use App\Models\TicketCall;
use App\Models\Unit;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class SectorsManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $unitFilter = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingSectorId = null;

    public string $name = '';

    public string $code = '';

    public ?int $unitId = null;

    public string $description = '';

    public bool $active = true;

    public bool $showCreateConfirmation = false;

    public string $creationOutcome = '';

    public string $creationOutcomeName = '';

    public string $creationOutcomeUnit = '';

    public bool $showEditConfirmation = false;

    public string $editOutcome = '';

    public string $editOutcomeName = '';

    public ?int $sectorPendingDeactivationId = null;

    public string $deactivationOutcome = '';

    public string $deactivationOutcomeName = '';

    public string $deactivationOutcomeUnit = '';

    public ?int $sectorPendingActivationId = null;

    public string $activationOutcome = '';

    public string $activationOutcomeName = '';

    public ?int $sectorPendingDeletionId = null;

    public bool $showPermanentConfirmation = false;

    public string $deletionConfirmation = '';

    public string $deletionOutcome = '';

    public string $deletionOutcomeName = '';

    public string $deletionOutcomeUnit = '';

    public string $statusMessage = '';

    public string $toastDetail = '';

    public string $toastTone = 'success';

    public function mount(): void
    {
        $this->authorize('viewAny', Sector::class);
    }

    public function dismissStatus(): void
    {
        $this->statusMessage = '';
        $this->toastDetail = '';
        $this->toastTone = 'success';
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedUnitFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUnitId(): void
    {
        if ($this->editingSectorId === null) {
            return;
        }

        $sector = Sector::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($this->editingSectorId)
            ->first();

        if ($sector !== null && (int) $this->unitId !== (int) $sector->unit_id) {
            $this->resetValidation(['code']);
        }
    }

    #[On('open-sector-form')]
    public function startCreate(): void
    {
        $this->authorize('create', Sector::class);
        $this->resetForm();
        $this->closeAccountDialogs();
        $this->showForm = true;
    }

    public function edit(int $sectorId): void
    {
        $sector = $this->sectorForCurrentClinic($sectorId);
        $this->authorize('update', $sector);
        $this->closeAccountDialogs();

        $this->editingSectorId = $sector->id;
        $this->name = $sector->name;
        $this->code = $sector->code;
        $this->unitId = $sector->unit_id;
        $this->description = (string) ($sector->description ?? '');
        $this->active = $sector->active;
        $this->showForm = true;
        $this->showCreateConfirmation = false;
        $this->showEditConfirmation = false;
        $this->creationOutcome = '';
        $this->editOutcome = '';
        $this->statusMessage = '';
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->unitFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function review(): void
    {
        $this->authorize('create', Sector::class);
        abort_if(auth()->user()?->clinic_id === null, 404);
        abort_if($this->editingSectorId !== null, 404);

        $this->prepareInput();
        $this->creationOutcome = '';
        $this->creationOutcomeName = '';
        $this->creationOutcomeUnit = $this->selectedUnitName();
        $this->showCreateConfirmation = true;
    }

    public function backToSectorForm(): void
    {
        $this->showCreateConfirmation = false;
        $this->showEditConfirmation = false;
        $this->creationOutcome = '';
        $this->editOutcome = '';
    }

    public function closeSectorDialog(): void
    {
        if ($this->creationOutcome === 'success') {
            $this->dismissCreationOutcome();

            return;
        }

        if ($this->editOutcome === 'success') {
            $this->dismissEditOutcome();

            return;
        }

        if ($this->showCreateConfirmation || $this->showEditConfirmation) {
            $this->backToSectorForm();

            return;
        }

        $this->cancel();
    }

    public function confirmCreate(CreateSector $createSector): void
    {
        if (! $this->showCreateConfirmation || $this->creationOutcome !== '' || $this->editingSectorId !== null) {
            return;
        }

        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);
        $this->authorize('create', Sector::class);
        $this->prepareInput();

        $createdName = $this->name;
        $unitName = $this->selectedUnitName();

        try {
            $createSector->handle($actor, $this->attributes());
        } catch (ValidationException $exception) {
            $this->showCreateConfirmation = false;
            $this->creationOutcome = '';
            $this->dispatch('sector-form-invalid');

            throw $exception;
        }

        $this->resetForm();
        $this->showForm = true;
        $this->showCreateConfirmation = true;
        $this->creationOutcome = 'success';
        $this->creationOutcomeName = $createdName;
        $this->creationOutcomeUnit = $unitName;
        $this->resetPage();
        unset($this->sectorStats);
    }

    public function dismissCreationOutcome(): void
    {
        if ($this->creationOutcome === 'success' && $this->creationOutcomeName !== '') {
            $this->statusMessage = 'Setor criado!';
            $this->toastDetail = 'O setor "'.$this->creationOutcomeName.'" foi cadastrado com sucesso.';
            $this->toastTone = 'success';
        }

        $this->resetForm();
    }

    public function save(): void
    {
        if ($this->editingSectorId === null) {
            $this->review();

            return;
        }

        $sector = $this->sectorForCurrentClinic($this->editingSectorId);
        $this->authorize('update', $sector);
        $this->prepareInput();
        $this->editOutcome = '';
        $this->editOutcomeName = $this->name;
        $this->showEditConfirmation = true;
    }

    public function confirmUpdate(UpdateSector $updateSector): void
    {
        if (! $this->showEditConfirmation || $this->editOutcome !== '' || $this->editingSectorId === null) {
            return;
        }

        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);
        $sector = $this->sectorForCurrentClinic($this->editingSectorId);
        $this->authorize('update', $sector);
        $this->prepareInput();

        $savedName = $this->name;

        try {
            $updateSector->handle($actor, $sector, $this->attributes());
        } catch (ValidationException $exception) {
            $this->showEditConfirmation = false;
            $this->editOutcome = '';
            $this->dispatch('sector-form-invalid');

            throw $exception;
        }

        $this->resetForm();
        $this->showForm = true;
        $this->showEditConfirmation = true;
        $this->editOutcome = 'success';
        $this->editOutcomeName = $savedName;
        $this->resetPage();
        unset($this->sectorStats);
    }

    public function dismissEditOutcome(): void
    {
        if ($this->editOutcome === 'success' && $this->editOutcomeName !== '') {
            $this->statusMessage = 'Alterações salvas!';
            $this->toastDetail = 'Os dados de "'.$this->editOutcomeName.'" foram atualizados.';
            $this->toastTone = 'success';
        }

        $this->resetForm();
    }

    public function confirmDeactivation(int $sectorId): void
    {
        $sector = $this->sectorForCurrentClinic($sectorId);
        $this->authorize('update', $sector);
        $this->closeAccountDialogs();
        $this->sectorPendingDeactivationId = $sector->id;
        $this->deactivationOutcome = '';
        $this->deactivationOutcomeName = $sector->name;
        $this->deactivationOutcomeUnit = (string) ($sector->unit?->name ?? '');
    }

    public function cancelDeactivation(): void
    {
        $this->sectorPendingDeactivationId = null;
        $this->deactivationOutcome = '';
        $this->deactivationOutcomeName = '';
        $this->deactivationOutcomeUnit = '';
    }

    public function closeDeactivationDialog(): void
    {
        if ($this->deactivationOutcome === 'success') {
            $this->dismissDeactivationOutcome();

            return;
        }

        $this->cancelDeactivation();
    }

    public function dismissDeactivationOutcome(): void
    {
        if ($this->deactivationOutcome === 'success' && $this->deactivationOutcomeName !== '') {
            $this->statusMessage = 'Setor desativado!';
            $this->toastDetail = 'O setor "'.$this->deactivationOutcomeName.'" foi desativado.';
            $this->toastTone = 'warning';
        }

        $this->sectorPendingDeactivationId = null;
        $this->deactivationOutcome = '';
        $this->deactivationOutcomeName = '';
        $this->deactivationOutcomeUnit = '';
    }

    public function deactivate(UpdateSector $updateSector): void
    {
        if ($this->deactivationOutcome === 'success') {
            return;
        }

        abort_if($this->sectorPendingDeactivationId === null, 404);

        $actor = auth()->user();
        $sector = $this->sectorForCurrentClinic($this->sectorPendingDeactivationId);
        $this->authorize('update', $sector);

        $updateSector->handle($actor, $sector, [
            'name' => $sector->name,
            'code' => $sector->code,
            'unit_id' => $sector->unit_id,
            'description' => $sector->description,
            'active' => false,
        ]);

        $this->deactivationOutcome = 'success';
        $this->deactivationOutcomeName = $sector->name;
        $this->resetPage();
        unset($this->sectorStats);
    }

    public function confirmActivation(int $sectorId): void
    {
        $sector = $this->sectorForCurrentClinic($sectorId);
        $this->authorize('update', $sector);
        $this->closeAccountDialogs();
        $this->sectorPendingActivationId = $sector->id;
        $this->activationOutcome = '';
        $this->activationOutcomeName = $sector->name;
    }

    public function cancelActivation(): void
    {
        $this->sectorPendingActivationId = null;
        $this->activationOutcome = '';
        $this->activationOutcomeName = '';
    }

    public function closeActivationDialog(): void
    {
        if ($this->activationOutcome === 'success') {
            $this->dismissActivationOutcome();

            return;
        }

        $this->cancelActivation();
    }

    public function dismissActivationOutcome(): void
    {
        if ($this->activationOutcome === 'success' && $this->activationOutcomeName !== '') {
            $this->statusMessage = 'Setor ativado!';
            $this->toastDetail = 'O setor "'.$this->activationOutcomeName.'" foi reativado.';
            $this->toastTone = 'success';
        }

        $this->sectorPendingActivationId = null;
        $this->activationOutcome = '';
        $this->activationOutcomeName = '';
    }

    public function activate(int $sectorId, UpdateSector $updateSector): void
    {
        if ($this->activationOutcome === 'success') {
            return;
        }

        $actor = auth()->user();
        $sector = $this->sectorForCurrentClinic($sectorId);
        $this->authorize('update', $sector);

        $updateSector->handle($actor, $sector, [
            'name' => $sector->name,
            'code' => $sector->code,
            'unit_id' => $sector->unit_id,
            'description' => $sector->description,
            'active' => true,
        ]);

        $this->sectorPendingActivationId = $sector->id;
        $this->activationOutcome = 'success';
        $this->activationOutcomeName = $sector->name;
        $this->resetPage();
        unset($this->sectorStats);
    }

    public function confirmDeletion(int $sectorId): void
    {
        $sector = $this->sectorForCurrentClinic($sectorId);
        $this->authorize('delete', $sector);
        $this->closeAccountDialogs();
        $this->sectorPendingDeletionId = $sector->id;
        $this->deletionOutcomeName = $sector->name;
        $this->deletionOutcomeUnit = (string) ($sector->unit?->name ?? '');
        $this->showPermanentConfirmation = false;
        $this->deletionConfirmation = '';
        $this->deletionOutcome = '';
        $this->resetValidation();
    }

    public function cancelDeletion(): void
    {
        $this->clearDeletionPrompt();
        $this->deletionOutcome = '';
        $this->deletionOutcomeName = '';
        $this->deletionOutcomeUnit = '';
        $this->resetValidation();
    }

    public function requestPermanentDeletion(): void
    {
        abort_if($this->sectorPendingDeletionId === null, 404);

        $sector = $this->sectorForCurrentClinic($this->sectorPendingDeletionId);
        $this->authorize('delete', $sector);
        $this->showPermanentConfirmation = true;
        $this->deletionConfirmation = '';
        $this->deletionOutcome = '';
        $this->resetValidation();
    }

    public function backToDeletionWarning(): void
    {
        abort_if($this->sectorPendingDeletionId === null, 404);

        $this->showPermanentConfirmation = false;
        $this->deletionConfirmation = '';
        $this->deletionOutcome = '';
        $this->resetValidation();
    }

    public function closeDeletionDialog(): void
    {
        if ($this->deletionOutcome === 'success') {
            $this->dismissDeletionOutcome();

            return;
        }

        if ($this->showPermanentConfirmation && $this->deletionOutcome === '' && $this->sectorPendingDeletionId !== null) {
            $this->backToDeletionWarning();

            return;
        }

        $this->cancelDeletion();
    }

    public function dismissDeletionOutcome(): void
    {
        if ($this->deletionOutcome === 'success' && $this->deletionOutcomeName !== '') {
            $this->statusMessage = 'Setor excluído';
            $this->toastDetail = '"'.$this->deletionOutcomeName.'" foi removido com sucesso.';
            $this->toastTone = 'success';
        }

        $this->deletionOutcome = '';
        $this->deletionOutcomeName = '';
        $this->deletionOutcomeUnit = '';
        $this->clearDeletionPrompt();
    }

    public function deleteSector(DeleteSector $deleteSector): void
    {
        if ($this->deletionOutcome === 'success') {
            return;
        }

        abort_if($this->sectorPendingDeletionId === null, 404);

        $actor = auth()->user();
        $sector = $this->sectorForCurrentClinic($this->sectorPendingDeletionId);
        $this->authorize('delete', $sector);

        if (trim($this->deletionConfirmation) !== 'EXCLUIR') {
            $this->addError('deletionConfirmation', 'Digite EXCLUIR para confirmar a exclusão permanente.');

            return;
        }

        $deletedName = $sector->name;

        try {
            $deleteSector->handle($actor, $sector);
        } catch (ValidationException $exception) {
            Log::info('Exclusão de setor recusada pela regra operacional.', [
                'sector_id' => $sector->id,
                'clinic_id' => $sector->clinic_id,
            ]);
            $this->deletionOutcome = 'blocked';

            return;
        } catch (Throwable $exception) {
            Log::warning('Falha ao excluir setor.', [
                'sector_id' => $sector->id,
                'clinic_id' => $sector->clinic_id,
                'exception' => $exception::class,
            ]);
            $this->deletionOutcome = 'error';

            return;
        }

        $this->clearDeletionPrompt();
        $this->showPermanentConfirmation = true;
        $this->deletionOutcome = 'success';
        $this->deletionOutcomeName = $deletedName;
        $this->resetPage();
        unset($this->sectorStats);
    }

    /**
     * @return array{desks: int, kiosks: int}
     */
    public function deactivationImpact(): array
    {
        $clinicId = auth()->user()?->clinic_id;
        $sectorId = $this->sectorPendingDeactivationId;

        if ($clinicId === null || $sectorId === null) {
            return ['desks' => 0, 'kiosks' => 0];
        }

        return [
            'desks' => (int) Desk::query()->where('clinic_id', $clinicId)->where('sector_id', $sectorId)->count(),
            'kiosks' => (int) Kiosk::query()->where('clinic_id', $clinicId)->where('sector_id', $sectorId)->count(),
        ];
    }

    /**
     * @return array{desks: int, kiosks: int, tickets: int, calls: int, panels: int}
     */
    public function deletionImpact(): array
    {
        $clinicId = auth()->user()?->clinic_id;
        $sectorId = $this->sectorPendingDeletionId;

        if ($clinicId === null || $sectorId === null) {
            return ['desks' => 0, 'kiosks' => 0, 'tickets' => 0, 'calls' => 0, 'panels' => 0];
        }

        return [
            'desks' => (int) Desk::query()->where('clinic_id', $clinicId)->where('sector_id', $sectorId)->count(),
            'kiosks' => (int) Kiosk::query()->where('clinic_id', $clinicId)->where('sector_id', $sectorId)->count(),
            'tickets' => (int) Ticket::query()->where('clinic_id', $clinicId)->where('sector_id', $sectorId)->count(),
            'calls' => (int) TicketCall::query()->where('clinic_id', $clinicId)->where('sector_id', $sectorId)->count(),
            'panels' => (int) DisplayPanel::query()
                ->where('clinic_id', $clinicId)
                ->whereHas('sectors', fn ($query) => $query->where('sectors.id', $sectorId))
                ->count(),
        ];
    }

    /**
     * @return array{total: int, active: int, inactive: int, desks: int}
     */
    #[Computed]
    public function sectorStats(): array
    {
        $clinicId = auth()->user()?->clinic_id;
        $sectors = Sector::query()->where('clinic_id', $clinicId);

        return [
            'total' => (int) (clone $sectors)->count(),
            'active' => (int) (clone $sectors)->where('active', true)->count(),
            'inactive' => (int) (clone $sectors)->where('active', false)->count(),
            'desks' => (int) Desk::query()->where('clinic_id', $clinicId)->whereNotNull('sector_id')->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Sector>
     */
    public function sectors(): LengthAwarePaginator
    {
        $clinicId = auth()->user()?->clinic_id;

        return Sector::query()
            ->with(['unit:id,name,clinic_id'])
            ->withCount('desks')
            ->where('clinic_id', $clinicId)
            ->when($this->search !== '', function ($query): void {
                $term = '%'.Str::lower($this->search).'%';
                $query->where(function ($search) use ($term): void {
                    $search->whereRaw('LOWER(name) like ?', [$term])
                        ->orWhereRaw('LOWER(code) like ?', [$term]);
                });
            })
            ->when($this->unitFilter !== '', fn ($query) => $query->where('unit_id', (int) $this->unitFilter))
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('active', true))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('active', false))
            ->orderBy('unit_id')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(10);
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function availableUnits(): Collection
    {
        return Unit::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'clinic_id', 'active']);
    }

    public function render(): View
    {
        return view('livewire.sectors-manager', [
            'sectors' => $this->sectors(),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        $clinicId = auth()->user()?->clinic_id;

        return [
            'name' => ['required', 'string', 'max:255'],
            'code' => [
                'required',
                'string',
                'max:32',
                'regex:/^[A-Z0-9_-]+$/',
                Rule::unique('sectors', 'code')
                    ->where(fn ($query) => $query->where('clinic_id', $clinicId)->where('unit_id', $this->unitId))
                    ->ignore($this->editingSectorId),
            ],
            'unitId' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'description' => ['nullable', 'string', 'max:255'],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do setor.',
            'code.required' => 'Informe o código do setor.',
            'code.regex' => 'Use apenas letras, números, hífen ou sublinhado.',
            'code.unique' => 'Já existe um setor com este código nesta unidade.',
            'unitId.required' => 'Selecione a unidade.',
            'unitId.exists' => 'A unidade selecionada não pertence à sua clínica.',
        ];
    }

    /**
     * @return array{name: string, code: string, unit_id: int, description: ?string, active: bool}
     */
    private function attributes(): array
    {
        $description = trim($this->description);

        return [
            'name' => $this->name,
            'code' => $this->code,
            'unit_id' => (int) $this->unitId,
            'description' => $description !== '' ? $description : null,
            'active' => $this->active,
        ];
    }

    public function selectedUnitName(): string
    {
        $unit = $this->availableUnits->firstWhere('id', (int) $this->unitId);

        return $unit?->name ?? '';
    }

    private function sectorForCurrentClinic(int $sectorId): Sector
    {
        return Sector::query()
            ->with('unit:id,name,clinic_id')
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($sectorId)
            ->firstOrFail();
    }

    private function prepareInput(): void
    {
        $this->code = Str::upper($this->code);

        try {
            $this->validate();
        } catch (ValidationException $exception) {
            $this->showCreateConfirmation = false;
            $this->showEditConfirmation = false;
            $this->dispatch('sector-form-invalid');

            throw $exception;
        }
    }

    private function closeAccountDialogs(): void
    {
        $this->sectorPendingDeactivationId = null;
        $this->deactivationOutcome = '';
        $this->sectorPendingActivationId = null;
        $this->activationOutcome = '';
        $this->clearDeletionPrompt();
        $this->deletionOutcome = '';
    }

    private function clearDeletionPrompt(): void
    {
        $this->sectorPendingDeletionId = null;
        $this->showPermanentConfirmation = false;
        $this->deletionConfirmation = '';
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingSectorId = null;
        $this->showForm = false;
        $this->name = '';
        $this->code = '';
        $this->unitId = null;
        $this->description = '';
        $this->active = true;
        $this->showCreateConfirmation = false;
        $this->creationOutcome = '';
        $this->creationOutcomeName = '';
        $this->creationOutcomeUnit = '';
        $this->showEditConfirmation = false;
        $this->editOutcome = '';
        $this->editOutcomeName = '';
        $this->sectorPendingDeactivationId = null;
        $this->sectorPendingDeletionId = null;
    }
}
