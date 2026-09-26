@extends('layouts.admin')

@section('title', 'Clínica / Unidades')
@section('heading', 'Clínica / Unidades')
@section('content-title', '1')

@section('content')
    <div class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm text-text-muted">
                    <span>Estrutura</span>
                    <span aria-hidden="true"> / </span>
                    <span class="text-text">Clínica / Unidades</span>
                </p>
                <h1 class="mt-1 text-[1.65rem] font-semibold tracking-tight text-text">Clínica / Unidades</h1>
                <p class="mt-1 text-sm text-text-muted">Gerencie os dados da clínica e suas unidades de atendimento.</p>
            </div>
            <button
                type="button"
                data-dialog-return
                class="inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-white shadow-sm transition duration-200 hover:bg-blue-700 hover:shadow-md active:translate-y-px"
                x-data
                x-on:click="Livewire.dispatch('open-unit-form')"
            >
                <x-admin.icon name="plus" class="size-4" />
                Adicionar unidade
            </button>
        </header>

        <section class="rounded-2xl border border-border bg-surface p-5 shadow-sm">
            <div class="mb-5 flex items-start justify-between gap-3">
                <div class="flex items-start gap-3">
                    <span class="flex size-10 shrink-0 items-center justify-center rounded-xl bg-[#dbe7f8] text-primary" aria-hidden="true">
                        <x-admin.icon name="building" class="size-5" />
                    </span>
                    <div>
                        <h2 class="text-base font-semibold text-text">Dados da clínica</h2>
                        <p class="mt-1 text-sm text-text-muted">Informações principais da organização.</p>
                    </div>
                </div>
                @if ($clinic->active)
                    <x-ui.badge tone="success"><span class="mr-1.5 size-1.5 rounded-full bg-success" aria-hidden="true"></span>Ativa</x-ui.badge>
                @else
                    <x-ui.badge tone="warning"><span class="mr-1.5 size-1.5 rounded-full bg-warning" aria-hidden="true"></span>Inativa</x-ui.badge>
                @endif
            </div>

            <form method="POST" action="{{ route('clinic.update') }}" class="grid gap-4 md:grid-cols-2" x-data="{ saving: false }" x-on:submit="saving = true">
                @csrf
                @method('PUT')

                <x-ui.input label="Nome" name="name" value="{{ old('name', $clinic->name) }}" required maxlength="255" autocomplete="organization">
                    <x-input-error :messages="$errors->get('name')" />
                </x-ui.input>

                <x-ui.input label="Identificador" name="slug" value="{{ old('slug', $clinic->slug) }}" required maxlength="255" autocomplete="off">
                    <p class="mt-1.5 text-xs text-text-muted">Usado em referências internas do sistema. Alterar o identificador pode afetar links já compartilhados.</p>
                    <x-input-error :messages="$errors->get('slug')" />
                </x-ui.input>

                <div class="md:col-span-2">
                    @if ($errors->any())
                        <p class="mb-3 text-sm text-danger" role="alert">Não foi possível salvar as alterações. Tente novamente.</p>
                    @endif
                    <x-ui.button type="submit" class="shadow-sm hover:shadow-md" x-bind:disabled="saving">
                        <span x-show="!saving">Salvar alterações</span>
                        <span x-cloak x-show="saving">Salvando...</span>
                    </x-ui.button>
                </div>
            </form>
        </section>

        <livewire:units-manager />
    </div>
@endsection
