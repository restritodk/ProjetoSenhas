<?php

namespace App\Livewire;

use App\Actions\CreateUser;
use App\Actions\DeleteUser;
use App\Actions\UpdateUser;
use App\Exceptions\LastAdministratorProtectedException;
use App\Models\Unit;
use App\Models\User;
use App\UserRole;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Contracts\View\View;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;
use Livewire\Attributes\Computed;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithPagination;
use Throwable;

class UsersManager extends Component
{
    use WithPagination;

    public string $search = '';

    public string $roleFilter = '';

    public string $statusFilter = '';

    public string $unitFilter = '';

    public bool $showForm = false;

    public bool $showCreateConfirmation = false;

    public string $creationOutcome = '';

    public string $creationOutcomeName = '';

    public string $creationOutcomeAccess = '';

    public ?int $editingUserId = null;

    public string $name = '';

    public string $email = '';

    public string $role = '';

    public string $password = '';

    public string $password_confirmation = '';

    public bool $active = true;

    /** @var list<int> */
    public array $unitIds = [];

    public ?int $userPendingDeactivationId = null;

    public string $deactivationOutcome = '';

    public string $deactivationOutcomeName = '';

    public ?int $userPendingActivationId = null;

    public string $activationOutcome = '';

    public string $activationOutcomeName = '';

    public ?int $userPendingDeletionId = null;

    public bool $showPermanentConfirmation = false;

    public string $deletionConfirmation = '';

    public string $deletionOutcome = '';

    public string $deletionOutcomeName = '';

    public string $deletionTargetEmail = '';

    public string $statusMessage = '';

    public string $toastDetail = '';

    public string $toastTone = 'success';

    public function mount(): void
    {
        $this->authorize('viewAny', User::class);
        $this->role = UserRole::ATTENDANT->value;

        $requestedRole = request()->query('role');
        if (is_string($requestedRole) && UserRole::tryFrom($requestedRole) !== null) {
            $this->roleFilter = $requestedRole;
        }
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedRoleFilter(): void
    {
        $this->resetPage();
    }

    public function updatedStatusFilter(): void
    {
        $this->resetPage();
    }

    public function updatedUnitFilter(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->search = '';
        $this->roleFilter = '';
        $this->statusFilter = '';
        $this->unitFilter = '';
        $this->resetPage();
    }

    public function dismissStatus(): void
    {
        $this->statusMessage = '';
        $this->toastDetail = '';
        $this->toastTone = 'success';
    }

    #[On('open-user-form')]
    public function startCreate(): void
    {
        $this->authorize('create', User::class);
        $this->resetForm();
        $this->showForm = true;
        $this->dismissStatus();
    }

    public function edit(int $userId): void
    {
        $user = $this->userForCurrentClinic($userId);
        $this->authorize('update', $user);

        $this->editingUserId = $user->id;
        $this->name = $user->name;
        $this->email = $user->email;
        $this->role = $user->role->value;
        $this->password = '';
        $this->password_confirmation = '';
        $this->active = $user->active;
        $this->unitIds = $user->units()->pluck('units.id')->map(fn ($id): int => (int) $id)->all();
        $this->showForm = true;
        $this->showCreateConfirmation = false;
        $this->creationOutcome = '';
        $this->userPendingDeactivationId = null;
        $this->dismissStatus();
    }

    public function cancel(): void
    {
        $this->resetForm();
    }

    public function selectAllUnits(): void
    {
        $this->authorize($this->editingUserId === null ? 'create' : 'update', $this->editingUserId === null ? User::class : $this->userForCurrentClinic($this->editingUserId));
        $this->unitIds = $this->availableUnits->pluck('id')->map(fn ($id): int => (int) $id)->all();
    }

    public function clearUnitSelection(): void
    {
        $this->unitIds = [];
    }

    public function review(): void
    {
        $this->authorize('create', User::class);
        abort_if(auth()->user()?->clinic_id === null, 404);
        abort_if($this->editingUserId !== null, 404);

        $this->prepareInput();
        $this->creationOutcome = '';
        $this->showCreateConfirmation = true;
    }

    public function backToUserForm(): void
    {
        $this->showCreateConfirmation = false;
        $this->creationOutcome = '';
    }

    public function closeUserDialog(): void
    {
        if ($this->creationOutcome === 'success') {
            $this->dismissCreationOutcome();

            return;
        }

        if ($this->showCreateConfirmation) {
            $this->backToUserForm();

            return;
        }

        $this->cancel();
    }

    public function confirmCreate(CreateUser $createUser): void
    {
        if (! $this->showCreateConfirmation || $this->creationOutcome !== '' || $this->editingUserId !== null) {
            return;
        }

        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);
        $this->authorize('create', User::class);
        $this->prepareInput();

        $createdName = $this->name;
        $access = $this->confirmedAccessLabel();

        $createUser->handle($actor, [
            'name' => $this->name,
            'email' => $this->email,
            'role' => UserRole::from($this->role),
            'password' => $this->password,
            'active' => $this->active,
            'unit_ids' => array_map(intval(...), $this->unitIds),
        ]);

        $this->resetForm();
        $this->showForm = true;
        $this->showCreateConfirmation = true;
        $this->creationOutcome = 'success';
        $this->creationOutcomeName = $createdName;
        $this->creationOutcomeAccess = $access;
        $this->password = '';
        $this->password_confirmation = '';
        $this->resetPage();
        unset($this->userStats);
    }

