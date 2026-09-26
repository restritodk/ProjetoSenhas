<div>
    @if ($statusMessage !== '')
        <x-ui.toast
            :title="$statusMessage"
            :message="$toastDetail !== '' ? $toastDetail : null"
            :tone="$toastTone"
            dismiss-method="dismissStatus"
            wire:key="user-toast-{{ md5($statusMessage.$toastDetail) }}"
        />
    @endif

    @error('status')
        <x-ui.alert type="danger" class="mb-4">{{ $message }}</x-ui.alert>
    @enderror

    <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
        <div class="flex items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-accent" aria-hidden="true">
                <x-admin.icon name="users" class="size-5" />
            </span>
            <div>
                <h2 class="text-base font-semibold text-text">Usuários</h2>
                <p class="mt-1 text-sm text-text-muted">Gerencie a equipe e os acessos às unidades autorizadas.</p>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap gap-2" aria-label="Resumo dos usuários">
            <span class="inline-flex items-center gap-2 rounded-xl border border-[#d5e2f3] bg-[#eef3fb] px-3 py-2 text-sm text-text">
                <span class="font-semibold tabular-nums">{{ $this->userStats['total'] }} {{ $this->userStats['total'] === 1 ? 'usuário' : 'usuários' }}</span>
            </span>
            <span class="inline-flex items-center gap-2 rounded-xl border border-[#cfe8d8] bg-[#e9f7ee] px-3 py-2 text-sm text-text">
                <span class="size-1.5 rounded-full bg-success" aria-hidden="true"></span>
                <span class="font-semibold tabular-nums">{{ $this->userStats['active'] }} {{ $this->userStats['active'] === 1 ? 'ativo' : 'ativos' }}</span>
            </span>
            <span class="inline-flex items-center gap-2 rounded-xl border border-[#f3e2c2] bg-[#fff6e8] px-3 py-2 text-sm text-text">
                <span class="size-1.5 rounded-full bg-warning" aria-hidden="true"></span>
                <span class="font-semibold tabular-nums">{{ $this->userStats['inactive'] }} {{ $this->userStats['inactive'] === 1 ? 'inativo' : 'inativos' }}</span>
            </span>
            <span class="inline-flex items-center gap-2 rounded-xl border border-[#ddd6fe] bg-[#f5f3ff] px-3 py-2 text-sm text-text">
                <span class="font-semibold tabular-nums">{{ $this->userStats['administrators'] }} {{ $this->userStats['administrators'] === 1 ? 'administrador' : 'administradores' }}</span>
            </span>
        </div>

        <div class="mt-5 flex flex-col gap-2 xl:flex-row xl:items-center">
            <div class="relative min-w-0 flex-1">
                <label for="user-search" class="sr-only">Buscar por nome ou e-mail</label>
                <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-text-muted" aria-hidden="true">
                    <x-admin.icon name="search" class="size-4" />
                </span>
                <input
                    id="user-search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar por nome ou e-mail..."
                    class="min-h-11 w-full rounded-xl border border-border bg-surface py-2 pl-11 pr-3 text-sm text-text shadow-sm placeholder:text-text-muted focus:border-accent focus:ring-2 focus:ring-accent/25"
                >
            </div>
            <div class="grid gap-2 sm:grid-cols-3 xl:flex">
                <div>
                    <label for="user-role-filter" class="sr-only">Filtrar por perfil</label>
                    <select id="user-role-filter" wire:model.live="roleFilter" class="min-h-11 w-full cursor-pointer rounded-xl border border-border bg-surface px-3 text-sm text-text shadow-sm xl:w-44">
                        <option value="">Todos os perfis</option>
                        @foreach ($roles as $availableRole)
                            <option value="{{ $availableRole->value }}">{{ $availableRole->label() }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="user-status-filter" class="sr-only">Filtrar por status</label>
                    <select id="user-status-filter" wire:model.live="statusFilter" class="min-h-11 w-full cursor-pointer rounded-xl border border-border bg-surface px-3 text-sm text-text shadow-sm xl:w-40">
                        <option value="">Todos os status</option>
                        <option value="active">Ativos</option>
                        <option value="inactive">Inativos</option>
                    </select>
                </div>
                <div>
                    <label for="user-unit-filter" class="sr-only">Filtrar por unidade</label>
                    <select id="user-unit-filter" wire:model.live="unitFilter" class="min-h-11 w-full cursor-pointer rounded-xl border border-border bg-surface px-3 text-sm text-text shadow-sm xl:w-48">
                        <option value="">Todas as unidades</option>
                        @foreach ($this->availableUnits as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
            </div>
            <p wire:loading.flex wire:target="search,roleFilter,statusFilter,unitFilter" class="hidden items-center text-xs text-text-muted">Atualizando lista...</p>
        </div>

        <div class="mt-5">
            @php
                $filtersActive = $search !== '' || $roleFilter !== '' || $statusFilter !== '' || $unitFilter !== '';
            @endphp
            @if ($users->isEmpty())
                <x-ui.empty-state
                    icon="users"
                    :title="$filtersActive ? 'Nenhum usuário encontrado.' : 'Nenhum usuário cadastrado.'"
                    :description="$filtersActive ? 'Tente alterar os filtros ou o termo pesquisado.' : 'Cadastre o primeiro usuário da clínica.'"
                >
                    @if ($filtersActive)
                        <x-ui.button variant="secondary" wire:click="clearFilters">Limpar filtros</x-ui.button>
                    @else
                        <x-ui.button wire:click="startCreate">
                            <x-admin.icon name="plus" class="size-4" />
                            Novo usuário
                        </x-ui.button>
                    @endif
                </x-ui.empty-state>
            @else
                <div class="hidden md:block">
                    <table class="w-full text-left text-sm">
                        <caption class="sr-only">Usuários da clínica</caption>
                        <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                            <tr>
                                <th scope="col" class="px-3 py-3 font-semibold">Usuário</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Perfil</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Unidades autorizadas</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($users as $user)
                                <tr wire:key="user-row-{{ $user->id }}" class="border-b border-border/70 last:border-0">
                                    <td class="px-3 py-4">
                                        <div class="flex items-center gap-3">
                                            <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#e7eef8] text-xs font-semibold text-primary" aria-hidden="true">{{ $user->initials() }}</span>
                                            <div class="min-w-0">
                                                <p class="truncate font-semibold text-text">
                                                    {{ $user->name }}
                                                    @if ($user->is(auth()->user()))
                                                        <x-ui.badge class="ml-1">Você</x-ui.badge>
                                                    @endif
                                                </p>
                                                <p class="truncate text-sm text-text-muted">{{ $user->email }}</p>
                                            </div>
                                        </div>
                                    </td>
                                    <td class="px-3 py-4">
                                        <x-ui.badge :tone="$user->isAdministrator() ? 'accent' : 'neutral'">{{ $user->role->label() }}</x-ui.badge>
                                    </td>
                                    <td class="px-3 py-4">
                                        @if ($user->active)
                                            <x-ui.badge tone="success"><span class="mr-1.5 size-1.5 rounded-full bg-success" aria-hidden="true"></span>Ativo</x-ui.badge>
                                        @else
                                            <x-ui.badge tone="warning"><span class="mr-1.5 size-1.5 rounded-full bg-warning" aria-hidden="true"></span>Inativo</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="px-3 py-4 text-text">
                                        @if ($user->isAdministrator())
                                            Todas as unidades
                                        @elseif ($user->units->isEmpty())
                                            <span class="text-text-muted">—</span>
                                        @elseif ($user->units->count() === 1)
                                            {{ $user->units->first()->name }}
                                        @else
                                            <span class="block">{{ $user->units->first()->name }}</span>
                                            <span class="text-xs text-text-muted">+{{ $user->units->count() - 1 }} {{ $user->units->count() - 1 === 1 ? 'unidade' : 'unidades' }}</span>
                                        @endif
                                    </td>
                                    <td class="px-3 py-4">
                                        @include('livewire.partials.user-actions', ['user' => $user, 'menuContext' => 'tabela'])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="space-y-3 md:hidden">
                    @foreach ($users as $user)
                        <article wire:key="user-card-{{ $user->id }}" class="rounded-xl border border-border bg-background p-4">
                            <div class="flex items-start gap-3">
                                <span class="flex size-10 shrink-0 items-center justify-center rounded-full bg-[#e7eef8] text-xs font-semibold text-primary" aria-hidden="true">{{ $user->initials() }}</span>
                                <div class="min-w-0 flex-1">
                                    <h3 class="truncate font-semibold text-text">
                                        {{ $user->name }}
                                        @if ($user->is(auth()->user()))
                                            <x-ui.badge>Você</x-ui.badge>
                                        @endif
                                    </h3>
                                    <p class="truncate text-sm text-text-muted">{{ $user->email }}</p>
                                    <div class="mt-2 flex flex-wrap gap-2">
                                        <x-ui.badge :tone="$user->isAdministrator() ? 'accent' : 'neutral'">{{ $user->role->label() }}</x-ui.badge>
                                        @if ($user->active)
                                            <x-ui.badge tone="success"><span class="mr-1.5 size-1.5 rounded-full bg-success" aria-hidden="true"></span>Ativo</x-ui.badge>
                                        @else
                                            <x-ui.badge tone="warning"><span class="mr-1.5 size-1.5 rounded-full bg-warning" aria-hidden="true"></span>Inativo</x-ui.badge>
                                        @endif
                                    </div>
                                    <p class="mt-2 text-sm text-text-muted">
                                        @if ($user->isAdministrator())
                                            Todas as unidades
                                        @elseif ($user->units->isEmpty())
                                            —
                                        @else
                                            {{ $user->units->pluck('name')->join(', ') }}
                                        @endif
                                    </p>
                                </div>
                            </div>
                            <div class="mt-4">
                                @include('livewire.partials.user-actions', ['user' => $user, 'menuContext' => 'cartao'])
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="mt-4">{{ $users->links() }}</div>
            @endif
        </div>
    </section>

    <x-ui.modal
        id="usuario-form-titulo"
        icon="user"
        max-width="3xl"
        :title="$editingUserId ? 'Editar usuário' : 'Novo usuário'"
        :description="$editingUserId ? 'Atualize os dados e os acessos deste usuário.' : 'Cadastre um novo usuário e defina seus acessos.'"
        :open="$showForm && ! $showCreateConfirmation && $creationOutcome === ''"
        close-method="closeUserDialog"
        loading-target="save"
    >
        <form
            wire:submit="{{ $editingUserId ? 'save' : 'review' }}"
            class="space-y-6"
            x-data
            x-on:user-form-invalid.window="$nextTick(() => $root.querySelector('[aria-invalid=true]')?.focus())"
        >
            <section>
                <h3 class="text-sm font-semibold text-text">Dados do usuário</h3>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    <x-ui.input label="Nome completo" name="user_name" id="user_name" wire:model="name" required maxlength="255" autocomplete="name" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                        <x-input-error :messages="$errors->get('name')" />
                    </x-ui.input>
                    <x-ui.input label="E-mail" name="user_email" id="user_email" type="email" wire:model="email" required maxlength="255" autocomplete="off" aria-invalid="{{ $errors->has('email') ? 'true' : 'false' }}">
                        <x-input-error :messages="$errors->get('email')" />
                    </x-ui.input>
                </div>
            </section>

            <section>
                <h3 class="text-sm font-semibold text-text">Perfil e acesso</h3>
                <div class="mt-3 grid gap-4 md:grid-cols-2 md:items-end">
                    <div>
                        <x-ui.select label="Perfil" name="user_role" id="user_role" wire:model="role" required aria-invalid="{{ $errors->has('role') ? 'true' : 'false' }}">
                            @foreach ($roles as $availableRole)
                                <option value="{{ $availableRole->value }}">{{ $availableRole->label() }}</option>
                            @endforeach
                        </x-ui.select>
                        <x-input-error :messages="$errors->get('role')" />
                    </div>
                    <div>
                        <p class="mb-1.5 text-sm font-medium text-text">Status da conta</p>
                        <label class="flex min-h-12 items-center justify-between gap-4 rounded-xl border border-border bg-surface px-3 shadow-sm">
                            <span class="text-sm text-text">Conta ativa</span>
                            <span class="relative inline-flex shrink-0 items-center">
                                <input type="checkbox" wire:model="active" role="switch" class="peer sr-only" aria-label="Conta ativa">
                                <span class="h-6 w-11 rounded-full bg-border transition peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-accent peer-checked:bg-accent"></span>
                                <span class="pointer-events-none absolute left-0.5 size-5 rounded-full bg-white shadow-sm transition peer-checked:translate-x-5"></span>
                            </span>
                        </label>
                        <x-input-error :messages="$errors->get('active')" />
                    </div>
                </div>
            </section>

            <section x-data="{ visible: false }">
                <div class="flex items-center justify-between gap-3">
                    <h3 class="text-sm font-semibold text-text">{{ $editingUserId ? 'Nova senha' : 'Senha inicial' }}</h3>
                    <button type="button" class="inline-flex min-h-9 cursor-pointer items-center gap-1.5 rounded-lg px-2 text-xs font-medium text-text-muted hover:bg-background focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent" @click="visible = ! visible" :aria-pressed="visible.toString()">
                        <x-admin.icon name="eye" class="size-4" />
                        <span x-text="visible ? 'Ocultar senha' : 'Mostrar senha'"></span>
                    </button>
                </div>
                <div class="mt-3 grid gap-4 md:grid-cols-2">
                    <div>
                        <label for="user_password" class="mb-1.5 block text-sm font-medium text-text">{{ $editingUserId ? 'Nova senha' : 'Senha inicial' }}</label>
                        <input id="user_password" name="user_password" :type="visible ? 'text' : 'password'" wire:model="password" autocomplete="new-password" @unless ($editingUserId) required @endunless aria-invalid="{{ $errors->has('password') ? 'true' : 'false' }}" class="min-h-12 w-full rounded-xl border border-border bg-surface px-3.5 py-2.5 text-sm text-text shadow-sm focus:border-accent focus:ring-2 focus:ring-accent/25">
                        <p class="mt-1 text-xs text-text-muted">{{ $editingUserId ? 'Deixe em branco para manter a senha atual.' : 'Mínimo de 8 caracteres.' }}</p>
                        <x-input-error :messages="$errors->get('password')" />
                    </div>
                    <div>
                        <label for="user_password_confirmation" class="mb-1.5 block text-sm font-medium text-text">Confirmar senha</label>
                        <input id="user_password_confirmation" name="user_password_confirmation" :type="visible ? 'text' : 'password'" wire:model="password_confirmation" autocomplete="new-password" @unless ($editingUserId) required @endunless class="min-h-12 w-full rounded-xl border border-border bg-surface px-3.5 py-2.5 text-sm text-text shadow-sm focus:border-accent focus:ring-2 focus:ring-accent/25">
                        <x-input-error :messages="$errors->get('password_confirmation')" />
                    </div>
                </div>
            </section>

            <section>
                @include('livewire.partials.unit-multi-select')
            </section>

            <button type="submit" class="sr-only">{{ $editingUserId ? 'Salvar alterações' : 'Criar usuário' }}</button>
        </form>
        <x-slot:actions>
            <x-ui.button variant="secondary" type="button" class="w-full sm:w-auto" wire:click="cancel">Cancelar</x-ui.button>
            <x-ui.button type="button" class="w-full sm:w-auto" wire:click="{{ $editingUserId ? 'save' : 'review' }}" wire:loading.attr="disabled" wire:target="review,save">
                <span wire:loading.remove wire:target="review,save">{{ $editingUserId ? 'Salvar alterações' : 'Criar usuário' }}</span>
                <span wire:loading wire:target="review,save">Validando...</span>
            </x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal
        id="usuario-confirmar-titulo"
        variant="confirm"
        max-width="lg"
        :icon="$creationOutcome === 'success' ? null : 'user'"
        :title="$creationOutcome === 'success' ? 'Usuário criado!' : 'Criar novo usuário?'"
        :description="$creationOutcome === 'success' ? '“'.$creationOutcomeName.'” foi cadastrado com sucesso.' : 'Confira os dados antes de continuar.'"
        :open="($showCreateConfirmation && $creationOutcome === '') || $creationOutcome === 'success'"
        close-method="closeUserDialog"
        loading-target="confirmCreate"
    >
        <x-slot:processing>
            @include('livewire.partials.user-creation-processing')
        </x-slot:processing>
        @if ($creationOutcome === 'success')
            @include('livewire.partials.user-creation-success')
        @else
            <dl class="mx-auto max-w-sm space-y-3 text-left">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Nome</dt>
                    <dd class="text-sm font-medium text-text">{{ $name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">E-mail</dt>
                    <dd class="text-sm font-medium text-text">{{ $email }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Perfil</dt>
                    <dd class="text-sm font-medium text-text">{{ \App\UserRole::tryFrom($role)?->label() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Status</dt>
                    <dd class="text-sm font-medium text-text">{{ $active ? 'Ativo' : 'Inativo' }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Unidades autorizadas</dt>
                    <dd class="text-sm font-medium text-text">{{ $this->confirmedAccessLabel() }}</dd>
                </div>
            </dl>
            <p class="mt-4 font-medium text-text">Deseja criar este usuário?</p>
        @endif
        @if ($creationOutcome !== 'success')
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="backToUserForm" wire:loading.attr="disabled" wire:target="confirmCreate">Voltar e editar</x-ui.button>
                <x-ui.button class="w-full sm:w-auto" wire:click="confirmCreate" wire:loading.attr="disabled" wire:target="confirmCreate">
                    <span wire:loading.remove wire:target="confirmCreate">Sim, criar usuário</span>
                    <span wire:loading wire:target="confirmCreate">Criando usuário...</span>
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.modal>

    @php
        $deletionImpact = $this->deletionImpact();
    @endphp

    <x-ui.modal
        id="desativar-usuario-titulo"
        variant="confirm"
        :icon="$deactivationOutcome === 'success' ? null : 'ban'"
        icon-tone="warning"
        :title="$deactivationOutcome === 'success' ? 'Usuário desativado' : 'Desativar usuário?'"
        :description="$deactivationOutcome === 'success' ? 'O acesso de “'.$deactivationOutcomeName.'” foi desativado com sucesso.' : null"
        :open="$userPendingDeactivationId !== null || $deactivationOutcome === 'success'"
        close-method="closeDeactivationDialog"
        loading-target="deactivate"
    >
        <x-slot:processing>
            @include('livewire.partials.user-status-processing', ['title' => 'Desativando usuário...', 'detail' => 'Aguarde enquanto o acesso está sendo atualizado.'])
        </x-slot:processing>
        @if ($deactivationOutcome === 'success')
            @include('livewire.partials.user-status-success', ['dismiss' => 'dismissDeactivationOutcome'])
            <p class="sr-only">Usuário desativado.</p>
        @else
            <p>Você está prestes a desativar:</p>
            <p class="mt-2 font-semibold text-text">“{{ $deactivationOutcomeName }}”</p>
            <p class="mt-3">O usuário perderá o acesso ao sistema e não poderá realizar um novo login enquanto a conta estiver desativada.</p>
            <p class="mt-2">Os dados e históricos do usuário serão preservados.</p>
            @error('status')
                <p class="mt-3 text-sm text-danger" role="alert">{{ $message }}</p>
            @enderror
        @endif
        @if ($deactivationOutcome !== 'success')
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancelDeactivation" wire:loading.attr="disabled" wire:target="deactivate">Cancelar</x-ui.button>
                <x-ui.button variant="danger" class="w-full sm:w-auto" wire:click="deactivate" wire:loading.attr="disabled" wire:target="deactivate">
                    <span wire:loading.remove wire:target="deactivate">Sim, desativar</span>
                    <span wire:loading wire:target="deactivate">Desativando...</span>
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.modal>

    <x-ui.modal
        id="reativar-usuario-titulo"
        variant="confirm"
        :icon="$activationOutcome === 'success' ? null : 'check'"
        icon-tone="success"
        :title="$activationOutcome === 'success' ? 'Usuário reativado' : 'Reativar usuário?'"
        :description="$activationOutcome === 'success' ? 'O acesso de “'.$activationOutcomeName.'” foi restaurado.' : null"
        :open="$userPendingActivationId !== null || $activationOutcome === 'success'"
        close-method="closeActivationDialog"
        loading-target="activate"
    >
        <x-slot:processing>
            @include('livewire.partials.user-status-processing', ['title' => 'Reativando usuário...', 'detail' => 'Aguarde enquanto o acesso está sendo restaurado.'])
        </x-slot:processing>
        @if ($activationOutcome === 'success')
            @include('livewire.partials.user-status-success', ['dismiss' => 'dismissActivationOutcome'])
            <p class="sr-only">Usuário ativado.</p>
        @else
            <p>Deseja restaurar o acesso de:</p>
            <p class="mt-2 font-semibold text-text">“{{ $activationOutcomeName }}”</p>
        @endif
        @if ($activationOutcome !== 'success')
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancelActivation" wire:loading.attr="disabled" wire:target="activate">Cancelar</x-ui.button>
                <x-ui.button class="w-full sm:w-auto" wire:click="activate({{ (int) $userPendingActivationId }})" wire:loading.attr="disabled" wire:target="activate">
                    <span wire:loading.remove wire:target="activate">Sim, reativar</span>
                    <span wire:loading wire:target="activate">Reativando...</span>
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.modal>

    <x-ui.modal
        id="excluir-usuario-titulo"
        variant="confirm"
        :icon="$deletionOutcome === 'success' ? null : 'trash'"
        icon-tone="danger"
        :title="$deletionOutcome === 'error' ? 'Não foi possível excluir' : ($deletionOutcome === 'success' ? 'Usuário excluído!' : ($showPermanentConfirmation ? 'Confirmação permanente' : 'Excluir usuário?'))"
        :description="$deletionOutcome === 'success' ? '“'.$deletionOutcomeName.'” foi removido com sucesso.' : null"
        :open="$userPendingDeletionId !== null || $deletionOutcome !== ''"
        close-method="closeDeletionDialog"
        loading-target="deleteUser"
    >
        <x-slot:processing>
            @include('livewire.partials.user-status-processing', ['title' => 'Excluindo usuário...', 'detail' => 'Estamos removendo a conta e tratando os dados vinculados com segurança.'])
        </x-slot:processing>
        @if ($deletionOutcome === 'success')
            @include('livewire.partials.user-status-success', ['dismiss' => 'dismissDeletionOutcome'])
        @elseif ($deletionOutcome === 'error')
            <p>Não foi possível concluir a exclusão.</p>
            <p class="mt-2">Nenhuma alteração parcial foi mantida.</p>
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancelDeletion">Fechar</x-ui.button>
                <x-ui.button class="w-full sm:w-auto" wire:click="deleteUser">Tentar novamente</x-ui.button>
            </x-slot:actions>
        @elseif ($showPermanentConfirmation)
            <p>Para confirmar a exclusão de <span class="font-semibold text-text">“{{ $deletionOutcomeName }}”</span>, digite:</p>
            <p class="mt-2 font-semibold tracking-wide text-text">EXCLUIR</p>
            <label class="mt-4 block text-left" for="confirmar-exclusao-usuario">
                <span class="sr-only">Digite EXCLUIR</span>
                <input id="confirmar-exclusao-usuario" type="text" wire:model.live="deletionConfirmation" autocomplete="off" class="min-h-12 w-full rounded-xl border border-border bg-surface px-3.5 text-sm text-text shadow-sm focus:border-accent focus:ring-2 focus:ring-accent/25">
            </label>
            @error('deletionConfirmation')
                <p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>
            @enderror
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="backToDeletionWarning" wire:loading.attr="disabled" wire:target="deleteUser">Voltar</x-ui.button>
                <x-ui.button variant="danger" class="w-full sm:w-auto" wire:click="deleteUser" wire:loading.attr="disabled" wire:target="deleteUser" :disabled="trim($deletionConfirmation) !== 'EXCLUIR'">
                    <span wire:loading.remove wire:target="deleteUser">Excluir permanentemente</span>
                    <span wire:loading wire:target="deleteUser">Excluindo...</span>
                </x-ui.button>
            </x-slot:actions>
        @else
            <p>Você está prestes a excluir permanentemente:</p>
            <p class="mt-2 font-semibold text-text">“{{ $deletionOutcomeName }}”</p>
            @if ($deletionTargetEmail !== '')
                <p class="mt-2 text-sm">E-mail: <span class="font-medium text-text">{{ $deletionTargetEmail }}</span></p>
            @endif
            <p class="mt-3">Esta ação não poderá ser desfeita.</p>
            @if ($deletionImpact['tickets'] > 0 || $deletionImpact['calls'] > 0 || $deletionImpact['transfers'] > 0)
                <ul class="mt-3 space-y-1 text-sm text-text">
                    @if ($deletionImpact['tickets'] > 0)
                        <li>{{ $deletionImpact['tickets'] }} {{ $deletionImpact['tickets'] === 1 ? 'atendimento' : 'atendimentos' }}</li>
                    @endif
                    @if ($deletionImpact['calls'] > 0)
                        <li>{{ $deletionImpact['calls'] }} {{ $deletionImpact['calls'] === 1 ? 'chamada' : 'chamadas' }}</li>
                    @endif
                    @if ($deletionImpact['transfers'] > 0)
                        <li>{{ $deletionImpact['transfers'] }} {{ $deletionImpact['transfers'] === 1 ? 'transferência' : 'transferências' }}</li>
                    @endif
                </ul>
            @endif
            @error('deletionConfirmation')
                <p class="mt-3 text-sm text-danger" role="alert">{{ $message }}</p>
            @enderror
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancelDeletion">Não, cancelar</x-ui.button>
                <x-ui.button variant="danger" class="w-full sm:w-auto" wire:click="requestPermanentDeletion">Excluir mesmo assim</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.modal>
</div>
