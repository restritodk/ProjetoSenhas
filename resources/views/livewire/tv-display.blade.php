@php
    $p = $presentation ?? [];
    $logoUrl = $p['logo_url'] ?? null;
    $slogan = trim((string) ($p['slogan'] ?? ''));
    $showDate = (bool) ($p['show_date'] ?? true);
    $showTime = (bool) ($p['show_time'] ?? true);
    $showConnection = (bool) ($p['show_connection_status'] ?? true);
    $showTicketType = (bool) ($p['show_ticket_type'] ?? true);
    $footerEnabled = (bool) ($p['footer_enabled'] ?? true);
    $footer1 = $p['footer_1'] ?? ['title' => 'Acompanhe sua senha', 'text' => 'Fique atento ao painel.'];
    $footer2 = $p['footer_2'] ?? ['title' => 'Dirija-se à mesa', 'text' => 'Quando sua senha for chamada.'];
    $footer3 = $p['footer_3'] ?? ['title' => 'Aguarde sua vez', 'text' => 'Obrigado pela compreensão.'];
    $chimeEnabled = (bool) ($p['chime_enabled'] ?? true);
    $speechEnabled = (bool) ($p['speech_enabled'] ?? true);
    $chimeVolume = (int) ($p['chime_volume'] ?? 70);
    $primaryColor = (string) ($p['primary_color'] ?? '#1e3a5f');
    $accentColor = (string) ($p['accent_color'] ?? '#2563eb');
    $onPrimary = (string) ($p['on_primary_color'] ?? '#ffffff');
    $logoBackground = \App\Support\LogoSurface::normalizeMode($p['logo_background'] ?? 'transparent');
    $logoBackgroundColor = \App\Support\LogoSurface::normalizeColor($p['logo_background_color'] ?? '#FFFFFF');
    $logoSurfaceCss = \App\Support\LogoSurface::cssBackground($logoBackground, $logoBackgroundColor);
    $isSmartTv = \App\Support\SmartTvBrowser::matches(request()->userAgent());
    // Relative URL — absolute asset(APP_URL) breaks when the TV opens the panel by IP/host mismatch.
    // Cache-bust so Samsung does not keep a stale/empty response after deploy.
    $effectPath = public_path('sond/efeitoSonoroNovo.mp3');
    $effectVersion = is_file($effectPath) ? (string) filemtime($effectPath) : (string) time();
    $smartTvEffectUrl = '/sond/efeitoSonoroNovo.mp3?v='.$effectVersion;
    $tvAudioDebug = (bool) config('app.debug') || (bool) config('tv_realtime.player_diagnostics', false);
    $diagQuery = (string) config('tv_realtime.diag_query', 'tvdiag');
    $tvDiagEnabled = $tvAudioDebug
        || request()->boolean($diagQuery)
        || request()->query($diagQuery) === '1';
    $tvBuildStamp = 'tv-'.substr(hash('sha1', $effectVersion.'|samsung-v5-split-audio'), 0, 10);
    $fastWatchEnabled = (bool) config('tv_realtime.fast_watch_enabled', true);
    $fastWatchIntervalMs = (int) config('tv_realtime.fast_watch_interval_ms', 400);
    $livewirePollMs = (int) config('tv_realtime.livewire_poll_ms', 3000);
    // Classic sRGB gradient (no "in oklab"). Tailwind v4's bg-gradient-to-r emits
    // `to right in oklab`, which Samsung Smart TV browsers treat as invalid and drop.
    // Start stop matches design-system --color-success (#15803d); via/to come from clinic branding.
    $footerBandFrom = '#15803d';
    $footerBandCss = sprintf(
        'background-color: %1$s; background-image: linear-gradient(to right, %2$s, %1$s, %3$s);',
        $accentColor,
        $footerBandFrom,
        $primaryColor,
    );
@endphp
<div
    id="tv-shell-root"
    data-tv-build-family="samsung-v5-split-audio"
    data-tv-build="{{ $tvBuildStamp }}"
    wire:poll.{{ $livewirePollMs }}ms="refreshFeed"
    class="tv-shell relative flex flex-col overflow-hidden"
    style="--color-primary: {{ $primaryColor }}; --color-accent: {{ $accentColor }}; --color-primary-dark: {{ $primaryColor }}; --color-primary-light: {{ $primaryColor }};"
    x-data="tvAudioUi(@js([
        'panelToken' => $publicToken,
        'chimeEnabled' => $chimeEnabled,
        'speechEnabled' => $speechEnabled,
        'chimeVolume' => $chimeVolume,
        'isSmartTv' => $isSmartTv,
        'effectUrl' => $smartTvEffectUrl,
        'audioDebug' => $tvAudioDebug,
        'diagEnabled' => $tvDiagEnabled,
        'buildStamp' => $tvBuildStamp,
        'fastWatchEnabled' => $fastWatchEnabled,
        'fastWatchIntervalMs' => $fastWatchIntervalMs,
        'watchUrl' => '/painel/'.$publicToken.'/watch',
        'diagIngestUrl' => (bool) config('tv_realtime.diag_ingest_enabled', false)
            ? '/painel/'.$publicToken.'/diag'
            : null,
        'sampleTtsUrl' => '/painel/'.$publicToken.'/diag/sample-tts?'.$diagQuery.'=1',
        'serverCursor' => (int) ($currentCall['id'] ?? 0),
        'serverDisplayCode' => (string) ($currentCall['display_code'] ?? ''),
        'serverDeskName' => (string) ($currentCall['desk_name'] ?? ''),
        'serverTicketTypeName' => (string) ($currentCall['ticket_type_name'] ?? ''),
    ]))"
    x-on:tv-watch-snapshot.window="onWatchSnapshot(\$event.detail)"
    x-on:tv-watch-refresh.window="if (\$event.detail && \$event.detail.panelToken === panelToken) { requestFeedRefresh() }"