    public function dismissCreationOutcome(): void
    {
        if ($this->creationOutcome === 'success' && $this->creationOutcomeName !== '') {
            $this->statusMessage = 'Usuário criado!';
            $this->toastDetail = '"'.$this->creationOutcomeName.'" foi cadastrado com sucesso.';
            $this->toastTone = 'success';
        }

        $this->resetForm();
    }

    public function save(UpdateUser $updateUser): void
    {
        if ($this->editingUserId === null) {
            $this->review();

            return;
        }

        $actor = auth()->user();
        abort_if($actor?->clinic_id === null, 404);

        $this->prepareInput();
        $target = $this->userForCurrentClinic($this->editingUserId);

        try {
            $updateUser->handle($actor, $target, [
                'name' => $this->name,
                'email' => $this->email,
                'role' => UserRole::from($this->role),
                'password' => $this->password !== '' ? $this->password : null,
                'active' => $this->active,
                'unit_ids' => array_map(intval(...), $this->unitIds),
            ]);
        } catch (LastAdministratorProtectedException $exception) {
            $this->addError('role', $exception->getMessage());
            $this->addError('active', $exception->getMessage());
            $this->dispatch('user-form-invalid');

            return;
        }

        $this->resetForm();
        $this->statusMessage = 'Usuário atualizado com sucesso.';
        $this->toastDetail = 'Os dados de acesso foram atualizados.';
        $this->toastTone = 'success';
        $this->resetPage();
        unset($this->userStats);
    }

    public function confirmDeactivation(int $userId): void
    {
        $user = $this->userForCurrentClinic($userId);
        $this->authorize('update', $user);
        $this->closeAccountDialogs();
        $this->userPendingDeactivationId = $user->id;
        $this->deactivationOutcome = '';
        $this->deactivationOutcomeName = $user->name;
    }

    public function cancelDeactivation(): void
    {
        $this->userPendingDeactivationId = null;
        $this->deactivationOutcome = '';
        $this->deactivationOutcomeName = '';
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
        $this->userPendingDeactivationId = null;
        $this->deactivationOutcome = '';
        $this->deactivationOutcomeName = '';
    }

    public function deactivate(UpdateUser $updateUser): void
    {
        abort_if($this->userPendingDeactivationId === null, 404);
        if ($this->deactivationOutcome === 'success') {
            return;
        }

        $actor = auth()->user();
        $target = $this->userForCurrentClinic($this->userPendingDeactivationId);
        $this->authorize('update', $target);

        if ((int) $actor->id === (int) $target->id) {
            $this->addError('status', 'Você não pode desativar a própria conta nesta tela.');

            return;
        }

        try {
            $updateUser->handle($actor, $target, [
                'name' => $target->name,
                'email' => $target->email,
                'role' => $target->role,
                'active' => false,
                'unit_ids' => $target->units()->pluck('units.id')->map(fn ($id): int => (int) $id)->all(),
            ]);
        } catch (LastAdministratorProtectedException $exception) {
            $this->addError('status', $exception->getMessage());

            return;
        }

        $this->forgetUserSessions($target->id);
        $this->deactivationOutcome = 'success';
        $this->deactivationOutcomeName = $target->name;
        $this->statusMessage = 'Usuário desativado.';
        $this->toastDetail = 'O acesso de "'.$target->name.'" foi desativado com sucesso.';
        $this->toastTone = 'warning';
        $this->resetPage();
        unset($this->userStats);
    }

