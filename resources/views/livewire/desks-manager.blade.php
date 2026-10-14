<div>
    @if ($statusMessage !== '')
        <x-ui.toast
            :title="$statusMessage"
            :message="$toastDetail !== '' ? $toastDetail : null"
            :tone="$toastTone"
            dismiss-method="dismissStatus"
            wire:key="desk-toast-{{ md5($statusMessage.$toastDetail) }}"
        />
    @endif

    <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
        <div class="flex items-start gap-3">
            <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-accent" aria-hidden="true">
                <x-admin.icon name="desk" class="size-5" />
            </span>
            <div>
                <h2 class="text-base font-semibold text-text">Mesas / Guichês</h2>
                <p class="mt-1 text-sm text-text-muted">Gerencie os pontos de atendimento vinculados às unidades e setores.</p>
            </div>
        </div>

        <div class="mt-5 flex flex-wrap gap-2" aria-label="Resumo das mesas">
            <span class="inline-flex items-center gap-2 rounded-xl border border-[#d5e2f3] bg-[#eef3fb] px-3 py-2 text-sm text-text">
                <span class="font-semibold tabular-nums">{{ $this->deskStats['total'] }} {{ $this->deskStats['total'] === 1 ? 'mesa' : 'mesas' }}</span>
            </span>
            <span class="inline-flex items-center gap-2 rounded-xl border border-[#cfe8d8] bg-[#e9f7ee] px-3 py-2 text-sm text-text">
                <span class="size-1.5 rounded-full bg-success" aria-hidden="true"></span>
                <span class="font-semibold tabular-nums">{{ $this->deskStats['active'] }} {{ $this->deskStats['active'] === 1 ? 'ativa' : 'ativas' }}</span>
            </span>
            <span class="inline-flex items-center gap-2 rounded-xl border border-[#f3e2c2] bg-[#fff6e8] px-3 py-2 text-sm text-text">
                <span class="size-1.5 rounded-full bg-warning" aria-hidden="true"></span>
                <span class="font-semibold tabular-nums">{{ $this->deskStats['inactive'] }} {{ $this->deskStats['inactive'] === 1 ? 'inativa' : 'inativas' }}</span>
            </span>
            <span class="inline-flex items-center gap-2 rounded-xl border border-[#ddd6fe] bg-[#f5f3ff] px-3 py-2 text-sm text-text">
                <span class="size-1.5 rounded-full bg-accent" aria-hidden="true"></span>
                <span class="font-semibold tabular-nums">{{ $this->deskStats['serving'] }} em atendimento</span>
            </span>
        </div>

        <div class="mt-5 flex flex-col gap-2 lg:flex-row lg:items-center">
            <div class="relative min-w-0 flex-1">
                <label for="desk-search" class="sr-only">Buscar por nome ou código</label>
                <span class="pointer-events-none absolute inset-y-0 left-0 flex w-11 items-center justify-center text-text-muted" aria-hidden="true">
                    <x-admin.icon name="search" class="size-4" />
                </span>
                <input
                    id="desk-search"
                    type="search"
                    wire:model.live.debounce.300ms="search"
                    placeholder="Buscar por nome ou código..."
                    class="min-h-11 w-full rounded-xl border border-border bg-surface py-2 pl-11 pr-3 text-sm text-text shadow-sm placeholder:text-text-muted focus:border-accent focus:ring-2 focus:ring-accent/25"
                >
            </div>
            <div class="grid gap-2 sm:grid-cols-3 lg:flex">
                <div>
                    <label for="desk-unit-filter" class="sr-only">Filtrar por unidade</label>
                    <select id="desk-unit-filter" wire:model.live="unitFilter" class="min-h-11 w-full cursor-pointer rounded-xl border border-border bg-surface px-3 text-sm text-text shadow-sm lg:w-52">
                        <option value="">Todas as unidades</option>
                        @foreach ($this->availableUnits as $unit)
                            <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="desk-sector-filter" class="sr-only">Filtrar por setor</label>
                    <select id="desk-sector-filter" wire:model.live="sectorFilter" class="min-h-11 w-full cursor-pointer rounded-xl border border-border bg-surface px-3 text-sm text-text shadow-sm lg:w-52">
                        <option value="">Todos os setores</option>
                        @foreach ($this->filterSectors as $sector)
                            <option value="{{ $sector->id }}">{{ $unitFilter === '' && $sector->unit ? $sector->name.' · '.$sector->unit->name : $sector->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="desk-status-filter" class="sr-only">Filtrar por status</label>
                    <select id="desk-status-filter" wire:model.live="statusFilter" class="min-h-11 w-full cursor-pointer rounded-xl border border-border bg-surface px-3 text-sm text-text shadow-sm lg:w-44">
                        <option value="">Todos os status</option>
                        <option value="active">Ativas</option>
                        <option value="inactive">Inativas</option>
                    </select>
                </div>
            </div>
            <p wire:loading.flex wire:target="search,unitFilter,sectorFilter,statusFilter" class="hidden items-center text-xs text-text-muted">Atualizando lista...</p>
        </div>

        <div class="mt-5">
            @php
                $filtersActive = $search !== '' || $unitFilter !== '' || $sectorFilter !== '' || $statusFilter !== '';
            @endphp
            @if ($desks->isEmpty())
                <x-ui.empty-state
                    icon="desk"
                    :title="$filtersActive ? 'Nenhuma mesa encontrada' : 'Nenhuma mesa cadastrada'"
                    :description="$filtersActive ? 'Não encontramos mesas ou guichês com os filtros selecionados.' : 'Cadastre um ponto de atendimento para começar.'"
                >
                    @if ($filtersActive)
                        <x-ui.button variant="secondary" wire:click="clearFilters">Limpar filtros</x-ui.button>
                    @else
                        <x-ui.button wire:click="startCreate">
                            <x-admin.icon name="plus" class="size-4" />
                            Nova mesa
                        </x-ui.button>
                    @endif
                </x-ui.empty-state>
            @else
                <div class="hidden md:block">
                    <table class="w-full text-left text-sm">
                        <caption class="sr-only">Mesas e guichês da clínica</caption>
                        <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                            <tr>
                                <th scope="col" class="px-3 py-3 font-semibold">Mesa / Guichê</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Código</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Unidade</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Setor</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($desks as $desk)
                                <tr wire:key="desk-row-{{ $desk->id }}" class="border-b border-border/70 last:border-0">
                                    <td class="px-3 py-4">
                                        <p class="font-semibold text-text">{{ $desk->name }}</p>
                                    </td>
                                    <td class="px-3 py-4">
                                        <span class="inline-flex rounded-lg border border-border bg-background px-2 py-1 font-mono text-xs font-medium tracking-wide text-text">{{ $desk->code }}</span>
                                    </td>
                                    <td class="px-3 py-4 text-text">{{ $desk->unit?->name }}</td>
                                    <td class="px-3 py-4 text-text">{{ $desk->sector?->name }}</td>
                                    <td class="px-3 py-4">
                                        <div class="flex flex-wrap items-center gap-2">
                                            @if ($desk->active)
                                                <x-ui.badge tone="success"><span class="mr-1.5 size-1.5 rounded-full bg-success" aria-hidden="true"></span>Ativa</x-ui.badge>
                                            @else
                                                <x-ui.badge tone="warning"><span class="mr-1.5 size-1.5 rounded-full bg-warning" aria-hidden="true"></span>Inativa</x-ui.badge>
                                            @endif
                                            @if ($desk->serving)
                                                <x-ui.badge tone="accent"><span class="mr-1.5 size-1.5 rounded-full bg-accent" aria-hidden="true"></span>Em atendimento</x-ui.badge>
                                            @endif
                                        </div>
                                    </td>
                                    <td class="px-3 py-4">
                                        @include('livewire.partials.desk-actions', ['desk' => $desk, 'menuContext' => 'tabela'])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="space-y-3 md:hidden">
                    @foreach ($desks as $desk)
                        <article wire:key="desk-card-{{ $desk->id }}" class="rounded-xl border border-border bg-background p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate font-semibold text-text">{{ $desk->name }}</h3>
                                    <p class="truncate text-sm text-text-muted">{{ $desk->unit?->name }}</p>
                                </div>
                                @include('livewire.partials.desk-actions', ['desk' => $desk, 'menuContext' => 'cartao'])
                            </div>
                            <div class="mt-3 flex flex-wrap items-center gap-2">
                                <span class="inline-flex rounded-lg border border-border bg-surface px-2 py-1 font-mono text-xs font-medium tracking-wide text-text">{{ $desk->code }}</span>
                                <span class="text-sm text-text-muted">{{ $desk->sector?->name }}</span>
                                @if ($desk->active)
                                    <x-ui.badge tone="success"><span class="mr-1.5 size-1.5 rounded-full bg-success" aria-hidden="true"></span>Ativa</x-ui.badge>
                                @else
                                    <x-ui.badge tone="warning"><span class="mr-1.5 size-1.5 rounded-full bg-warning" aria-hidden="true"></span>Inativa</x-ui.badge>
                                @endif
                                @if ($desk->serving)
                                    <x-ui.badge tone="accent"><span class="mr-1.5 size-1.5 rounded-full bg-accent" aria-hidden="true"></span>Em atendimento</x-ui.badge>
                                @endif
                            </div>
                        </article>
                    @endforeach
                </div>
                <div class="mt-4">{{ $desks->links() }}</div>
            @endif
        </div>
    </section>

    <x-ui.modal
        id="mesa-form-titulo"
        icon="desk"
        max-width="3xl"
        :title="$editingDeskId ? 'Editar mesa / guichê' : 'Nova mesa / guichê'"
        :description="$editingDeskId ? 'Atualize os dados deste ponto de atendimento.' : 'Cadastre um novo ponto de atendimento.'"
        :open="$showForm && ! $showCreateConfirmation && $creationOutcome === '' && ! $showEditConfirmation && $editOutcome === ''"
        close-method="closeDeskDialog"
        loading-target="review,save"
    >
        <form
            wire:submit="{{ $editingDeskId ? 'save' : 'review' }}"
            class="space-y-5"
            x-data
            x-on:desk-form-invalid.window="$nextTick(() => $root.querySelector('[aria-invalid=true]')?.focus())"
        >
            <x-ui.input label="Nome" name="desk_name" id="desk_name" wire:model="name" required maxlength="255" autocomplete="off" placeholder="Guichê 05" aria-invalid="{{ $errors->has('name') ? 'true' : 'false' }}">
                <x-input-error :messages="$errors->get('name')" />
            </x-ui.input>

            <x-ui.input label="Código" name="desk_code" id="desk_code" wire:model="code" required maxlength="32" autocomplete="off" placeholder="G5" class="font-mono uppercase" aria-invalid="{{ $errors->has('code') ? 'true' : 'false' }}">
                <p class="mt-1 text-xs text-text-muted">Único nesta unidade. Letras, números, hífen ou sublinhado.</p>
                <x-input-error :messages="$errors->get('code')" />
            </x-ui.input>

            <div>
                <label for="desk_unit" class="mb-1.5 block text-sm font-medium text-text">Unidade</label>
                <select id="desk_unit" wire:model.live="unitId" required aria-invalid="{{ $errors->has('unitId') ? 'true' : 'false' }}" class="min-h-12 w-full cursor-pointer rounded-xl border border-border bg-surface px-3.5 text-sm text-text shadow-sm focus:border-accent focus:ring-2 focus:ring-accent/25">
                    <option value="">Selecione uma unidade</option>
                    @foreach ($this->availableUnits as $unit)
                        <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                    @endforeach
                </select>
                <x-input-error :messages="$errors->get('unitId')" />
            </div>

            <div>
                <label for="desk_sector" class="mb-1.5 block text-sm font-medium text-text">Setor</label>
                <select id="desk_sector" wire:model="sectorId" required @disabled($unitId === null) aria-invalid="{{ $errors->has('sectorId') ? 'true' : 'false' }}" class="min-h-12 w-full cursor-pointer rounded-xl border border-border bg-surface px-3.5 text-sm text-text shadow-sm focus:border-accent focus:ring-2 focus:ring-accent/25 disabled:cursor-not-allowed disabled:opacity-60">
                    <option value="">Selecione um setor...</option>
                    @foreach ($this->availableSectors as $sector)
                        <option value="{{ $sector->id }}">{{ $sector->name }}</option>
                    @endforeach
                </select>
                <p class="mt-1 text-xs text-text-muted">Somente setores da unidade selecionada.</p>
                <x-input-error :messages="$errors->get('sectorId')" />
            </div>

            <div>
                <p class="mb-1.5 text-sm font-medium text-text">Status da mesa</p>
                <label class="flex min-h-12 items-center justify-between gap-4 rounded-xl border border-border bg-surface px-3 shadow-sm">
                    <span class="text-sm text-text">Mesa ativa</span>
                    <span class="relative inline-flex shrink-0 items-center">
                        <input type="checkbox" wire:model="active" role="switch" class="peer sr-only" aria-label="Mesa ativa">
                        <span class="h-6 w-11 rounded-full bg-border transition peer-focus-visible:outline peer-focus-visible:outline-2 peer-focus-visible:outline-offset-2 peer-focus-visible:outline-accent peer-checked:bg-accent"></span>
                        <span class="pointer-events-none absolute left-0.5 size-5 rounded-full bg-white shadow-sm transition peer-checked:translate-x-5"></span>
                    </span>
                </label>
            </div>

            <button type="submit" class="sr-only">{{ $editingDeskId ? 'Salvar alterações' : 'Criar mesa' }}</button>
        </form>
        <x-slot:actions>
            <x-ui.button variant="secondary" type="button" class="w-full sm:w-auto" wire:click="cancel">Cancelar</x-ui.button>
            <x-ui.button type="button" class="w-full sm:w-auto" wire:click="{{ $editingDeskId ? 'save' : 'review' }}" wire:loading.attr="disabled" wire:target="review,save">
                <span wire:loading.remove wire:target="review,save">{{ $editingDeskId ? 'Salvar alterações' : 'Criar mesa' }}</span>
                <span wire:loading wire:target="review,save">Validando...</span>
            </x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal
        id="mesa-confirmar-titulo"
        variant="confirm"
        max-width="lg"
        :icon="$creationOutcome === 'success' ? null : 'desk'"
        :title="$creationOutcome === 'success' ? 'Mesa criada!' : 'Criar nova mesa?'"
        :description="$creationOutcome === 'success' ? '“'.$creationOutcomeName.'” foi cadastrado com sucesso em “'.$creationOutcomeUnit.'”.' : 'Confira os dados antes de continuar.'"
        :open="($showCreateConfirmation && $creationOutcome === '') || $creationOutcome === 'success'"
        close-method="closeDeskDialog"
        loading-target="confirmCreate"
    >
        <x-slot:processing>
            @include('livewire.partials.sector-status-processing', ['icon' => 'desk', 'title' => 'Criando mesa...', 'detail' => 'Estamos configurando o novo ponto de atendimento.'])
        </x-slot:processing>
        @if ($creationOutcome === 'success')
            @include('livewire.partials.user-status-success', ['dismiss' => 'dismissCreationOutcome'])
            @if ($creationOutcomeSector !== '')
                <p class="mt-4 text-sm text-text-muted">Setor: <span class="font-medium text-text">{{ $creationOutcomeSector }}</span></p>
            @endif
        @else
            <dl class="mx-auto max-w-sm space-y-3 text-left">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Mesa / Guichê</dt>
                    <dd class="text-sm font-medium text-text">{{ $name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Código</dt>
                    <dd class="font-mono text-sm font-medium text-text">{{ $code }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Unidade</dt>
                    <dd class="text-sm font-medium text-text">{{ $creationOutcomeUnit !== '' ? $creationOutcomeUnit : $this->selectedUnitName() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Setor</dt>
                    <dd class="text-sm font-medium text-text">{{ $creationOutcomeSector !== '' ? $creationOutcomeSector : $this->selectedSectorName() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Status</dt>
                    <dd class="text-sm font-medium text-text">{{ $active ? 'Ativa' : 'Inativa' }}</dd>
                </div>
            </dl>
            <p class="mt-4 font-medium text-text">Deseja criar este ponto de atendimento?</p>
        @endif
        @if ($creationOutcome !== 'success')
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="backToDeskForm" wire:loading.attr="disabled" wire:target="confirmCreate">Não, voltar</x-ui.button>
                <x-ui.button class="w-full sm:w-auto" wire:click="confirmCreate" wire:loading.attr="disabled" wire:target="confirmCreate">
                    <span wire:loading.remove wire:target="confirmCreate">Sim, criar mesa</span>
                    <span wire:loading wire:target="confirmCreate">Criando mesa...</span>
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.modal>

    <x-ui.modal
        id="mesa-editar-confirmar-titulo"
        variant="confirm"
        max-width="lg"
        :icon="$editOutcome === 'success' ? null : 'pencil'"
        :title="$editOutcome === 'success' ? 'Alterações salvas!' : 'Salvar alterações?'"
        :description="$editOutcome === 'success' ? 'Os dados de “'.$editOutcomeName.'” foram atualizados.' : 'Confira os dados antes de continuar.'"
        :open="($showEditConfirmation && $editOutcome === '') || $editOutcome === 'success'"
        close-method="closeDeskDialog"
        loading-target="confirmUpdate"
    >
        <x-slot:processing>
            @include('livewire.partials.sector-status-processing', ['icon' => 'desk', 'title' => 'Salvando alterações...', 'detail' => 'Estamos atualizando este ponto de atendimento.'])
        </x-slot:processing>
        @if ($editOutcome === 'success')
            @include('livewire.partials.user-status-success', ['dismiss' => 'dismissEditOutcome'])
        @else
            <dl class="mx-auto max-w-sm space-y-3 text-left">
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Mesa</dt>
                    <dd class="text-sm font-medium text-text">{{ $name }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Código</dt>
                    <dd class="font-mono text-sm font-medium text-text">{{ $code }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Unidade</dt>
                    <dd class="text-sm font-medium text-text">{{ $this->selectedUnitName() }}</dd>
                </div>
                <div>
                    <dt class="text-xs font-medium uppercase tracking-wide text-text-muted">Setor</dt>
                    <dd class="text-sm font-medium text-text">{{ $this->selectedSectorName() }}</dd>
                </div>
            </dl>
        @endif
        @if ($editOutcome !== 'success')
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="backToDeskForm" wire:loading.attr="disabled" wire:target="confirmUpdate">Não, voltar</x-ui.button>
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
        id="desativar-mesa-titulo"
        variant="confirm"
        :icon="$deactivationOutcome === 'success' ? null : ($deactivationOutcome === 'blocked' ? 'ban' : 'ban')"
        icon-tone="warning"
        :title="match ($deactivationOutcome) {
            'success' => 'Mesa desativada!',
            'blocked' => 'Não é possível desativar',
            default => 'Desativar mesa?',
        }"
        :description="$deactivationOutcome === 'success' ? 'A mesa “'.$deactivationOutcomeName.'” não poderá ser utilizada para novos atendimentos.' : null"
        :open="$deskPendingDeactivationId !== null || $deactivationOutcome === 'success'"
        close-method="closeDeactivationDialog"
        loading-target="deactivate"
    >
        <x-slot:processing>
            @include('livewire.partials.sector-status-processing', ['icon' => 'desk', 'title' => 'Desativando mesa...', 'detail' => 'Aguarde enquanto o status está sendo atualizado.'])
        </x-slot:processing>
        @if ($deactivationOutcome === 'success')
            @include('livewire.partials.user-status-success', ['dismiss' => 'dismissDeactivationOutcome'])
        @elseif ($deactivationOutcome === 'blocked')
            <p class="font-semibold text-text">“{{ $deactivationOutcomeName }}”</p>
            <p class="mt-3">{{ $deactivationBlockMessage }}</p>
            <x-slot:actions>
                <x-ui.button class="w-full sm:w-auto" wire:click="cancelDeactivation">Entendi</x-ui.button>
            </x-slot:actions>
        @else
            <p>Você está prestes a desativar:</p>
            <p class="mt-2 font-semibold text-text">“{{ $deactivationOutcomeName }}”</p>
            @if ($deactivationOutcomeUnit !== '')
                <p class="mt-2">Unidade: <span class="font-medium text-text">{{ $deactivationOutcomeUnit }}</span></p>
            @endif
            @if ($deactivationOutcomeSector !== '')
                <p class="mt-1">Setor: <span class="font-medium text-text">{{ $deactivationOutcomeSector }}</span></p>
            @endif
            @if ($deactivationImpact['assignments'] > 0 || $deactivationImpact['open'] > 0 || $deactivationImpact['waiting'] > 0)
                <ul class="mt-3 space-y-1 text-sm text-text">
                    @if ($deactivationImpact['assignments'] > 0)
                        <li>{{ $deactivationImpact['assignments'] }} {{ $deactivationImpact['assignments'] === 1 ? 'atendente vinculado' : 'atendentes vinculados' }}</li>
                    @endif
                    @if ($deactivationImpact['open'] > 0)
                        <li>{{ $deactivationImpact['open'] }} {{ $deactivationImpact['open'] === 1 ? 'atendimento em andamento' : 'atendimentos em andamento' }}</li>
                    @endif
                    @if ($deactivationImpact['waiting'] > 0)
                        <li>{{ $deactivationImpact['waiting'] }} {{ $deactivationImpact['waiting'] === 1 ? 'senha aguardando direcionada' : 'senhas aguardando direcionadas' }}</li>
                    @endif
                </ul>
            @endif
            <p class="mt-3">Enquanto estiver inativa, esta mesa não poderá ser utilizada para novos atendimentos.</p>
        @endif
        @if ($deactivationOutcome === '')
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancelDeactivation" wire:loading.attr="disabled" wire:target="deactivate">Não, cancelar</x-ui.button>
                <x-ui.button variant="danger" class="w-full sm:w-auto" wire:click="deactivate" wire:loading.attr="disabled" wire:target="deactivate">
                    <span wire:loading.remove wire:target="deactivate">Sim, desativar</span>
                    <span wire:loading wire:target="deactivate">Desativando mesa...</span>
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.modal>

    <x-ui.modal
        id="ativar-mesa-titulo"
        variant="confirm"
        :icon="$activationOutcome === 'success' ? null : 'check'"
        icon-tone="success"
        :title="$activationOutcome === 'success' ? 'Mesa ativada!' : 'Ativar mesa?'"
        :description="$activationOutcome === 'success' ? 'A mesa “'.$activationOutcomeName.'” voltou a ficar disponível.' : null"
        :open="$deskPendingActivationId !== null || $activationOutcome === 'success'"
        close-method="closeActivationDialog"
        loading-target="activate"
    >
        <x-slot:processing>
            @include('livewire.partials.sector-status-processing', ['icon' => 'desk', 'title' => 'Ativando mesa...', 'detail' => 'Aguarde enquanto o status está sendo atualizado.'])
        </x-slot:processing>
        @if ($activationOutcome === 'success')
            @include('livewire.partials.user-status-success', ['dismiss' => 'dismissActivationOutcome'])
        @else
            <p>Deseja reativar:</p>
            <p class="mt-2 font-semibold text-text">“{{ $activationOutcomeName }}”?</p>
            @if ($activationOutcomeUnit !== '')
                <p class="mt-2">Unidade: <span class="font-medium text-text">{{ $activationOutcomeUnit }}</span></p>
            @endif
            @if ($activationOutcomeSector !== '')
                <p class="mt-1">Setor: <span class="font-medium text-text">{{ $activationOutcomeSector }}</span></p>
            @endif
        @endif
        @if ($activationOutcome !== 'success')
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancelActivation" wire:loading.attr="disabled" wire:target="activate">Não, cancelar</x-ui.button>
                <x-ui.button class="w-full sm:w-auto" wire:click="activate({{ (int) $deskPendingActivationId }})" wire:loading.attr="disabled" wire:target="activate">
                    <span wire:loading.remove wire:target="activate">Sim, ativar</span>
                    <span wire:loading wire:target="activate">Ativando mesa...</span>
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.modal>

    @php
        $deletionImpact = $this->deletionImpact();
    @endphp

    <x-ui.modal
        id="excluir-mesa-titulo"
        variant="confirm"
        :icon="$deletionOutcome === 'success' ? null : 'trash'"
        icon-tone="danger"
        :title="match ($deletionOutcome) {
            'success' => 'Mesa excluída!',
            'blocked' => 'Não é possível excluir',
            'error' => 'Não foi possível concluir a operação.',
            default => $showPermanentConfirmation ? 'Confirmação permanente' : 'Excluir mesa?',
        }"
        :description="$deletionOutcome === 'success' ? '“'.$deletionOutcomeName.'” foi removido com sucesso.' : null"
        :open="$deskPendingDeletionId !== null || $deletionOutcome !== ''"
        close-method="closeDeletionDialog"
        loading-target="deleteDesk"
    >
        <x-slot:processing>
            @include('livewire.partials.sector-status-processing', ['icon' => 'desk', 'title' => 'Excluindo mesa...', 'detail' => 'Estamos tratando os dados vinculados com segurança.'])
        </x-slot:processing>
        @if ($deletionOutcome === 'success')
            @include('livewire.partials.user-status-success', ['dismiss' => 'dismissDeletionOutcome'])
        @elseif ($deletionOutcome === 'blocked')
            <p class="font-semibold text-text">“{{ $deletionOutcomeName }}”</p>
            <p class="mt-3">{{ $deletionBlockMessage }}</p>
            <x-slot:actions>
                <x-ui.button class="w-full sm:w-auto" wire:click="cancelDeletion">Entendi</x-ui.button>
            </x-slot:actions>
        @elseif ($deletionOutcome === 'error')
            <p>Não foi possível concluir a operação.</p>
            <p class="mt-2">Os dados foram preservados. Tente novamente.</p>
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancelDeletion">Fechar</x-ui.button>
            </x-slot:actions>
        @elseif ($showPermanentConfirmation)
            <p>Para excluir definitivamente:</p>
            <p class="mt-2 font-semibold text-text">“{{ $deletionOutcomeName }}”</p>
            <p class="mt-3">digite:</p>
            <p class="mt-2 font-semibold tracking-wide text-text">EXCLUIR</p>
            <label class="mt-4 block text-left" for="confirmar-exclusao-mesa">
                <span class="sr-only">Digite EXCLUIR</span>
                <input id="confirmar-exclusao-mesa" type="text" wire:model.live="deletionConfirmation" autocomplete="off" class="min-h-12 w-full rounded-xl border border-border bg-surface px-3.5 text-sm text-text shadow-sm focus:border-accent focus:ring-2 focus:ring-accent/25">
            </label>
            @error('deletionConfirmation')
                <p class="mt-2 text-sm text-danger" role="alert">{{ $message }}</p>
            @enderror
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="backToDeletionWarning" wire:loading.attr="disabled" wire:target="deleteDesk">Voltar</x-ui.button>
                <x-ui.button variant="danger" class="w-full sm:w-auto" wire:click="deleteDesk" wire:loading.attr="disabled" wire:target="deleteDesk" :disabled="trim($deletionConfirmation) !== 'EXCLUIR'">
                    <span wire:loading.remove wire:target="deleteDesk">Excluir permanentemente</span>
                    <span wire:loading wire:target="deleteDesk">Excluindo mesa...</span>
                </x-ui.button>
            </x-slot:actions>
        @else
            <p>Você está prestes a excluir permanentemente:</p>
            <p class="mt-2 font-semibold text-text">“{{ $deletionOutcomeName }}”</p>
            <dl class="mx-auto mt-3 max-w-sm space-y-2 text-left text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-text-muted">Código</dt>
                    <dd class="font-mono font-medium text-text">{{ $deletionOutcomeCode }}</dd>
                </div>
                @if ($deletionOutcomeUnit !== '')
                    <div class="flex justify-between gap-4">
                        <dt class="text-text-muted">Unidade</dt>
                        <dd class="font-medium text-text">{{ $deletionOutcomeUnit }}</dd>
                    </div>
                @endif
                @if ($deletionOutcomeSector !== '')
                    <div class="flex justify-between gap-4">
                        <dt class="text-text-muted">Setor</dt>
                        <dd class="font-medium text-text">{{ $deletionOutcomeSector }}</dd>
                    </div>
                @endif
            </dl>
            @if ($deletionImpact['assignments'] > 0 || $deletionImpact['tickets'] > 0 || $deletionImpact['calls'] > 0 || $deletionImpact['transfers'] > 0)
                <ul class="mt-3 space-y-1 text-sm text-text">
                    @if ($deletionImpact['assignments'] > 0)
                        <li>{{ $deletionImpact['assignments'] }} {{ $deletionImpact['assignments'] === 1 ? 'atendente vinculado' : 'atendentes vinculados' }}</li>
                    @endif
                    @if ($deletionImpact['tickets'] > 0)
                        <li>{{ $deletionImpact['tickets'] }} {{ $deletionImpact['tickets'] === 1 ? 'atendimento histórico' : 'atendimentos históricos' }}</li>
                    @endif
                    @if ($deletionImpact['calls'] > 0)
                        <li>{{ $deletionImpact['calls'] }} {{ $deletionImpact['calls'] === 1 ? 'chamada registrada' : 'chamadas registradas' }}</li>
                    @endif
                    @if ($deletionImpact['transfers'] > 0)
                        <li>{{ $deletionImpact['transfers'] }} {{ $deletionImpact['transfers'] === 1 ? 'transferência' : 'transferências' }}</li>
                    @endif
                </ul>
            @endif
            <p class="mt-3">Esta ação poderá afetar dados vinculados e não poderá ser desfeita.</p>
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancelDeletion">Não, cancelar</x-ui.button>
                <x-ui.button variant="danger" class="w-full sm:w-auto" wire:click="requestPermanentDeletion">Excluir mesmo assim</x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.modal>
</div>
