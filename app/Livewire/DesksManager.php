<?php

namespace App\Livewire;

use App\Actions\CreateDesk;
use App\Actions\DeleteDesk;
use App\Actions\UpdateDesk;
use App\Models\Desk;
use App\Models\DeskAssignment;
use App\Models\Sector;
use App\Models\Ticket;
use App\Models\TicketCall;
use App\Models\TicketTransfer;
use App\Models\Unit;
use App\TicketStatus;
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

class DesksManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $unitFilter = '';

    public string $sectorFilter = '';

    public string $statusFilter = '';

    public bool $showForm = false;

    public ?int $editingDeskId = null;

    public string $name = '';

    public string $code = '';

    public ?int $unitId = null;

    public ?int $sectorId = null;

    public bool $active = true;

    public bool $showCreateConfirmation = false;

    public string $creationOutcome = '';

    public string $creationOutcomeName = '';

    public string $creationOutcomeUnit = '';

    public string $creationOutcomeSector = '';

    public bool $showEditConfirmation = false;

    public string $editOutcome = '';

    public string $editOutcomeName = '';

    public ?int $deskPendingDeactivationId = null;

    public string $deactivationOutcome = '';

    public string $deactivationOutcomeName = '';

    public string $deactivationOutcomeUnit = '';

    public string $deactivationOutcomeSector = '';

    public string $deactivationBlockMessage = '';

    public ?int $deskPendingActivationId = null;

    public string $activationOutcome = '';

    public string $activationOutcomeName = '';

    public string $activationOutcomeUnit = '';

    public string $activationOutcomeSector = '';

    public ?int $deskPendingDeletionId = null;

    public bool $showPermanentConfirmation = false;

    public string $deletionConfirmation = '';

    public string $deletionOutcome = '';

    public string $deletionOutcomeName = '';

    public string $deletionOutcomeCode = '';

    public string $deletionOutcomeUnit = '';

    public string $deletionOutcomeSector = '';

    public string $deletionBlockMessage = '';

    public string $statusMessage = '';

    public string $toastDetail = '';

    public string $toastTone = 'success';

    public function mount(): void
    {
        $this->authorize('viewAny', Desk::class);
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
        if ($this->sectorFilter !== '') {
            $belongs = Sector::query()
                ->where('clinic_id', auth()->user()?->clinic_id)
                ->when($this->unitFilter !== '', fn ($query) => $query->where('unit_id', (int) $this->unitFilter))
                ->whereKey((int) $this->sectorFilter)
                ->exists();

            if (! $belongs) {
                $this->sectorFilter = '';
            }
        }

        $this->resetPage();
    }

    public function updatedSectorFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUnitId(): void
    {
        if ($this->unitId === null) {
            $this->sectorId = null;

            return;
        }

        if ($this->sectorId === null) {
            return;
        }

        $belongs = Sector::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->where('unit_id', $this->unitId)
            ->whereKey($this->sectorId)
            ->exists();

        if (! $belongs) {
            $this->sectorId = null;
        }
    }

    #[On('open-desk-form')]
    public function startCreate(): void
    {
        $this->authorize('create', Desk::class);
        $this->resetForm();
        $this->closeAccountDialogs();
        $this->showForm = true;
    }

    public function edit(int $deskId): void
    {
        $desk = $this->deskForCurrentClinic($deskId);
        $this->authorize('update', $desk);
        $this->closeAccountDialogs();

        $this->editingDeskId = $desk->id;
        $this->name = $desk->name;
        $this->code = $desk->code;
        $this->unitId = $desk->unit_id;
        $this->sectorId = $desk->sector_id;
        $this->active = $desk->active;
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
        $this->sectorFilter = '';
        $this->statusFilter = '';
        $this->resetPage();
    }

    public function review(): void
    {
        $this->authorize('create', Desk::class);
        abort_if(auth()->user()?->clinic_id === null, 404);
        abort_if($this->editingDeskId !== null, 404);

        $this->prepareInput();
        $this->creationOutcome = '';
        $this->creationOutcomeName = $this->name;
        $this->creationOutcomeUnit = $this->selectedUnitName();
        $this->creationOutcomeSector = $this->selectedSectorName();
        $this->showCreateConfirmation = true;
    }

    public function backToDeskForm(): void
    {
        $this->showCreateConfirmation = false;
        $this->showEditConfirmation = false;
        $this->creationOutcome = '';
        $this->editOutcome = '';
    }

    public function closeDeskDialog(): void
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
            $this->backToDeskForm();

            return;
        }

        $this->cancel();
    }

    public function confirmCreate(CreateDesk $createDesk): void
    {
        if (! $this->showCreateConfirmation || $this->creationOutcome !== '' || $this->editingDeskId !== null) {
            return;
        }

        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);
        $this->authorize('create', Desk::class);
        $this->prepareInput();

        $createdName = $this->name;
        $unitName = $this->selectedUnitName();
        $sectorName = $this->selectedSectorName();

        try {
            $createDesk->handle($actor, $this->attributes());
        } catch (ValidationException $exception) {
            $this->showCreateConfirmation = false;
            $this->creationOutcome = '';
            $this->dispatch('desk-form-invalid');

            throw $exception;
        }

        $this->resetForm();
        $this->showForm = true;
        $this->showCreateConfirmation = true;
        $this->creationOutcome = 'success';
        $this->creationOutcomeName = $createdName;
        $this->creationOutcomeUnit = $unitName;
        $this->creationOutcomeSector = $sectorName;
        $this->resetPage();
        unset($this->deskStats);
    }

    public function dismissCreationOutcome(): void
    {
        if ($this->creationOutcome === 'success' && $this->creationOutcomeName !== '') {
            $this->statusMessage = 'Mesa criada!';
            $this->toastDetail = '“'.$this->creationOutcomeName.'” foi cadastrado com sucesso.';
            $this->toastTone = 'success';
        }

        $this->resetForm();
    }

    public function save(): void
    {
        if ($this->editingDeskId === null) {
            $this->review();

            return;
        }

        $desk = $this->deskForCurrentClinic($this->editingDeskId);
        $this->authorize('update', $desk);
        $this->prepareInput();
        $this->editOutcome = '';
        $this->editOutcomeName = $this->name;
        $this->showEditConfirmation = true;
    }

    public function confirmUpdate(UpdateDesk $updateDesk): void
    {
        if (! $this->showEditConfirmation || $this->editOutcome !== '' || $this->editingDeskId === null) {
            return;
        }

        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);
        $desk = $this->deskForCurrentClinic($this->editingDeskId);
        $this->authorize('update', $desk);
        $this->prepareInput();

        $savedName = $this->name;

        try {
            $updateDesk->handle($actor, $desk, $this->attributes());
        } catch (ValidationException $exception) {
            $this->showEditConfirmation = false;
            $this->editOutcome = '';
            $this->dispatch('desk-form-invalid');

            throw $exception;
        }

        $this->resetForm();
        $this->showForm = true;
        $this->showEditConfirmation = true;
        $this->editOutcome = 'success';
        $this->editOutcomeName = $savedName;
        $this->resetPage();
        unset($this->deskStats);
    }

    public function dismissEditOutcome(): void
    {
        if ($this->editOutcome === 'success' && $this->editOutcomeName !== '') {
            $this->statusMessage = 'Alterações salvas!';
            $this->toastDetail = 'Os dados de “'.$this->editOutcomeName.'” foram atualizados.';
            $this->toastTone = 'success';
        }

        $this->resetForm();
    }

    public function confirmDeactivation(int $deskId): void
    {
        $desk = $this->deskForCurrentClinic($deskId);
        $this->authorize('update', $desk);
        $this->closeAccountDialogs();
        $this->deskPendingDeactivationId = $desk->id;
        $this->deactivationOutcome = '';
        $this->deactivationBlockMessage = '';
        $this->deactivationOutcomeName = $desk->name;
        $this->deactivationOutcomeUnit = (string) ($desk->unit?->name ?? '');
        $this->deactivationOutcomeSector = (string) ($desk->sector?->name ?? '');

        if ($desk->hasWaitingTargetedTickets()) {
            $this->deactivationOutcome = 'blocked';
            $this->deactivationBlockMessage = 'Não é possível desativar a mesa enquanto houver senhas aguardando direcionadas a ela.';

            return;
        }

        if ($desk->hasOpenAttendance()) {
            $this->deactivationOutcome = 'blocked';
            $this->deactivationBlockMessage = 'O “'.$desk->name.'” possui um atendimento em andamento. Finalize ou transfira o atendimento antes de desativar esta mesa.';
        }
    }

    public function cancelDeactivation(): void
    {
        $this->deskPendingDeactivationId = null;
        $this->deactivationOutcome = '';
        $this->deactivationOutcomeName = '';
        $this->deactivationOutcomeUnit = '';
        $this->deactivationOutcomeSector = '';
        $this->deactivationBlockMessage = '';
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
            $this->statusMessage = 'Mesa desativada!';
            $this->toastDetail = 'A mesa “'.$this->deactivationOutcomeName.'” foi desativada.';
            $this->toastTone = 'warning';
        }

        $this->cancelDeactivation();
    }

    public function deactivate(UpdateDesk $updateDesk): void
    {
        if ($this->deactivationOutcome === 'success') {
            return;
        }

        abort_if($this->deskPendingDeactivationId === null, 404);

        $actor = auth()->user();
        $desk = $this->deskForCurrentClinic($this->deskPendingDeactivationId);
        $this->authorize('update', $desk);

        try {
            $updateDesk->handle($actor, $desk, [
                'name' => $desk->name,
                'code' => $desk->code,
                'unit_id' => $desk->unit_id,
                'sector_id' => $desk->sector_id,
                'active' => false,
            ]);
        } catch (ValidationException $exception) {
            $this->deactivationOutcome = 'blocked';
            $this->deactivationBlockMessage = collect($exception->errors())->flatten()->first()
                ?? 'Não é possível desativar esta mesa.';

            return;
        }

        $this->deactivationOutcome = 'success';
        $this->deactivationOutcomeName = $desk->name;
        $this->resetPage();
        unset($this->deskStats);
    }

    public function confirmActivation(int $deskId): void
    {
        $desk = $this->deskForCurrentClinic($deskId);
        $this->authorize('update', $desk);
        $this->closeAccountDialogs();
        $this->deskPendingActivationId = $desk->id;
        $this->activationOutcome = '';
        $this->activationOutcomeName = $desk->name;
        $this->activationOutcomeUnit = (string) ($desk->unit?->name ?? '');
        $this->activationOutcomeSector = (string) ($desk->sector?->name ?? '');
    }

    public function cancelActivation(): void
    {
        $this->deskPendingActivationId = null;
        $this->activationOutcome = '';
        $this->activationOutcomeName = '';
        $this->activationOutcomeUnit = '';
        $this->activationOutcomeSector = '';
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
            $this->statusMessage = 'Mesa ativada!';
            $this->toastDetail = 'A mesa “'.$this->activationOutcomeName.'” foi reativada.';
            $this->toastTone = 'success';
        }

        $this->cancelActivation();
    }

    public function activate(int $deskId, UpdateDesk $updateDesk): void
    {
        if ($this->activationOutcome === 'success') {
            return;
        }

        $actor = auth()->user();
        $desk = $this->deskForCurrentClinic($deskId);
        $this->authorize('update', $desk);

        $updateDesk->handle($actor, $desk, [
            'name' => $desk->name,
            'code' => $desk->code,
            'unit_id' => $desk->unit_id,
            'sector_id' => $desk->sector_id,
            'active' => true,
        ]);

        $this->deskPendingActivationId = $desk->id;
        $this->activationOutcome = 'success';
        $this->activationOutcomeName = $desk->name;
        $this->activationOutcomeUnit = (string) ($desk->unit?->name ?? '');
        $this->activationOutcomeSector = (string) ($desk->sector?->name ?? '');
        $this->resetPage();
        unset($this->deskStats);
    }

    public function confirmDeletion(int $deskId): void
    {
        $desk = $this->deskForCurrentClinic($deskId);
        $this->authorize('delete', $desk);
        $this->closeAccountDialogs();
        $this->rememberDeletionDesk($desk);
        $this->showPermanentConfirmation = false;
        $this->deletionConfirmation = '';
        $this->deletionOutcome = '';
        $this->deletionBlockMessage = '';
        $this->resetValidation();
    }

    public function cancelDeletion(): void
    {
        $this->clearDeletionPrompt();
        $this->deletionOutcome = '';
        $this->deletionOutcomeName = '';
        $this->deletionOutcomeCode = '';
        $this->deletionOutcomeUnit = '';
        $this->deletionOutcomeSector = '';
        $this->deletionBlockMessage = '';
        $this->resetValidation();
    }

    public function requestPermanentDeletion(): void
    {
        abort_if($this->deskPendingDeletionId === null, 404);

        $desk = $this->deskForCurrentClinic($this->deskPendingDeletionId);
        $this->authorize('delete', $desk);
        $this->showPermanentConfirmation = true;
        $this->deletionConfirmation = '';
        $this->deletionOutcome = '';
        $this->resetValidation();
    }

    public function backToDeletionWarning(): void
    {
        abort_if($this->deskPendingDeletionId === null, 404);

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

        if ($this->showPermanentConfirmation && $this->deletionOutcome === '' && $this->deskPendingDeletionId !== null) {
            $this->backToDeletionWarning();

            return;
        }

        $this->cancelDeletion();
    }

    public function dismissDeletionOutcome(): void
    {
        if ($this->deletionOutcome === 'success' && $this->deletionOutcomeName !== '') {
            $this->statusMessage = 'Mesa excluída!';
            $this->toastDetail = '“'.$this->deletionOutcomeName.'” foi removido com sucesso.';
            $this->toastTone = 'success';
        }

        $this->deletionOutcome = '';
        $this->deletionOutcomeName = '';
        $this->deletionOutcomeCode = '';
        $this->deletionOutcomeUnit = '';
        $this->deletionOutcomeSector = '';
        $this->deletionBlockMessage = '';
        $this->clearDeletionPrompt();
    }

    public function deleteDesk(DeleteDesk $deleteDesk): void
    {
        if ($this->deletionOutcome === 'success') {
            return;
        }

        abort_if($this->deskPendingDeletionId === null, 404);

        $actor = auth()->user();
        $desk = $this->deskForCurrentClinic($this->deskPendingDeletionId);
        $this->authorize('delete', $desk);

        if (trim($this->deletionConfirmation) !== 'EXCLUIR') {
            $this->addError('deletionConfirmation', 'Digite EXCLUIR para confirmar a exclusão permanente.');

            return;
        }

        $deletedName = $desk->name;

        try {
            $deleteDesk->handle($actor, $desk);
        } catch (ValidationException $exception) {
            Log::info('Exclusão de mesa recusada pela regra operacional.', [
                'desk_id' => $desk->id,
                'clinic_id' => $desk->clinic_id,
            ]);
            $this->deletionOutcome = 'blocked';
            $this->deletionBlockMessage = collect($exception->errors())->flatten()->first()
                ?? 'Não é possível excluir esta mesa.';

            return;
        } catch (Throwable $exception) {
            Log::warning('Falha ao excluir mesa.', [
                'desk_id' => $desk->id,
                'clinic_id' => $desk->clinic_id,
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
        unset($this->deskStats);
    }

    /**
     * @return array{assignments: int, open: int, waiting: int}
     */
    public function deactivationImpact(): array
    {
        $clinicId = auth()->user()?->clinic_id;
        $deskId = $this->deskPendingDeactivationId;

        if ($clinicId === null || $deskId === null) {
            return ['assignments' => 0, 'open' => 0, 'waiting' => 0];
        }

        $desk = $this->deskForCurrentClinic($deskId);

        return [
            'assignments' => (int) DeskAssignment::query()->where('clinic_id', $clinicId)->where('desk_id', $deskId)->count(),
            'open' => (int) $desk->openAttendances()->where('clinic_id', $clinicId)->count(),
            'waiting' => (int) Ticket::query()
                ->where('clinic_id', $clinicId)
                ->where('target_desk_id', $deskId)
                ->where('status', TicketStatus::WAITING)
                ->count(),
        ];
    }

    /**
     * @return array{assignments: int, tickets: int, calls: int, transfers: int}
     */
    public function deletionImpact(): array
    {
        $clinicId = auth()->user()?->clinic_id;
        $deskId = $this->deskPendingDeletionId;

        if ($clinicId === null || $deskId === null) {
            return ['assignments' => 0, 'tickets' => 0, 'calls' => 0, 'transfers' => 0];
        }

        return [
            'assignments' => (int) DeskAssignment::query()->where('clinic_id', $clinicId)->where('desk_id', $deskId)->count(),
            'tickets' => (int) Ticket::query()
                ->where('clinic_id', $clinicId)
                ->where(function ($query) use ($deskId): void {
                    $query->where('current_desk_id', $deskId)
                        ->orWhere('target_desk_id', $deskId);
                })
                ->count(),
            'calls' => (int) TicketCall::query()->where('clinic_id', $clinicId)->where('desk_id', $deskId)->count(),
            'transfers' => (int) TicketTransfer::query()
                ->where('clinic_id', $clinicId)
                ->where(function ($query) use ($deskId): void {
                    $query->where('from_desk_id', $deskId)
                        ->orWhere('to_desk_id', $deskId);
                })
                ->count(),
        ];
    }

    /**
     * @return array{total: int, active: int, inactive: int, serving: int}
     */
    #[Computed]
    public function deskStats(): array
    {
        $clinicId = auth()->user()?->clinic_id;
        $desks = Desk::query()->where('clinic_id', $clinicId);

        return [
            'total' => (int) (clone $desks)->count(),
            'active' => (int) (clone $desks)->where('active', true)->count(),
            'inactive' => (int) (clone $desks)->where('active', false)->count(),
            'serving' => (int) Desk::query()
                ->where('clinic_id', $clinicId)
                ->whereHas('openAttendances', fn ($query) => $query->where('clinic_id', $clinicId))
                ->count(),
        ];
    }

    /**
     * @return LengthAwarePaginator<int, Desk>
     */
    public function desks(): LengthAwarePaginator
    {
        $clinicId = auth()->user()?->clinic_id;

        return Desk::query()
            ->with(['unit:id,name,clinic_id', 'sector:id,name,unit_id,clinic_id'])
            ->withExists(['openAttendances as serving' => fn ($query) => $query->where('clinic_id', $clinicId)])
            ->where('clinic_id', $clinicId)
            ->when($this->search !== '', function ($query): void {
                $term = '%'.Str::lower($this->search).'%';
                $query->where(function ($search) use ($term): void {
                    $search->whereRaw('LOWER(name) like ?', [$term])
                        ->orWhereRaw('LOWER(code) like ?', [$term]);
                });
            })
            ->when($this->unitFilter !== '', fn ($query) => $query->where('unit_id', (int) $this->unitFilter))
            ->when($this->sectorFilter !== '', fn ($query) => $query->where('sector_id', (int) $this->sectorFilter))
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('active', true))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('active', false))
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

    /**
     * @return Collection<int, Sector>
     */
    #[Computed]
    public function availableSectors(): Collection
    {
        if ($this->unitId === null) {
            return new Collection;
        }

        return Sector::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->where('unit_id', $this->unitId)
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'unit_id', 'clinic_id', 'active']);
    }

    /**
     * @return Collection<int, Sector>
     */
    #[Computed]
    public function filterSectors(): Collection
    {
        return Sector::query()
            ->with('unit:id,name,clinic_id')
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->when($this->unitFilter !== '', fn ($query) => $query->where('unit_id', (int) $this->unitFilter))
            ->orderBy('name')
            ->orderBy('id')
            ->get(['id', 'name', 'unit_id', 'clinic_id']);
    }

    public function render(): View
    {
        return view('livewire.desks-manager', [
            'desks' => $this->desks(),
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
                Rule::unique('desks', 'code')
                    ->where(fn ($query) => $query->where('unit_id', $this->unitId))
                    ->ignore($this->editingDeskId),
            ],
            'unitId' => [
                'required',
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
            'sectorId' => [
                'required',
                'integer',
                Rule::exists('sectors', 'id')->where(fn ($query) => $query
                    ->where('clinic_id', $clinicId)
                    ->where('unit_id', $this->unitId)),
            ],
            'active' => ['boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da mesa/guichê.',
            'code.required' => 'Informe o código da mesa/guichê.',
            'code.regex' => 'Use apenas letras, números, hífen ou sublinhado.',
            'code.unique' => 'Já existe uma mesa/guichê com este código nesta unidade.',
            'unitId.required' => 'Selecione a unidade.',
            'unitId.exists' => 'A unidade selecionada não pertence à sua clínica.',
            'sectorId.required' => 'Selecione o setor.',
            'sectorId.exists' => 'O setor selecionado não pertence à unidade informada.',
        ];
    }

    /**
     * @return array{name: string, code: string, unit_id: int, sector_id: int, active: bool}
     */
    private function attributes(): array
    {
        return [
            'name' => $this->name,
            'code' => $this->code,
            'unit_id' => (int) $this->unitId,
            'sector_id' => (int) $this->sectorId,
            'active' => $this->active,
        ];
    }

    public function selectedUnitName(): string
    {
        $unit = $this->availableUnits->firstWhere('id', (int) $this->unitId);

        return $unit?->name ?? '';
    }

    public function selectedSectorName(): string
    {
        $sector = $this->availableSectors->firstWhere('id', (int) $this->sectorId);

        return $sector?->name ?? '';
    }

    private function deskForCurrentClinic(int $deskId): Desk
    {
        return Desk::query()
            ->with(['unit:id,name,clinic_id', 'sector:id,name,unit_id,clinic_id'])
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($deskId)
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
            $this->dispatch('desk-form-invalid');

            throw $exception;
        }
    }

    private function closeAccountDialogs(): void
    {
        $this->deskPendingDeactivationId = null;
        $this->deactivationOutcome = '';
        $this->deactivationBlockMessage = '';
        $this->deskPendingActivationId = null;
        $this->activationOutcome = '';
        $this->clearDeletionPrompt();
        $this->deletionOutcome = '';
        $this->deletionBlockMessage = '';
    }

    private function clearDeletionPrompt(): void
    {
        $this->deskPendingDeletionId = null;
        $this->showPermanentConfirmation = false;
        $this->deletionConfirmation = '';
    }

    private function rememberDeletionDesk(Desk $desk): void
    {
        $this->deskPendingDeletionId = $desk->id;
        $this->deletionOutcomeName = $desk->name;
        $this->deletionOutcomeCode = $desk->code;
        $this->deletionOutcomeUnit = (string) ($desk->unit?->name ?? '');
        $this->deletionOutcomeSector = (string) ($desk->sector?->name ?? '');
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingDeskId = null;
        $this->showForm = false;
        $this->name = '';
        $this->code = '';
        $this->unitId = null;
        $this->sectorId = null;
        $this->active = true;
        $this->showCreateConfirmation = false;
        $this->creationOutcome = '';
        $this->creationOutcomeName = '';
        $this->creationOutcomeUnit = '';
        $this->creationOutcomeSector = '';
        $this->showEditConfirmation = false;
        $this->editOutcome = '';
        $this->editOutcomeName = '';
    }
}
