<div wire:poll.20s.visible class="min-w-0">
    <header class="sticky top-0 z-20 border-b border-border bg-surface">
        <div class="flex min-h-16 items-center justify-between gap-3 px-4 sm:px-6 lg:px-8">
            <div class="flex min-w-0 items-center gap-3">
                <button
                    type="button"
                    class="inline-flex size-11 cursor-pointer items-center justify-center rounded-lg border border-border text-primary lg:hidden"
                    @click="sidebarOpen = true"
                    aria-controls="navegacao-principal"
                    :aria-expanded="sidebarOpen.toString()"
                >
                    <span class="sr-only">Abrir menu</span>
                    <x-admin.icon name="menu" class="size-5" />
                </button>
                <div class="min-w-0">
                    <p class="truncate text-sm font-semibold text-text">{{ $snapshot['clinic_name'] }}</p>
                    @if ($units->count() > 1)
                        <label class="mt-0.5 flex min-w-0 items-center gap-1 text-xs text-text-muted" for="dashboard-unidade">
                            <span class="shrink-0">Unidade:</span>
                            <select
                                id="dashboard-unidade"
                                wire:model.live="unitKey"
                                class="min-w-0 max-w-[14rem] cursor-pointer truncate border-0 bg-transparent p-0 text-xs font-medium text-text focus:ring-0"
                            >
                                <option value="">Todas as unidades</option>
                                @foreach ($units as $unit)
                                    <option value="{{ $unit->id }}">{{ $unit->name }}</option>
                                @endforeach
                            </select>
                        </label>
                    @else
                        <p class="truncate text-xs text-text-muted">
                            Unidade: {{ $units->first()->name ?? 'Nenhuma unidade ativa' }}
                        </p>
                    @endif
                </div>
            </div>

            <div class="relative" x-data="{ open: false }" @keydown.escape.window="open = false">
                <button
                    type="button"
                    class="flex min-h-11 cursor-pointer items-center gap-3 rounded-xl border border-border bg-background px-2.5 py-1 text-left"
                    @click="open = !open"
                    :aria-expanded="open.toString()"
                    aria-haspopup="menu"
                >
                    <span class="flex size-8 items-center justify-center rounded-full bg-primary text-xs font-semibold text-white" aria-hidden="true">
                        {{ \Illuminate\Support\Str::substr($userName, 0, 1) }}
                    </span>
                    <span class="hidden sm:block">
                        <span class="block max-w-[10rem] truncate text-sm font-medium text-text">{{ $roleLabel }}</span>
                        <span class="block max-w-[10rem] truncate text-xs text-text-muted">{{ $snapshot['clinic_name'] }}</span>
                    </span>
                </button>
                <div
                    x-cloak
                    x-show="open"
                    x-transition
                    @click.outside="open = false"
                    class="absolute right-0 mt-2 w-60 rounded-xl border border-border bg-surface p-2 shadow-lg"
                    role="menu"
                >
                    <p class="px-3 py-2 text-sm font-medium text-text">{{ $userName }}</p>
                    <p class="px-3 pb-2 text-xs text-text-muted">{{ auth()->user()->email }}</p>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="flex min-h-11 w-full cursor-pointer items-center rounded-lg px-3 text-sm text-text hover:bg-background" role="menuitem">
                            Sair
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </header>

    <div class="space-y-4 px-4 py-5 sm:px-6 lg:px-8">
        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div>
                <h1 class="text-[1.65rem] font-semibold tracking-tight text-text">Dashboard</h1>
                <p class="mt-1 text-sm text-text-muted">Visão geral do funcionamento da clínica em tempo real.</p>
            </div>
            <div
                class="flex flex-wrap items-center gap-x-3 gap-y-1 text-sm text-text-muted"
                x-data="{
                    dateLabel: '',
                    timeLabel: '',
                    tick() {
                        const now = new Date();
                        const raw = new Intl.DateTimeFormat('pt-BR', { weekday: 'long', day: 'numeric', month: 'long', year: 'numeric' }).format(now);
                        this.dateLabel = raw.charAt(0).toUpperCase() + raw.slice(1);
                        this.timeLabel = new Intl.DateTimeFormat('pt-BR', { hour: '2-digit', minute: '2-digit' }).format(now);
                    }
                }"
                x-init="tick(); setInterval(() => tick(), 15000)"
            >
                <time aria-live="off" x-text="dateLabel">{{ ucfirst(\Carbon\CarbonImmutable::now(config('app.timezone'))->locale('pt_BR')->isoFormat('dddd, D [de] MMMM [de] YYYY')) }}</time>
                <span class="hidden text-border sm:inline" aria-hidden="true">|</span>
                <time class="font-semibold tabular-nums text-text" aria-live="off" x-text="timeLabel">{{ \Carbon\CarbonImmutable::now(config('app.timezone'))->format('H:i') }}</time>
                <span class="inline-flex items-center gap-1.5 font-medium text-success" title="O painel administrativo está carregado nesta sessão.">
                    <span class="size-2 rounded-full bg-success" aria-hidden="true"></span>
                    Sistema online
                </span>
            </div>
        </div>

        <section aria-label="Indicadores do período" class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-6">
            <x-admin.kpi-card :label="$snapshot['issued']['label']" :value="$snapshot['issued']['value']" tone="blue" icon="ticket">
                @if ($snapshot['issued']['delta'] !== null)
                    <span @class([
                        'font-medium',
                        'text-success' => $snapshot['issued']['delta'] > 0,
                        'text-danger' => $snapshot['issued']['delta'] < 0,
                        'text-text-muted' => $snapshot['issued']['delta'] === 0,
                    ])>
                        {{ $snapshot['issued']['delta'] > 0 ? '↑' : ($snapshot['issued']['delta'] < 0 ? '↓' : '→') }}
                        {{ abs($snapshot['issued']['delta']) }}%
                    </span>
                    <span class="text-text-muted">vs. {{ $snapshot['comparison_label'] }}</span>
                @elseif ($snapshot['issued']['value'] === 0)
                    <span class="text-text-muted">Nenhuma senha emitida {{ $snapshot['period_key'] === 'today' ? 'hoje' : 'no período' }}.</span>
                @else
                    <span class="text-text-muted">sem base vs. {{ $snapshot['comparison_label'] }}</span>
                @endif
            </x-admin.kpi-card>

            <x-admin.kpi-card label="Aguardando" :value="$snapshot['waiting']['value']" tone="amber" icon="users">
                <span class="font-medium text-[#b45309]">{{ $snapshot['waiting']['hint'] }}</span>
            </x-admin.kpi-card>

            <x-admin.kpi-card label="Em atendimento" :value="$snapshot['in_service']['value']" tone="sky" icon="user">
                <span class="text-text-muted">{{ $snapshot['in_service']['hint'] }}</span>
            </x-admin.kpi-card>

            <x-admin.kpi-card :label="$snapshot['completed']['label']" :value="$snapshot['completed']['value']" tone="green" icon="check">
                @if ($snapshot['completed']['delta'] !== null)
                    <span @class([
                        'font-medium',
                        'text-success' => $snapshot['completed']['delta'] > 0,
                        'text-danger' => $snapshot['completed']['delta'] < 0,
                        'text-text-muted' => $snapshot['completed']['delta'] === 0,
                    ])>
                        {{ $snapshot['completed']['delta'] > 0 ? '↑' : ($snapshot['completed']['delta'] < 0 ? '↓' : '→') }}
                        {{ abs($snapshot['completed']['delta']) }}%
                    </span>
                    <span class="text-text-muted">vs. {{ $snapshot['comparison_label'] }}</span>
                @elseif ($snapshot['completed']['value'] === 0)
                    <span class="text-text-muted">Nenhum atendimento finalizado.</span>
                @else
                    <span class="text-text-muted">sem base vs. {{ $snapshot['comparison_label'] }}</span>
                @endif
            </x-admin.kpi-card>

            <x-admin.kpi-card label="Tempo médio de espera" :value="$snapshot['wait']['display']" tone="violet" icon="clock">
                <span class="text-text-muted">{{ $snapshot['wait']['hint'] }}</span>
            </x-admin.kpi-card>

            <x-admin.kpi-card label="Mesas ativas" :value="$snapshot['desks_summary']['display']" tone="rose" icon="monitor">
                @if ($snapshot['desks_summary']['percent'] !== null)
                    <span class="font-medium text-[#e11d48]">{{ $snapshot['desks_summary']['percent'] }}% operacionais</span>
                @else
                    <span class="text-text-muted">Nenhum guichê cadastrado.</span>
                @endif
            </x-admin.kpi-card>
        </section>

        <div class="flex flex-col gap-3 lg:flex-row lg:items-end lg:justify-between">
            <div role="radiogroup" aria-label="Período dos indicadores" class="flex flex-wrap gap-2">
                @foreach (['today' => 'Hoje', '7d' => '7 dias', '30d' => '30 dias'] as $key => $label)
                    <button
                        type="button"
                        role="radio"
                        wire:click="setPeriod('{{ $key }}')"
                        wire:key="period-{{ $key }}"
                        aria-checked="{{ $snapshot['period_key'] === $key && ! $customOpen ? 'true' : 'false' }}"
                        @class([
                            'min-h-9 cursor-pointer rounded-lg px-3.5 text-sm font-medium',
                            'bg-accent text-white' => $snapshot['period_key'] === $key && ! $customOpen,
                            'border border-border bg-surface text-text-muted hover:bg-background' => ! ($snapshot['period_key'] === $key && ! $customOpen),
                        ])
                    >
                        {{ $label }}
                    </button>
                @endforeach
                <button
                    type="button"
                    role="radio"
                    wire:click="openCustom"
                    aria-checked="{{ $customOpen || $snapshot['period_key'] === 'custom' ? 'true' : 'false' }}"
                    @class([
                        'min-h-9 cursor-pointer rounded-lg px-3.5 text-sm font-medium',
                        'bg-accent text-white' => $customOpen || $snapshot['period_key'] === 'custom',
                        'border border-border bg-surface text-text-muted hover:bg-background' => ! ($customOpen || $snapshot['period_key'] === 'custom'),
                    ])
                >
                    Personalizado
                </button>
            </div>

            @if ($customOpen || $snapshot['period_key'] === 'custom')
                <form wire:submit="applyCustomPeriod" class="flex flex-wrap items-end gap-2">
                    <div>
                        <label for="dashboard-de" class="mb-1 block text-xs font-medium text-text-muted">De</label>
                        <input id="dashboard-de" type="date" wire:model="customFrom" class="min-h-9 rounded-lg border border-border bg-surface px-2 text-sm text-text">
                    </div>
                    <div>
                        <label for="dashboard-ate" class="mb-1 block text-xs font-medium text-text-muted">Até</label>
                        <input id="dashboard-ate" type="date" wire:model="customTo" class="min-h-9 rounded-lg border border-border bg-surface px-2 text-sm text-text">
                    </div>
                    <button type="submit" class="min-h-9 cursor-pointer rounded-lg bg-primary px-3 text-sm font-medium text-white">
                        Aplicar
                    </button>
                </form>
            @endif
        </div>
        @error('customFrom')
            <p class="text-sm text-danger" role="alert">{{ $message }}</p>
        @enderror
        @if ($errorMessage !== '')
            <p class="text-sm text-danger" role="alert">{{ $errorMessage }}</p>
        @endif

        <div class="grid grid-cols-1 gap-4 xl:grid-cols-3">
            <section class="rounded-2xl border border-border bg-surface p-4 shadow-sm xl:col-span-2 sm:p-5" aria-labelledby="fluxo-atendimentos">
                <div class="flex flex-col gap-2 sm:flex-row sm:items-start sm:justify-between">
                    <div>
                        <h2 id="fluxo-atendimentos" class="text-base font-semibold text-text">Fluxo de atendimentos</h2>
                        <p class="mt-0.5 text-sm text-text-muted">{{ $snapshot['chart']['subtitle'] }}</p>
                    </div>
                    @if (! $snapshot['chart']['empty'])
                        <ul class="flex flex-wrap gap-x-3 gap-y-1 text-xs text-text-muted">
                            @foreach ($snapshot['chart']['legend'] as $item)
                                <li class="inline-flex items-center gap-1.5">
                                    <span class="size-2 rounded-full" style="background: {{ $item['color'] }}" aria-hidden="true"></span>
                                    {{ $item['name'] }}
                                </li>
                            @endforeach
                        </ul>
                    @endif
                </div>

                @if ($snapshot['chart']['empty'])
                    <p class="flex min-h-56 items-center justify-center text-sm text-text-muted">Nenhuma senha emitida {{ $snapshot['period_key'] === 'today' ? 'hoje' : 'no período' }}.</p>
                @else
                    <div
                        class="relative mt-3"
                        x-data="{ tip: null }"
                        @mouseleave="tip = null"
                    >
                        <svg viewBox="0 0 640 220" class="h-auto w-full" aria-hidden="true">
                            @foreach ($snapshot['chart']['y_labels'] as $tick)
                                <line x1="36" x2="628" y1="{{ $tick['y'] }}" y2="{{ $tick['y'] }}" stroke="#e2e8f0" stroke-width="1"></line>
                                <text x="0" y="{{ $tick['y'] + 4 }}" fill="#5b6b7c" font-size="11">{{ $tick['text'] }}</text>
                            @endforeach
                            @foreach ($snapshot['chart']['areas'] as $area)
                                <path d="{{ $area['d'] }}" fill="{{ $area['color'] }}" fill-opacity="0.28" stroke="none"></path>
                            @endforeach
                            <path d="{{ $snapshot['chart']['line'] }}" fill="none" stroke="#1e3a5f" stroke-width="2" stroke-linejoin="round" stroke-linecap="round"></path>
                            @foreach ($snapshot['chart']['x_labels'] as $label)
                                <text x="{{ $label['x'] }}" y="212" fill="#5b6b7c" font-size="11" text-anchor="middle">{{ $label['text'] }}</text>
                            @endforeach
                        </svg>
                        <div class="absolute inset-x-0 top-0 h-[78%]">
                            @foreach ($snapshot['chart']['columns'] as $column)
                                <button
                                    type="button"
                                    class="absolute top-0 h-full cursor-pointer border-0 bg-transparent p-0"
                                    style="left: {{ $column['left'] }}%; width: {{ max($column['width'], 2) }}%"
                                    @mouseenter="tip = {{ \Illuminate\Support\Js::from($column['tip']) }}"
                                    @focus="tip = {{ \Illuminate\Support\Js::from($column['tip']) }}"
                                    @blur="tip = null"
                                    aria-label="{{ $column['tip']['label'] }}, total {{ $column['tip']['total'] }}"
                                ></button>
                            @endforeach
                        </div>
                        <div
                            x-cloak
                            x-show="tip"
                            class="pointer-events-none absolute z-10 w-44 -translate-x-1/2 rounded-lg border border-border bg-surface p-2.5 text-xs shadow-md"
                            :style="tip ? `left: ${tip.left}%` : ''"
                        >
                            <p class="font-semibold text-text" x-text="tip ? tip.label : ''"></p>
                            <p class="mt-1 flex justify-between gap-3">
                                <span class="text-text-muted">Total</span>
                                <span class="font-semibold tabular-nums text-text" x-text="tip ? tip.total : ''"></span>
                            </p>
                            <template x-for="part in (tip ? tip.parts : [])" :key="part.name">
                                <p class="mt-1 flex items-center justify-between gap-3">
                                    <span class="inline-flex items-center gap-1.5 text-text-muted">
                                        <span class="size-2 rounded-full" :style="`background:${part.color}`"></span>
                                        <span x-text="part.name"></span>
                                    </span>
                                    <span class="font-medium tabular-nums text-text" x-text="part.count"></span>
                                </p>
                            </template>
                        </div>
                    </div>
                    <div class="sr-only">
                    <table>
                        <caption>Fluxo de atendimentos, {{ $snapshot['chart']['subtitle'] }}</caption>
                        <thead>
                            <tr>
                                <th>Horário</th>
                                <th>Total</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach ($snapshot['chart']['buckets'] as $bucket)
                                <tr>
                                    <td>{{ $bucket['label'] }}</td>
                                    <td>{{ $bucket['total'] }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                    </div>
                @endif
            </section>

            <section class="rounded-2xl border border-border bg-surface p-4 shadow-sm sm:p-5" aria-labelledby="fila-por-tipo">
                <h2 id="fila-por-tipo" class="text-base font-semibold text-text">Fila atual por tipo de senha</h2>
                @if ($snapshot['queue']['total'] === 0)
                    <p class="flex min-h-40 items-center text-sm text-text-muted">Nenhuma senha aguardando no momento.</p>
                @else
                    <ul class="mt-4 space-y-3">
                        @foreach ($snapshot['queue']['items'] as $item)
                            <li class="rounded-xl border border-border bg-background px-3 py-3" wire:key="queue-{{ $item['prefix'] }}-{{ $item['name'] }}">
                                <div class="flex items-start justify-between gap-3">
                                    <div class="flex min-w-0 items-center gap-2.5">
                                        <span class="flex size-8 shrink-0 items-center justify-center rounded-lg text-white" style="background: {{ $item['color'] }}" aria-hidden="true">
                                            <x-admin.icon name="ticket" class="size-4" />
                                        </span>
                                        <p class="truncate text-sm font-medium text-text">
                                            {{ $item['name'] }}
                                            @if ($item['prefix'] !== '')
                                                <span class="text-text-muted">({{ $item['prefix'] }})</span>
                                            @endif
                                        </p>
                                    </div>
                                    <p class="text-right">
                                        <span class="block text-lg font-semibold leading-none tabular-nums text-text">{{ $item['waiting'] }}</span>
                                        <span class="text-[11px] text-text-muted">aguardando</span>
                                    </p>
                                </div>
                                <div class="mt-2.5 h-1.5 overflow-hidden rounded-full bg-white" role="presentation">
                                    <div class="h-full rounded-full" style="width: {{ $item['percent'] }}%; background: {{ $item['color'] }}"></div>
                                </div>
                                <p class="mt-1.5 text-right text-[11px] text-text-muted">{{ $item['percent'] }}% da fila</p>
                            </li>
                        @endforeach
                    </ul>
                @endif
            </section>
        </div>

        <div class="grid grid-cols-1 gap-4 lg:grid-cols-2 xl:grid-cols-3">
            <section class="min-w-0 rounded-2xl border border-border bg-surface p-4 shadow-sm sm:p-5" aria-labelledby="mesas-guiches">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 id="mesas-guiches" class="text-base font-semibold text-text">Mesas / Guichês</h2>
                    @if ($snapshot['can_view_desks'])
                        <a href="{{ route('desks.index') }}" class="text-sm font-medium text-accent hover:underline">Ver todas</a>
                    @endif
                </div>
                @if ($snapshot['desks'] === [])
                    <p class="py-8 text-sm text-text-muted">Nenhum guichê cadastrado.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[28rem] text-left text-sm">
                            <thead>
                                <tr class="text-xs text-text-muted">
                                    <th class="pb-2 font-medium" scope="col">Mesa</th>
                                    <th class="pb-2 font-medium" scope="col">Status</th>
                                    <th class="pb-2 font-medium" scope="col">Senha atual</th>
                                    <th class="pb-2 font-medium" scope="col">Tempo</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($snapshot['desks'] as $desk)
                                    <tr class="border-t border-border/80" wire:key="desk-{{ $desk['name'] }}">
                                        <td class="py-2 pr-2 font-medium text-text">{{ $desk['name'] }}</td>
                                        <td class="py-2 pr-2">
                                            <span @class([
                                                'inline-flex items-center rounded-full px-2 py-0.5 text-xs font-medium',
                                                'bg-[#e8f0fe] text-[#1d4ed8]' => $desk['tone'] === 'busy',
                                                'bg-[#e8f6ee] text-[#15803d]' => $desk['tone'] === 'available',
                                                'bg-[#eef2f6] text-text-muted' => $desk['tone'] === 'inactive',
                                            ])>{{ $desk['status'] }}</span>
                                        </td>
                                        <td class="py-2 pr-2 font-semibold tabular-nums text-text">{{ $desk['ticket'] ?? '—' }}</td>
                                        <td class="py-2 tabular-nums text-text-muted">{{ $desk['elapsed'] ?? '—' }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="min-w-0 rounded-2xl border border-border bg-surface p-4 shadow-sm sm:p-5" aria-labelledby="paineis-tv">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 id="paineis-tv" class="text-base font-semibold text-text">Painéis de TV</h2>
                    @if ($snapshot['can_view_panels'])
                        <a href="{{ route('display-panels.index') }}" class="text-sm font-medium text-accent hover:underline">Ver todas</a>
                    @endif
                </div>
                @if ($snapshot['panels'] === [])
                    <p class="py-8 text-sm text-text-muted">Nenhum painel cadastrado.</p>
                @else
                    <div class="overflow-x-auto">
                        <table class="w-full min-w-[22rem] text-left text-sm">
                            <thead>
                                <tr class="text-xs text-text-muted">
                                    <th class="pb-2 font-medium" scope="col">Nome</th>
                                    <th class="pb-2 font-medium" scope="col">Status</th>
                                    <th class="pb-2 font-medium" scope="col">Última atualização</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($snapshot['panels'] as $panel)
                                    <tr class="border-t border-border/80" wire:key="panel-{{ $panel['name'] }}">
                                        <td class="py-2 pr-2 font-medium text-text">{{ $panel['name'] }}</td>
                                        <td class="py-2 pr-2">
                                            <span class="inline-flex items-center gap-1.5 text-xs font-medium text-text">
                                                <span @class([
                                                    'size-2 rounded-full',
                                                    'bg-success' => $panel['active'],
                                                    'bg-[#94a3b8]' => ! $panel['active'],
                                                ]) aria-hidden="true"></span>
                                                {{ $panel['status'] }}
                                            </span>
                                        </td>
                                        <td class="py-2 text-text-muted">{{ $panel['updated'] }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @endif
            </section>

            <section class="min-w-0 rounded-2xl border border-border bg-surface p-4 shadow-sm sm:p-5 lg:col-span-2 xl:col-span-1" aria-labelledby="atividade-recente">
                <div class="mb-3 flex items-center justify-between gap-3">
                    <h2 id="atividade-recente" class="text-base font-semibold text-text">Atividade recente</h2>
                </div>
                @if ($snapshot['activity'] === [])
                    <p class="py-8 text-sm text-text-muted">Nenhuma atividade no período.</p>
                @else
                    <ol class="space-y-3">
                        @foreach ($snapshot['activity'] as $event)
                            <li class="flex gap-3" wire:key="activity-{{ $loop->index }}-{{ $event['time'] }}-{{ $event['title'] }}">
                                <span @class([
                                    'mt-0.5 flex size-7 shrink-0 items-center justify-center rounded-full',
                                    'bg-[#e8f0fe] text-[#1d4ed8]' => in_array($event['tone'], ['call', 'recall', 'transfer'], true),
                                    'bg-[#e8f6ee] text-[#15803d]' => in_array($event['tone'], ['done', 'start'], true),
                                    'bg-[#fdeeee] text-[#e11d48]' => $event['tone'] === 'noshow',
                                ]) aria-hidden="true">
                                    <x-admin.icon :name="match ($event['tone']) {
                                        'done' => 'check',
                                        'start' => 'user',
                                        'noshow' => 'ban',
                                        'transfer' => 'sort',
                                        default => 'ticket',
                                    }" class="size-3.5" />
                                </span>
                                <div class="min-w-0">
                                    <p class="text-xs tabular-nums text-text-muted">{{ $event['time'] }}</p>
                                    <p class="text-sm font-medium text-text">{{ $event['title'] }}</p>
                                    <p class="text-xs text-text-muted">{{ $event['detail'] }}</p>
                                </div>
                            </li>
                        @endforeach
                    </ol>
                @endif
            </section>
        </div>
    </div>
</div>
