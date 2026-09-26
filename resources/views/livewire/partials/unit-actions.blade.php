@php($menuContext = $menuContext ?? 'menu')
<div class="flex items-center justify-end gap-2">
    <x-ui.button variant="secondary" class="px-3" wire:click="edit({{ $unit->id }})">
        <x-admin.icon name="pencil" class="size-4" />
        Editar
    </x-ui.button>

    <div
        class="relative"
        x-data="{
            open: false,
            placed: false,
            placement: 'bottom',
            top: 0,
            left: 0,
            onReflow: null,
            init() {
                this.onReflow = () => {
                    if (this.open) {
                        this.place()
                    }
                }
                window.addEventListener('resize', this.onReflow)
                window.addEventListener('scroll', this.onReflow, true)
            },
            destroy() {
                window.removeEventListener('resize', this.onReflow)
                window.removeEventListener('scroll', this.onReflow, true)
            },
            place() {
                const trigger = this.$refs.trigger ?? this.$root.querySelector('[aria-haspopup]')
                const menu = this.$refs.menu ?? this.$root.querySelector('[role=menu]')
                if (! trigger || ! menu) {
                    return false
                }
                const margin = 8
                const gap = 6
                const rect = trigger.getBoundingClientRect()
                const measured = menu.offsetHeight >= 1 && menu.offsetWidth >= 1
                const width = measured ? menu.offsetWidth : 208
                const height = measured ? menu.offsetHeight : 112
                const spaceBelow = window.innerHeight - rect.bottom - margin
                const spaceAbove = rect.top - margin
                const fitsBelow = spaceBelow >= height + gap
                const fitsAbove = spaceAbove >= height + gap
                const openUp = ! fitsBelow && (fitsAbove || spaceAbove > spaceBelow)
                this.placement = openUp ? 'top' : 'bottom'
                let top = openUp ? rect.top - height - gap : rect.bottom + gap
                let left = rect.right - width
                top = Math.max(margin, Math.min(top, window.innerHeight - height - margin))
                left = Math.max(margin, Math.min(left, window.innerWidth - width - margin))
                this.top = top
                this.left = left
                this.placed = true
                return measured
            },
            openMenu() {
                if (this.open) {
                    this.close(true)
                    return
                }
                window.dispatchEvent(new CustomEvent('close-unit-menus', { detail: { source: this.$el } }))
                this.placed = false
                this.open = true
                this.$nextTick(() => this.schedulePlace(0))
            },
            schedulePlace(attempt) {
                if (! this.open) {
                    return
                }
                const ready = this.place()
                if (! ready && attempt < 12) {
                    setTimeout(() => this.schedulePlace(attempt + 1), 32)
                    return
                }
                this.$root.querySelector('[role=menuitem]')?.focus()
            },
            close(restoreFocus) {
                if (! this.open) {
                    return
                }
                this.open = false
                this.placed = false
                if (restoreFocus) {
                    this.$refs.trigger?.focus()
                }
            },
            onKeydown(event) {
                if (! this.open) {
                    return
                }
                const items = [...this.$refs.menu.querySelectorAll('[role=menuitem]')]
                const current = items.indexOf(document.activeElement)
                if (event.key === 'ArrowDown') {
                    event.preventDefault()
                    items[(current + 1 + items.length) % items.length]?.focus()
                }
                if (event.key === 'ArrowUp') {
                    event.preventDefault()
                    items[(current - 1 + items.length) % items.length]?.focus()
                }
                if (event.key === 'Home') {
                    event.preventDefault()
                    items[0]?.focus()
                }
                if (event.key === 'End') {
                    event.preventDefault()
                    items[items.length - 1]?.focus()
                }
            },
        }"
        x-on:close-unit-menus.window="if ($event.detail.source !== $el) close(false)"
        @keydown="onKeydown($event)"
        @keydown.escape.window="if (open) { $event.stopPropagation(); close(true) }"
        @click.outside="close(false)"
    >
        <button
            type="button"
            x-ref="trigger"
            class="inline-flex size-11 cursor-pointer items-center justify-center rounded-xl border border-border bg-surface text-text-muted transition duration-200 hover:bg-background hover:text-text focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent"
            @click="openMenu()"
            :aria-expanded="open.toString()"
            aria-haspopup="menu"
            aria-controls="unit-menu-{{ $menuContext }}-{{ $unit->id }}"
            aria-label="Mais ações de {{ $unit->name }}"
        >
            <x-admin.icon name="dots" class="size-5" />
        </button>
        <div
            id="unit-menu-{{ $menuContext }}-{{ $unit->id }}"
            x-ref="menu"
            x-cloak
            x-show="open"
            :data-placement="placement"
            class="hc-menu-in fixed z-30 rounded-xl border border-border bg-surface p-1 shadow-lg"
            :class="open ? '' : 'pointer-events-none'"
            :style="(placed ? `top:${top}px;left:${left}px;` : 'visibility:hidden;') + 'width:min(208px,calc(100vw - 1rem))'"
            role="menu"
            aria-label="Ações de {{ $unit->name }}"
        >
            @if ($unit->active)
                <button type="button" role="menuitem" class="flex min-h-11 w-full cursor-pointer items-center gap-2 rounded-lg px-3 text-left text-sm font-medium text-text hover:bg-background focus-visible:bg-background focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent" wire:click="confirmDeactivation({{ $unit->id }})" @click="close(false)">
                    <x-admin.icon name="ban" class="size-4 shrink-0 text-text-muted" />
                    Desativar
                </button>
            @else
                <button type="button" role="menuitem" class="flex min-h-11 w-full cursor-pointer items-center gap-2 rounded-lg px-3 text-left text-sm font-medium text-success hover:bg-green-50 focus-visible:bg-green-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent" wire:click="confirmActivation({{ $unit->id }})" @click="close(false)">
                    <x-admin.icon name="check" class="size-4 shrink-0" />
                    Ativar
                </button>
            @endif
            <div class="my-1 border-t border-border" role="separator"></div>
            <button type="button" role="menuitem" class="flex min-h-11 w-full cursor-pointer items-center gap-2 rounded-lg px-3 text-left text-sm font-medium text-danger hover:bg-red-50 focus-visible:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-danger" wire:click="confirmDeletion({{ $unit->id }})" @click="close(false)">
                <x-admin.icon name="trash" class="size-4 shrink-0" />
                Excluir unidade
            </button>
        </div>
    </div>
</div>
