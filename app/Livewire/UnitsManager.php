<?php

namespace App\Livewire;

use App\Actions\DeleteUnit;
use App\Models\DisplayPanel;
use App\Models\Ticket;
use App\Models\TicketCall;
use App\Models\TicketSequence;
use App\Models\TicketTransfer;
use App\Models\Unit;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Throwable;

class UnitsManager extends Component
{
    public string $name = '';

    public string $slug = '';

    public string $search = '';

    public string $statusFilter = 'all';

    public bool $showForm = false;

    public bool $showCreateConfirmation = false;

    public ?int $editingUnitId = null;

    public ?int $unitPendingDeactivationId = null;

    public ?int $unitPendingActivationId = null;

    public ?int $unitPendingDeletionId = null;

    public bool $showPermanentConfirmation = false;

    public string $deletionConfirmation = '';

    public string $deletionOutcome = '';

    public string $deletionOutcomeName = '';

    public string $statusMessage = '';

    public string $toastDetail = '';

    public string $toastTone = 'success';

    public function mount(): void
    {
        $this->authorize('manageAny', Unit::class);
    }

    #[On('open-unit-form')]
    public function startCreate(): void
    {
        $this->authorize('create', Unit::class);
        $this->resetForm();
        $this->showForm = true;
    }

    public function edit(int $unitId): void
    {
        $unit = $this->unitForCurrentClinic($unitId);
        $this->authorize('update', $unit);

        $this->resetValidation();
        $this->editingUnitId = $unit->id;
        $this->name = $unit->name;
        $this->slug = $unit->slug;
        $this->showForm = true;
        $this->showCreateConfirmation = false;
        $this->unitPendingDeactivationId = null;
        $this->unitPendingActivationId = null;
        $this->clearDeletionPrompt();
        $this->clearToast();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function save(): void
    {
        $user = auth()->user();
        abort_if($user?->clinic_id === null, 404);

        $this->slug = $this->slug !== '' ? Str::slug($this->slug) : Str::slug($this->name);

        $this->validate();

        if ($this->editingUnitId === null) {
            $this->authorize('create', Unit::class);
            $this->showCreateConfirmation = true;

            return;
        }

        try {
            $unit = $this->unitForCurrentClinic($this->editingUnitId);
            $this->authorize('update', $unit);
            $unit->update([
                'name' => $this->name,
                'slug' => $this->slug,
            ]);
        } catch (QueryException) {
            $this->addError('form', 'Não foi possível salvar as alterações. Tente novamente.');

            return;
        }

        $message = 'Alterações salvas';
        $detail = 'As informações da unidade foram atualizadas.';
        $tone = 'success';

        $this->resetForm();
        $this->statusMessage = $message;
        $this->toastDetail = $detail;
        $this->toastTone = $tone;
        unset($this->clinicUnits, $this->units, $this->unitStats);
    }

    public function cancelCreateConfirmation(): void
    {
        $this->showCreateConfirmation = false;
        $this->showForm = true;
    }

    public function confirmCreate(): void
    {
        $user = auth()->user();
        abort_if($user?->clinic_id === null, 404);
        abort_unless($this->showCreateConfirmation, 404);
        abort_if($this->editingUnitId !== null, 404);

        $this->authorize('create', Unit::class);
        $this->slug = $this->slug !== '' ? Str::slug($this->slug) : Str::slug($this->name);

        try {
            $this->validate();
        } catch (ValidationException $exception) {
            $this->showCreateConfirmation = false;
            $this->showForm = true;

            throw $exception;
        }

        $createdName = $this->name;

        try {
            Unit::query()->create([
                'clinic_id' => $user->clinic_id,
                'name' => $this->name,
                'slug' => $this->slug,
                'active' => true,
            ]);
        } catch (QueryException $exception) {
            Log::warning('Falha ao criar unidade.', [
                'clinic_id' => $user->clinic_id,
                'exception' => $exception->getMessage(),
            ]);
            $this->addError('form', 'Não foi possível salvar as alterações. Tente novamente.');

            return;
        }

        $this->resetForm();
        $this->statusMessage = 'Unidade criada!';
        $this->toastDetail = '"'.$createdName.'" foi adicionada com sucesso.';
        $this->toastTone = 'success';
        unset($this->clinicUnits, $this->units, $this->unitStats);
    }

    public function confirmDeletion(int $unitId): void
    {
        $unit = $this->unitForCurrentClinic($unitId);
        $this->authorize('delete', $unit);
        $this->showForm = false;
        $this->showCreateConfirmation = false;
        $this->unitPendingDeactivationId = null;
        $this->unitPendingActivationId = null;
        $this->clearDeletionPrompt();
        $this->unitPendingDeletionId = $unit->id;
        $this->clearToast();
        $this->resetValidation();
    }

    public function cancelDeletion(): void
    {
        $this->clearDeletionPrompt();
        $this->deletionOutcome = '';
        $this->deletionOutcomeName = '';
        $this->resetValidation();
    }

    public function requestPermanentDeletion(): void
    {
        abort_if($this->unitPendingDeletionId === null, 404);

        $unit = $this->unitForCurrentClinic($this->unitPendingDeletionId);
        $this->authorize('delete', $unit);
        abort_unless($this->deletionImpact()['hasDependencies'] ?? false, 404);

        $this->showPermanentConfirmation = true;
        $this->deletionConfirmation = '';
        $this->deletionOutcome = '';
        $this->resetValidation();
    }

    public function backToDeletionWarning(): void
    {
        abort_if($this->unitPendingDeletionId === null, 404);

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

        if ($this->showPermanentConfirmation && $this->deletionOutcome === '' && $this->unitPendingDeletionId !== null) {
            $this->backToDeletionWarning();

            return;
        }

        $this->cancelDeletion();
    }

    public function dismissDeletionOutcome(): void
    {
        if ($this->deletionOutcome === 'success' && $this->deletionOutcomeName !== '') {
            $this->statusMessage = 'Unidade excluída';
            $this->toastDetail = '"'.$this->deletionOutcomeName.'" foi excluída permanentemente.';
            $this->toastTone = 'success';
        }

        $this->deletionOutcome = '';
        $this->deletionOutcomeName = '';
        $this->clearDeletionPrompt();
    }

    public function deleteUnit(): void
    {
        abort_if($this->unitPendingDeletionId === null, 404);

        $unit = $this->unitForCurrentClinic($this->unitPendingDeletionId);
        $this->authorize('delete', $unit);
        $deletedName = $unit->name;

        if ($this->requiresTypedConfirmation($unit) && trim($this->deletionConfirmation) !== 'EXCLUIR') {
            $this->addError('deletionConfirmation', 'Digite EXCLUIR para confirmar a exclusão permanente.');

            return;
        }

        try {
            app(DeleteUnit::class)->handle(auth()->user(), $unit);
        } catch (Throwable $exception) {
            Log::warning('Falha ao excluir unidade.', [
                'unit_id' => $unit->id,
                'clinic_id' => $unit->clinic_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $this->deletionOutcome = 'error';

            return;
        }

        $keptPermanentStep = $this->showPermanentConfirmation;
        $this->clearDeletionPrompt();
        $this->showPermanentConfirmation = $keptPermanentStep;
        $this->deletionOutcome = 'success';
        $this->deletionOutcomeName = $deletedName;
        unset($this->clinicUnits, $this->units, $this->unitStats);
    }

    public function confirmDeactivation(int $unitId): void
    {
        $unit = $this->unitForCurrentClinic($unitId);
        $this->authorize('update', $unit);
        $this->showForm = false;
        $this->showCreateConfirmation = false;
        $this->unitPendingActivationId = null;
        $this->clearDeletionPrompt();
        $this->unitPendingDeactivationId = $unit->id;
        $this->clearToast();
        $this->resetValidation();
    }

    public function cancelDeactivation(): void
    {
        $this->unitPendingDeactivationId = null;
        $this->resetValidation();
    }

    public function deactivate(): void
    {
        abort_if($this->unitPendingDeactivationId === null, 404);

        $unit = $this->unitForCurrentClinic($this->unitPendingDeactivationId);
        $this->authorize('update', $unit);

        try {
            $unit->update(['active' => false]);
        } catch (QueryException) {
            $this->addError('form', 'Não foi possível salvar as alterações. Tente novamente.');

            return;
        }

        $name = $unit->name;
        $this->unitPendingDeactivationId = null;
        $this->statusMessage = 'Unidade desativada.';
        $this->toastDetail = '"'.$name.'" deixou de ficar disponível para novas operações. O histórico foi preservado.';
        $this->toastTone = 'warning';
        unset($this->clinicUnits, $this->units, $this->unitStats);
    }

    public function confirmActivation(int $unitId): void
    {
        $unit = $this->unitForCurrentClinic($unitId);
        $this->authorize('update', $unit);
        $this->showForm = false;
        $this->unitPendingDeactivationId = null;
        $this->unitPendingActivationId = $unit->id;
        $this->clearToast();
        $this->resetValidation();
    }

    public function cancelActivation(): void
    {
        $this->unitPendingActivationId = null;
        $this->resetValidation();
    }

    public function activateConfirmed(): void
    {
        abort_if($this->unitPendingActivationId === null, 404);

        $this->activate($this->unitPendingActivationId);

        if ($this->statusMessage !== '') {
            $this->unitPendingActivationId = null;
        }
    }

    public function activate(int $unitId): void
    {
        $unit = $this->unitForCurrentClinic($unitId);
        $this->authorize('update', $unit);

        try {
            $unit->update(['active' => true]);
        } catch (QueryException) {
            $this->addError('form', 'Não foi possível salvar as alterações. Tente novamente.');

            return;
        }

        $this->statusMessage = 'Unidade ativada.';
        $this->toastDetail = '"'.$unit->name.'" voltou a ficar disponível.';
        $this->toastTone = 'success';
        unset($this->clinicUnits, $this->units, $this->unitStats);
    }

    public function dismissStatus(): void
    {
        $this->clearToast();
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function clinicUnits(): Collection
    {
        return Unit::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->orderBy('name')
            ->orderBy('id')
            ->get();
    }

    /**
     * @return Collection<int, Unit>
     */
    #[Computed]
    public function units(): Collection
    {
        $search = mb_strtolower(trim($this->search));

        return $this->clinicUnits
            ->filter(function (Unit $unit) use ($search): bool {
                if ($this->statusFilter === 'active' && ! $unit->active) {
                    return false;
                }

                if ($this->statusFilter === 'inactive' && $unit->active) {
                    return false;
                }

                if ($search === '') {
                    return true;
                }

                return str_contains(mb_strtolower($unit->name), $search)
                    || str_contains(mb_strtolower($unit->slug), $search);
            })
            ->values();
    }

    /**
     * @return array{total: int, active: int, inactive: int}
     */
    #[Computed]
    public function unitStats(): array
    {
        $units = $this->clinicUnits;

        return [
            'total' => $units->count(),
            'active' => $units->where('active', true)->count(),
            'inactive' => $units->where('active', false)->count(),
        ];
    }

    public function render(): View
    {
        return view('livewire.units-manager', [
            'pendingDeactivation' => $this->pendingUnit($this->unitPendingDeactivationId),
            'pendingActivation' => $this->pendingUnit($this->unitPendingActivationId),
            'pendingDeletion' => $this->pendingUnit($this->unitPendingDeletionId),
            'deletionImpact' => $this->deletionImpact(),
            'clinicName' => auth()->user()?->clinic?->name ?? 'esta clínica',
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::unique('units', 'slug')
                    ->where(fn ($query) => $query->where('clinic_id', auth()->user()?->clinic_id))
                    ->ignore($this->editingUnitId),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Informe o nome da unidade.',
            'name.max' => 'O nome da unidade deve ter no máximo 255 caracteres.',
            'slug.required' => 'Informe o identificador da unidade.',
            'slug.regex' => 'Use apenas letras minúsculas, números e hífens.',
            'slug.unique' => 'Já existe uma unidade com este identificador nesta clínica.',
        ];
    }

    private function unitForCurrentClinic(int $unitId): Unit
    {
        return Unit::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($unitId)
            ->firstOrFail();
    }

    private function pendingUnit(?int $unitId): ?Unit
    {
        if ($unitId === null) {
            return null;
        }

        return $this->clinicUnits->firstWhere('id', $unitId);
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->name = '';
        $this->slug = '';
        $this->editingUnitId = null;
        $this->showForm = false;
        $this->showCreateConfirmation = false;
        $this->unitPendingDeactivationId = null;
        $this->unitPendingActivationId = null;
        $this->clearDeletionPrompt();
        $this->clearToast();
    }

    private function clearDeletionPrompt(): void
    {
        $this->unitPendingDeletionId = null;
        $this->showPermanentConfirmation = false;
        $this->deletionConfirmation = '';
    }

    private function requiresTypedConfirmation(Unit $unit): bool
    {
        return $this->dependencyCounts($unit)['hasDependencies'];
    }

    /**
     * @return array{sectors: int, desks: int, kiosks: int, panels: int, attendances: int, calls: int, transfers: int, sequences: int, users: int, policies: int, offers: int, lines: list<string>, hasDependencies: bool, historyBlocked: bool}|null
     */
    private function deletionImpact(): ?array
    {
        if ($this->unitPendingDeletionId === null) {
            return null;
        }

        $unit = $this->pendingUnit($this->unitPendingDeletionId);
        if ($unit === null) {
            return null;
        }

        return $this->dependencyCounts($unit);
    }

    /**
     * @return array{sectors: int, desks: int, kiosks: int, panels: int, attendances: int, calls: int, transfers: int, sequences: int, users: int, policies: int, offers: int, lines: list<string>, hasDependencies: bool, historyBlocked: bool}
     */
    private function dependencyCounts(Unit $unit): array
    {
        $sectors = $unit->sectors()->count();
        $desks = $unit->desks()->count();
        $kiosks = $unit->kiosks()->count();
        $panels = DisplayPanel::query()
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->count();
        $attendances = $this->recordsForUnit(Ticket::class, $unit);
        $calls = $this->recordsForUnit(TicketCall::class, $unit);
        $transfers = $this->recordsForUnit(TicketTransfer::class, $unit);
        $sequences = $this->recordsForUnit(TicketSequence::class, $unit);
        $users = $unit->users()->count();
        $policies = $unit->queuePolicy()->count();
        $offers = $unit->unitTicketTypes()->count();

        $lines = array_values(array_filter([
            $sectors > 0 ? $this->countLabel($sectors, 'setor', 'setores') : null,
            $desks > 0 ? $this->countLabel($desks, 'mesa/guichê', 'mesas/guichês') : null,
            $kiosks > 0 ? $this->countLabel($kiosks, 'totem', 'totens') : null,
            $panels > 0 ? $this->countLabel($panels, 'painel', 'painéis') : null,
            $attendances > 0 ? $this->countLabel($attendances, 'atendimento histórico', 'atendimentos históricos') : null,
            $calls > 0 ? $this->countLabel($calls, 'chamada registrada', 'chamadas registradas') : null,
            $transfers > 0 ? $this->countLabel($transfers, 'transferência registrada', 'transferências registradas') : null,
            $users > 0 ? $this->countLabel($users, 'vínculo de usuário', 'vínculos de usuário') : null,
            $offers > 0 ? $this->countLabel($offers, 'tipo de senha vinculado', 'tipos de senha vinculados') : null,
            $policies > 0 ? $this->countLabel($policies, 'política de fila', 'políticas de fila') : null,
            $sequences > 0 ? $this->countLabel($sequences, 'numeração de senha', 'numerações de senha') : null,
        ]));

        $hasDependencies = $lines !== [];

        return [
            'sectors' => $sectors,
            'desks' => $desks,
            'kiosks' => $kiosks,
            'panels' => $panels,
            'attendances' => $attendances,
            'calls' => $calls,
            'transfers' => $transfers,
            'sequences' => $sequences,
            'users' => $users,
            'policies' => $policies,
            'offers' => $offers,
            'lines' => $lines,
            'hasDependencies' => $hasDependencies,
            'historyBlocked' => $attendances > 0 || $calls > 0 || $transfers > 0,
        ];
    }

    /**
     * @param  class-string<Ticket|TicketCall|TicketTransfer|TicketSequence>  $model
     */
    private function recordsForUnit(string $model, Unit $unit): int
    {
        return $model::query()
            ->where('clinic_id', $unit->clinic_id)
            ->where('unit_id', $unit->id)
            ->count();
    }

    private function countLabel(int $count, string $singular, string $plural): string
    {
        return $count.' '.($count === 1 ? $singular : $plural);
    }

    private function clearToast(): void
    {
        $this->statusMessage = '';
        $this->toastDetail = '';
        $this->toastTone = 'success';
    }
}
