@props([
    'title',
    'description' => null,
    'icon' => null,
])

<div {{ $attributes->merge(['class' => 'rounded-2xl border border-dashed border-border bg-background px-6 py-10 text-center']) }}>
    @if ($icon)
        <span class="mx-auto mb-3 flex size-12 items-center justify-center rounded-full bg-surface text-primary" aria-hidden="true">
            <x-admin.icon :name="$icon" class="size-6" />
        </span>
    @endif
    <p class="text-base font-semibold text-text">{{ $title }}</p>
    @if ($description)
        <p class="mt-2 text-sm text-text-muted">{{ $description }}</p>
    @endif
    <div class="mt-4">{{ $slot }}</div>
</div>