>
    {{-- Stable audio host (wire:ignore). Keep OFF-SCREEN but never clip/opacity:0 —
        Tizen/Samsung often refuse play() on clipped or fully invisible media elements. --}}
    <div
        wire:ignore
        aria-hidden="true"
        data-tv-audio-host
        style="position:fixed;left:-2000px;top:0;width:8px;height:8px;overflow:hidden;opacity:0.01;pointer-events:none;z-index:-1;"
        x-data
        x-init="$nextTick(() => window.__humanaTvCallAudio && window.__humanaTvCallAudio.ensure(@js($publicToken), @js([
            'chimeEnabled' => $chimeEnabled,
            'speechEnabled' => $speechEnabled,
            'chimeVolume' => $chimeVolume,
            'isSmartTv' => $isSmartTv,
            'effectUrl' => $smartTvEffectUrl,
            'audioDebug' => $tvAudioDebug || $tvDiagEnabled,
        ])))"
    >
        <audio
            data-tv-call-effect
            preload="auto"
            playsinline
            src="{{ $smartTvEffectUrl }}"
        ></audio>
        <audio
            data-tv-call-speech
            preload="auto"
            playsinline
        ></audio>
    </div>
    {{-- HEADER --}}
    <header class="tv-header flex shrink-0 items-center justify-between gap-2 border-b-4 border-primary bg-primary px-[max(0.75rem,var(--tv-pad))] sm:gap-4 sm:px-5 lg:px-8" style="color: {{ $onPrimary }};">
        <div class="flex min-w-0 max-w-[55%] items-center lg:max-w-[60%]">
            @if ($logoUrl)
                <div
                    class="tv-brand-logo-wrap tv-brand-logo-wrap--{{ $logoBackground }}"
                    @if ($logoSurfaceCss !== null)
                        style="background: {{ $logoSurfaceCss }};"
                    @endif
                >
                    <img
                        src="{{ $logoUrl }}"
                        alt=""
                        class="tv-brand-logo"
                    >
                </div>
            @else
                <div class="flex min-w-0 items-center gap-2 sm:gap-3">
                    <span class="flex size-9 shrink-0 items-center justify-center rounded-lg bg-white/15 text-sm font-bold sm:size-11 sm:text-lg lg:size-12 lg:text-xl" aria-hidden="true">hC</span>
                    <p class="min-w-0 truncate text-base font-bold tracking-tight sm:text-xl md:text-2xl lg:text-3xl">
                        {{ $clinicName !== '' ? $clinicName : config('app.name') }}
                    </p>
                </div>
            @endif
        </div>

        <div class="flex min-w-[10.5rem] shrink-0 flex-col items-end justify-center gap-0.5 sm:min-w-[13rem] sm:gap-1 md:min-w-[15rem]">
            @if ($showDate || $showTime)
            <div
                class="max-w-full text-right leading-none"
                x-data="{
                    now: new Date(),
                    init() {
                        setInterval(() => { this.now = new Date() }, 1000)
                    },
                    formatDate(options) {
                        const raw = this.now.toLocaleDateString('pt-BR', options).toLowerCase()

                        return raw.charAt(0).toUpperCase() + raw.slice(1)
                    },
                    dateLabel() {
                        return this.formatDate({
                            weekday: 'long',
                            day: 'numeric',
                            month: 'long',
                            year: 'numeric',
                        })
                    },
                    dateLabelMedium() {
                        return this.formatDate({
                            weekday: 'long',
                            day: 'numeric',
                            month: 'short',
                        })
                    },
                    dateLabelShort() {
                        return this.formatDate({
                            weekday: 'short',
                            day: '2-digit',
                            month: 'short',
                        })
                    },
                    timeLabel() {
                        return this.now.toLocaleTimeString('pt-BR', { hour: '2-digit', minute: '2-digit' })
                    }
                }"
            >
                @if ($showDate)
                    <p class="hidden text-[0.7rem] font-medium opacity-70 xl:block xl:text-[0.8125rem]" x-text="dateLabel()"></p>
                    <p class="hidden text-[0.65rem] font-medium opacity-70 sm:block xl:hidden" x-text="dateLabelMedium()"></p>
                    <p class="text-[0.6rem] font-medium opacity-70 sm:hidden" x-text="dateLabelShort()"></p>
                @endif
                @if ($showTime)
                    <p class="tv-time mt-0.5 font-bold tabular-nums tracking-tight sm:mt-1" x-text="timeLabel()"></p>
                @endif
            </div>
            @endif

            <div class="flex flex-wrap items-center justify-end gap-x-1.5 gap-y-1 sm:gap-x-2.5">
                @if ($showConnection)
                <p class="text-[0.6rem] sm:text-xs">
                    @if ($connectionStatus === 'online')
                        <span class="inline-flex items-center gap-1 text-emerald-300 sm:gap-1.5">
                            <span class="size-1.5 rounded-full bg-emerald-400 sm:size-2" aria-hidden="true"></span>
                            Online
                        </span>
                    @elseif ($connectionStatus === 'reconnecting')
                        <span class="inline-flex items-center gap-1 text-amber-300 sm:gap-1.5">
                            <span class="size-1.5 rounded-full bg-amber-400 sm:size-2" aria-hidden="true"></span>
                            Reconectando
                        </span>
                    @else
                        <span class="inline-flex items-center gap-1 text-red-300 sm:gap-1.5">
                            <span class="size-1.5 rounded-full bg-red-400 sm:size-2" aria-hidden="true"></span>
                            Offline
                        </span>
                    @endif
                </p>
                @endif
                <button
                    type="button"
                    class="inline-flex min-h-7 items-center justify-center rounded-md border border-white/20 bg-white/10 px-1.5 py-0.5 text-[0.6rem] font-medium text-white/90 transition hover:bg-white/20 sm:min-h-8 sm:px-2.5 sm:text-xs"
                    @click="enableSound()"
                    x-text="soundEnabled ? '🔊 Som ativo' : '🔊 Ativar som'"
                ></button>
                <button
                    type="button"
                    class="inline-flex min-h-7 items-center justify-center rounded-md border border-white/20 bg-white/10 px-1.5 py-0.5 text-[0.6rem] font-medium text-white/90 transition hover:bg-white/20 sm:min-h-8 sm:px-2.5 sm:text-xs"
                    @click="toggleFullscreen()"
                    x-text="isFullscreen ? '⛶ Sair' : '⛶ Tela cheia'"
                ></button>
                <button
                    type="button"
                    class="inline-flex min-h-7 items-center justify-center rounded-md border border-white/20 bg-white/10 px-1.5 py-0.5 text-[0.6rem] font-medium text-white/90 transition hover:bg-white/20 sm:min-h-8 sm:px-2.5 sm:text-xs"
                    x-show="diagEnabled || diagVisible"
                    x-cloak
                    @click="resumeMedia()"
                >▶ Retomar mídia</button>
                <button
                    type="button"
                    class="inline-flex min-h-7 items-center justify-center rounded-md border border-amber-300/40 bg-amber-500/20 px-1.5 py-0.5 text-[0.6rem] font-medium text-white transition hover:bg-amber-500/30 sm:min-h-8 sm:px-2.5 sm:text-xs"
                    x-show="diagVisible"
                    x-cloak
                    @click="diagProbeEffect()"
                >♪ Testar efeito</button>
                <button
                    type="button"
                    class="inline-flex min-h-7 items-center justify-center rounded-md border border-amber-300/40 bg-amber-500/20 px-1.5 py-0.5 text-[0.6rem] font-medium text-white transition hover:bg-amber-500/30 sm:min-h-8 sm:px-2.5 sm:text-xs"
                    x-show="diagVisible"
                    x-cloak
                    @click="diagProbeTts()"
                >🎤 Testar TTS</button>
                <button
                    type="button"
                    class="inline-flex min-h-7 items-center justify-center rounded-md border border-amber-300/40 bg-amber-500/20 px-1.5 py-0.5 text-[0.6rem] font-medium text-white transition hover:bg-amber-500/30 sm:min-h-8 sm:px-2.5 sm:text-xs"
                    x-show="diagVisible"
                    x-cloak
                    @click="diagProbeSequence()"
                >▶♪ Sequência</button>
            </div>
        </div>
    </header>

    {{-- Smart TV on-screen diagnostics (no secrets). Enable with ?tvdiag=1 --}}
    <div
        x-show="diagVisible"
        x-cloak
        data-tv-diag-hud
        class="pointer-events-none absolute inset-x-0 bottom-0 z-[80] max-h-[42%] overflow-auto bg-black/80 p-2 font-mono text-[10px] leading-snug text-emerald-200 sm:text-[11px]"
        style="white-space: pre-wrap;"
        x-text="diagText()"
    ></div>

    @if (! $available)
        <main class="flex min-h-0 flex-1 items-center justify-center bg-background px-4 text-center text-text sm:px-8">
            <div class="max-w-2xl rounded-2xl border-2 border-primary/20 bg-surface p-6 shadow-lg sm:rounded-3xl sm:p-12">
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-accent sm:text-sm">{{ config('app.name') }}</p>
                <h1 class="mt-3 text-2xl font-bold text-primary sm:mt-4 sm:text-4xl">Painel indisponível</h1>
                <p class="mt-3 text-sm text-text-muted sm:mt-4 sm:text-lg">Este painel está inativo ou temporariamente sem dados operacionais.</p>
            </div>
        </main>
    @else
        {{-- MAIN: mobile prioriza senha; md+ usa grid mídia/chamadas --}}
        <main
            class="grid min-h-0 flex-1 grid-cols-1 gap-[var(--tv-gap)] p-[var(--tv-pad)]
                max-md:grid-rows-[minmax(0,1.15fr)_minmax(0,0.95fr)_minmax(7.5rem,0.7fr)]
                md:grid-cols-[minmax(0,1.55fr)_minmax(16rem,1fr)]
                lg:grid-cols-[minmax(0,1.9fr)_minmax(20rem,1fr)]
                xl:grid-cols-[minmax(0,2fr)_minmax(22rem,1fr)]"
        >
            {{-- MEDIA: wire:ignore + @island keep the player DOM/Alpine alive across
                 TicketCall morphs, watch-triggered refreshFeed, and highlight toggles.
                 Playlist updates stay on the nested TvMediaPlayer poll + window event.
                 Do NOT bind live props from TvDisplay (clinic-name etc.) — that remounts media. --}}
            <section
                wire:ignore
                class="relative min-h-0 overflow-hidden rounded-xl border-2 border-primary bg-primary shadow-sm max-md:order-3 sm:rounded-2xl sm:border-[3px] md:order-none"
                data-tv-media-shell
            >
                @island(name: 'tv-media')
                    <livewire:tv-media-player
                        wire:key="tv-media-{{ $publicToken }}"
                        :public-token="$publicToken"
                    />
                @endisland
            </section>

            {{-- CALLS COLUMN --}}
            <aside class="flex min-h-0 flex-col gap-[var(--tv-gap)] max-md:contents md:order-none">
                {{-- CURRENT CALL — server HTML for Livewire; Alpine overlays fast-watch text. --}}
                <section
                    class="flex min-h-0 flex-col overflow-hidden rounded-xl border-2 border-primary bg-surface max-md:order-1 sm:rounded-2xl sm:border-[3px] md:flex-[1.55] {{ $highlight ? 'tv-highlight' : '' }}"
                    :class="fastHighlight ? 'tv-highlight' : ''"
                    wire:key="current-call-{{ $currentCall['id'] ?? 'none' }}"
                >
                    <div class="shrink-0 bg-primary px-3 py-1.5 text-center sm:px-4 sm:py-2.5 md:py-3">
                        <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-white sm:text-sm sm:tracking-[0.22em] md:text-base lg:text-lg">Senha atual</h2>
                    </div>

                    <div class="flex min-h-0 flex-1 flex-col items-center justify-center gap-1.5 px-2 py-2 text-center sm:gap-2.5 sm:px-4 sm:py-4 md:gap-3 md:px-5 md:py-5">
                        @if ($currentCall)
                            <p
                                class="tv-code {{ $highlight ? 'tv-code-pulse' : '' }} w-full shrink-0 font-bold tracking-tight text-danger"
                                :class="fastHighlight ? 'tv-code-pulse' : ''"
                                x-text="visibleDisplayCode() || {{ \Illuminate\Support\Js::from($currentCall['display_code']) }}"
                            >{{ $currentCall['display_code'] }}</p>
                            <div class="h-0.5 w-16 shrink-0 bg-danger/50 sm:w-24 md:w-32" aria-hidden="true"></div>
                            <p
                                class="tv-desk shrink-0 font-bold uppercase tracking-wide text-primary"
                                x-text="visibleDeskName() || {{ \Illuminate\Support\Js::from($currentCall['desk_name']) }}"
                            >{{ $currentCall['desk_name'] }}</p>
                            @if ($showTicketType)
                                <p
                                    class="shrink-0 text-[0.7rem] font-medium leading-snug text-text-muted sm:text-sm md:text-base"
                                    x-text="visibleTicketTypeName() || {{ \Illuminate\Support\Js::from($currentCall['ticket_type_name']) }}"
                                >{{ $currentCall['ticket_type_name'] }}</p>
                            @endif
                        @else
                            <div x-show="! visibleDisplayCode()" class="flex w-full flex-col items-center justify-center gap-1.5">
                                <p class="tv-code w-full font-bold text-primary/30">—</p>
                                <div class="h-0.5 w-16 shrink-0 bg-border sm:w-24" aria-hidden="true"></div>
                                <p class="text-sm font-semibold uppercase tracking-wide text-text-muted sm:text-lg md:text-xl">Aguardando chamada</p>
                            </div>
                            <div x-cloak x-show="visibleDisplayCode()" class="flex w-full flex-col items-center justify-center gap-1.5 sm:gap-2.5 md:gap-3">
                                <p class="tv-code tv-code-pulse w-full shrink-0 font-bold tracking-tight text-danger" x-text="visibleDisplayCode()"></p>
                                <div class="h-0.5 w-16 shrink-0 bg-danger/50 sm:w-24 md:w-32" aria-hidden="true"></div>
                                <p class="tv-desk shrink-0 font-bold uppercase tracking-wide text-primary" x-text="visibleDeskName()"></p>
                                @if ($showTicketType)
                                    <p class="shrink-0 text-[0.7rem] font-medium leading-snug text-text-muted sm:text-sm md:text-base" x-text="visibleTicketTypeName()"></p>
                                @endif
                            </div>
                        @endif
                    </div>
                </section>

                {{-- RECENT CALLS --}}
                <section class="flex min-h-0 flex-col overflow-hidden rounded-xl border-2 border-primary bg-surface max-md:order-2 sm:rounded-2xl sm:border-[3px] md:flex-[0.85]">
                    <div class="shrink-0 bg-primary px-3 py-1.5 text-center sm:px-4 sm:py-2.5 md:py-3">
                        <h2 class="text-xs font-bold uppercase tracking-[0.18em] text-white sm:text-sm sm:tracking-[0.22em] md:text-base lg:text-lg">Últimas chamadas</h2>
                    </div>

                    <div class="min-h-0 flex-1 overflow-hidden px-1.5 py-1.5 sm:px-3 sm:py-3">
                        @if (count($recentCalls) === 0)
                            <p class="flex h-full items-center justify-center text-xs text-text-muted sm:text-sm md:text-base">Nenhuma chamada registrada.</p>
                        @else
                            <table class="h-full w-full table-fixed text-left">
                                <caption class="sr-only">Últimas chamadas desta unidade</caption>
                                <thead>
                                    <tr class="text-[0.55rem] uppercase tracking-wide text-text-muted sm:text-[0.65rem] md:text-xs">
                                        <th scope="col" class="px-1 pb-1 font-semibold sm:px-1.5 sm:pb-2 md:px-2">Senha</th>
                                        <th scope="col" class="px-1 pb-1 font-semibold sm:px-1.5 sm:pb-2 md:px-2">Tipo</th>
                                        <th scope="col" class="px-1 pb-1 font-semibold sm:px-1.5 sm:pb-2 md:px-2">Mesa</th>
                                        <th scope="col" class="px-1 pb-1 text-right font-semibold sm:px-1.5 sm:pb-2 md:px-2">Horário</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    @foreach ($recentCalls as $call)
                                        <tr wire:key="tv-call-{{ $call['id'] }}" class="border-t border-border/80">
                                            <td class="px-1 py-1 align-middle sm:px-1.5 sm:py-2 md:px-2 md:py-2.5">
                                                <span class="tv-history-code font-bold text-primary">
                                                    {{ $call['display_code'] }}
                                                    @if (($call['call_type'] ?? '') === 'recall')
                                                        <span class="ml-0.5 text-[0.7em] font-semibold text-accent" title="Rechamada">↻</span>
                                                    @endif
                                                </span>
                                            </td>
                                            <td class="px-1 py-1 align-middle sm:px-1.5 md:px-2">
                                                <span class="inline-flex min-w-6 items-center justify-center rounded-md bg-primary/10 px-1 py-0.5 text-[0.65rem] font-bold text-primary sm:min-w-8 sm:px-1.5 sm:text-xs md:min-w-9 md:text-sm">
                                                    {{ $call['ticket_type_prefix'] ?? '—' }}
                                                </span>
                                            </td>
                                            <td class="truncate px-1 py-1 align-middle text-[0.7rem] font-semibold text-text sm:px-1.5 sm:text-sm md:px-2 md:text-base lg:text-lg">
                                                {{ $call['desk_name'] }}
                                            </td>
                                            <td class="px-1 py-1 text-right align-middle text-[0.7rem] font-medium tabular-nums text-text-muted sm:px-1.5 sm:text-sm md:px-2 md:text-base">
                                                {{ $call['called_at'] }}
                                            </td>
                                        </tr>
                                    @endforeach
                                </tbody>
                            </table>
                        @endif
                    </div>
                </section>
            </aside>
        </main>

        {{-- FOOTER: clinic accent/primary via classic linear-gradient (Smart TV safe). --}}
        @if ($footerEnabled)
        <footer class="tv-footer flex shrink-0 items-stretch text-white" style="{{ $footerBandCss }}">
            <div class="mx-auto grid h-full w-full max-w-[100rem] grid-cols-3 divide-x divide-white/20">
                <div class="flex items-center justify-center gap-1.5 px-1 text-center sm:gap-3 sm:px-3 lg:px-4">
                    <span class="hidden text-xl md:inline lg:text-2xl" aria-hidden="true">👁</span>
                    <div class="min-w-0">
                        <p class="truncate text-[0.55rem] font-bold uppercase tracking-wide sm:text-xs md:text-sm lg:text-base">{{ $footer1['title'] ?? '' }}</p>
                        <p class="mt-0.5 hidden text-[0.65rem] text-white/85 sm:block md:text-xs">{{ $footer1['text'] ?? '' }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-center gap-1.5 px-1 text-center sm:gap-3 sm:px-3 lg:px-4">
                    <span class="hidden text-xl md:inline lg:text-2xl" aria-hidden="true">➡</span>
                    <div class="min-w-0">
                        <p class="truncate text-[0.55rem] font-bold uppercase tracking-wide sm:text-xs md:text-sm lg:text-base">{{ $footer2['title'] ?? '' }}</p>
                        <p class="mt-0.5 hidden text-[0.65rem] text-white/85 sm:block md:text-xs">{{ $footer2['text'] ?? '' }}</p>
                    </div>
                </div>
                <div class="flex items-center justify-center gap-1.5 px-1 text-center sm:gap-3 sm:px-3 lg:px-4">
                    <span class="hidden text-xl md:inline lg:text-2xl" aria-hidden="true">🤝</span>
                    <div class="min-w-0">
                        <p class="truncate text-[0.55rem] font-bold uppercase tracking-wide sm:text-xs md:text-sm lg:text-base">{{ $footer3['title'] ?? '' }}</p>
                        <p class="mt-0.5 hidden text-[0.65rem] text-white/85 sm:block md:text-xs">{{ $footer3['text'] ?? '' }}</p>
                    </div>
                </div>
            </div>
        </footer>
        @endif
    @endif
</div>

<script>
    (function () {
        if (window.__humanaTvCallAudio) {
            return;
        }

        const storageKey = (token) => 'tvCallAudioUnlocked:' + token;
        const DEFAULT_EFFECT_URL = '/sond/efeitoSonoroNovo.mp3';
        // Watchdogs: a media element that does not fire ended/error must not stall the call queue.
        const LOAD_TIMEOUT_MS = 10000;
        const MAX_CLIP_MS = 30000;
        const MAX_CALL_MS = 60000;
        const MAX_QUEUE = 20;
        const PLAYED_HISTORY = 100;

        window.__humanaTvCallAudio = {
            panels: Object.create(null),
            debug: false,

            /**
             * Central Smart TV detection. Keep aligned with App\Support\SmartTvBrowser.
             * Does not treat mobile SamsungBrowser alone as a Smart TV.
             */
            isSmartTvBrowser(userAgent) {
                const ua = String(userAgent || (typeof navigator !== 'undefined' ? navigator.userAgent : '') || '');
                if (!ua) {
                    return false;
                }
                if (/\bTizen\b/i.test(ua)) {
                    return true;
                }
                if (/SMART[\s_-]?TV/i.test(ua)) {
                    return true;
                }
                if (/\bSmartTV\b/i.test(ua)) {
                    return true;
                }
                if (/\bHbbTV\b/i.test(ua)) {
                    return true;
                }
                if (/\bWeb0S\b/i.test(ua) || (/\bwebOS\b/i.test(ua) && /\bTV\b/i.test(ua))) {
                    return true;
                }
                if (/SamsungBrowser/i.test(ua)) {
                    return /\b(TV|Tizen|SMART[\s_-]?TV|SmartTV)\b/i.test(ua);
                }
                return false;
            },

            log(message, detail) {
                try {
                    if (window.__humanaTvDiag && typeof window.__humanaTvDiag.push === 'function') {
                        const extra = detail !== undefined
                            ? (' ' + (typeof detail === 'string' ? detail : JSON.stringify(detail)))
                            : '';
                        window.__humanaTvDiag.push(String(message) + extra.slice(0, 120));
                    }
                    if (this.debug) {
                        if (detail !== undefined) {
                            console.info('[TV Audio]', message, detail);
                        } else {
                            console.info('[TV Audio]', message);
                        }
                    }
                } catch (e) {}
            },

            warn(message, detail) {
                try {
                    if (window.__humanaTvDiag && typeof window.__humanaTvDiag.push === 'function') {
                        const extra = detail !== undefined
                            ? (' ' + (typeof detail === 'string' ? detail : JSON.stringify(detail)))
                            : '';
                        window.__humanaTvDiag.push('WARN ' + String(message) + extra.slice(0, 100));
                    }
                    if (detail !== undefined) {
                        console.warn('[TV Audio]', message, detail);
                    } else {
                        console.warn('[TV Audio]', message);
                    }
                } catch (e) {}
            },

            markPhase(panel, phase, detail) {
                panel.audioPhase = phase;
                panel.timings = panel.timings || {};
                panel.timings[phase] = Date.now();
                const origin = panel.timings.CALL_RECEIVED || panel.timings[phase];
                const delta = Date.now() - origin;
                this.log(phase, detail !== undefined ? detail : ('+' + delta + 'ms'));
            },

            ensure(token, options = {}) {
                if (!token) {
                    return this.panels[token];
                }

                const hintSmartTv = options.isSmartTv === true
                    || this.isSmartTvBrowser(options.userAgent);
                const effectUrl = typeof options.effectUrl === 'string' && options.effectUrl !== ''
                    ? options.effectUrl
                    : DEFAULT_EFFECT_URL;

                if (options.audioDebug === true) {
                    this.debug = true;
                }

                if (!this.panels[token]) {
                    this.panels[token] = {
                        token: token,
                        soundEnabled: false,
                        speaking: false,
                        lastPlayedCallId: null,
                        playingCallId: null,
                        playedIds: Object.create(null),
                        playedOrder: [],
                        queue: [],
                        _draining: false,
                        _itemSeq: 0,
                        _itemTimer: null,
                        _audioSession: false,
                        audioCtx: null,
                        clinicChimeEnabled: options.chimeEnabled !== false,
                        clinicSpeechEnabled: options.speechEnabled !== false,
                        chimeVolume: Math.max(0, Math.min(100, Number(options.chimeVolume ?? 70))),
                        isSmartTv: hintSmartTv || this.isSmartTvBrowser(),
                        effectUrl: effectUrl,
                        effectAudio: null,
                        effectUnlocked: false,
                        effectBuffer: null,
                        effectDecodeFailed: false,
                        lastEffectPath: null,
                        lastTtsPath: null,
                        lastTtsError: null,
                        audioPhase: 'IDLE',
                        effectSource: null,
                        ttsSource: null,
                        timings: Object.create(null),
                        ttsBlobUrl: null,
                        lastPlayError: null,
                    };

                    try {
                        this.panels[token].soundEnabled = sessionStorage.getItem(storageKey(token)) === '1';
                    } catch (e) {
                        this.panels[token].soundEnabled = false;
                    }

                    this.log('smartTv=' + String(this.panels[token].isSmartTv));
                    this.ensureSmartTvEffect(this.panels[token]);
                } else {
                    this.panels[token].clinicChimeEnabled = options.chimeEnabled !== false;
                    this.panels[token].clinicSpeechEnabled = options.speechEnabled !== false;
                    this.panels[token].chimeVolume = Math.max(0, Math.min(100, Number(options.chimeVolume ?? 70)));
                    this.panels[token].isSmartTv = hintSmartTv || this.panels[token].isSmartTv || this.isSmartTvBrowser();
                    this.panels[token].effectUrl = effectUrl;
                    this.ensureSmartTvEffect(this.panels[token]);
                }

                return this.panels[token];
            },

            isEnabled(token) {
                return Boolean(this.panels[token]?.soundEnabled);
            },

            ensureSmartTvEffect(panel) {
                if (panel.effectAudio && panel.effectAudio.isConnected !== false) {
                    return panel.effectAudio;
                }

                const url = panel.effectUrl || DEFAULT_EFFECT_URL;
                let audio = null;
                try {
                    audio = document.querySelector('audio[data-tv-call-effect]');
                } catch (e) {
                    audio = null;
                }

                if (!audio) {
                    audio = document.createElement('audio');
                    audio.setAttribute('data-tv-call-effect', '1');
                    audio.preload = 'auto';
                    audio.playsInline = true;
                    audio.setAttribute('playsinline', '');
                    try {
                        document.body.appendChild(audio);
                    } catch (e) {}
                }

                try {
                    // Prefer relative path so host/IP mismatches do not break the TV.
                    // Do NOT call load() again when src is already set — Samsung may revoke
                    // the unmuted unlock granted on the "Ativar som" gesture.
                    const currentSrc = audio.getAttribute('src') || '';
                    if (currentSrc !== url) {
                        audio.setAttribute('src', url);
                        audio.src = url;
                        audio.preload = 'auto';
                        audio.playsInline = true;
                        audio.load();
                    } else {
                        audio.preload = 'auto';
                        audio.playsInline = true;
                    }
                } catch (e) {
                    this.warn('effect load failed', e && e.message ? e.message : e);
                }

                panel.effectAudio = audio;
                return audio;
            },

            /**
             * Must play UNMUTED inside the user gesture. Muted unlock does not authorize
             * later unmuted play() on many Samsung/Tizen browsers.
             */
            async decodeEffectBuffer(panel) {
                if (panel.effectBuffer || panel.effectDecodeFailed) {
                    return panel.effectBuffer;
                }

                const url = panel.effectUrl || DEFAULT_EFFECT_URL;
                try {
                    const ctx = await this.ensureAudioContext(panel);
                    if (! ctx) {
                        panel.effectDecodeFailed = true;

                        return null;
                    }
                    const response = await fetch(url, { credentials: 'same-origin', cache: 'force-cache' });
                    if (! response.ok) {
                        throw new Error('effect-http-' + response.status);
                    }
                    const raw = await response.arrayBuffer();
                    panel.effectBuffer = await ctx.decodeAudioData(raw.slice(0));
                    this.log('EFFECT_DECODED', { bytes: raw.byteLength, duration: panel.effectBuffer.duration });

                    return panel.effectBuffer;
                } catch (e) {
                    panel.effectDecodeFailed = true;
                    this.warn('EFFECT_DECODE_FAILED', e && e.message ? e.message : e);

                    return null;
                }
            },

            stopBufferSources(panel) {
                ['effectSource', 'ttsSource'].forEach((key) => {
                    const source = panel[key];
                    if (! source) {
                        return;
                    }
                    try {
                        source.onended = null;
                        source.stop(0);
                    } catch (e) {}
                    panel[key] = null;
                });
            },

            /**
             * Play a decoded AudioBuffer (efeitoSonoroNovo or Google TTS WAV/MP3).
             */
            playDecodedBuffer(panel, buffer, kind, onEnd) {
                const safeEnd = () => {
                    if (typeof onEnd === 'function') {
                        onEnd();
                    }
                };

                if (! buffer) {
                    safeEnd();

                    return;
                }

                this.stopBufferSources(panel);
                this.ensureAudioContext(panel).then((ctx) => {
                    if (! ctx) {
                        safeEnd();

                        return;
                    }
                    try {
                        if (ctx.state === 'suspended') {
                            ctx.resume().catch(() => {});
                        }
                        const source = ctx.createBufferSource();
                        const gain = ctx.createGain();
                        const peak = Math.max(0.05, Math.min(1, (panel.chimeVolume / 100)));
                        source.buffer = buffer;
                        gain.gain.value = peak;
                        source.connect(gain);
                        gain.connect(ctx.destination);
                        panel.speaking = true;
                        if (kind === 'effect') {
                            panel.effectSource = source;
                            panel.lastEffectPath = 'webaudio-buffer';
                            panel.audioPhase = 'effect';
                            this.log('EFFECT_PLAY_RESOLVED', 'webaudio-buffer');
                        } else {
                            panel.ttsSource = source;
                            panel.lastTtsPath = 'webaudio-buffer';
                            panel.lastTtsError = null;
                            panel.audioPhase = 'tts';
                            this.log('TTS_PLAYING', { path: 'webaudio-buffer', duration: buffer.duration || null });
                        }
                        let ended = false;
                        const finish = (reason) => {
                            if (ended) {
                                return;
                            }
                            ended = true;
                            panel.speaking = false;
                            if (kind === 'effect') {
                                panel.effectSource = null;
                                this.log('EFFECT_ENDED', reason || 'buffer');
                            } else {
                                panel.ttsSource = null;
                                panel.audioPhase = 'idle';
                                this.log('TTS_ENDED', reason || 'buffer');
                            }
                            safeEnd();
                        };
                        source.onended = () => finish('buffer-ended');
                        source.start(0);
                        const ms = Math.min(MAX_CLIP_MS, Math.max(500, (buffer.duration || 2) * 1000 + 500));
                        setTimeout(() => finish('buffer-timeout'), ms);
                    } catch (e) {
                        const msg = e && e.message ? e.message : String(e || 'buffer-play');
                        if (kind === 'tts') {
                            panel.lastTtsError = msg;
                        }
                        this.warn('AUDIO_ERROR', msg);
                        safeEnd();
                    }
                }).catch(() => safeEnd());
            },

            /**
             * Play the NEW efeitoSonoroNovo via WebAudio (decoded buffer).
             * This is NOT the old oscillator beep — same MP3 file, Samsung-safe path.
             */
            playEffectBuffer(panel, onEnd) {
                this.playDecodedBuffer(panel, panel.effectBuffer, 'effect', onEnd);
            },

            async decodeUrlToBuffer(panel, url) {
                const ctx = await this.ensureAudioContext(panel);
                if (! ctx || ! url) {
                    return null;
                }
                const response = await fetch(url, { credentials: 'same-origin', cache: 'no-store' });
                if (! response.ok) {
                    throw new Error('tts-http-' + response.status);
                }
                const raw = await response.arrayBuffer();
                if (raw.byteLength < 64) {
                    throw new Error('tts-empty-body');
                }

                return await ctx.decodeAudioData(raw.slice(0));
            },

            async unlockSmartTvEffect(panel) {
                const audio = this.ensureSmartTvEffect(panel);
                audio.muted = false;
                audio.volume = 1;
                const smartTv = panel.isSmartTv || this.isSmartTvBrowser();

                // Smart TV: keep the HTMLAudio unlock path clean. Opening AudioContext
                // during unlock steals the Tizen media pipeline and silences the ding.
                // Desktop may still decode for WebAudio fallback.
                if (! smartTv) {
                    await this.decodeEffectBuffer(panel);
                }

                try {
                    if (audio.readyState < 2) {
                        await new Promise((resolve) => {
                            let settled = false;
                            const finish = () => {
                                if (settled) {
                                    return;
                                }
                                settled = true;
                                audio.removeEventListener('canplay', finish);
                                audio.removeEventListener('loadeddata', finish);
                                resolve();
                            };
                            audio.addEventListener('canplay', finish);
                            audio.addEventListener('loadeddata', finish);
                            setTimeout(finish, 2500);
                            try {
                                audio.load();
                            } catch (e) {}
                        });
                    }
                } catch (e) {}

                try {
                    audio.pause();
                    try {
                        audio.currentTime = 0;
                    } catch (e) {}
                    const playPromise = audio.play();
                    if (playPromise && typeof playPromise.then === 'function') {
                        await playPromise;
                    }
                    panel.effectUnlocked = true;
                    panel.lastEffectPath = 'html-audio';
                    this.log('effect unlocked (unmuted play on gesture)');
                    // Let a short burst confirm sound, then stop — full play happens on TicketCall.
                    await new Promise((resolve) => setTimeout(resolve, 450));
                    try {
                        audio.pause();
                        audio.currentTime = 0;
                    } catch (e) {}
                    if (! smartTv) {
                        await this.ensureAudioContext(panel);
                    } else {
                        // Unlock the SPEECH element separately so Google TTS never reuses
                        // the effect element's src (that broke recalls with "old" audio).
                        try {
                            const speech = this.ensureSpeechAudio(panel);
                            speech.muted = false;
                            speech.volume = 1;
                            const ding = panel.effectUrl || DEFAULT_EFFECT_URL;
                            if ((speech.getAttribute('src') || '') !== ding) {
                                speech.setAttribute('src', ding);
                                speech.src = ding;
                            }
                            const sp = speech.play();
                            if (sp && typeof sp.then === 'function') {
                                await sp;
                            }
                            speech.pause();
                            try {
                                speech.currentTime = 0;
                            } catch (err) {}
                            // Leave speech unlocked; TTS overwrites src with a same-origin blob.
                            this.log('speech element unlocked for TTS');
                        } catch (err) {
                            this.warn('speech unlock skipped', err && err.message ? err.message : err);
                        }
                    }
                } catch (e) {
                    if (! smartTv && panel.effectBuffer) {
                        panel.effectUnlocked = true;
                        panel.lastEffectPath = 'webaudio-buffer';
                        this.warn('html-audio unlock failed — buffer ready', e && e.message ? e.message : e);
                        this.playEffectBuffer(panel, () => {});

                        return;
                    }
                    panel.effectUnlocked = false;
                    this.warn('effect unlock failed', e && e.message ? e.message : e);
                }
            },

            async enable(token, options = {}) {
                const panel = this.ensure(token, options);
                panel.soundEnabled = true;

                try {
                    sessionStorage.setItem(storageKey(token), '1');
                } catch (e) {}

                if (panel.isSmartTv || this.isSmartTvBrowser()) {
                    panel.isSmartTv = true;
                }

                // Always unlock the HTMLAudioElement used for efeitoSonoroNovo.mp3.
                // Desktop previously unlocked only AudioContext/WebAudio chime, so later
                // playSmartTvEffect() was rejected (NotAllowedError) and the ding never played.
                await this.unlockSmartTvEffect(panel);

                if (! panel.effectUnlocked) {
                    await this.ensureAudioContext(panel);
                    if (panel.clinicChimeEnabled) {
                        await this.playChime(panel);
                    }
                }

                this.log('SOUND_UNLOCK_RESULT', {
                    unlocked: !! panel.effectUnlocked,
                    path: panel.lastEffectPath || null,
                    readyState: panel.effectAudio ? panel.effectAudio.readyState : null,
                    src: panel.effectAudio ? String(panel.effectAudio.getAttribute('src') || '').slice(-48) : null,
                });

                return true;
            },

            /**
             * Diagnostic probes (user-gesture only). Do not create TicketCall rows.
             * Isolates autoplay unlock vs sequencer vs network TTS.
             */
            probeSnapshot(panel, label) {
                const audio = panel && panel.effectAudio ? panel.effectAudio : null;
                const snap = {
                    label: label,
                    soundEnabled: !!(panel && panel.soundEnabled),
                    unlocked: !!(panel && panel.effectUnlocked),
                    phase: panel ? panel.audioPhase : null,
                    smartTv: !!(panel && (panel.isSmartTv || this.isSmartTvBrowser())),
                    hasEl: !! audio,
                    rs: audio ? audio.readyState : null,
                    paused: audio ? audio.paused : null,
                    muted: audio ? audio.muted : null,
                    vol: audio ? audio.volume : null,
                    ct: audio ? Number(audio.currentTime || 0).toFixed(2) : null,
                    src: audio ? String(audio.getAttribute('src') || audio.currentSrc || '').slice(-56) : null,
                    err: panel ? (panel.lastPlayError || panel.lastTtsError || null) : null,
                };
                this.log('PROBE_SNAP', snap);

                return snap;
            },

            probeEffect(token) {
                const panel = this.ensure(token);
                this.probeSnapshot(panel, 'effect-before');
                this.log('PROBE_EFFECT_START', 'legacy-playSmartTvEffect');
                // Exact path that worked on Samsung in 85aa9e6 (not the v4 sequence wrapper).
                this.playSmartTvEffect(panel, () => {
                    this.probeSnapshot(panel, 'effect-after');
                    this.log('PROBE_EFFECT_DONE', panel.lastEffectPath || panel.lastPlayError || 'ok');
                });
            },

            probeTts(token, sampleUrl) {
                const panel = this.ensure(token);
                const url = typeof sampleUrl === 'string' && sampleUrl !== ''
                    ? sampleUrl
                    : null;
                if (! url) {
                    this.warn('PROBE_TTS_SKIP', 'missing-sample-url');

                    return;
                }
                this.probeSnapshot(panel, 'tts-before');
                this.log('PROBE_TTS_START', url.slice(0, 100));
                let audio;
                try {
                    audio = this.ensureSmartTvEffect(panel);
                } catch (e) {
                    this.warn('PROBE_TTS_FAIL', 'no-element');

                    return;
                }
                // Same HTMLAudio element as the call path — still inside the click gesture.
                this.playHtmlAudioClip(panel, audio, url, 'tts', (ok, reason) => {
                    this.probeSnapshot(panel, 'tts-after');
                    this.log('PROBE_TTS_DONE', { ok: ok, reason: reason || null });
                    this.restoreEffectSrc(panel);
                });
            },

            probeSequence(token, sampleUrl) {
                const panel = this.ensure(token);
                const ttsUrl = typeof sampleUrl === 'string' && sampleUrl !== '' ? sampleUrl : null;
                this.probeSnapshot(panel, 'seq-before');
                this.log('PROBE_SEQ_START', { effect: panel.effectUrl || DEFAULT_EFFECT_URL, tts: ttsUrl });
                this.playSmartTvSequence(panel, {
                    effectUrl: panel.effectUrl || DEFAULT_EFFECT_URL,
                    ttsUrl: ttsUrl,
                    isCurrentItem: () => true,
                }, () => {
                    this.probeSnapshot(panel, 'seq-after');
                    this.log('PROBE_SEQ_DONE', {
                        phase: panel.audioPhase,
                        effectPath: panel.lastEffectPath,
                        ttsPath: panel.lastTtsPath,
                        playErr: panel.lastPlayError,
                        ttsErr: panel.lastTtsError,
                    });
                });
            },

            async ensureAudioContext(panel) {
                try {
                    const AudioCtx = window.AudioContext || window.webkitAudioContext;
                    if (!AudioCtx) {
                        return null;
                    }
                    if (!panel.audioCtx || panel.audioCtx.state === 'closed') {
                        panel.audioCtx = new AudioCtx();
                    }
                    if (panel.audioCtx.state === 'suspended') {
                        await panel.audioCtx.resume();
                    }
                    return panel.audioCtx;
                } catch (e) {
                    return null;
                }
            },

            enqueue(items) {
                const list = Array.isArray(items) ? items : [items];
                list.forEach((item) => this.announce(item));
            },

            /**
             * Drop queued announcements for a ticket that left CALLED
             * (start service / complete / no-show). The clip already playing
             * finishes; other tickets in the queue stay.
             */
            invalidateTicket(token, ticketId) {
                const panel = this.panels[token];
                const id = ticketId != null ? Number(ticketId) : null;
                if (!panel || id === null || Number.isNaN(id) || id <= 0) {
                    return;
                }

                const before = (panel.queue || []).length;
                panel.queue = (panel.queue || []).filter((item) => {
                    const itemTicket = item && item.ticketId != null ? Number(item.ticketId) : null;
                    return itemTicket !== id;
                });
                const removed = before - panel.queue.length;
                if (removed > 0) {
                    this.log('queued announcements invalidated for ticket', { ticketId: id, removed: removed });
                }
            },

            announce(payload) {
                if (!payload || typeof payload !== 'object') {
                    return;
                }

                const token = payload.panelToken;
                const callId = payload.callId != null ? Number(payload.callId) : null;
                const panel = this.panels[token] || this.ensure(token);

                this.log('CALL_RECEIVED', {
                    callId: callId,
                    correlation: payload.correlation || null,
                    displayCode: payload.displayCode || '',
                });

                if (!panel || !panel.soundEnabled) {
                    this.warn('announce skipped — sound not enabled (press Ativar som)');
                    return;
                }

                panel.queue = panel.queue || [];

                // Per-instance dedupe only — other TVs keep their own cursor.
                // Recalls create a new TicketCall id, so a recall still plays.
                if (callId !== null) {
                    if (panel.playingCallId === callId || panel.playedIds[callId]) {
                        this.log('call ignored (already played)', callId);
                        return;
                    }
                    if (panel.queue.some((item) => Number(item.callId) === callId)) {
                        this.log('call ignored (already queued)', callId);
                        return;
                    }
                }

                if (panel.queue.length >= MAX_QUEUE) {
                    const dropped = panel.queue.shift();
                    this.warn('call queue full — dropping oldest', dropped ? dropped.callId : null);
                }

                panel.queue.push(payload);
                if (!panel._draining) {
                    this.playNext(token);
                }
            },

            rememberPlayed(panel, callId) {
                if (callId === null || panel.playedIds[callId]) {
                    return;
                }
                panel.playedIds[callId] = true;
                panel.playedOrder.push(callId);
                while (panel.playedOrder.length > PLAYED_HISTORY) {
                    delete panel.playedIds[panel.playedOrder.shift()];
                }
            },

            /**
             * Autoplay policy rejected unmuted playback: surface "Ativar som" again
             * instead of pretending sound is on.
             */
            markAudioBlocked(panel, err) {
                if (!err || err.name !== 'NotAllowedError' || !panel.soundEnabled) {
                    return;
                }
                this.warn('audio blocked by autoplay policy — operator must press "Ativar som"');
                panel.soundEnabled = false;
                panel.queue = [];
                try {
                    sessionStorage.removeItem(storageKey(panel.token));
                } catch (e) {}
                window.dispatchEvent(new CustomEvent('tv-call-audio-blocked', { detail: { panelToken: panel.token } }));
            },

            playNext(token) {
                const panel = this.panels[token];
                if (!panel) {
                    return;
                }

                if (panel._itemTimer) {
                    clearTimeout(panel._itemTimer);
                    panel._itemTimer = null;
                }

                const payload = panel.soundEnabled ? (panel.queue || []).shift() : null;
                if (!payload) {
                    panel.queue = panel.soundEnabled ? panel.queue : [];
                    panel._draining = false;
                    panel.speaking = false;
                    panel.playingCallId = null;
                    if (panel._audioSession) {
                        panel._audioSession = false;
                        this.log('call audio session end');
                        window.dispatchEvent(new CustomEvent('tv-call-audio-end', { detail: { panelToken: token } }));
                    }
                    return;
                }

                panel._draining = true;
                const callId = payload.callId != null ? Number(payload.callId) : null;
                panel.playingCallId = callId;
                if (callId !== null) {
                    panel.lastPlayedCallId = callId;
                    this.rememberPlayed(panel, callId);
                }

                if (!panel._audioSession) {
                    panel._audioSession = true;
                    this.log('call audio session begin', callId);
                    window.dispatchEvent(new CustomEvent('tv-call-audio-begin', { detail: { panelToken: token, callId } }));
                }

                // Late callbacks from a previous item (timeouts, rejected promises) must not
                // advance the queue twice or start a stale voice.
                panel._itemSeq += 1;
                const itemSeq = panel._itemSeq;
                const isCurrentItem = () => panel._itemSeq === itemSeq;
                this.log('call audio start', { callId: callId, displayCode: payload.displayCode || '' });

                const finish = () => {
                    if (!isCurrentItem()) {
                        return;
                    }
                    panel._itemSeq += 1;
                    panel.speaking = false;
                    this.log('call audio end', callId);
                    this.playNext(token);
                };

                panel._itemTimer = setTimeout(() => {
                    if (!isCurrentItem()) {
                        return;
                    }
                    this.warn('call audio watchdog — forcing next', callId);
                    this.stopBufferSources(panel);
                    this.stopSmartTvEffect(panel);
                    this.stopSpeechElement(panel);
                    finish();
                }, MAX_CALL_MS);

                let audioUrl = payload.audioUrl || payload.audio_url || null;
                const announcement = payload.announcement || '';

                // Reconstruct relative TTS URL when the feed omitted it (warm race).
                if (! audioUrl && callId && token) {
                    audioUrl = '/painel/' + encodeURIComponent(token) + '/tts/' + callId;
                }

                panel.timings = Object.create(null);
                this.markPhase(panel, 'CALL_RECEIVED', { callId: callId, source: payload.source || 'livewire' });

                // Smart TV: single HTMLAudio element sequences effect → Google TTS.
                // Mixing WebAudio during unlock/TTS silenced efeitoSonoroNovo on Tizen (7ee566a).
                if (panel.isSmartTv || this.isSmartTvBrowser()) {
                    panel.isSmartTv = true;
                    this.playSmartTvSequence(panel, {
                        effectUrl: panel.clinicChimeEnabled ? (panel.effectUrl || DEFAULT_EFFECT_URL) : null,
                        ttsUrl: (panel.clinicSpeechEnabled && panel.soundEnabled) ? audioUrl : null,
                        isCurrentItem: isCurrentItem,
                    }, finish);

                    return;
                }

                const playVoice = () => {
                    if (! isCurrentItem()) {
                        return;
                    }
                    if (! panel.clinicSpeechEnabled || ! panel.soundEnabled) {
                        this.warn('TTS_SKIPPED', 'speech disabled');
                        finish();

                        return;
                    }

                    if (! audioUrl) {
                        panel.lastTtsError = 'missing-audio-url';
                        this.warn('TTS_SKIPPED', 'missing-audio-url');
                        finish();

                        return;
                    }

                    this.markPhase(panel, 'TTS_LOADING');
                    this.stopBufferSources(panel);
                    this.stopSmartTvEffect(panel);

                    const onVoiceError = () => {
                        this.playGoogleTtsSmartTv(panel, audioUrl, finish, () => {
                            this.speak(panel, announcement, finish, null);
                        });
                    };
                    this.playServerAudio(panel, audioUrl, finish, onVoiceError);
                };

                if (panel.clinicChimeEnabled) {
                    this.markPhase(panel, 'EFFECT_LOADING', panel.effectUrl || DEFAULT_EFFECT_URL);
                    this.log('EFFECT_START_REQUESTED', panel.effectUrl || DEFAULT_EFFECT_URL);
                    this.playSmartTvEffect(panel, playVoice);

                    return;
                }

                playVoice();
            },

            /**
             * Samsung/Tizen announce sequence with SPLIT elements:
             * - data-tv-call-effect stays permanently on efeitoSonoroNovo.mp3 (every call/recall)
             * - data-tv-call-speech plays Google TTS (blob prefetch during ding)
             * Sharing one element for both caused recalls to replay residual TTS as "old effect".
             */
            playSmartTvSequence(panel, options, onEnd) {
                const done = () => {
                    this.revokeTtsBlob(panel);
                    this.markPhase(panel, 'COMPLETED');
                    if (typeof onEnd === 'function') {
                        onEnd();
                    }
                };
                const isCurrent = typeof options.isCurrentItem === 'function'
                    ? options.isCurrentItem
                    : () => true;

                if (! isCurrent()) {
                    return;
                }

                const effectUrl = options.effectUrl || null;
                const ttsUrl = options.ttsUrl || null;
                let ttsPrefetch = null;

                if (ttsUrl) {
                    this.markPhase(panel, 'TTS_PREFETCH', ttsUrl);
                    ttsPrefetch = this.fetchTtsBlob(ttsUrl).then((blob) => {
                        this.revokeTtsBlob(panel);
                        panel.ttsBlobUrl = URL.createObjectURL(blob);
                        this.markPhase(panel, 'TTS_READY', { bytes: blob.size });

                        return panel.ttsBlobUrl;
                    }).catch((err) => {
                        panel.lastTtsError = err && err.message ? err.message : 'tts-prefetch';
                        this.warn('TTS_PREFETCH_FAILED', panel.lastTtsError);

                        return null;
                    });
                }

                const afterEffect = () => {
                    if (! isCurrent()) {
                        return;
                    }
                    if (! ttsUrl) {
                        this.warn('TTS_SKIPPED', 'no-url-or-disabled');
                        done();

                        return;
                    }

                    const playTts = (src) => {
                        if (! isCurrent()) {
                            return;
                        }
                        if (! src) {
                            panel.lastTtsError = panel.lastTtsError || 'tts-unavailable';
                            this.markPhase(panel, 'ERROR_TTS', panel.lastTtsError);
                            done();

                            return;
                        }
                        let speech;
                        try {
                            speech = this.ensureSpeechAudio(panel);
                        } catch (e) {
                            panel.lastTtsError = 'speech-element';
                            this.playGoogleTtsSmartTv(panel, ttsUrl, done, done);

                            return;
                        }
                        this.markPhase(panel, 'TTS_LOADING', String(src).slice(0, 80));
                        this.log('TTS_START_REQUESTED', String(src).slice(0, 120));
                        // Speech element only — never change the effect element's src.
                        this.playHtmlAudioClip(panel, speech, src, 'tts', (ok, reason) => {
                            if (! isCurrent()) {
                                return;
                            }
                            if (! ok) {
                                panel.lastTtsError = reason || 'tts-play-failed';
                                this.warn('TTS_HTML_FAILED', panel.lastTtsError);
                                this.playGoogleTtsSmartTv(panel, ttsUrl, done, done);

                                return;
                            }
                            done();
                        });
                    };

                    Promise.resolve(ttsPrefetch).then((blobUrl) => {
                        playTts(blobUrl || ttsUrl);
                    }).catch(() => playTts(ttsUrl));
                };

                if (! effectUrl) {
                    afterEffect();

                    return;
                }

                // Path that worked for the ding on Samsung (85aa9e6): dedicated effect element.
                this.markPhase(panel, 'EFFECT_LOADING', effectUrl);
                this.log('EFFECT_START_REQUESTED', effectUrl);
                this.ensureEffectSrcPinned(panel);
                this.playSmartTvEffect(panel, () => {
                    if (! isCurrent()) {
                        return;
                    }
                    afterEffect();
                });
            },

            revokeTtsBlob(panel) {
                if (panel.ttsBlobUrl) {
                    try {
                        URL.revokeObjectURL(panel.ttsBlobUrl);
                    } catch (e) {}
                    panel.ttsBlobUrl = null;
                }
            },

            /**
             * Keep data-tv-call-effect permanently on efeitoSonoroNovo — never leave TTS/blob there.
             */
            ensureEffectSrcPinned(panel) {
                const audio = this.ensureSmartTvEffect(panel);
                const url = panel.effectUrl || DEFAULT_EFFECT_URL;
                if (! audio || ! url) {
                    return audio;
                }
                try {
                    const attr = audio.getAttribute('src') || '';
                    const looksLikeEffect = attr.indexOf('efeitoSonoroNovo') !== -1
                        && attr.indexOf('/tts/') === -1
                        && attr.indexOf('blob:') !== 0;
                    if (! looksLikeEffect || (attr !== url && attr.split('?')[0] !== url.split('?')[0])) {
                        audio.setAttribute('src', url);
                        audio.src = url;
                    }
                } catch (e) {}

                return audio;
            },

            restoreEffectSrc(panel) {
                this.ensureEffectSrcPinned(panel);
            },

            /**
             * Wait for Google TTS cache (warm via defer) with short backoff — not multi-second sleeps.
             */
            async fetchTtsBlob(url) {
                const waits = [0, 200, 400, 700, 1100];
                let lastErr = 'tts-unknown';
                for (let i = 0; i < waits.length; i++) {
                    if (waits[i] > 0) {
                        await new Promise((resolve) => setTimeout(resolve, waits[i]));
                    }
                    try {
                        const response = await fetch(url, {
                            credentials: 'same-origin',
                            cache: 'no-store',
                            headers: { 'Accept': 'audio/*,*/*' },
                        });
                        if (response.status === 503 || response.status === 429 || response.status === 404) {
                            lastErr = 'tts-http-' + response.status;
                            continue;
                        }
                        if (! response.ok) {
                            lastErr = 'tts-http-' + response.status;
                            continue;
                        }
                        const blob = await response.blob();
                        if (blob.size < 64) {
                            lastErr = 'tts-empty';
                            continue;
                        }

                        return blob;
                    } catch (e) {
                        lastErr = e && e.message ? e.message : 'tts-fetch';
                    }
                }
                throw new Error(lastErr);
            },

            /**
             * Play one clip on a dedicated HTMLAudioElement. Confirms playing before success.
             * Does not mark effect/TTS as played on play() alone.
             */
            playHtmlAudioClip(panel, audio, src, kind, onEnd) {
                const safeEnd = (ok, reason) => {
                    if (typeof onEnd === 'function') {
                        onEnd(ok, reason);
                    }
                };

                if (! audio || ! src) {
                    safeEnd(false, 'missing-src');

                    return;
                }

                panel.speaking = true;
                panel.lastPlayError = null;
                this.markPhase(panel, kind === 'effect' ? 'EFFECT_LOADING' : 'TTS_LOADING');

                let settled = false;
                let started = false;
                let clipTimer = null;
                const clearTimer = () => {
                    if (clipTimer) {
                        clearTimeout(clipTimer);
                        clipTimer = null;
                    }
                };
                const finish = (ok, reason) => {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    clearTimer();
                    try {
                        audio.onended = null;
                        audio.onerror = null;
                        audio.onplaying = null;
                    } catch (e) {}
                    panel.speaking = false;
                    if (ok) {
                        if (kind === 'effect') {
                            panel.lastEffectPath = 'html-audio';
                            this.markPhase(panel, 'EFFECT_ENDED', reason || 'ended');
                        } else {
                            panel.lastTtsPath = 'html-audio';
                            panel.lastTtsError = null;
                            this.markPhase(panel, 'TTS_ENDED', reason || 'ended');
                        }
                        safeEnd(true, reason);

                        return;
                    }
                    if (kind === 'tts') {
                        panel.lastTtsError = reason || 'failed';
                    } else {
                        panel.lastPlayError = reason || 'failed';
                    }
                    this.warn(kind === 'effect' ? 'EFFECT_FAILED' : 'TTS_FAILED', reason || 'failed');
                    try {
                        audio.pause();
                    } catch (e) {}
                    safeEnd(false, reason);
                };

                const armWatchdog = () => {
                    clearTimer();
                    const duration = Number(audio.duration);
                    const limit = isFinite(duration) && duration > 0
                        ? Math.min(MAX_CLIP_MS, duration * 1000 + 1500)
                        : (kind === 'effect' ? 8000 : LOAD_TIMEOUT_MS);
                    clipTimer = setTimeout(() => {
                        if (started && ! audio.paused && ! audio.ended) {
                            // Still playing — extend once based on remaining time.
                            const remain = (Number(audio.duration) - Number(audio.currentTime)) * 1000;
                            if (isFinite(remain) && remain > 500) {
                                clipTimer = setTimeout(() => finish(true, 'timeout-complete'), Math.min(MAX_CLIP_MS, remain + 500));

                                return;
                            }
                        }
                        finish(started, started ? 'timeout-started' : 'timeout-no-start');
                    }, limit);
                };

                const startPlay = () => {
                    if (started || settled) {
                        return;
                    }
                    started = true;
                    try {
                        audio.onended = () => finish(true, 'ended');
                        audio.onerror = () => finish(false, 'media-error');
                        audio.onplaying = () => {
                            if (kind === 'effect') {
                                this.markPhase(panel, 'EFFECT_PLAYING');
                            } else {
                                this.markPhase(panel, 'TTS_PLAYING');
                            }
                            armWatchdog();
                        };
                        try {
                            if (! audio.paused) {
                                audio.pause();
                            }
                        } catch (e) {}
                        try {
                            if (audio.readyState >= 1 && ! String(src).startsWith('blob:')) {
                                audio.currentTime = 0;
                            }
                        } catch (e) {}
                        this.log(kind === 'effect' ? 'EFFECT_PLAY_INVOKED' : 'TTS_PLAY_INVOKED');
                        const playPromise = audio.play();
                        if (playPromise && typeof playPromise.then === 'function') {
                            playPromise.then(() => {
                                panel.effectUnlocked = true;
                                armWatchdog();
                            }).catch((err) => {
                                const msg = err && err.name ? (err.name + ':' + (err.message || '')) : String(err || 'play-reject');
                                panel.lastPlayError = msg;
                                this.warn('AUDIO_PLAY_REJECTED', msg);
                                finish(false, msg);
                            });
                        } else {
                            armWatchdog();
                        }
                    } catch (e) {
                        finish(false, e && e.message ? e.message : 'play-throw');
                    }
                };

                try {
                    const attr = audio.getAttribute('src') || '';
                    const srcStr = String(src || '');
                    const isEffectFile = (u) => typeof u === 'string'
                        && u.indexOf('efeitoSonoroNovo') !== -1
                        && u.indexOf('/tts/') === -1
                        && u.indexOf('blob:') !== 0;
                    // Only skip src rewrite for effect when BOTH sides are the ding MP3.
                    // Never treat residual TTS/blob currentSrc as "same effect" (recall bug).
                    const sameEffect = kind === 'effect'
                        && isEffectFile(attr)
                        && isEffectFile(srcStr)
                        && (attr === srcStr || attr.split('?')[0] === srcStr.split('?')[0]);
                    if (! sameEffect) {
                        audio.setAttribute('src', srcStr);
                        audio.src = srcStr;
                    }
                } catch (e) {
                    finish(false, 'src-set-failed');

                    return;
                }

                // Do not call load() — it voids the Samsung unmuted unlock from "Ativar som".
                if (audio.readyState >= 2 && kind === 'effect') {
                    startPlay();

                    return;
                }

                const onReady = () => {
                    audio.removeEventListener('canplay', onReady);
                    audio.removeEventListener('loadeddata', onReady);
                    startPlay();
                };
                audio.addEventListener('canplay', onReady);
                audio.addEventListener('loadeddata', onReady);
                setTimeout(() => {
                    if (! settled && ! started) {
                        startPlay();
                    }
                }, kind === 'effect' ? 1200 : 2000);
            },

            /**
             * Google Cloud TTS for Smart TV: fetch the panel TTS URL, decode, play via WebAudio.
             * Retries briefly while the warm job finishes writing the cache.
             */
            playGoogleTtsSmartTv(panel, url, onEnd, onFatal) {
                const done = () => {
                    panel.audioPhase = 'idle';
                    if (typeof onEnd === 'function') {
                        onEnd();
                    }
                };
                const fatal = () => {
                    panel.audioPhase = 'idle';
                    if (typeof onFatal === 'function') {
                        onFatal();

                        return;
                    }
                    done();
                };

                this.log('TTS_START_REQUESTED', url);
                panel.lastTtsError = null;
                this.markPhase(panel, 'TTS_LOADING', 'webaudio-fallback');

                let attempt = 0;
                const delays = [0, 250, 500, 900];
                const tryOnce = () => {
                    attempt += 1;
                    this.decodeUrlToBuffer(panel, url).then((buffer) => {
                        if (! buffer) {
                            throw new Error('tts-decode-empty');
                        }
                        this.playDecodedBuffer(panel, buffer, 'tts', done);
                    }).catch((err) => {
                        const msg = err && err.message ? err.message : String(err || 'tts-fail');
                        panel.lastTtsError = msg;
                        this.warn('TTS_RETRY', { attempt: attempt, error: msg });
                        if (attempt < delays.length) {
                            setTimeout(tryOnce, delays[attempt] || 900);

                            return;
                        }
                        this.warn('AUDIO_ERROR', 'tts-exhausted: ' + msg);
                        this.markPhase(panel, 'ERROR_TTS', msg);
                        fatal();
                    });
                };
                tryOnce();
            },

            playSmartTvEffect(panel, onEnd) {
                const safeEnd = () => {
                    panel.speaking = false;
                    if (typeof onEnd === 'function') {
                        onEnd();
                    }
                };

                const fallbackNewEffect = () => {
                    // Prefer decoded efeitoSonoroNovo — never the old oscillator beep on Smart TV.
                    if (panel.effectBuffer) {
                        this.playEffectBuffer(panel, safeEnd);

                        return;
                    }
                    this.decodeEffectBuffer(panel).then((buf) => {
                        if (buf) {
                            this.playEffectBuffer(panel, safeEnd);

                            return;
                        }
                        this.warn('AUDIO_ERROR', 'effect mp3 unavailable — skipping ding, continue TTS');
                        safeEnd();
                    });
                };

                let audio;
                try {
                    audio = this.ensureSmartTvEffect(panel);
                } catch (e) {
                    this.warn('effect error', 'ensure');
                    fallbackNewEffect();
                    return;
                }

                panel.speaking = true;
                audio.muted = false;
                audio.volume = 1;

                let settled = false;
                let started = false;
                let clipTimer = null;
                const done = (reason) => {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    if (clipTimer) {
                        clearTimeout(clipTimer);
                        clipTimer = null;
                    }
                    try {
                        audio.onended = null;
                        audio.onerror = null;
                    } catch (e) {}
                    if (reason === 'timeout') {
                        this.warn('EFFECT_ENDED', 'timeout');
                        try {
                            audio.pause();
                        } catch (e) {}
                        safeEnd();
                        return;
                    }
                    if (reason === 'error') {
                        this.warn('AUDIO_ERROR', 'html-audio failed — trying decoded efeitoSonoroNovo');
                        fallbackNewEffect();
                        return;
                    }
                    panel.lastEffectPath = 'html-audio';
                    this.log('EFFECT_ENDED', reason || 'ended');
                    safeEnd();
                };

                const armClipWatchdog = () => {
                    if (clipTimer) {
                        clearTimeout(clipTimer);
                    }
                    const duration = Number(audio.duration);
                    const limit = isFinite(duration) && duration > 0
                        ? Math.min(MAX_CLIP_MS, duration * 1000 + 2000)
                        : MAX_CLIP_MS;
                    clipTimer = setTimeout(() => done('timeout'), limit);
                };

                const startPlay = () => {
                    if (started || settled) {
                        return;
                    }
                    started = true;
                    try {
                        audio.onended = () => done('ended');
                        audio.onerror = () => done('error');
                        armClipWatchdog();
                        try {
                            if (typeof audio.paused === 'boolean' && !audio.paused) {
                                audio.pause();
                            }
                        } catch (e) {}
                        try {
                            // Seek without load() — load() after unlock breaks Samsung autoplay grant.
                            if (audio.readyState >= 1) {
                                audio.currentTime = 0;
                            }
                        } catch (e) {}
                        this.log('EFFECT_PLAY_RESOLVED', 'play() invoked');
                        const playPromise = audio.play();
                        if (playPromise && typeof playPromise.then === 'function') {
                            playPromise.then(() => {
                                panel.effectUnlocked = true;
                                panel.lastEffectPath = 'html-audio';
                                this.log('EFFECT_PLAY_RESOLVED', 'playing');
                                armClipWatchdog();
                            }).catch((err) => {
                                this.warn('AUDIO_PLAY_REJECTED', err && err.message ? err.message : err);
                                // On Smart TV, try decoded NEW mp3 before declaring autoplay blocked.
                                if (panel.isSmartTv || this.isSmartTvBrowser() || panel.effectBuffer) {
                                    done('error');

                                    return;
                                }
                                this.markAudioBlocked(panel, err);
                                done('error');
                            });
                        }
                    } catch (e) {
                        this.warn('effect error', 'play');
                        done('error');
                    }
                };

                // Do not call audio.load() here — it resets the element and can void the
                // unmuted unlock from "Ativar som" on Tizen/Samsung browsers.
                if (audio.readyState >= 2) {
                    startPlay();
                    return;
                }

                const onReady = () => {
                    audio.removeEventListener('canplay', onReady);
                    audio.removeEventListener('loadeddata', onReady);
                    startPlay();
                };
                audio.addEventListener('canplay', onReady);
                audio.addEventListener('loadeddata', onReady);
                setTimeout(() => {
                    if (!settled) {
                        startPlay();
                    }
                }, 1500);
            },

            async playChime(panel) {
                try {
                    const ctx = await this.ensureAudioContext(panel);
                    if (!ctx) {
                        return;
                    }
                    const osc = ctx.createOscillator();
                    const gain = ctx.createGain();
                    const peak = Math.max(0.0001, (panel.chimeVolume / 100) * 0.2);
                    const t0 = ctx.currentTime;
                    osc.type = 'sine';
                    osc.frequency.setValueAtTime(880, t0);
                    osc.frequency.exponentialRampToValueAtTime(660, t0 + 0.18);
                    gain.gain.setValueAtTime(0.0001, t0);
                    gain.gain.exponentialRampToValueAtTime(peak, t0 + 0.02);
                    gain.gain.exponentialRampToValueAtTime(0.0001, t0 + 0.28);
                    osc.connect(gain);
                    gain.connect(ctx.destination);
                    osc.start(t0);
                    osc.stop(t0 + 0.3);
                    await new Promise((resolve) => setTimeout(resolve, 320));
                } catch (e) {
                    // Autoplay/policy failures are expected until the operator enables sound.
                }
            },

            stopSpeech(panel) {
                try {
                    if ('speechSynthesis' in window) {
                        window.speechSynthesis.cancel();
                    }
                } catch (e) {}
                this.stopFallbackAudio(panel);
                this.stopSpeechElement(panel);
                this.stopSmartTvEffect(panel);
                panel.speaking = false;
                panel._speechStarted = false;
                if (panel._speechProbeTimer) {
                    clearTimeout(panel._speechProbeTimer);
                    panel._speechProbeTimer = null;
                }
            },

            stopSmartTvEffect(panel) {
                if (panel.effectSource) {
                    try {
                        panel.effectSource.onended = null;
                        panel.effectSource.stop(0);
                    } catch (e) {}
                    panel.effectSource = null;
                }
                if (!panel.effectAudio) {
                    return;
                }
                try {
                    panel.effectAudio.onended = null;
                    panel.effectAudio.onerror = null;
                    panel.effectAudio.pause();
                    panel.effectAudio.currentTime = 0;
                } catch (e) {}
            },

            stopSpeechElement(panel) {
                if (!panel.speechAudio) {
                    return;
                }
                try {
                    panel.speechAudio.onended = null;
                    panel.speechAudio.onerror = null;
                    panel.speechAudio.pause();
                } catch (e) {}
            },

            ensureSpeechAudio(panel) {
                if (panel.speechAudio && panel.speechAudio.isConnected !== false) {
                    return panel.speechAudio;
                }

                let audio = null;
                try {
                    audio = document.querySelector('audio[data-tv-call-speech]');
                } catch (e) {
                    audio = null;
                }

                if (!audio) {
                    audio = document.createElement('audio');
                    audio.setAttribute('data-tv-call-speech', '1');
                    audio.preload = 'auto';
                    audio.playsInline = true;
                    audio.setAttribute('playsinline', '');
                    try {
                        document.body.appendChild(audio);
                    } catch (e) {}
                }

                panel.speechAudio = audio;
                return audio;
            },

            /**
             * Server MP3/WAV for the call. Separate element from the ding so the
             * Smart TV unlock on the effect player is left untouched.
             */
            playServerAudio(panel, url, onEnd, onError) {
                this.log('TTS_START_REQUESTED', url || null);
                panel.lastTtsError = null;
                panel.audioPhase = 'tts';

                const fail = () => {
                    if (typeof onError === 'function') {
                        onError();
                        return;
                    }
                    if (typeof onEnd === 'function') {
                        onEnd();
                    }
                };

                if (!url) {
                    panel.lastTtsError = 'missing-url';
                    fail();
                    return;
                }

                let audio;
                try {
                    audio = this.ensureSpeechAudio(panel);
                } catch (e) {
                    panel.lastTtsError = 'speech-element';
                    fail();
                    return;
                }

                // Only one call clip at a time.
                this.stopBufferSources(panel);
                this.stopSmartTvEffect(panel);

                panel.speaking = true;
                audio.muted = false;
                audio.volume = 1;

                let settled = false;
                let started = false;
                let timer = null;
                const clearTimer = () => {
                    if (timer) {
                        clearTimeout(timer);
                        timer = null;
                    }
                };
                const done = (ok, reason) => {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    clearTimer();
                    try {
                        audio.onended = null;
                        audio.onerror = null;
                        audio.onplaying = null;
                    } catch (e) {}
                    panel.speaking = false;
                    panel.audioPhase = 'idle';
                    if (ok) {
                        panel.lastTtsPath = 'html-audio';
                        this.log('TTS_ENDED');
                        if (typeof onEnd === 'function') {
                            onEnd();
                        }
                        return;
                    }
                    panel.lastTtsError = reason || 'voice-failed';
                    this.warn('AUDIO_ERROR', reason || 'voice-failed');
                    try {
                        audio.pause();
                    } catch (e) {}
                    fail();
                };

                audio.onended = () => done(true);
                audio.onerror = () => done(false, 'media-error');
                audio.onplaying = () => {
                    clearTimer();
                    const duration = Number(audio.duration);
                    const limit = isFinite(duration) && duration > 0
                        ? Math.min(MAX_CLIP_MS, duration * 1000 + 2000)
                        : MAX_CLIP_MS;
                    panel.lastTtsPath = 'html-audio';
                    this.log('TTS_PLAYING', { duration: isFinite(duration) ? duration : null });
                    timer = setTimeout(() => done(true), limit);
                };
                timer = setTimeout(() => done(false, 'load-timeout'), LOAD_TIMEOUT_MS);

                const startPlay = () => {
                    if (started || settled) {
                        return;
                    }
                    started = true;
                    try {
                        const playPromise = audio.play();
                        if (playPromise && typeof playPromise.catch === 'function') {
                            playPromise.catch((err) => {
                                // Prefer WebAudio Google TTS fallback over disabling sound.
                                done(false, err && err.name ? err.name : 'play-rejected');
                            });
                        }
                    } catch (e) {
                        done(false, 'play-throw');
                    }
                };

                try {
                    audio.pause();
                } catch (e) {}

                try {
                    audio.src = url;
                    if (audio.readyState >= 2) {
                        startPlay();
                    } else {
                        const onReady = () => {
                            audio.removeEventListener('canplay', onReady);
                            audio.removeEventListener('loadeddata', onReady);
                            startPlay();
                        };
                        audio.addEventListener('canplay', onReady);
                        audio.addEventListener('loadeddata', onReady);
                        setTimeout(() => {
                            if (! settled) {
                                startPlay();
                            }
                        }, 1500);
                    }
                } catch (e) {
                    done(false, 'play-throw');
                }
            },

            stopFallbackAudio(panel) {
                if (panel.fallbackAudio) {
                    try {
                        panel.fallbackAudio.onended = null;
                        panel.fallbackAudio.onerror = null;
                        panel.fallbackAudio.pause();
                        panel.fallbackAudio.removeAttribute('src');
                        panel.fallbackAudio.load();
                    } catch (e) {}
                    panel.fallbackAudio = null;
                }
            },

            /**
             * Desktop only: prefer speechSynthesis when it actually starts speaking.
             * Smart TV must not reach this path (announce routes to MP3 effect).
             * Desktop fallback WAV is kept for browsers where speechSynthesis is a stub.
             */
            speak(panel, text, onEnd, audioUrl) {
                const safeEnd = () => {
                    panel.speaking = false;
                    if (typeof onEnd === 'function') {
                        onEnd();
                    }
                };

                if (panel.isSmartTv || this.isSmartTvBrowser()) {
                    // Hard guard: Smart TV must not use speech or robotic TTS.
                    safeEnd();
                    return;
                }

                if (!text) {
                    safeEnd();
                    return;
                }

                const playFallback = () => {
                    if (!audioUrl) {
                        safeEnd();
                        return;
                    }
                    this.playAudioUrl(panel, audioUrl, safeEnd);
                };

                if (!('speechSynthesis' in window)) {
                    playFallback();
                    return;
                }

                try {
                    window.speechSynthesis.cancel();
                } catch (e) {}

                this.stopFallbackAudio(panel);

                let settled = false;
                panel.speaking = true;
                panel._speechStarted = false;

                const utterance = new SpeechSynthesisUtterance(text);
                utterance.lang = 'pt-BR';
                utterance.rate = 0.95;

                const settleNative = () => {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    if (panel._speechProbeTimer) {
                        clearTimeout(panel._speechProbeTimer);
                        panel._speechProbeTimer = null;
                    }
                    safeEnd();
                };

                const settleFallback = () => {
                    if (settled) {
                        return;
                    }
                    settled = true;
                    if (panel._speechProbeTimer) {
                        clearTimeout(panel._speechProbeTimer);
                        panel._speechProbeTimer = null;
                    }
                    try {
                        window.speechSynthesis.cancel();
                    } catch (e) {}
                    playFallback();
                };

                utterance.onstart = () => {
                    panel._speechStarted = true;
                    if (panel._speechProbeTimer) {
                        clearTimeout(panel._speechProbeTimer);
                        panel._speechProbeTimer = null;
                    }
                };

                utterance.onend = () => {
                    if (!panel._speechStarted) {
                        // Instant end without start → API is a stub.
                        settleFallback();
                        return;
                    }
                    settleNative();
                };

                utterance.onerror = () => {
                    settleFallback();
                };

                // If onstart does not fire, treat as non-functional TTS.
                panel._speechProbeTimer = setTimeout(() => {
                    if (!panel._speechStarted) {
                        settleFallback();
                    }
                }, 1200);

                try {
                    window.speechSynthesis.speak(utterance);
                } catch (e) {
                    settleFallback();
                }
            },

            playAudioUrl(panel, url, onEnd) {
                this.playServerAudio(panel, url, onEnd, onEnd);
            },
        };
    })();

    /**
     * Fast-watch singleton — survives Livewire morphs of the TvDisplay root.
     * Alpine remounts must NOT create duplicate timers (that caused media restarts).
     */
    (function () {
        if (window.__humanaTvWatch) {
            return;
        }

        window.__humanaTvWatch = {
            panels: Object.create(null),

            start(options = {}) {
                const token = options.panelToken || '';
                const url = typeof options.watchUrl === 'string' ? options.watchUrl : '';
                if (! token || ! url || options.fastWatchEnabled !== true) {
                    return;
                }

                const existing = this.panels[token];
                if (existing && existing.timer) {
                    existing.cursor = Math.max(existing.cursor || 0, Number(options.serverCursor) || 0);
                    existing.url = url;
                    return;
                }

                const state = {
                    token: token,
                    url: url,
                    cursor: Number(options.serverCursor) || 0,
                    intervalMs: Math.max(250, Number(options.fastWatchIntervalMs) || 400),
                    timeoutMs: Math.max(1000, Number(options.fastWatchTimeoutMs) || 3000),
                    inFlight: false,
                    refreshInFlight: false,
                    timer: null,
                };
                this.panels[token] = state;

                const tick = () => this.poll(token);
                tick();
                state.timer = setInterval(tick, state.intervalMs);
            },

            async poll(token) {
                const state = this.panels[token];
                if (! state || state.inFlight || document.hidden) {
                    return;
                }
                state.inFlight = true;
                const startedAt = Date.now();
                const controller = (typeof AbortController !== 'undefined') ? new AbortController() : null;
                const timer = controller
                    ? setTimeout(() => { try { controller.abort(); } catch (e) {} }, state.timeoutMs)
                    : null;
                try {
                    const response = await fetch(state.url, {
                        method: 'GET',
                        credentials: 'same-origin',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                        },
                        cache: 'no-store',
                        signal: controller ? controller.signal : undefined,
                    });
                    state.lastHttp = response.status;
                    state.lastMs = Date.now() - startedAt;
                    state.lastAt = new Date().toISOString().slice(11, 19);
                    if (! response.ok) {
                        state.lastError = 'http-' + response.status;
                        if (window.__humanaTvDiag) {
                            window.__humanaTvDiag.push('WATCH_HTTP ' + response.status + ' ' + state.lastMs + 'ms');
                        }

                        return;
                    }
                    const data = await response.json();
                    state.lastError = null;
                    this.apply(token, data);
                } catch (e) {
                    state.lastHttp = 0;
                    state.lastMs = Date.now() - startedAt;
                    state.lastAt = new Date().toISOString().slice(11, 19);
                    state.lastError = (e && e.name === 'AbortError') ? 'abort-timeout' : 'fetch-fail';
                    if (window.__humanaTvDiag) {
                        window.__humanaTvDiag.push('WATCH_ERR ' + state.lastError + ' ' + state.lastMs + 'ms');
                    }
                    // Abort/offline — Livewire wire:poll remains recovery.
                } finally {
                    if (timer) {
                        clearTimeout(timer);
                    }
                    state.inFlight = false;
                }
            },

            apply(token, data) {
                const state = this.panels[token];
                if (! state || ! data || data.enabled === false) {
                    return;
                }

                const cursor = data.cursor != null ? Number(data.cursor) : 0;
                const ticketId = data.ticket_id != null ? Number(data.ticket_id) : null;
                const status = typeof data.ticket_status === 'string' ? data.ticket_status : null;

                if (status && status !== 'called' && ticketId && window.__humanaTvCallAudio) {
                    window.__humanaTvCallAudio.invalidateTicket(token, ticketId);
                }

                window.dispatchEvent(new CustomEvent('tv-watch-snapshot', {
                    detail: {
                        panelToken: token,
                        cursor: cursor,
                        data: data,
                        isNew: cursor > state.cursor,
                    },
                }));

                if (cursor > state.cursor) {
                    const prev = state.cursor;
                    state.cursor = cursor;
                    state.lastNewAt = new Date().toISOString().slice(11, 19);
                    if (window.__humanaTvDiag) {
                        window.__humanaTvDiag.push(
                            'WATCH_NEW cursor=' + prev + '→' + cursor
                            + ' code=' + (data.display_code || '-')
                            + ' audio=' + (data.audio_url ? 'yes' : 'no')
                        );
                    }

                    // Enqueue announce immediately — do not wait for Livewire morph (~3s poll).
                    // Dedup by callId prevents double play when refreshFeed also enqueues.
                    if (window.__humanaTvCallAudio) {
                        window.__humanaTvCallAudio.enqueue([{
                            panelToken: token,
                            callId: cursor,
                            ticketId: ticketId,
                            ticketStatus: status,
                            announcement: typeof data.announcement === 'string' ? data.announcement : '',
                            displayCode: data.display_code ? String(data.display_code) : '',
                            audioUrl: typeof data.audio_url === 'string' ? data.audio_url : null,
                            correlation: typeof data.correlation === 'string' ? data.correlation : ('tc-' + cursor),
                            source: 'watch',
                        }]);
                    }

                    window.dispatchEvent(new CustomEvent('tv-watch-refresh', {
                        detail: { panelToken: token },
                    }));
                }
            },

            watchSnapshot(token) {
                const state = this.panels[token];
                if (! state) {
                    return null;
                }

                return {
                    cursor: state.cursor,
                    lastHttp: state.lastHttp != null ? state.lastHttp : null,
                    lastMs: state.lastMs != null ? state.lastMs : null,
                    lastAt: state.lastAt || null,
                    lastError: state.lastError || null,
                    lastNewAt: state.lastNewAt || null,
                    intervalMs: state.intervalMs,
                };
            },

            syncCursor(token, cursor) {
                const state = this.panels[token];
                if (! state) {
                    return;
                }
                const next = Number(cursor) || 0;
                if (next >= state.cursor) {
                    state.cursor = next;
                }
            },
        };
    })();

    function tvAudioUi(options = {}) {
        const token = options.panelToken || '';
        const bootAt = Date.now();

        return {
            soundEnabled: false,
            isFullscreen: false,
            panelToken: token,
            fastHighlight: false,
            fastCursor: Number(options.serverCursor) || 0,
            serverDisplayCode: options.serverDisplayCode || '',
            serverDeskName: options.serverDeskName || '',
            serverTicketTypeName: options.serverTicketTypeName || '',
            fastDisplayCode: '',
            fastDeskName: '',
            fastTicketTypeName: '',
            diagEnabled: options.diagEnabled === true,
            diagVisible: options.diagEnabled === true,
            buildStamp: options.buildStamp || '',
            pageLoads: 1,
            lastFeedAt: null,
            lastCallIdSeen: null,
            _refreshInFlight: false,
            _watchInFlight: false,
            _diagTimer: null,
            init() {
                if (window.__humanaTvCallAudio) {
                    window.__humanaTvCallAudio.ensure(token, options);
                    this.soundEnabled = window.__humanaTvCallAudio.isEnabled(token);
                }
                try {
                    const key = 'tvDiagLoads:' + token;
                    const prev = Number(sessionStorage.getItem(key) || '0') || 0;
                    this.pageLoads = prev + 1;
                    sessionStorage.setItem(key, String(this.pageLoads));
                } catch (e) {
                    this.pageLoads = 1;
                }
                document.addEventListener('fullscreenchange', () => {
                    this.isFullscreen = Boolean(document.fullscreenElement);
                });
                window.addEventListener('tv-call-audio-blocked', (event) => {
                    if (event.detail && event.detail.panelToken === token) {
                        this.soundEnabled = false;
                    }
                });
                window.addEventListener('pagehide', () => {
                    if (window.__humanaTvDiag) {
                        window.__humanaTvDiag.push('PAGEHIDE');
                    }
                });
                this.$watch('$wire.currentCall', (value) => {
                    const call = value && typeof value === 'object' ? value : null;
                    this.serverDisplayCode = call && call.display_code ? String(call.display_code) : '';
                    this.serverDeskName = call && call.desk_name ? String(call.desk_name) : '';
                    this.serverTicketTypeName = call && call.ticket_type_name ? String(call.ticket_type_name) : '';
                    const serverCursor = call && call.id != null ? Number(call.id) : 0;
                    this.lastFeedAt = new Date().toISOString().slice(11, 19);
                    this.lastCallIdSeen = serverCursor || this.lastCallIdSeen;
                    if (serverCursor >= this.fastCursor) {
                        this.fastCursor = serverCursor;
                        this.fastDisplayCode = '';
                        this.fastDeskName = '';
                        this.fastTicketTypeName = '';
                        this.fastHighlight = false;
                    }
                    if (window.__humanaTvWatch) {
                        window.__humanaTvWatch.syncCursor(token, serverCursor);
                    }
                    if (call && call.ticket_id && call.ticket_status && call.ticket_status !== 'called') {
                        if (window.__humanaTvCallAudio) {
                            window.__humanaTvCallAudio.invalidateTicket(token, call.ticket_id);
                        }
                    }
                });
                if (window.__humanaTvWatch) {
                    window.__humanaTvWatch.start(options);
                }
                if (this.diagVisible) {
                    this._diagTimer = setInterval(() => {
                        // Alpine x-text re-evaluates when reactive deps change; nudge via noop.
                        this.lastFeedAt = this.lastFeedAt;
                        this.flushDiagIngest();
                    }, 1000);
                }
                if (! window.__humanaTvDiag) {
                    window.__humanaTvDiag = {
                        lines: [],
                        push(msg) {
                            try {
                                const stamp = new Date().toISOString().slice(11, 23);
                                this.lines.push(stamp + ' ' + msg);
                                if (this.lines.length > 40) {
                                    this.lines.shift();
                                }
                            } catch (e) {}
                        },
                    };
                }
                window.__humanaTvDiag.push('BOOT build=' + (options.buildStamp || '?') + ' smartTv=' + String(options.isSmartTv === true));
            },
            onWatchSnapshot(detail) {
                if (! detail || detail.panelToken !== token || ! detail.isNew || ! detail.data) {
                    return;
                }
                const data = detail.data;
                this.fastCursor = detail.cursor;
                this.fastDisplayCode = data.display_code ? String(data.display_code) : '';
                this.fastDeskName = data.desk_name ? String(data.desk_name) : '';
                this.fastTicketTypeName = data.ticket_type_name ? String(data.ticket_type_name) : '';
                this.fastHighlight = Boolean(this.fastDisplayCode);
                if (window.__humanaTvDiag) {
                    window.__humanaTvDiag.push('WATCH_NEW cursor=' + detail.cursor + ' code=' + this.fastDisplayCode);
                }
            },
            visibleDisplayCode() {
                return this.fastDisplayCode || this.serverDisplayCode || '';
            },
            visibleDeskName() {
                return this.fastDeskName || this.serverDeskName || '';
            },
            visibleTicketTypeName() {
                return this.fastTicketTypeName || this.serverTicketTypeName || '';
            },
            requestFeedRefresh() {
                if (this._refreshInFlight || ! this.$wire || typeof this.$wire.refreshFeed !== 'function') {
                    return;
                }
                this._refreshInFlight = true;
                if (window.__humanaTvDiag) {
                    window.__humanaTvDiag.push('FEED_REFRESH');
                }
                Promise.resolve(this.$wire.refreshFeed())
                    .catch(() => {})
                    .finally(() => {
                        this._refreshInFlight = false;
                    });
            },
            flushDiagIngest() {
                const url = options.diagIngestUrl;
                if (! url || ! window.__humanaTvDiag || ! Array.isArray(window.__humanaTvDiag.lines)) {
                    return;
                }
                if (this._diagFlushInFlight) {
                    return;
                }
                const sent = Number(window.__humanaTvDiag._sentAt || 0) || 0;
                const pending = window.__humanaTvDiag.lines.slice(sent, sent + 20);
                if (pending.length === 0) {
                    return;
                }
                this._diagFlushInFlight = true;
                fetch(url, {
                    method: 'POST',
                    credentials: 'same-origin',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-Requested-With': 'XMLHttpRequest',
                    },
                    body: JSON.stringify({ events: pending }),
                }).then(() => {
                    window.__humanaTvDiag._sentAt = sent + pending.length;
                }).catch(() => {
                    // Keep HUD lines; retry next tick.
                }).finally(() => {
                    this._diagFlushInFlight = false;
                });
            },
            resumeMedia() {
                const player = window.__humanaTvMediaPlayer;
                if (player && typeof player.forceResume === 'function') {
                    player.forceResume();
                    if (window.__humanaTvDiag) {
                        window.__humanaTvDiag.push('FORCE_RESUME');
                    }
                }
            },
            diagProbeEffect() {
                if (! window.__humanaTvCallAudio) {
                    return;
                }
                window.__humanaTvCallAudio.probeEffect(token);
            },
            diagProbeTts() {
                if (! window.__humanaTvCallAudio) {
                    return;
                }
                window.__humanaTvCallAudio.probeTts(token, options.sampleTtsUrl || null);
            },
            diagProbeSequence() {
                if (! window.__humanaTvCallAudio) {
                    return;
                }
                window.__humanaTvCallAudio.probeSequence(token, options.sampleTtsUrl || null);
            },
            diagText() {
                const ua = typeof navigator !== 'undefined' ? navigator.userAgent : '';
                const panel = window.__humanaTvCallAudio && window.__humanaTvCallAudio.panels
                    ? window.__humanaTvCallAudio.panels[token]
                    : null;
                const player = window.__humanaTvMediaPlayer;
                const snap = player && typeof player.playbackSnapshot === 'function'
                    ? player.playbackSnapshot()
                    : {};
                const video = document.querySelector('[data-tv-media-video]');
                const watch = window.__humanaTvWatch && typeof window.__humanaTvWatch.watchSnapshot === 'function'
                    ? window.__humanaTvWatch.watchSnapshot(token)
                    : null;
                const timings = panel && panel.timings ? panel.timings : null;
                const tDelta = (key) => {
                    if (! timings || ! timings.CALL_RECEIVED || ! timings[key]) {
                        return '-';
                    }

                    return String(timings[key] - timings.CALL_RECEIVED) + 'ms';
                };
                const ctxState = panel && panel.audioCtx ? panel.audioCtx.state : 'none';
                const lines = [
                    'build=' + (this.buildStamp || '?'),
                    'ua=' + ua.slice(0, 120),
                    'smartTv=' + String(options.isSmartTv === true || (window.__humanaTvCallAudio && window.__humanaTvCallAudio.isSmartTvBrowser())),
                    'pageLoads(session)=' + this.pageLoads + ' uptimeMs=' + (Date.now() - bootAt),
                    'sound=' + String(this.soundEnabled)
                        + ' phase=' + (panel && panel.audioPhase ? panel.audioPhase : 'IDLE')
                        + ' ctx=' + ctxState,
                    'effectPath=' + (panel && panel.lastEffectPath ? panel.lastEffectPath : '-')
                        + ' unlocked=' + String(!!(panel && panel.effectUnlocked))
                        + ' playErr=' + (panel && panel.lastPlayError ? panel.lastPlayError : '-'),
                    'ttsPath=' + (panel && panel.lastTtsPath ? panel.lastTtsPath : '-')
                        + ' ttsErr=' + (panel && panel.lastTtsError ? panel.lastTtsError : '-'),
                    'lat effect=' + tDelta('EFFECT_PLAYING')
                        + ' tts=' + tDelta('TTS_PLAYING')
                        + ' done=' + tDelta('COMPLETED'),
                    'watch http=' + (watch && watch.lastHttp != null ? watch.lastHttp : '-')
                        + ' ms=' + (watch && watch.lastMs != null ? watch.lastMs : '-')
                        + ' at=' + (watch && watch.lastAt ? watch.lastAt : '-')
                        + ' err=' + (watch && watch.lastError ? watch.lastError : '-')
                        + ' newAt=' + (watch && watch.lastNewAt ? watch.lastNewAt : '-')
                        + ' cur=' + (watch && watch.cursor != null ? watch.cursor : '-'),
                    'feedAt=' + (this.lastFeedAt || '-') + ' callId=' + (this.lastCallIdSeen || '-'),
                    'videoId=' + (snap.videoIdentity || '-')
                        + ' media=' + (snap.id || '-') + '/' + (snap.type || '-')
                        + ' inst=' + (snap.instanceId || '-'),
                    't=' + (snap.currentTime != null ? Number(snap.currentTime).toFixed(2) : '-')
                        + ' paused=' + String(snap.paused)
                        + ' rs=' + String(snap.readyState)
                        + ' ns=' + String(snap.networkState)
                        + ' muted=' + String(snap.muted)
                        + ' announce=' + String(snap.announceActive),
                    'videoEl=' + (video ? 'yes' : 'NO')
                        + ' src=' + (video && video.currentSrc ? video.currentSrc.slice(-40) : '-')
                        + ' vErr=' + (snap.lastVideoError || '-'),
                    'stuck=' + String(!!(player && player.mediaStuck)),
                ];
                if (window.__humanaTvDiag && window.__humanaTvDiag.lines) {
                    lines.push('---');
                    lines.push.apply(lines, window.__humanaTvDiag.lines.slice(-12));
                }

                return lines.join('\n');
            },
            async enableSound() {
                if (!window.__humanaTvCallAudio) {
                    return;
                }
                this.soundEnabled = true;
                await window.__humanaTvCallAudio.enable(token, options);
                this.soundEnabled = window.__humanaTvCallAudio.isEnabled(token);
                if (window.__humanaTvDiag) {
                    window.__humanaTvDiag.push('SOUND_ENABLE ok=' + String(this.soundEnabled));
                }
            },
            toggleFullscreen() {
                // Stable shell container — do not remount media / change video src.
                const root = document.getElementById('tv-shell-root') || document.documentElement;
                if (! document.fullscreenElement) {
                    if (root.requestFullscreen) {
                        root.requestFullscreen().catch(() => {});
                    }
                } else if (document.exitFullscreen) {
                    document.exitFullscreen().catch(() => {});
                }
            },
        };
    }
</script>
