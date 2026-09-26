@php
    $selectedLabel = $this->selectedUnitsLabel();
    $selectedCount = count(array_filter($unitIds, fn ($id): bool => $id !== '' && $id !== null));
@endphp

<div
    class="relative"
    x-data="{
        open: false,
        query: '',
        labelText: @js($selectedLabel !== '' ? $selectedLabel : 'Selecione as unidades...'),
        count: {{ $selectedCount }},
        refresh() {
            const checked = [...this.$root.querySelectorAll('input[type=checkbox]:checked')]
            this.count = checked.length
            const names = checked.map((box) => box.getAttribute('data-unit-name') || '')
            if (names.length === 0) {
                this.labelText = 'Selecione as unidades...'
            } else if (names.length <= 2) {
                this.labelText = names.join(', ')
            } else {
                this.labelText = names.slice(0, 2).join(', ') + ' +' + (names.length - 2)
            }
        },
        toggle() {
            this.open = ! this.open
            if (this.open) {
                this.$nextTick(() => this.$refs.search?.focus())
            }
        },
        close() {
            this.open = false
            this.query = ''
        },
        matches(name, slug) {
            const term = this.query.trim().toLowerCase()
            if (term === '') {
                return true
            }
            return name.toLowerCase().includes(term) || slug.toLowerCase().includes(term)
        },
    }"
    @keydown.escape.window="if (open) { $event.stopImmediatePropagation(); close() }"
    @click.outside="close()"
>
    <p id="unidades-autorizadas-rotulo" class="text-sm font-semibold text-text">Unidades autorizadas</p>
    <p class="mt-1 text-sm text-text-muted">Selecione em quais unidades este usuário poderá trabalhar.</p>
    @if ($role === \App\UserRole::ADMINISTRATOR->value)
        <p class="mt-2 text-sm text-text">O administrador opera todas as unidades desta clínica.</p>
    @endif

    @if ($this->availableUnits->isEmpty())
        <p class="mt-3 text-sm text-text-muted">Nenhuma unidade cadastrada nesta clínica.</p>
    @else
        <button
            type="button"
            class="mt-3 flex min-h-12 w-full cursor-pointer items-center justify-between gap-3 rounded-xl border border-border bg-surface px-3.5 text-left text-sm shadow-sm focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
            @click="toggle()"
            :aria-expanded="open.toString()"
            aria-haspopup="listbox"
            aria-controls="unidades-autorizadas-painel"
            aria-labelledby="unidades-autorizadas-rotulo"
        >
            <span class="truncate" :class="count === 0 ? 'text-text-muted' : 'text-text'" x-text="labelText"></span>
            <x-admin.icon name="chevron-down" class="size-4 shrink-0 text-text-muted" />
        </button>

        <div
            id="unidades-autorizadas-painel"
            x-cloak
            x-show="open"
            class="absolute z-20 mt-2 w-full overflow-hidden rounded-xl border border-border bg-surface shadow-lg"
            role="listbox"
            aria-label="Unidades autorizadas"
        >
            <div class="border-b border-border p-2">
                <label class="relative block">
                    <span class="pointer-events-none absolute inset-y-0 left-0 flex w-10 items-center justify-center text-text-muted">
                        <x-admin.icon name="search" class="size-4" />
                    </span>
                    <input
                        x-ref="search"
                        type="search"
                        x-model="query"
                        placeholder="Buscar unidade..."
                        aria-label="Buscar unidade"
                        @keydown.enter.prevent
                        class="min-h-11 w-full rounded-lg border border-border bg-surface py-2 pl-10 pr-3 text-sm text-text focus:border-accent focus:ring-2 focus:ring-accent/25"
                    >
                </label>
            </div>
            <div class="max-h-52 overflow-y-auto p-1">
                @foreach ($this->availableUnits as $unit)
                    <label
                        wire:key="unit-option-{{ $unit->id }}"
                        x-show="matches(@js($unit->name), @js($unit->slug))"
                        class="flex min-h-14 cursor-pointer items-center gap-3 rounded-lg px-3 hover:bg-background"
                    >
                        <input type="checkbox" value="{{ $unit->id }}" data-unit-name="{{ $unit->name }}" wire:model="unitIds" @change="refresh()" class="size-4 rounded border-border text-accent focus:ring-accent">
                        <span>
                            <span class="block text-sm font-medium text-text">{{ $unit->name }}@unless ($unit->active) <span class="font-normal text-text-muted">(desativada)</span>@endunless</span>
                            <span class="block font-mono text-xs text-text-muted">{{ $unit->slug }}</span>
                        </span>
                    </label>
                @endforeach
            </div>
            <div class="flex items-center justify-between gap-3 border-t border-border px-3 py-2">
                <p class="text-xs text-text-muted"><span x-text="count"></span> <span x-text="count === 1 ? 'selecionada' : 'selecionadas'"></span></p>
                <div class="flex gap-3">
                    <button type="button" class="cursor-pointer text-sm font-medium text-accent hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent" wire:click="selectAllUnits">Selecionar todas</button>
                    <button type="button" class="cursor-pointer text-sm font-medium text-text-muted hover:underline focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent" wire:click="clearUnitSelection">Limpar</button>
                </div>
            </div>
        </div>
    @endif
    <x-input-error :messages="$errors->get('unitIds')" />
    <x-input-error :messages="$errors->get('unitIds.*')" />
</div>
