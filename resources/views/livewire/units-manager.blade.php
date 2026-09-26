<div>
    @if ($statusMessage !== '')
        <x-ui.toast
            :title="$statusMessage"
            :message="$toastDetail"
            :tone="$toastTone"
            dismiss-method="dismissStatus"
            wire:key="unit-toast-{{ md5($statusMessage.$toastDetail) }}"
        />
    @endif

    <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
        <div class="flex flex-col gap-4 sm:flex-row sm:items-start sm:justify-between">
            <div class="flex items-start gap-3">
                <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-blue-50 text-accent" aria-hidden="true">
                    <x-admin.icon name="layers" class="size-5" />
                </span>
                <div>
                    <h2 class="text-base font-semibold text-text">Unidades</h2>
                    <p class="mt-1 text-sm text-text-muted">Gerencie os locais de atendimento vinculados à clínica.</p>
                </div>
            </div>
            <x-ui.button wire:click="startCreate" data-dialog-return class="shadow-sm hover:shadow-md active:translate-y-px">
                <x-admin.icon name="plus" class="size-4" />
                Adicionar unidade
            </x-ui.button>
        </div>

        <div class="mt-5 flex flex-col gap-3 lg:flex-row lg:items-center lg:justify-between">
            <div class="flex flex-wrap gap-2" aria-label="Resumo das unidades">
                <span class="inline-flex items-center gap-2 rounded-xl border border-[#d5e2f3] bg-[#eef3fb] px-3 py-2 text-sm text-text">
                    <span class="font-semibold tabular-nums">{{ $this->unitStats['total'] }} {{ $this->unitStats['total'] === 1 ? 'unidade' : 'unidades' }}</span>
                </span>
                <span class="inline-flex items-center gap-2 rounded-xl border border-[#cfe8d8] bg-[#e9f7ee] px-3 py-2 text-sm text-text">
                    <span class="size-1.5 rounded-full bg-success" aria-hidden="true"></span>
                    <span class="font-semibold tabular-nums">{{ $this->unitStats['active'] }} {{ $this->unitStats['active'] === 1 ? 'ativa' : 'ativas' }}</span>
                </span>
                <span class="inline-flex items-center gap-2 rounded-xl border border-[#f3e2c2] bg-[#fff6e8] px-3 py-2 text-sm text-text">
                    <span class="size-1.5 rounded-full bg-warning" aria-hidden="true"></span>
                    <span class="font-semibold tabular-nums">{{ $this->unitStats['inactive'] }} {{ $this->unitStats['inactive'] === 1 ? 'inativa' : 'inativas' }}</span>
                </span>
            </div>

            <div class="flex flex-col gap-2 sm:flex-row">
                <div class="relative min-w-0 sm:w-56">
                    <label for="unit-search" class="sr-only">Buscar unidade</label>
                    <input
                        id="unit-search"
                        type="search"
                        wire:model.live.debounce.300ms="search"
                        placeholder="Buscar unidade..."
                        class="min-h-11 w-full rounded-xl border border-border bg-surface px-3.5 text-sm text-text shadow-sm placeholder:text-text-muted"
                    >
                </div>
                <div>
                    <label for="unit-status" class="sr-only">Filtrar por status</label>
                    <select id="unit-status" wire:model.live="statusFilter" class="min-h-11 w-full cursor-pointer rounded-xl border border-border bg-surface px-3 text-sm text-text shadow-sm sm:w-auto">
                        <option value="all">Todas as unidades</option>
                        <option value="active">Ativas</option>
                        <option value="inactive">Inativas</option>
                    </select>
                </div>
            </div>
        </div>

        <div class="mt-5">
            @if ($this->clinicUnits->isEmpty())
                <x-ui.empty-state icon="building" title="Nenhuma unidade cadastrada" description="Cadastre a primeira unidade de atendimento desta clínica.">
                    <x-ui.button wire:click="startCreate">
                        <x-admin.icon name="plus" class="size-4" />
                        Adicionar unidade
                    </x-ui.button>
                </x-ui.empty-state>
            @elseif ($this->units->isEmpty())
                <x-ui.empty-state icon="layers" title="Nenhuma unidade encontrada" description="Ajuste a busca ou o filtro de status para ver outras unidades." />
            @else
                <div class="hidden md:block">
                    <table class="w-full text-left text-sm">
                        <caption class="sr-only">Unidades da clínica</caption>
                        <thead class="border-b border-border text-xs uppercase tracking-wide text-text-muted">
                            <tr>
                                <th scope="col" class="px-3 py-3 font-semibold">Unidade</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Identificador</th>
                                <th scope="col" class="px-3 py-3 font-semibold">Status</th>
                                <th scope="col" class="px-3 py-3 text-right font-semibold">Ações</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($this->units as $unit)
                                <tr wire:key="unit-row-{{ $unit->id }}" wire:transition.opacity.duration.250ms class="border-b border-border/70 last:border-0">
                                    <td class="px-3 py-4 font-semibold text-text">{{ $unit->name }}</td>
                                    <td class="px-3 py-4">
                                        <span class="inline-flex rounded-md bg-background px-2 py-1 font-mono text-xs text-text-muted">{{ $unit->slug }}</span>
                                    </td>
                                    <td class="px-3 py-4">
                                        @if ($unit->active)
                                            <x-ui.badge tone="success"><span class="mr-1.5 size-1.5 rounded-full bg-success" aria-hidden="true"></span>Ativa</x-ui.badge>
                                        @else
                                            <x-ui.badge tone="warning"><span class="mr-1.5 size-1.5 rounded-full bg-warning" aria-hidden="true"></span>Inativa</x-ui.badge>
                                        @endif
                                    </td>
                                    <td class="px-3 py-4">
                                        @include('livewire.partials.unit-actions', ['unit' => $unit, 'menuContext' => 'tabela'])
                                    </td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>

                <div class="space-y-3 md:hidden">
                    @foreach ($this->units as $unit)
                        <article wire:key="unit-card-{{ $unit->id }}" wire:transition.opacity.duration.250ms class="rounded-xl border border-border bg-background p-4">
                            <div class="flex items-start justify-between gap-3">
                                <div class="min-w-0">
                                    <h3 class="truncate font-semibold text-text">{{ $unit->name }}</h3>
                                    <p class="mt-1 font-mono text-xs text-text-muted">{{ $unit->slug }}</p>
                                </div>
                                @if ($unit->active)
                                    <x-ui.badge tone="success"><span class="mr-1.5 size-1.5 rounded-full bg-success" aria-hidden="true"></span>Ativa</x-ui.badge>
                                @else
                                    <x-ui.badge tone="warning"><span class="mr-1.5 size-1.5 rounded-full bg-warning" aria-hidden="true"></span>Inativa</x-ui.badge>
                                @endif
                            </div>
                            <div class="mt-4">
                                @include('livewire.partials.unit-actions', ['unit' => $unit, 'menuContext' => 'cartao'])
                            </div>
                        </article>
                    @endforeach
                </div>
            @endif
        </div>
    </section>

    <x-ui.modal
        id="unidade-form-titulo"
        icon="plus"
        :title="$editingUnitId ? 'Editar unidade' : 'Nova unidade'"
        :description="$editingUnitId ? 'Atualize as informações desta unidade.' : 'Cadastre uma nova unidade de atendimento para esta clínica.'"
        :open="$showForm && ! $showCreateConfirmation"
        close-method="cancel"
    >
        <form id="unidade-form" wire:submit="save" class="space-y-4" novalidate>
            @if ($errors->has('form'))
                <x-ui.alert type="danger">{{ $errors->first('form') }}</x-ui.alert>
            @endif

            <x-ui.input label="Nome da unidade" name="unit_name" id="unit_name" wire:model="name" required maxlength="255" placeholder="Ex: Recepção" autocomplete="off">
                <x-input-error :messages="$errors->get('name')" />
            </x-ui.input>

            <x-ui.input label="Identificador" name="unit_slug" id="unit_slug" wire:model="slug" maxlength="255" placeholder="Ex: recepcao" autocomplete="off">
                <p class="mt-1.5 text-xs text-text-muted">
                    @if ($editingUnitId)
                        O identificador só muda se você alterá-lo. Mudar o nome não atualiza o identificador.
                    @else
                        Se ficar vazio, será gerado automaticamente a partir do nome.
                    @endif
                </p>
                <x-input-error :messages="$errors->get('slug')" />
            </x-ui.input>
        </form>
        <x-slot:actions>
            <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancel" wire:loading.attr="disabled" wire:target="save">Cancelar</x-ui.button>
            <x-ui.button type="submit" form="unidade-form" class="w-full sm:w-auto" wire:loading.attr="disabled" wire:target="save">
                @if ($editingUnitId)
                    <span wire:loading.remove wire:target="save">Salvar alterações</span>
                    <span wire:loading wire:target="save">Salvando...</span>
                @else
                    Criar unidade
                @endif
            </x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal
        id="criar-unidade-titulo"
        variant="confirm"
        icon="building"
        title="Criar nova unidade?"
        :description="'Você está prestes a adicionar “'.$name.'” à '.$clinicName.'.'"
        :open="$showCreateConfirmation"
        close-method="cancelCreateConfirmation"
    >
        <p>Identificador: <span class="font-mono text-text">{{ $slug }}</span></p>
        <p class="mt-2">Deseja continuar?</p>
        @if ($errors->has('form'))
            <div class="mt-3 text-left">
                <x-ui.alert type="danger">{{ $errors->first('form') }}</x-ui.alert>
            </div>
        @endif
        <x-slot:actions>
            <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancelCreateConfirmation" wire:loading.attr="disabled" wire:target="confirmCreate">Não, voltar</x-ui.button>
            <x-ui.button class="w-full sm:w-auto" wire:click="confirmCreate" wire:loading.attr="disabled" wire:target="confirmCreate">
                <span wire:loading.remove wire:target="confirmCreate">Sim, criar unidade</span>
                <span wire:loading wire:target="confirmCreate">Criando...</span>
            </x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal
        id="desativar-unidade-titulo"
        variant="confirm"
        icon="alert"
        icon-tone="warning"
        title="Desativar unidade?"
        :description="$pendingDeactivation ? 'A unidade “'.$pendingDeactivation->name.'” deixará de ficar disponível para novas operações.' : ''"
        :open="$pendingDeactivation !== null"
        close-method="cancelDeactivation"
    >
        <p>Esta ação não exclui o histórico de atendimentos, mesas, usuários e registros relacionados.</p>
        @if ($errors->has('form'))
            <div class="mt-3">
                <x-ui.alert type="danger">{{ $errors->first('form') }}</x-ui.alert>
            </div>
        @endif
        <x-slot:actions>
            <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancelDeactivation" wire:loading.attr="disabled" wire:target="deactivate">Não, cancelar</x-ui.button>
            <x-ui.button variant="danger" class="w-full sm:w-auto" wire:click="deactivate" wire:loading.attr="disabled" wire:target="deactivate">
                <span wire:loading.remove wire:target="deactivate">Sim, desativar</span>
                <span wire:loading wire:target="deactivate">Desativando...</span>
            </x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal
        id="ativar-unidade-titulo"
        variant="confirm"
        icon="check"
        icon-tone="success"
        title="Ativar unidade?"
        :description="$pendingActivation ? 'Deseja tornar “'.$pendingActivation->name.'” disponível novamente?' : ''"
        :open="$pendingActivation !== null"
        close-method="cancelActivation"
    >
        @if ($errors->has('form'))
            <x-ui.alert type="danger">{{ $errors->first('form') }}</x-ui.alert>
        @endif
        <x-slot:actions>
            <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancelActivation" wire:loading.attr="disabled" wire:target="activateConfirmed">Não, cancelar</x-ui.button>
            <x-ui.button variant="success" class="w-full sm:w-auto" wire:click="activateConfirmed" wire:loading.attr="disabled" wire:target="activateConfirmed">
                <span wire:loading.remove wire:target="activateConfirmed">Sim, ativar</span>
                <span wire:loading wire:target="activateConfirmed">Ativando...</span>
            </x-ui.button>
        </x-slot:actions>
    </x-ui.modal>

    <x-ui.modal
        id="excluir-unidade-titulo"
        variant="confirm"
        :icon="$deletionOutcome === 'success' ? null : ($deletionOutcome === 'error' ? 'alert' : 'trash')"
        :icon-tone="$deletionOutcome === 'error' ? 'danger' : 'danger'"
        :title="$deletionOutcome === 'success' ? 'Unidade excluída!' : ($deletionOutcome === 'error' ? 'Não foi possível concluir a exclusão.' : 'Excluir unidade?')"
        :description="$deletionOutcome === 'success' ? '“'.$deletionOutcomeName.'” foi excluída permanentemente.' : ($deletionOutcome === 'error' ? 'Nenhuma alteração parcial foi mantida.' : ($pendingDeletion ? 'Você está prestes a excluir permanentemente “'.$pendingDeletion->name.'”.' : ''))"
        :open="($pendingDeletion !== null && ! $showPermanentConfirmation && $deletionOutcome === '') || ($deletionOutcome !== '' && ! $showPermanentConfirmation)"
        close-method="closeDeletionDialog"
        loading-target="deleteUnit"
    >
        <x-slot:processing>
            @include('livewire.partials.unit-deletion-processing')
        </x-slot:processing>
        @if ($deletionOutcome === 'success')
            @include('livewire.partials.unit-deletion-success')
        @elseif ($deletionOutcome === 'error')
            @include('livewire.partials.unit-deletion-error')
        @elseif ($deletionImpact && $deletionImpact['hasDependencies'])
            <p class="font-semibold text-danger">Atenção: esta unidade possui registros vinculados.</p>
            <ul class="mx-auto mt-3 max-w-xs space-y-1 text-left text-sm font-medium text-text">
                @foreach ($deletionImpact['lines'] as $line)
                    <li>{{ $line }}</li>
                @endforeach
            </ul>
            @if ($deletionImpact['sectors'] > 0)
                <p class="mt-3">Os setores pertencentes exclusivamente a esta unidade também serão excluídos.</p>
            @endif
            <p class="mt-3 font-medium text-text">A exclusão permanente poderá remover dados dependentes desta unidade. Esta ação não poderá ser desfeita.</p>
            @if ($deletionImpact['historyBlocked'])
                <p class="mt-3">Atendimentos, chamadas e transferências históricas permanecem na clínica, com o nome desta unidade. Os dados operacionais vinculados deixam de existir.</p>
            @endif
        @else
            <p class="font-medium text-text">Esta ação é permanente e não poderá ser desfeita.</p>
        @endif
        @if ($errors->has('form') || $errors->has('deletionConfirmation'))
            <div class="mt-3 text-left">
                <x-ui.alert type="danger">{{ $errors->first('form') ?: $errors->first('deletionConfirmation') }}</x-ui.alert>
            </div>
        @endif
        @if ($deletionOutcome === 'error')
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancelDeletion">Fechar</x-ui.button>
                <x-ui.button variant="danger" class="w-full sm:w-auto" wire:click="deleteUnit" wire:loading.attr="disabled" wire:target="deleteUnit">Tentar novamente</x-ui.button>
            </x-slot:actions>
        @elseif ($deletionOutcome !== 'success')
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="cancelDeletion">Não, cancelar</x-ui.button>
                @if ($deletionImpact['hasDependencies'] ?? false)
                    <x-ui.button variant="danger" class="w-full sm:w-auto" wire:click="requestPermanentDeletion">
                        <x-admin.icon name="trash" class="size-4" />
                        Excluir mesmo assim
                    </x-ui.button>
                @else
                    <x-ui.button variant="danger" class="w-full sm:w-auto" wire:click="deleteUnit" wire:loading.attr="disabled" wire:target="deleteUnit">
                        <span wire:loading.remove wire:target="deleteUnit">Sim, excluir unidade</span>
                        <span wire:loading wire:target="deleteUnit">Excluindo...</span>
                    </x-ui.button>
                @endif
            </x-slot:actions>
        @endif
    </x-ui.modal>

    <x-ui.modal
        id="excluir-unidade-permanente-titulo"
        variant="confirm"
        :icon="$deletionOutcome === 'success' ? null : ($deletionOutcome === 'error' ? 'alert' : 'trash')"
        icon-tone="danger"
        :title="$deletionOutcome === 'success' ? 'Unidade excluída!' : ($deletionOutcome === 'error' ? 'Não foi possível concluir a exclusão.' : 'Confirmação permanente')"
        :description="$deletionOutcome === 'success' ? '“'.$deletionOutcomeName.'” foi excluída permanentemente.' : ($deletionOutcome === 'error' ? 'Nenhuma alteração parcial foi mantida.' : ($pendingDeletion ? 'Você está prestes a excluir definitivamente a unidade “'.$pendingDeletion->name.'”.' : ''))"
        :open="($pendingDeletion !== null && $showPermanentConfirmation && $deletionOutcome === '') || ($deletionOutcome !== '' && $showPermanentConfirmation)"
        close-method="closeDeletionDialog"
        loading-target="deleteUnit"
    >
        <x-slot:processing>
            @include('livewire.partials.unit-deletion-processing')
        </x-slot:processing>
        @if ($deletionOutcome === 'success')
            @include('livewire.partials.unit-deletion-success')
        @elseif ($deletionOutcome === 'error')
            @include('livewire.partials.unit-deletion-error')
        @else
        <p>Esta operação não poderá ser desfeita.</p>
        <div class="mt-4 text-left">
            <label for="confirmar-exclusao-unidade" class="block text-sm font-medium text-text">Digite EXCLUIR para confirmar</label>
            <input
                id="confirmar-exclusao-unidade"
                type="text"
                wire:model.live="deletionConfirmation"
                wire:loading.attr="disabled"
                wire:target="deleteUnit"
                autocomplete="off"
                spellcheck="false"
                class="mt-2 min-h-11 w-full rounded-xl border border-border bg-surface px-3 text-sm text-text shadow-sm"
            >
            @error('deletionConfirmation')
                <p class="mt-2 text-sm text-danger">{{ $message }}</p>
            @enderror
        </div>
        @if ($errors->has('form'))
            <div class="mt-3 text-left">
                <x-ui.alert type="danger">{{ $errors->first('form') }}</x-ui.alert>
            </div>
        @endif
        @endif
        @if ($deletionOutcome === 'error')
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="backToDeletionWarning" wire:loading.attr="disabled" wire:target="deleteUnit">Voltar</x-ui.button>
                <x-ui.button variant="danger" class="w-full sm:w-auto" wire:click="deleteUnit" wire:loading.attr="disabled" wire:target="deleteUnit">Tentar novamente</x-ui.button>
            </x-slot:actions>
        @elseif ($deletionOutcome !== 'success')
            <x-slot:actions>
                <x-ui.button variant="secondary" class="w-full sm:w-auto" wire:click="backToDeletionWarning" wire:loading.attr="disabled" wire:target="deleteUnit">Voltar</x-ui.button>
                <x-ui.button
                    variant="danger"
                    class="w-full sm:w-auto"
                    wire:click="deleteUnit"
                    wire:loading.attr="disabled"
                    wire:target="deleteUnit"
                    :disabled="trim($deletionConfirmation) !== 'EXCLUIR'"
                >
                    <span wire:loading.remove wire:target="deleteUnit" class="inline-flex items-center gap-2">
                        <x-admin.icon name="trash" class="size-4" />
                        Excluir permanentemente
                    </span>
                    <span wire:loading wire:target="deleteUnit">Excluindo unidade...</span>
                </x-ui.button>
            </x-slot:actions>
        @endif
    </x-ui.modal>
</div>
