<div class="flex flex-col items-center">
    <span class="hc-deletion-ring flex size-14 items-center justify-center rounded-full bg-blue-50 text-accent" aria-hidden="true">
        <x-admin.icon :name="$icon ?? 'layers'" class="size-6" />
    </span>
    <p class="mt-4 text-base font-semibold text-text">{{ $title }}</p>
    <p class="mt-2 max-w-xs text-sm leading-6 text-text-muted">{{ $detail }}</p>
    <p class="mt-2 text-xs text-text-muted">Não feche esta janela.</p>
</div>