    public function confirmActivation(int $userId): void
    {
        $user = $this->userForCurrentClinic($userId);
        $this->authorize('update', $user);
        $this->closeAccountDialogs();
        $this->userPendingActivationId = $user->id;
        $this->activationOutcome = '';
        $this->activationOutcomeName = $user->name;
    }

    public function cancelActivation(): void
    {
        $this->userPendingActivationId = null;
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
        $this->userPendingActivationId = null;
        $this->activationOutcome = '';
        $this->activationOutcomeName = '';
    }

    public function activate(int $userId, UpdateUser $updateUser): void
    {
        if ($this->activationOutcome === 'success') {
            return;
        }

        $actor = auth()->user();
        $target = $this->userForCurrentClinic($userId);
        $this->authorize('update', $target);

        $updateUser->handle($actor, $target, [
            'name' => $target->name,
            'email' => $target->email,
            'role' => $target->role,
            'active' => true,
            'unit_ids' => $target->units()->pluck('units.id')->map(fn ($id): int => (int) $id)->all(),
        ]);

        $this->userPendingActivationId = $target->id;
        $this->activationOutcome = 'success';
        $this->activationOutcomeName = $target->name;
        $this->statusMessage = 'Usuário ativado.';
        $this->toastDetail = 'O acesso de "'.$target->name.'" foi restaurado.';
        $this->toastTone = 'success';
        $this->resetPage();
        unset($this->userStats);
    }

    public function confirmDeletion(int $userId): void
    {
        $user = $this->userForCurrentClinic($userId);
        $this->authorize('delete', $user);
        $this->closeAccountDialogs();
        $this->userPendingDeletionId = $user->id;
        $this->deletionOutcomeName = $user->name;
        $this->deletionTargetEmail = $user->email;
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
        $this->resetValidation();
    }

    public function requestPermanentDeletion(): void
    {
        abort_if($this->userPendingDeletionId === null, 404);

        $user = $this->userForCurrentClinic($this->userPendingDeletionId);
        $this->authorize('delete', $user);
        $this->showPermanentConfirmation = true;
        $this->deletionConfirmation = '';
        $this->deletionOutcome = '';
        $this->resetValidation();
    }

    public function backToDeletionWarning(): void
    {
        abort_if($this->userPendingDeletionId === null, 404);

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

        if ($this->showPermanentConfirmation && $this->deletionOutcome === '' && $this->userPendingDeletionId !== null) {
            $this->backToDeletionWarning();

            return;
        }

        $this->cancelDeletion();
    }

    public function dismissDeletionOutcome(): void
    {
        if ($this->deletionOutcome === 'success' && $this->deletionOutcomeName !== '') {
            $this->statusMessage = 'Usuário excluído';
            $this->toastDetail = '"'.$this->deletionOutcomeName.'" foi removido com sucesso.';
            $this->toastTone = 'success';
        }

        $this->deletionOutcome = '';
        $this->deletionOutcomeName = '';
        $this->clearDeletionPrompt();
    }

    public function deleteUser(DeleteUser $deleteUser): void
    {
        if ($this->deletionOutcome === 'success') {
            return;
        }

        abort_if($this->userPendingDeletionId === null, 404);

        $actor = auth()->user();
        $target = $this->userForCurrentClinic($this->userPendingDeletionId);
        $this->authorize('delete', $target);

        if (trim($this->deletionConfirmation) !== 'EXCLUIR') {
            $this->addError('deletionConfirmation', 'Digite EXCLUIR para confirmar a exclusão permanente.');

            return;
        }

        $deletedName = $target->name;

        try {
            $deleteUser->handle($actor, $target);
        } catch (LastAdministratorProtectedException $exception) {
            $this->addError('deletionConfirmation', $exception->getMessage());

            return;
        } catch (Throwable $exception) {
            Log::warning('Falha ao excluir usuário.', [
                'user_id' => $target->id,
                'clinic_id' => $target->clinic_id,
                'exception' => $exception::class,
                'message' => $exception->getMessage(),
            ]);
            $this->deletionOutcome = 'error';

            return;
        }

        $this->clearDeletionPrompt();
        $this->showPermanentConfirmation = true;
        $this->deletionOutcome = 'success';
        $this->deletionOutcomeName = $deletedName;
        $this->resetPage();
        unset($this->userStats);
    }

