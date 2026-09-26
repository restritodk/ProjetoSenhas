@extends('layouts.admin')

@section('title', 'Usuários')
@section('heading', 'Usuários')
@section('content-title', '1')

@section('content')
    <div class="space-y-6">
        <header class="flex flex-col gap-4 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm text-text-muted">
                    <span>Gestão</span>
                    <span aria-hidden="true"> / </span>
                    <span class="text-text">Usuários</span>
                </p>
                <h1 class="mt-1 text-[1.65rem] font-semibold tracking-tight text-text">Usuários</h1>
                <p class="mt-1 text-sm text-text-muted">Gerencie os usuários, acessos e permissões da sua clínica.</p>
            </div>
            <button
                type="button"
                data-dialog-return
                class="inline-flex min-h-11 cursor-pointer items-center justify-center gap-2 rounded-xl bg-accent px-4 py-2 text-sm font-semibold text-white shadow-sm transition duration-200 hover:bg-blue-700 hover:shadow-md focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-accent active:translate-y-px"
                x-data
                x-on:click="Livewire.dispatch('open-user-form')"
            >
                <x-admin.icon name="plus" class="size-4" />
                Novo usuário
            </button>
        </header>

        <livewire:users-manager />
    </div>
@endsection
