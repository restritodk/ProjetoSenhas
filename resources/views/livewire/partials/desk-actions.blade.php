@php
    $menuContext = $menuContext ?? 'menu';
@endphp
<x-ui.action-menu
    :menu-id="'desk-menu-'.$menuContext.'-'.$desk->id"
    :label="'Mais ações de '.$desk->name"
    :menu-label="'Ações de '.$desk->name"
    close-event="close-desk-menus"
>
    <button type="button" role="menuitem" class="flex min-h-11 w-full cursor-pointer items-center gap-2 rounded-lg px-3 text-left text-sm font-medium text-text hover:bg-background focus-visible:bg-background focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent" wire:click="edit({{ $desk->id }})" @click="close(false)">
        <x-admin.icon name="pencil" class="size-4 shrink-0 text-text-muted" />
        Editar
    </button>
    @if ($desk->active)
        <button type="button" role="menuitem" class="flex min-h-11 w-full cursor-pointer items-center gap-2 rounded-lg px-3 text-left text-sm font-medium text-text hover:bg-background focus-visible:bg-background focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent" wire:click="confirmDeactivation({{ $desk->id }})" @click="close(false)">
            <x-admin.icon name="ban" class="size-4 shrink-0 text-text-muted" />
            Desativar
        </button>
    @else
        <button type="button" role="menuitem" class="flex min-h-11 w-full cursor-pointer items-center gap-2 rounded-lg px-3 text-left text-sm font-medium text-success hover:bg-green-50 focus-visible:bg-green-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent" wire:click="confirmActivation({{ $desk->id }})" @click="close(false)">
            <x-admin.icon name="check" class="size-4 shrink-0" />
            Ativar
        </button>
    @endif
    @can('delete', $desk)
        <div class="my-1 border-t border-border" role="separator"></div>
        <button type="button" role="menuitem" class="flex min-h-11 w-full cursor-pointer items-center gap-2 rounded-lg px-3 text-left text-sm font-medium text-danger hover:bg-red-50 focus-visible:bg-red-50 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent" wire:click="confirmDeletion({{ $desk->id }})" @click="close(false)">
            <x-admin.icon name="trash" class="size-4 shrink-0" />
            Excluir mesa
        </button>
    @endcan
</x-ui.action-menu>