    /**
     * @return array{tickets: int, calls: int, transfers: int}
     */
    public function deletionImpact(): array
    {
        $clinicId = auth()->user()?->clinic_id;
        $userId = $this->userPendingDeletionId;

        if ($clinicId === null || $userId === null) {
            return ['tickets' => 0, 'calls' => 0, 'transfers' => 0];
        }

        return [
            'tickets' => (int) DB::table('tickets')
                ->where('clinic_id', $clinicId)
                ->where(function ($query) use ($userId): void {
                    $query->where('called_by_user_id', $userId)
                        ->orWhere('started_by_user_id', $userId)
                        ->orWhere('completed_by_user_id', $userId)
                        ->orWhere('no_show_by_user_id', $userId);
                })
                ->count(),
            'calls' => (int) DB::table('ticket_calls')
                ->where('clinic_id', $clinicId)
                ->where('called_by_user_id', $userId)
                ->count(),
            'transfers' => (int) DB::table('ticket_transfers')
                ->where('clinic_id', $clinicId)
                ->where('transferred_by_user_id', $userId)
                ->count(),
        ];
    }

    public function selectedUnitsLabel(): string
    {
        $ids = array_map(intval(...), $this->unitIds);
        $names = $this->availableUnits
            ->filter(fn (Unit $unit): bool => in_array($unit->id, $ids, true))
            ->pluck('name')
            ->values();

        if ($names->isEmpty()) {
            return '';
        }

        if ($names->count() <= 2) {
            return $names->join(', ');
        }

        return $names->take(2)->join(', ').' +'.($names->count() - 2);
    }

    /**
     * @return LengthAwarePaginator<int, User>
     */
    public function users(): LengthAwarePaginator
    {
        $clinicId = auth()->user()?->clinic_id;

        return User::query()
            ->with(['units:id,name,clinic_id'])
            ->where('clinic_id', $clinicId)
            ->when($this->search !== '', function ($query): void {
                $term = '%'.Str::lower($this->search).'%';
                $query->where(function ($search) use ($term): void {
                    $search->whereRaw('LOWER(name) like ?', [$term])
                        ->orWhereRaw('LOWER(email) like ?', [$term]);
                });
            })
            ->when($this->roleFilter !== '', fn ($query) => $query->where('role', $this->roleFilter))
            ->when($this->statusFilter === 'active', fn ($query) => $query->where('active', true))
            ->when($this->statusFilter === 'inactive', fn ($query) => $query->where('active', false))
            ->when($this->unitFilter !== '', function ($query): void {
                $unitId = (int) $this->unitFilter;
                $query->where(function ($inner) use ($unitId): void {
                    $inner->where('role', UserRole::ADMINISTRATOR->value)
                        ->orWhereHas('units', fn ($units) => $units->whereKey($unitId));
                });
            })
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
            ->get(['id', 'name', 'slug', 'clinic_id', 'active']);
    }

    /**
     * @return array{total: int, active: int, inactive: int, administrators: int}
     */
    #[Computed]
    public function userStats(): array
    {
        $clinicId = auth()->user()?->clinic_id;
        $row = User::query()
            ->where('clinic_id', $clinicId)
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when active = 1 then 1 else 0 end) as active_count')
            ->selectRaw('sum(case when active = 0 then 1 else 0 end) as inactive_count')
            ->selectRaw('sum(case when role = ? then 1 else 0 end) as administrator_count', [UserRole::ADMINISTRATOR->value])
            ->first();

