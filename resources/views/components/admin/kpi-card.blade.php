@props([
    'label',
    'value',
    'tone' => 'blue',
    'icon' => 'ticket',
])

@php
    $tones = [
        'blue' => ['card' => 'border-[#d5e2f3] bg-[#eef3fb]', 'icon' => 'bg-[#dbe7f8] text-[#1d4ed8]'],
        'amber' => ['card' => 'border-[#f3e2c2] bg-[#fff6e8]', 'icon' => 'bg-[#fde7c2] text-[#b45309]'],
        'sky' => ['card' => 'border-[#d5e6f2] bg-[#eef6fb]', 'icon' => 'bg-[#d7ebf8] text-[#0369a1]'],
        'green' => ['card' => 'border-[#cfe8d8] bg-[#e9f7ee]', 'icon' => 'bg-[#d3f0de] text-[#15803d]'],
        'violet' => ['card' => 'border-[#ddd6f3] bg-[#f4f1fb]', 'icon' => 'bg-[#e6e0f8] text-[#6d28d9]'],
        'rose' => ['card' => 'border-[#f6d4d8] bg-[#fdeeee]', 'icon' => 'bg-[#fbdfe3] text-[#e11d48]'],
    ];
    $palette = $tones[$tone] ?? $tones['blue'];
@endphp

<article {{ $attributes->merge(['class' => 'rounded-xl border px-3.5 py-3 shadow-sm '.$palette['card']]) }}>
    <div class="flex items-start gap-3">
        <span class="flex size-9 shrink-0 items-center justify-center rounded-full {{ $palette['icon'] }}" aria-hidden="true">
            <x-admin.icon :name="$icon" class="size-[18px]" />
        </span>
        <div class="min-w-0">
            <p class="truncate text-[13px] font-medium text-text-muted">{{ $label }}</p>
            <p class="mt-1 text-[1.65rem] font-semibold leading-none tracking-tight text-text tabular-nums">{{ $value }}</p>
        </div>
    </div>
    <div class="mt-2.5 min-h-5 text-xs leading-5">
        {{ $slot }}
    </div>
</article>
