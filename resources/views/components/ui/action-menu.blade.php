@props([
    'menuId',
    'label',
    'menuLabel',
    'closeEvent' => 'close-action-menus',
])

<div
    class="relative flex justify-end"
    x-data="{
        open: false,
        placed: false,
        placement: 'bottom',
        top: 0,
        left: 0,
        onReflow: null,
        onForeignClose: null,
        closeEvent: @js($closeEvent),
        init() {
            this.onReflow = () => {
                if (this.open) {
                    this.place()
                }
            }
            this.onForeignClose = (event) => {
                if ((event.detail?.source ?? null) !== this.$el) {
                    this.close(false)
                }
            }
            window.addEventListener('resize', this.onReflow)
            window.addEventListener('scroll', this.onReflow, true)
            window.addEventListener(this.closeEvent, this.onForeignClose)
        },
        destroy() {
            window.removeEventListener('resize', this.onReflow)
            window.removeEventListener('scroll', this.onReflow, true)
            window.removeEventListener(this.closeEvent, this.onForeignClose)
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
            const width = measured ? menu.offsetWidth : 220
            const height = measured ? menu.offsetHeight : 168
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
            window.dispatchEvent(new CustomEvent(this.closeEvent, { detail: { source: this.$el } }))
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
            const items = [...this.$refs.menu.querySelectorAll('[role=menuitem]:not([disabled])')]
            const current = items.indexOf(document.activeElement)
            if (event.key === 'ArrowDown') {
                event.preventDefault()
                items[(current + 1 + items.length) % items.length]?.focus()
            }
            if (event.key === 'ArrowUp') {
                event.preventDefault()
                items[(current - 1 + items.length) % items.length]?.focus()
            }
        },
    }"
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
        aria-controls="{{ $menuId }}"
        aria-label="{{ $label }}"
    >
        <x-admin.icon name="dots" class="size-5" />
    </button>
    <div
        id="{{ $menuId }}"
        x-ref="menu"
        x-cloak
        x-show="open"
        :data-placement="placement"
        class="hc-menu-in fixed z-30 rounded-xl border border-border bg-surface p-1 shadow-lg"
        :class="open ? '' : 'pointer-events-none'"
        :style="(placed ? `top:${top}px;left:${left}px;` : 'visibility:hidden;') + 'width:min(240px,calc(100vw - 1rem))'"
        role="menu"
        aria-label="{{ $menuLabel }}"
    >
        {{ $slot }}
    </div>
</div>
