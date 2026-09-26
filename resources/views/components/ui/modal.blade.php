@props([
    'title',
    'description' => null,
    'icon' => null,
    'iconTone' => 'accent',
    'variant' => 'form',
    'open' => false,
    'maxWidth' => 'md',
    'closeMethod' => null,
    'loadingTarget' => null,
])

@php
    $maxWidthClass = match ($maxWidth) {
        'lg' => 'max-w-lg',
        'xl' => 'max-w-xl',
        '2xl' => 'max-w-2xl',
        '3xl' => 'max-w-3xl',
        default => 'max-w-md',
    };
    $titleId = $attributes->get('id', 'modal-title');
    $descriptionId = $titleId.'-descricao';
    $iconClasses = match ($iconTone) {
        'warning' => 'bg-amber-50 text-warning',
        'success' => 'bg-green-50 text-success',
        'danger' => 'bg-red-50 text-danger',
        default => 'bg-blue-50 text-accent',
    };
@endphp

@if ($open)
    <div
        class="fixed inset-0 z-50 flex items-end justify-center p-4 sm:items-center"
        role="dialog"
        aria-modal="true"
        aria-labelledby="{{ $titleId }}"
        @if ($description) aria-describedby="{{ $descriptionId }}" @endif
        x-data="{
            alive: true,
            acquired: false,
            previousCandidate: null,
            init() {
                this.previousCandidate = document.activeElement instanceof HTMLElement ? document.activeElement : null;
                this.$nextTick(() => {
                    requestAnimationFrame(() => {
                        if (! this.alive || ! this.$el.isConnected) {
                            return;
                        }
                        this.acquire();
                        const field = this.$el.querySelector('input, select, textarea');
                        const target = field ?? this.focusable()[0];
                        if (target) {
                            target.focus();
                        }
                    });
                });
            },
            destroy() {
                this.alive = false;
                this.release();
            },
            lock() {
                if (! window.__hcDialogLock) {
                    window.__hcDialogLock = { depth: 0, nodes: [], previous: null };
                }

                return window.__hcDialogLock;
            },
            acquire() {
                if (this.acquired) {
                    return;
                }
                const lock = this.lock();
                if (lock.depth === 0) {
                    lock.previous = this.previousCandidate;
                    this.markSiblings(lock);
                }
                lock.depth += 1;
                this.acquired = true;
            },
            markSiblings(lock) {
                let node = this.$el;
                while (node.parentElement && node !== document.body) {
                    [...node.parentElement.children].forEach((sibling) => {
                        if (sibling === node || sibling.tagName === 'SCRIPT' || sibling.tagName === 'STYLE') {
                            return;
                        }
                        if (sibling.hasAttribute('inert') || sibling.hasAttribute('data-hc-dialog-lock')) {
                            return;
                        }
                        sibling.setAttribute('inert', '');
                        sibling.setAttribute('data-hc-dialog-lock', '');
                        lock.nodes.push(sibling);
                    });
                    node = node.parentElement;
                }
            },
            release() {
                if (! this.acquired) {
                    return;
                }
                this.acquired = false;
                const lock = this.lock();
                lock.depth = Math.max(0, lock.depth - 1);
                if (lock.depth > 0) {
                    return;
                }
                lock.nodes.forEach((el) => {
                    if (! el.isConnected || ! el.hasAttribute('data-hc-dialog-lock')) {
                        return;
                    }
                    el.removeAttribute('inert');
                    el.removeAttribute('data-hc-dialog-lock');
                });
                lock.nodes = [];
                const previous = lock.previous;
                lock.previous = null;
                const previousIsUsable = previous
                    && previous.isConnected
                    && previous !== document.body
                    && previous !== document.documentElement
                    && ! previous.closest('[role=dialog]');
                if (previousIsUsable) {
                    previous.focus({ preventScroll: true });
                    return;
                }
                const fallback = document.querySelector('[data-dialog-return]');
                if (fallback instanceof HTMLElement) {
                    fallback.focus({ preventScroll: true });
                }
            },
            focusable() {
                return [...this.$el.querySelectorAll('button, [href], input, select, textarea, [tabindex]:not([tabindex=\'-1\'])')]
                    .filter((el) => !el.disabled && el.getAttribute('aria-hidden') !== 'true');
            },
            trap(event) {
                if (event.key !== 'Tab') return;
                const items = this.focusable();
                if (items.length === 0) {
                    event.preventDefault();
                    return;
                }
                const first = items[0];
                const last = items[items.length - 1];
                if (event.shiftKey && document.activeElement === first) {
                    event.preventDefault();
                    last.focus();
                } else if (!event.shiftKey && document.activeElement === last) {
                    event.preventDefault();
                    first.focus();
                }
            }
        }"
        x-on:keydown.tab="trap($event)"
        @if ($closeMethod)
            x-on:keydown.escape.window="if ($root.querySelector('[data-close-guard]')?.disabled) return; $wire.{{ $closeMethod }}()"
        @endif
    >
        <div class="absolute inset-0 bg-primary-dark/40 hc-fade" @if ($closeMethod) x-on:click="if ($root.querySelector('[data-close-guard]')?.disabled) return; $wire.{{ $closeMethod }}()" @endif></div>

        <div class="hc-modal-in relative z-10 flex max-h-[calc(100dvh-2rem)] w-full {{ $maxWidthClass }} flex-col overflow-hidden rounded-2xl border border-border bg-surface shadow-lg">
            @isset($processing)
                <div wire:loading.flex wire:target="{{ $loadingTarget }}" class="cursor-wait flex-col items-center justify-center px-6 py-12 text-center" aria-busy="true" aria-live="polite">
                    {{ $processing }}
                </div>
            @endisset
            <div @if (isset($processing)) wire:loading.remove wire:target="{{ $loadingTarget }}" @endif class="flex min-h-0 flex-1 flex-col">
            @if ($variant === 'confirm')
                <div class="shrink-0 px-6 pb-2 pt-6 text-center">
                    @if ($closeMethod)
                        <div class="mb-2 flex justify-end">
                            <button type="button" class="inline-flex size-9 cursor-pointer items-center justify-center rounded-lg text-text-muted hover:bg-background disabled:cursor-not-allowed disabled:opacity-60" wire:click="{{ $closeMethod }}" aria-label="Fechar" @if ($loadingTarget) wire:loading.attr="disabled" wire:target="{{ $loadingTarget }}" data-close-guard @endif>
                                <x-admin.icon name="x" class="size-4" />
                            </button>
                        </div>
                    @endif
                    @if ($icon)
                        <span class="mx-auto flex size-12 items-center justify-center rounded-full {{ $iconClasses }}" aria-hidden="true">
                            <x-admin.icon :name="$icon" class="size-6" />
                        </span>
                    @endif
                    <h2 id="{{ $titleId }}" class="mt-4 text-lg font-semibold text-text">{{ $title }}</h2>
                    @if ($description)
                        <p id="{{ $descriptionId }}" class="mt-2 text-sm leading-6 text-text-muted">{{ $description }}</p>
                    @endif
                </div>
            @else
                <div class="flex shrink-0 items-start justify-between gap-4 border-b border-border px-6 py-4">
                    <div class="flex min-w-0 items-start gap-3">
                        @if ($icon)
                            <span class="mt-0.5 flex size-10 shrink-0 items-center justify-center rounded-xl {{ $iconClasses }}" aria-hidden="true">
                                <x-admin.icon :name="$icon" class="size-5" />
                            </span>
                        @endif
                        <div class="min-w-0">
                            <h2 id="{{ $titleId }}" class="text-lg font-semibold text-text">{{ $title }}</h2>
                            @if ($description)
                                <p id="{{ $descriptionId }}" class="mt-1 text-sm leading-5 text-text-muted">{{ $description }}</p>
                            @endif
                        </div>
                    </div>
                    @if ($closeMethod)
                        <button type="button" class="inline-flex size-9 shrink-0 cursor-pointer items-center justify-center rounded-lg text-text-muted hover:bg-background" wire:click="{{ $closeMethod }}" aria-label="Fechar">
                            <x-admin.icon name="x" class="size-4" />
                        </button>
                    @endif
                </div>
            @endif

            <div @class(['min-h-0 flex-1 overflow-y-auto px-6 py-4 text-sm text-text-muted', 'text-center' => $variant === 'confirm'])>
                {{ $slot }}
            </div>

            @isset($actions)
                <div class="flex shrink-0 flex-col gap-2 border-t border-border px-6 py-4 sm:flex-row sm:justify-end">{{ $actions }}</div>
            @endisset
            </div>
        </div>
    </div>
@endif
