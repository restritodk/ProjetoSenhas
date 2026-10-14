<div>
    @if ($statusMessage !== '')
        <x-ui.toast
            :title="$statusMessage"
            :message="$toastDetail !== '' ? $toastDetail : null"
            :tone="$toastTone"
            dismiss-method="dismissStatus"
            wire:key="sector-toast-{{ md5($statusMessage.$toastDetail) }}"
        />
    @endif

    <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
        <div class="flex items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-accent" aria-hidden="true">
                <x-admin.icon name="layers" class="size-5" />
            </span>
            <div>
                <h2 class="text-base font-semibold text-text">Setores</h2>
                <p class="mt-1 text-sm text-text-muted">Gerencie as áreas de atendimento vinculadas às unidades.</p>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap gap-2" aria-label="Resumo dos setores">
            <span class="inline-flex items-center gap-2 rounded-xl border border-[#d5e2f3] bg-[#eef3fb] px-3 py-2 text-sm text-text">
                <span class="font-semibold tabular-nums">{{ $this->sectorStats['total'] }} {{ $this->sectorStats['total'] === 1 ? 'setor' : 'setores' }}</span>
            </span>
            <span class="inline-flex items-center gap-2 rounded-xl border border-[#cfe8d8] bg-[#e9f7ee] px-3 py-2 text-sm text-text">
                <span class="size-1.5 rounded-full bg-success" aria-hidden="true"></span>
                <span class="font-semibold tabular-nums">{{ $this->sectorStats['active'] }} {{ $this->sectorStats['active'] === 1 ? 'ativo' : 'ativos' }}</span>
            </span>
            <span class="inline-flex items-center gap-2 rounded-xl border border-[#f3e2c2] bg-[#fff6e8] px-3 py-2 text-sm text-text">
                <span class="size-1.5 rounded-full bg-warning" aria-hidden="true"></span>
                <span class="font-semibold tabular-nums">{{ $this->sectorStats['inactive'] }} {{ $this->sectorStats['inactive'] === 1 ? 'inativo' : 'inativos' }}</span>
            </span>
            <span class="inline-flex items-center gap-2 rounded-xl border border-[#ddd6fe] bg-[#f5f3ff] px-3 py-2 text-sm text-text">
                <span class="font-semibold tabular-nums">{{ $this->sectorStats['desks'] }} {{ $this->sectorStats['desks'] === 1 ? 'mesa vinculada' : 'mesas vinculadas' }}</span>
            </span>
        </div>

        <div class="mt-5 flex flex-col gap-2 lg:flex-row lg:items-center">
            <div class="relative min-w-0 flex-1">
                <label for="sector-search" class="sr-only">Buscar setor</label>
                <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-text-muted" aria-hidden="true">
                    <x-admin.icon name="search" class="size-4" />
                </span>
                <input
                    id="sector-search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar setor..."
                    class="min-h-11 w-full rounded-xl border border-border bg-surface py-2 pl-11 pr-3 text-sm text-text shadow-sm placeholder:text-text-muted focus:border-accent focus:ring-2 focus:ring-accent/25"
                >
            </div>
            <div class="grid gap-2 sm:grid-cols-2 lg:flex">
                <div>
                    <label for="sector-unit-filter" class="sr-only">Filtrar por unidade</label>
                    <select id="sector-unit-filter" wire:model.live="unitFilter" class="min-h-11 w-full cursor-pointer rounded-xl border border-border bg-surface px-3 text-sm text-text shadow-sm lg:w-52">
                        <option value="">Todas as unidades</option>
                        @foreach ($this->availableUnits as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="sector-status-filter" class="sr-only">Filtrar por status</label>
                    <select id="sector-status-filter" wire:model.live="statusFilter" class="min-h-11 w-full cursor-pointer rounded-xl border border-border bg-surface px-3 text-sm text-text shadow-sm lg:w-44">
                        <option value="">Todos os status</option>
                        <option value="active">Ativos</option>
                        <option value="inactive">Inativos</option>
                    </select>
                </div>
            </div>
            <p wire:loading.flex wire:target="search,unitFilter,statusFilter" class="hidden items-center text-xs text-text-muted">Atualizando lista...</p>
        </div>

        <div class="mt-5">
            @php
                $filtersActive = $search !== '' || $unitFilter !== '' || $statusFilter !== '';
            @endphp
            @if ($sectors->isEmpty())
                <x-ui.empty-state
                    icon="layers"
                    :title="$filtersActive ? 'Nenhum setor encontrado' : 'Nenhum setor cadastrado'"
                    :description="$filtersActive ? 'Não encontramos setores com os filtros selecionados.' : 'Crie o primeiro setor para organizar suas áreas de atendimento.'"
                >
                    @if ($filtersActive)
                        <x-ui.button variant="secondary" wire:click="clearFilters">Limpar filtros</x-ui.button>
                    @else
                        <x-ui.button wire:click="startCreate">
                            <x-admin.icon name="plus" class="size-4" />
                            Novo setor
                        </x-ui.button>
                    @endif
                </x-ui.empty-state>
            @else
                <div class="hidden md:block">
                    <table class="w-full text-left text-sm">
                        <caption class="sr-only">Setores da clínica</caption>
                        <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                            <tr>
                                <th scope="col" class="px-3 py-3 font-semibold">Setor</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Unidade</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Código</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Mesas</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($sectors as $sector)
                                <tr wire:key="sector-row-{{ $sector->id }}" class="border-b border-border/70 last:border-0">
                                    <td class="px-3 py-4">
                                        <p class="font-semibold text-text">{{ $sector->name }}</p>
                                        <p class="text-sm text-text-muted">{{ $sector->description ?: 'Área de atendimento' }}</p>
                                    </td>
                                    <td class="px-3 py-4 text-text">{{ $sector->unit?->name }}</td>
                                    <td class="px-3 py-4">
                                        <span class="inline-flex rounded-lg border border-border bg-background px-2 py-1 font-mono text-xs font-medium tracking-wide text-text">{{ $sector->code }}</span>
                                    </td>
                                    <td class="px-3 py-4 tabular-nums text-text">{{ $sector->desks_count }}</td>
                                    <td class="px-3 py-4">
                                        @if ($sector->active)
                                            <x-ui.badge tone="success"><span class="mr-1.5 size-1.5 rounded-full bg-success" aria-hidden="true"></span>Ativo</x-ui.badge>
                                        @else
                                            <x-ui.badge tone="warning"><span class="mr-1.5 size-1.5 rounded-full bg-warning" aria-hidden="true"></span>Inativo</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="px-3 py-4">
                                        @include('livewire.partials.sector-actions', ['sector' => $sector, 'menuContext' => 'tabela'])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="space-y-3 md:hidden">
                    @foreach ($sectors as $sector)
                        <article wire:key="sector-card-{{ $sector->id }}" class="rounded-xl border border-border bg-background p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate font-semibold text-text">{{ $sector->name }}</h3>
                                    <p class="truncate text-sm text-text-muted">{{ $sector->unit?->name }}</p>
                                </div>
                                @include('livewire.partials.sector-actions', ['sector' => $sector, 'menuContext' => 'cartao'])
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-lg border border-border bg-surface px-2 py-1 font-mono text-xs font-medium tracking-wide text-text">{{ $sector->code }}</span>
                                <span class="text-sm text-text-muted">{{ $sector->desks_count }} {{ $sector->desks_count === 1 ? 'mesa' : 'mesas' }}</span>
                                @if ($sector->active)
                                    <x-ui.badge tone="success"><span class="mr-1.5 size-1.5 rounded-full bg-success" aria-hidden="true"></span>Ativo</x-ui.badge>
                                @else
                                    <x-ui.badge tone="warning"><span class="mr-1.5 size-1.5 rounded-full bg-warning" aria-hidden="true"></span>Inativo</x-ui.badge>
                                @endif
                            </div>
                            <p class="mt-2 text-sm text-text-muted">{{ $sector->description ?: 'Área de atendimento' }}</p>
                        </article>
                    @endforeach
                </div>
                <div class="mt-4">{{ $sectors->links() }}</div>
            @endif
        </div>
    </section>

    <x-ui.modal
        id="setor-form-titulo"
        icon="layers"
        max-width="3xl"
        :title="$editingSectorId ? 'Editar setor' : 'Novo setor'"
        :description="$editingSectorId ? 'Atualize os dados desta área de atendimento.' : 'Cadastre uma nova área de atendimento.'"
        :open="$showForm && ! $showCreateConfirmation && $creationOutcome === '' && ! $showEditConfirmation && $editOutcome === ''"
        close-method="closeSectorDialog"
        loading-target="review,save"
    >
        <form
            wire:submit="{{ $editingSectorId ? 'save' : 'review' }}"
            class="space-y-5"
            x-data
            x-on:sector-form-invalid.window="$nextTick(() => $root.querySelector('[aria-invalid=true]')?.focus())"
        >
            <x-ui.input label="Nome do setor" name="sector_name" id="sector_name" wire:model="name" required maxlength="255" autocomplete="off" placeholder="Ex.: Recepção" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                <x-input-error :messages="$errors->get('name')" />
            </x-ui.input>

            <div>
                <label for="sector_unit" class="mb-1.5 block text-sm font-medium text-text">Unidade</label>
                <select id="sector_unit" wire:model.live="unitId" required aria-invalid="{{ $errors->has('unitId') ? 'true' : 'false' }}" class="min-h-12 w-full cursor-pointer rounded-xl border border-border bg-surface px-3.5 text-sm text-text shadow-sm focus:border-accent focus:ring-2 focus:ring-accent/25">
                    <option value="">Selecione uma unidade</option>
                    @foreach ($this->availableUnits as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-text-muted">O setor fica vinculado a uma unidade desta clínica.</p>
                <x-input-error :messages="$errors->get('unitId')" />
            </div>

            <x-ui.input label="Código" name="sector_code" id="sector_code" wire:model="code" required maxlength="32" autocomplete="off" placeholder="Ex.: RECEPCAO" class="font-mono uppercase" aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}">
                <p class="mt-1 text-xs text-text-muted">Único nesta unidade. Letras, números, hífen ou sublinhado. Convertido para maiúsculas.</p>
                <x-input-error :messages="$errors->get('code')" />
            </x-ui.input>

            <x-ui.input label="Descrição" name="sector_description" id="sector_description" wire:model="description" maxlength="255" autocomplete="off" aria-invalid="{{ $errors->has('description') ? 'true' : 'false' }}">
                <p class="mt-1 text-xs text-text-muted">Opcional. Aparece como apoio na lista.</p>
                <x-input-error :messages="$errors->get('description')" />
            </x-ui.input>

            <div>
                <p class="mb-1.5 text-sm font-medium text-text">Status</p>
                <label class="flex min-h-12 items-center justify-between gap-4 rounded-xl border border-border bg-surface px-3 shadow-sm">
                    <span class="text-sm text-text">Setor ativo</span>
                    <span class="relative inline-flex shrink-0 items-center">
                        <input type="checkbox" wire:model="active" role="switch" class="peer sr-only" aria-label="Setor ativo">
                        <span class="h-6 w-11 rounded-full bg-border transition peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-accent peer-checked:bg-accent"></span>
                        <span class="pointer-events-none absolute left-0.5 size-5 rounded-full bg-white shadow-sm transition peer-checked:translate-x-5"></span>
                    </span>
                </label>
            </div>

            <button type="submit" class="sr-only">{{ $editingSectorId ? 'Salvar alterações' : 'Criar setor' }}</button>
        </form>
        <x-slot:actions>
            <x-ui.button variant="secondary" type="button" class="w-full sm:w-auto" wire:click="cancel">Cancelar</x-ui.button>
            <x-ui.button type="button" class="w-full sm:w-auto" wire:click="{{ $editingSectorId ? 'save' : 'review' }}" wire:loading.attr="disabled" wire:target="review,save">
                <span wire:loading.remove wire:target="review,save">{{ $editingSectorId ? 'Salvar alterações' : 'Criar setor' }}</span>
                <span wire:loading wire:target="review,save">Validando...</span>
            </x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal
        id="setor-confirmar-titulo"
        variant="confirm"
        max-width="lg"
        :icon="$creationOutcome === 'success' ? null : 'layers'"
        :title="$creationOutcome === 'success' ? 'Setor criado!' : 'Criar novo setor?'"
        :description="$creationOutcome === 'success' ? 'O setor “'.$creationOutcomeName.'” foi cadastrado com sucesso em “'.$creationOutcomeUnit.'”.' : 'Confira os dados antes de continuar.'"
        :open="($showCreateConfirmation && $creationOutcome === '') || $creationOutcome === 'success'"
        close-method="closeSectorDialog"
        loading-target="confirmCreate"
    >
        <x-slot:processing>
            @include('livewire.partials.sector-status-processing', ['title' => 'Criando setor...', 'detail' => 'Estamos configurando a nova área de atendimento.'])
        </x-slot:processing>
        @if ($creationOutcome === 'success')
            @include('livewire.partials.user-status-success', ['dismiss' => 'dismissCreationOutcome'])
        @else
            <dl class="mx-auto max-w-sm space-y-3 text-left">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Setor</dt>
                    <dd class="text-sm font-medium text-text">{{ $name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Unidade</dt>
                    <dd class="text-sm font-medium text-text">{{ $creationOutcomeUnit !== '' ? $creationOutcomeUnit : $this->selectedUnitName() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Código</dt>
                    <dd class="font-mono text-sm font-medium text-text">{{ $code }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Status</dt>
                    <dd class="text-sm font-medium text-text">{{ $active ? 'Ativo' : 'Inativo' }}</dd>
                </div>
            </dl>
            <p class="mt-4 font-medium text-text">Deseja cadastrar este setor?</p>
        @endif
        @if ($creationOutcome !== 'success')
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="backToSectorForm" wire:loading.attr="disabled" wire:target="confirmCreate">Não, voltar</x-ui.button>
                <x-ui.button class="w-full sm:w-auto" wire:click="confirmCreate" wire:loading.attr="disabled" wire:target="confirmCreate">
                    <span wire:loading.remove wire:target="confirmCreate">Sim, criar setor</span>
                    <span wire:loading wire:target="confirmCreate">Criando setor...</span>
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.modal>

    <x-ui.modal
        id="setor-editar-confirmar-titulo"
        variant="confirm"
        max-width="lg"
        :icon="$editOutcome === 'success' ? null : 'pencil'"
        :title="$editOutcome === 'success' ? 'Alterações salvas!' : 'Salvar alterações?'"
        :description="$editOutcome === 'success' ? 'Os dados de “'.$editOutcomeName.'” foram atualizados.' : 'Confira os dados antes de continuar.'"
        :open="($showEditConfirmation && $editOutcome === '') || $editOutcome === 'success'"
        close-method="closeSectorDialog"
        loading-target="confirmUpdate"
    >
        <x-slot:processing>
            @include('livewire.partials.sector-status-processing', ['title' => 'Salvando alterações...', 'detail' => 'Estamos atualizando esta área de atendimento.'])
        </x-slot:processing>
        @if ($editOutcome === 'success')
            @include('livewire.partials.user-status-success', ['dismiss' => 'dismissEditOutcome'])
        @else
            <dl class="mx-auto max-w-sm space-y-3 text-left">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Setor</dt>
                    <dd class="text-sm font-medium text-text">{{ $name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Unidade</dt>
                    <dd class="text-sm font-medium text-text">{{ $this->selectedUnitName() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Código</dt>
                    <dd class="font-mono text-sm font-medium text-text">{{ $code }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Status</dt>
                    <dd class="text-sm font-medium text-text">{{ $active ? 'Ativo' : 'Inativo' }}</dd>
                </div>
            </dl>
        @endif
        @if ($editOutcome !== 'success')
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="backToSectorForm" wire:loading.attr="disabled" wire:target="confirmUpdate">Não, voltar</x-ui.button>
                <x-ui.button class="w-full sm:w-auto" wire:click="confirmUpdate" wire:loading.attr="disabled" wire:target="confirmUpdate">
                    <span wire:loading.remove wire:target="confirmUpdate">Sim, salvar</span>
                    <span wire:loading wire:target="confirmUpdate">Salvando alterações...</span>
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.modal>

    @php
        $deactivationImpact = $this->deactivationImpact();
    @endphp

    <x-ui.modal
        id="desativar-setor-titulo"
        variant="confirm"
        :icon="$deactivationOutcome === 'success' ? null : 'ban'"
        icon-tone="warning"
        :title="$deactivationOutcome === 'success' ? 'Setor desativado!' : 'Desativar setor?'"
        :description="$deactivationOutcome === 'success' ? 'O setor “'.$deactivationOutcomeName.'” não receberá novos direcionamentos enquanto estiver inativo.' : null"
        :open="$sectorPendingDeactivationId !== null || $deactivationOutcome === 'success'"
        close-method="closeDeactivationDialog"
        loading-target="deactivate"
    >
        <x-slot:processing>
            @include('livewire.partials.sector-status-processing', ['title' => 'Desativando setor...', 'detail' => 'Aguarde enquanto o status está sendo atualizado.'])
        </x-slot:processing>
        @if ($deactivationOutcome === 'success')
            @include('livewire.partials.user-status-success', ['dismiss' => 'dismissDeactivationOutcome'])
        @else
            <p>Você está prestes a desativar o setor</p>
            <p class="mt-2 font-semibold text-text">“{{ $deactivationOutcomeName }}”</p>
            @if ($deactivationOutcomeUnit !== '')
                <p class="mt-2">Unidade: <span class="font-medium text-text">{{ $deactivationOutcomeUnit }}</span></p>
            @endif
            @if ($deactivationImpact['desks'] > 0 || $deactivationImpact['kiosks'] > 0)
                <p class="mt-3">Este setor possui:</p>
                <ul class="mt-2 space-y-1 text-sm text-text">
                    @if ($deactivationImpact['desks'] > 0)
                        <li>{{ $deactivationImpact['desks'] }} {{ $deactivationImpact['desks'] === 1 ? 'mesa vinculada' : 'mesas vinculadas' }}</li>
                    @endif
                    @if ($deactivationImpact['kiosks'] > 0)
                        <li>{{ $deactivationImpact['kiosks'] }} {{ $deactivationImpact['kiosks'] === 1 ? 'totem vinculado' : 'totens vinculados' }}</li>
                    @endif
                </ul>
            @endif
            <p class="mt-3">Novos atendimentos não deverão ser direcionados para este setor enquanto ele estiver inativo.</p>
        @endif
        @if ($deactivationOutcome !== 'success')
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancelDeactivation" wire:loading.attr="disabled" wire:target="deactivate">Não, cancelar</x-ui.button>
                <x-ui.button variant="danger" class="w-full sm:w-auto" wire:click="deactivate" wire:loading.attr="disabled" wire:target="deactivate">
                    <span wire:loading.remove wire:target="deactivate">Sim, desativar</span>
                    <span wire:loading wire:target="deactivate">Desativando setor...</span>
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.modal>

    <x-ui.modal
        id="ativar-setor-titulo"
        variant="confirm"
        :icon="$activationOutcome === 'success' ? null : 'check'"
        icon-tone="success"
        :title="$activationOutcome === 'success' ? 'Setor ativado!' : 'Ativar setor?'"
        :description="$activationOutcome === 'success' ? 'O setor “'.$activationOutcomeName.'” voltou a ficar disponível.' : null"
        :open="$sectorPendingActivationId !== null || $activationOutcome === 'success'"
        close-method="closeActivationDialog"
        loading-target="activate"
    >
        <x-slot:processing>
            @include('livewire.partials.sector-status-processing', ['title' => 'Ativando setor...', 'detail' => 'Aguarde enquanto o status está sendo atualizado.'])
        </x-slot:processing>
        @if ($activationOutcome === 'success')
            @include('livewire.partials.user-status-success', ['dismiss' => 'dismissActivationOutcome'])
        @else
            <p>Deseja reativar “{{ $activationOutcomeName }}”?</p>
        @endif
        @if ($activationOutcome !== 'success')
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancelActivation" wire:loading.attr="disabled" wire:target="activate">Não, cancelar</x-ui.button>
                <x-ui.button class="w-full sm:w-auto" wire:click="activate({{ (int) $sectorPendingActivationId }})" wire:loading.attr="disabled" wire:target="activate">
                    <span wire:loading.remove wire:target="activate">Sim, ativar</span>
                    <span wire:loading wire:target="activate">Ativando setor...</span>
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.modal>

    @php
        $deletionImpact = $this->deletionImpact();
    @endphp

    <x-ui.modal
        id="excluir-setor-titulo"
        variant="confirm"
        :icon="in_array($deletionOutcome, ['success'], true) ? null : 'trash'"
        icon-tone="danger"
        :title="match ($deletionOutcome) {
            'success' => 'Setor excluído!',
            'blocked' => 'Não foi possível concluir a operação.',
            'error' => 'Não foi possível concluir a operação.',
            default => $showPermanentConfirmation ? 'Confirmação permanente' : 'Excluir setor?',
        }"
        :description="$deletionOutcome === 'success' ? 'O setor “'.$deletionOutcomeName.'” foi removido com sucesso.' : null"
        :open="$sectorPendingDeletionId !== null || $deletionOutcome !== ''"
        close-method="closeDeletionDialog"
        loading-target="deleteSector"
    >
        <x-slot:processing>
            @include('livewire.partials.sector-status-processing', ['title' => 'Excluindo setor...', 'detail' => 'Estamos tratando os dados vinculados com segurança.'])
        </x-slot:processing>
        @if ($deletionOutcome === 'success')
            @include('livewire.partials.user-status-success', ['dismiss' => 'dismissDeletionOutcome'])
        @elseif ($deletionOutcome === 'blocked')
            <p>Este setor possui mesas, histórico ou painéis vinculados.</p>
            <p class="mt-2">Os dados foram preservados. Desative o setor em vez de excluí-lo.</p>
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancelDeletion">Fechar</x-ui.button>
            </x-slot:actions>
        @elseif ($deletionOutcome === 'error')
            <p>Não foi possível concluir a operação.</p>
            <p class="mt-2">Os dados foram preservados. Tente novamente.</p>
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancelDeletion">Fechar</x-ui.button>
            </x-slot:actions>
        @elseif ($showPermanentConfirmation)
            <p>Para excluir definitivamente o setor</p>
            <p class="mt-2 font-semibold text-text">“{{ $deletionOutcomeName }}”</p>
            <p class="mt-3">digite:</p>
            <p class="mt-2 font-semibold tracking-wide text-text">EXCLUIR</p>
            <label class="mt-4 block text-left" for="confirmar-exclusao-setor">
                <span class="sr-only">Digite EXCLUIR</span>
                <input id="confirmar-exclusao-setor" type="text" wire:model.live="deletionConfirmation" autocomplete="off" class="min-h-12 w-full rounded-xl border border-border bg-surface px-3.5 text-sm text-text shadow-sm focus:border-accent focus:ring-2 focus:ring-accent/25">
            </label>
            @error('deletionConfirmation')
                <p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>
            @enderror
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="backToDeletionWarning" wire:loading.attr="disabled" wire:target="deleteSector">Voltar</x-ui.button>
                <x-ui.button variant="danger" class="w-full sm:w-auto" wire:click="deleteSector" wire:loading.attr="disabled" wire:target="deleteSector" :disabled="trim($deletionConfirmation) !== 'EXCLUIR'">
                    <span wire:loading.remove wire:target="deleteSector">Excluir permanentemente</span>
                    <span wire:loading wire:target="deleteSector">Excluindo setor...</span>
                </x-ui.button>
            </x-slot:actions>
        @else
            <p>Você está prestes a excluir permanentemente:</p>
            <p class="mt-2 font-semibold text-text">“{{ $deletionOutcomeName }}”</p>
            @if ($deletionOutcomeUnit !== '')
                <p class="mt-2">Unidade: <span class="font-medium text-text">{{ $deletionOutcomeUnit }}</span></p>
            @endif
            @if ($deletionImpact['desks'] > 0 || $deletionImpact['kiosks'] > 0 || $deletionImpact['tickets'] > 0 || $deletionImpact['calls'] > 0 || $deletionImpact['panels'] > 0)
                <ul class="mt-3 space-y-1 text-sm text-text">
                    @if ($deletionImpact['desks'] > 0)
                        <li>{{ $deletionImpact['desks'] }} {{ $deletionImpact['desks'] === 1 ? 'mesa/guichê' : 'mesas/guichês' }}</li>
                    @endif
                    @if ($deletionImpact['kiosks'] > 0)
                        <li>{{ $deletionImpact['kiosks'] }} {{ $deletionImpact['kiosks'] === 1 ? 'totem' : 'totens' }}</li>
                    @endif
                    @if ($deletionImpact['tickets'] > 0)
                        <li>{{ $deletionImpact['tickets'] }} {{ $deletionImpact['tickets'] === 1 ? 'atendimento histórico' : 'atendimentos históricos' }}</li>
                    @endif
                    @if ($deletionImpact['calls'] > 0)
                        <li>{{ $deletionImpact['calls'] }} {{ $deletionImpact['calls'] === 1 ? 'chamada' : 'chamadas' }}</li>
                    @endif
                    @if ($deletionImpact['panels'] > 0)
                        <li>{{ $deletionImpact['panels'] }} {{ $deletionImpact['panels'] === 1 ? 'painel' : 'painéis' }}</li>
                    @endif
                </ul>
            @endif
            <p class="mt-3">Esta operação pode afetar dados vinculados e não poderá ser desfeita.</p>
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancelDeletion">Não, cancelar</x-ui.button>
                <x-ui.button variant="danger" class="w-full sm:w-auto" wire:click="requestPermanentDeletion">Excluir mesmo assim</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.modal>
</div>