        return [
            'total' => (int) ($row->total ?? 0),
            'active' => (int) ($row->active_count ?? 0),
            'inactive' => (int) ($row->inactive_count ?? 0),
            'administrators' => (int) ($row->administrator_count ?? 0),
        ];
    }

    public function render(): View
    {
        return view('livewire.users-manager', [
            'users' => $this->users(),
            'roles' => UserRole::cases(),
            'activeAdministratorCount' => User::query()
                ->where('clinic_id', auth()->user()?->clinic_id)
                ->where('role', UserRole::ADMINISTRATOR)
                ->where('active', true)
                ->count(),
        ]);
    }

    /**
     * @return array<string, list<mixed>>
     */
    protected function rules(): array
    {
        $clinicId = auth()->user()?->clinic_id;
        $passwordRules = ['nullable', 'string'];

        if ($this->editingUserId === null || $this->password !== '') {
            $passwordRules = [
                $this->editingUserId === null ? 'required' : 'nullable',
                'string',
                'confirmed',
                Password::min(8),
            ];
        }

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'email:rfc',
                Rule::unique('users', 'email')->ignore($this->editingUserId),
            ],
            'role' => ['required', Rule::enum(UserRole::class)],
            'password' => $passwordRules,
            'active' => ['boolean'],
            'unitIds' => ['array'],
            'unitIds.*' => [
                'integer',
                Rule::exists('units', 'id')->where(fn ($query) => $query->where('clinic_id', $clinicId)),
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    protected function messages(): array
    {
        return [
            'name.required' => 'Informe o nome do usuário.',
            'email.required' => 'Informe o e-mail do usuário.',
            'email.email' => 'Informe um e-mail válido.',
            'email.unique' => 'Já existe um usuário com este e-mail.',
            'role.required' => 'Selecione o perfil do usuário.',
            'password.required' => 'Informe a senha inicial.',
            'password.confirmed' => 'A confirmação da senha não confere.',
            'password.min' => 'A senha deve ter no mínimo 8 caracteres.',
            'unitIds.*.exists' => 'Uma ou mais unidades não pertencem à sua clínica.',
        ];
    }

    private function userForCurrentClinic(int $userId): User
    {
        return User::query()
            ->where('clinic_id', auth()->user()?->clinic_id)
            ->whereKey($userId)
            ->firstOrFail();
    }

    private function closeAccountDialogs(): void
    {
        $this->showForm = false;
        $this->showCreateConfirmation = false;
        $this->creationOutcome = '';
        $this->userPendingDeactivationId = null;
        $this->deactivationOutcome = '';
        $this->userPendingActivationId = null;
        $this->activationOutcome = '';
        $this->clearDeletionPrompt();
        $this->deletionOutcome = '';
    }

    private function clearDeletionPrompt(): void
    {
        $this->userPendingDeletionId = null;
        $this->showPermanentConfirmation = false;
        $this->deletionConfirmation = '';
        $this->deletionTargetEmail = '';
    }

    private function forgetUserSessions(int $userId): void
    {
        if (! Schema::hasTable('sessions')) {
            return;
        }

        DB::table('sessions')->where('user_id', $userId)->delete();
    }

    private function resetForm(): void
    {
        $this->resetValidation();
        $this->editingUserId = null;
        $this->showForm = false;
        $this->name = '';
        $this->email = '';
        $this->role = UserRole::ATTENDANT->value;
        $this->password = '';
        $this->password_confirmation = '';
        $this->active = true;
        $this->unitIds = [];
        $this->userPendingDeactivationId = null;
        $this->showCreateConfirmation = false;
        $this->creationOutcome = '';
        $this->creationOutcomeName = '';
        $this->creationOutcomeAccess = '';
    }

    private function prepareInput(): void
    {
        $this->email = Str::lower($this->email);

        try {
            $this->validate();
        } catch (ValidationException $exception) {
            $this->showCreateConfirmation = false;
            $this->dispatch('user-form-invalid');

            throw $exception;
        }
    }

    public function confirmedAccessLabel(): string
    {
        if ($this->role === UserRole::ADMINISTRATOR->value) {
            return 'Todas as unidades';
        }

        $names = $this->availableUnits
            ->whereIn('id', array_map(intval(...), $this->unitIds))
            ->pluck('name');

        if ($names->isEmpty()) {
            return 'Nenhuma unidade';
        }

        return $names->join(', ');
    }
}
