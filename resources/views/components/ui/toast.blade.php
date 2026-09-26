@props([
    'title',
    'message' => null,
    'tone' => 'success',
    'dismissMethod' => null,
])

@php
    $circle = match ($tone) {
        'warning' => 'bg-amber-50 text-warning',
        'danger' => 'bg-red-50 text-danger',
        default => 'bg-green-50 text-success',
    };
@endphp

<div
    role="status"
    aria-live="polite"
    {{ $attributes->merge(['class' => 'hc-modal-in fixed bottom-4 right-4 z-[60] w-[min(100%-2rem,22rem)] rounded-2xl border border-border bg-surface p-4 shadow-lg']) }}
    @if ($dismissMethod)
        x-data
        x-init="setTimeout(() => $wire.{{ $dismissMethod }}(), 5000)"
    @endif
>
    <div class="flex items-start gap-3">
        <span class="flex size-10 shrink-0 items-center justify-center rounded-full {{ $circle }}" aria-hidden="true">
            <svg class="size-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5">
                <path class="hc-check" stroke-linecap="round" stroke-linejoin="round" d="M5 13l4 4L19 7" />
            </svg>
        </span>
        <div class="min-w-0 flex-1 pt-0.5">
            <p class="text-sm font-semibold text-text">{{ $title }}</p>
            @if ($message)
                <p class="mt-1 text-sm leading-5 text-text-muted">{{ $message }}</p>
            @endif
        </div>
        @if ($dismissMethod)
            <button type="button" class="inline-flex size-8 shrink-0 cursor-pointer items-center justify-center rounded-lg text-text-muted hover:bg-background" wire:click="{{ $dismissMethod }}" aria-label="Dispensar aviso">
                <x-admin.icon name="x" class="size-4" />
            </button>
        @endif
    </div>
</div>
