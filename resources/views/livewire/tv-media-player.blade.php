<div wire:poll.45s="refreshPlaylist" class="relative h-full w-full">
    {{--
        Alpine owns the playback surface. wire:ignore + TvDisplay @island(tv-media)
        keep TicketCall / feed morphs from destroying <video>/YouTube.

        CRITICAL: this player must NEVER listen to call-announcement bus events.
        Call effect/TTS use separate <audio> elements in tv-display.
        No pause/load/src/muted/volume changes are allowed because of a call.
    --}}
    <div
        wire:ignore
        class="relative h-full w-full"
        x-data="tvMediaPlayer(@js($items))"
        x-on:tv-playlist-updated.window="setItems(($event.detail && $event.detail.items) ? $event.detail.items : [])"
    >
        <div class="absolute inset-0 overflow-hidden bg-gradient-to-br from-primary via-primary-light to-primary-dark">
            <div
                x-show="!hasItems"
                x-cloak
                class="flex h-full items-center justify-center p-4 text-center sm:p-6 lg:p-8"
            >
                <div class="max-w-full px-2">
                    <p class="mx-auto flex size-12 items-center justify-center rounded-xl bg-white text-lg font-bold text-primary sm:size-16 sm:rounded-2xl sm:text-2xl" aria-hidden="true">hC</p>
                    <h2 class="mt-3 truncate text-xl font-bold tracking-tight text-white sm:mt-6 sm:text-3xl md:text-4xl lg:text-5xl" x-text="clinicLabel"></h2>
                    <p class="mt-2 text-xs text-white/80 sm:mt-4 sm:text-base md:text-lg">Cuidado com clareza. Acompanhe sua senha neste painel.</p>
                </div>
            </div>

            <div
                x-show="hasItems && current && current.type === 'image'"
                x-cloak
                class="absolute inset-0 h-full w-full bg-primary-dark"
            >
                <img
                    x-ref="localImage"
                    alt=""
                    class="h-full w-full object-cover"
                    x-on:error="failCurrent('image-error')"
                    x-on:load="onImageReady()"
                >
            </div>

            {{-- Persistent <video>: never x-if — recreating the element restarts playback. --}}
            <div
                x-show="hasItems && current && current.type === 'video'"
                x-cloak
                class="absolute inset-0 h-full w-full bg-black"
            >
                <video
                    x-ref="localVideo"
                    data-tv-media-video
                    class="h-full w-full object-cover"
                    autoplay
                    playsinline
                    muted
                    x-on:loadeddata="onLocalVideoReady()"
                    x-on:playing="onLocalVideoPlaying(\$event)"
                    x-on:pause="onLocalVideoPaused(\$event)"
                    x-on:waiting="diagMediaEvent('waiting', \$event)"
                    x-on:stalled="diagMediaEvent('stalled', \$event)"
                    x-on:suspend="diagMediaEvent('suspend', \$event)"
                    x-on:emptied="diagMediaEvent('emptied', \$event)"
                    x-on:abort="diagMediaEvent('abort', \$event)"
                    x-on:timeupdate="onLocalVideoTimeUpdate(\$event)"
                    x-on:ended="onLocalVideoEnded()"
                    x-on:error="onLocalVideoError()"
                ></video>
            </div>

            <div
                x-show="hasItems && current && current.type === 'youtube'"
                x-cloak
                class="absolute inset-0 h-full w-full overflow-hidden bg-black"
            >
                <div data-youtube-host class="absolute inset-0 h-full w-full"></div>
            </div>

            <p
                x-show="showAudioHint"
                x-cloak
                class="pointer-events-none absolute bottom-2 left-2 z-10 max-w-[min(100%,18rem)] rounded-md bg-black/55 px-2 py-1 text-[0.65rem] leading-snug text-white/90 sm:bottom-3 sm:left-3 sm:text-xs"
                role="status"
            >
                Áudio da mídia bloqueado pelo navegador (autoplay).
            </p>
        </div>
    </div>
</div>

<script>
    if (@js((bool) config('tv_realtime.player_diagnostics') || (bool) config('app.debug')) && !window.__humanaTvMorphProbe) {
        window.__humanaTvMorphProbe = true;
        document.addEventListener('livewire:init', function () {
            try {
                window.Livewire.hook('morph.removed', function (hookArgs) {
                    const el = hookArgs && hookArgs.el;
                    if (el && el.nodeType === 1 && (el.matches('video, iframe') || el.querySelector('video, iframe'))) {
                        window.console.warn('[TV Media] morph removed a media element', el);
                    }
                });
            } catch (e) {}
        });
    }

    function tvMediaPlayer(initialItems = []) {
        const diagnostics = @js((bool) config('tv_realtime.player_diagnostics') || (bool) config('app.debug'));
        const instanceId = 'tv-media-' + Math.random().toString(36).slice(2, 10);

        return {
            items: Array.isArray(initialItems) ? initialItems : [],
            pendingItems: null,
            index: 0,
            imageTimer: null,
            loadWatchdog: null,
            errorRecoveryTimer: null,
            mountRetries: 0,
            failedIds: {},
            ytPlayer: null,
            ytMountedId: null,
            imageTimerStartedFor: null,
            boundVideoItemId: null,
            boundImageItemId: null,
            playbackToken: 0,
            advancing: false,
            lastVideoTime: 0,
            interruptionTimer: null,
            interruptionCount: 0,
            interruptionWindowStart: 0,
            pauseWatchdog: null,
            mediaStuck: false,
            stuckSince: 0,
            announceActive: false,
            lastVideoError: null,
            videoIdentity: null,
            videoElementSeq: 0,
            instanceId: instanceId,
            debug: diagnostics,
            autoplayAudioBlocked: false,
            clinicLabel: @js($clinicName !== '' ? $clinicName : config('app.name')),
            get hasItems() {
                return this.playableItems.length > 0;
            },
            get playableItems() {
                return (this.items || []).filter((item) => this.isPlayableItem(item));
            },
            get current() {
                if (! this.hasItems) {
                    return null;
                }
                if (this.index < 0 || this.index >= this.playableItems.length) {
                    this.index = 0;
                }

                return this.playableItems[this.index] || null;
            },
            get showAudioHint() {
                const item = this.current;

                return !!(item && this.itemWantsAudio(item) && this.autoplayAudioBlocked);
            },
            isPlayableItem(item) {
                if (! item || this.failedIds[item.id]) {
                    return false;
                }
                if (item.type === 'youtube') {
                    return typeof item.video_id === 'string' && item.video_id.length === 11;
                }

                return typeof item.url === 'string' && item.url !== '';
            },
            isLongFormType(type) {
                return type === 'video' || type === 'youtube';
            },
            youtubeHost() {
                return this.$root.querySelector('[data-youtube-host]');
            },
            /**
             * MEDIA AUDIO config only (playlist play_with_audio).
             * Independent from call-effect "Ativar som" / soundEnabled.
             */
            itemWantsAudio(item = null) {
                const target = item || this.current;

                return !!(target && target.play_with_audio === true);
            },
            shouldPlayWithAudio(item = null) {
                return this.itemWantsAudio(item);
            },
            enforceConfiguredMute() {
                const item = this.current;
                if (! item || this.itemWantsAudio(item)) {
                    return;
                }

                if (item.type === 'video') {
                    const video = this.$refs.localVideo;
                    if (video) {
                        video.muted = true;
                    }

                    return;
                }

                if (item.type === 'youtube' && this.ytPlayer) {
                    try {
                        this.ytPlayer.mute();
                        if (typeof this.ytPlayer.setVolume === 'function') {
                            this.ytPlayer.setVolume(0);
                        }
                    } catch (e) {}
                }
            },
            playbackSnapshot() {
                const item = this.current;
                const video = this.$refs.localVideo;

                return {
                    instanceId: this.instanceId,
                    videoIdentity: this.videoIdentity || (video && video.__tvMediaSeq ? this.instanceId + '#' + video.__tvMediaSeq : null),
                    id: item ? item.id : null,
                    type: item ? item.type : null,
                    seq: video ? video.__tvMediaSeq : null,
                    currentTime: video ? video.currentTime : null,
                    paused: video ? video.paused : null,
                    ended: video ? video.ended : null,
                    readyState: video ? video.readyState : null,
                    networkState: video ? video.networkState : null,
                    muted: video ? video.muted : null,
                    volume: video ? video.volume : null,
                    currentSrc: video ? video.currentSrc : null,
                    announceActive: this.announceActive,
                    lastVideoError: this.lastVideoError,
                };
            },
            diag(tag, detail) {
                if (! this.debug) {
                    return;
                }
                try {
                    window.console.info('[TV Media][' + tag + ']', detail !== undefined ? detail : this.playbackSnapshot());
                } catch (e) {}
            },
            diagMediaEvent(name, event) {
                if (! this.debug) {
                    return;
                }
                const video = event && event.target;
                this.diag('VIDEO_' + String(name).toUpperCase(), {
                    instanceId: this.instanceId,
                    currentTime: video ? video.currentTime : null,
                    paused: video ? video.paused : null,
                    readyState: video ? video.readyState : null,
                });
            },
            debugLog(message, detail) {
                this.diag(message, detail);
            },
            playlistFingerprint(items) {
                return JSON.stringify(Array.isArray(items) ? items : []);
            },
            clearImageTimer() {
                if (this.imageTimer) {
                    clearTimeout(this.imageTimer);
                    this.imageTimer = null;
                }
                this.imageTimerStartedFor = null;
            },
            clearLoadWatchdog() {
                if (this.loadWatchdog) {
                    clearTimeout(this.loadWatchdog);
                    this.loadWatchdog = null;
                }
            },
            clearErrorRecoveryTimer() {
                if (this.errorRecoveryTimer) {
                    clearTimeout(this.errorRecoveryTimer);
                    this.errorRecoveryTimer = null;
                }
            },
            clearMediaTimers() {
                this.clearImageTimer();
                this.clearLoadWatchdog();
            },
            clearInterruptionTimer() {
                if (this.interruptionTimer) {
                    clearTimeout(this.interruptionTimer);
                    this.interruptionTimer = null;
                }
            },
            bumpPlaybackToken() {
                this.playbackToken += 1;

                return this.playbackToken;
            },
            setItems(items) {
                const nextItems = Array.isArray(items) ? items : [];
                if (this.playlistFingerprint(this.items) === this.playlistFingerprint(nextItems)) {
                    this.pendingItems = null;

                    return;
                }

                const current = this.current;
                const currentId = current ? current.id : null;
                this.diag('PLAYLIST_UPDATED', { count: nextItems.length, currentId: currentId });
                const nextPlayable = nextItems.filter((item) => {
                    if (! item) {
                        return false;
                    }
                    if (item.type === 'youtube') {
                        return typeof item.video_id === 'string' && item.video_id.length === 11;
                    }

                    return typeof item.url === 'string' && item.url !== '';
                });
                const stillExists = currentId !== null
                    && nextPlayable.some((item) => item.id === currentId);

                if (current && this.isLongFormType(current.type) && stillExists) {
                    this.pendingItems = nextItems;

                    return;
                }

                this.pendingItems = null;
                this.applyPlaylist(nextItems, {
                    preferCurrentId: stillExists ? currentId : null,
                });
            },
            applyPlaylist(nextItems, options = {}) {
                const preferCurrentId = options.preferCurrentId ?? null;
                this.items = Array.isArray(nextItems) ? nextItems : [];
                this.failedIds = {};
                this.mountRetries = 0;
                this.clearErrorRecoveryTimer();

                if (preferCurrentId !== null) {
                    const idx = this.playableItems.findIndex((item) => item.id === preferCurrentId);
                    if (idx >= 0) {
                        this.index = idx;
                        const item = this.current;
                        if (item && item.type === 'image' && this.imageTimerStartedFor !== item.id) {
                            this.bindImageSrc(item);
                            this.startImageTimer(item);
                        }
                        this.applyMediaAudio();

                        return;
                    }
                }

                this.destroyYouTube();
                this.clearMediaTimers();
                this.index = 0;
                this.autoplayAudioBlocked = false;
                this.$nextTick(() => this.activateCurrent());
            },
            bindImageSrc(item) {
                const image = this.$refs.localImage;
                if (! image || ! item || item.type !== 'image') {
                    return;
                }
                if (this.boundImageItemId === item.id && image.getAttribute('src') === item.url) {
                    return;
                }
                this.boundImageItemId = item.id;
                image.setAttribute('src', item.url);
                image.alt = item.name || '';
            },
            /**
             * Change video src only when the playlist item identity/url changes.
             * Never rewrite src on TicketCall updates.
             */
            bindVideoSrc(item) {
                const video = this.$refs.localVideo;
                if (! video || ! item || item.type !== 'video') {
                    return false;
                }

                if (! video.__tvMediaSeq) {
                    this.videoElementSeq += 1;
                    video.__tvMediaSeq = this.videoElementSeq;
                    this.videoIdentity = this.instanceId + '#' + video.__tvMediaSeq;
                    this.diag('VIDEO_ELEMENT_BOUND', { seq: video.__tvMediaSeq, instanceId: this.instanceId });
                    if (window.__humanaTvDiag) {
                        window.__humanaTvDiag.push('VIDEO_BOUND ' + this.videoIdentity);
                    }
                }

                const sameItem = this.boundVideoItemId === item.id;
                const sameSrc = video.getAttribute('src') === item.url || video.currentSrc.endsWith(item.url);
                if (sameItem && sameSrc && video.readyState > 0) {
                    return false;
                }

                this.boundVideoItemId = item.id;
                this.lastVideoTime = 0;
                video.setAttribute('src', item.url);
                this.diag('VIDEO_SRC_BOUND', { id: item.id, url: item.url, seq: video.__tvMediaSeq });

                return true;
            },
            activateCurrent() {
                this.clearMediaTimers();
                this.clearInterruptionTimer();
                this.autoplayAudioBlocked = false;
                const token = this.bumpPlaybackToken();
                const item = this.current;
                this.diag('ACTIVATE', item ? { id: item.id, type: item.type, url: item.url || item.video_id } : null);
                if (! item) {
                    this.destroyYouTube();
                    this.advancing = false;
                    if ((this.items || []).length > 0) {
                        this.scheduleErrorRecovery();
                    }

                    return;
                }

                this.clearErrorRecoveryTimer();

                if (item.type === 'image') {
                    this.destroyYouTube();
                    this.advancing = false;
                    this.$nextTick(() => {
                        if (this.playbackToken !== token) {
                            return;
                        }
                        this.bindImageSrc(item);
                        this.startImageTimer(item, token);
                    });

                    return;
                }

                if (item.type === 'video') {
                    this.destroyYouTube();
                    this.advancing = false;
                    this.$nextTick(() => {
                        if (this.playbackToken !== token) {
                            return;
                        }
                        this.bindVideoSrc(item);
                        this.onLocalVideoReady(token);
                    });

                    return;
                }

                if (item.type === 'youtube') {
                    this.advancing = false;
                    this.$nextTick(() => {
                        if (this.playbackToken !== token) {
                            return;
                        }
                        this.mountYouTube(item, token);
                    });
                }
            },
            startImageTimer(item, token = null) {
                this.clearImageTimer();
                if (! item || item.type !== 'image') {
                    return;
                }
                const activeToken = token === null ? this.playbackToken : token;
                if (this.playbackToken !== activeToken) {
                    return;
                }
                const seconds = Math.max(3, Number(item.duration_seconds || 10));
                this.imageTimerStartedFor = item.id;
                this.imageTimer = setTimeout(() => {
                    if (this.playbackToken !== activeToken) {
                        return;
                    }
                    if (! this.current || this.current.id !== item.id || this.current.type !== 'image') {
                        return;
                    }
                    this.advanceToNextMedia('IMAGE_TIMEOUT');
                }, seconds * 1000);
            },
            onImageReady() {
                const item = this.current;
                if (! item || item.type !== 'image') {
                    return;
                }
                if (this.imageTimerStartedFor === item.id && this.imageTimer) {
                    return;
                }
                this.startImageTimer(item);
            },
            onLocalVideoReady(token = null) {
                const item = this.current;
                const video = this.$refs.localVideo;
                const activeToken = token === null ? this.playbackToken : token;
                if (this.playbackToken !== activeToken) {
                    return;
                }
                if (! item || item.type !== 'video' || ! video) {
                    return;
                }

                // Autoplay policy: start muted, then apply playlist audio config only.
                if (! this.itemWantsAudio(item)) {
                    video.muted = true;
                } else {
                    video.muted = true;
                }

                const start = video.play();
                if (start && typeof start.then === 'function') {
                    start.catch((err) => {
                        this.diag('VIDEO_PLAY_REJECTED', {
                            name: err && err.name ? err.name : 'unknown',
                            message: err && err.message ? err.message : String(err || ''),
                        });
                    }).finally(() => {
                        if (this.playbackToken === activeToken) {
                            this.applyMediaAudio();
                        }
                    });
                } else {
                    this.applyMediaAudio();
                }
            },
            onLocalVideoEnded() {
                const item = this.current;
                if (! item || item.type !== 'video') {
                    return;
                }
                this.diag('VIDEO_ENDED', { id: item.id });
                this.advanceToNextMedia('VIDEO_ENDED');
            },
            onLocalVideoPlaying(event) {
                const video = event && event.target;
                this.mediaStuck = false;
                this.stuckSince = 0;
                this.interruptionCount = 0;
                this.diag('VIDEO_PLAYING', {
                    seq: video ? video.__tvMediaSeq : null,
                    currentTime: video ? video.currentTime : null,
                });
                if (window.__humanaTvDiag) {
                    window.__humanaTvDiag.push('VIDEO_PLAYING t=' + (video ? Number(video.currentTime).toFixed(2) : '-'));
                }
            },
            onLocalVideoTimeUpdate(event) {
                const video = event && event.target;
                if (video && video === this.$refs.localVideo) {
                    this.lastVideoTime = video.currentTime || 0;
                }
            },
            /**
             * Browser audio-focus may pause <video> when a separate call <audio> plays.
             * Resume the SAME element — never remount, never load(), never change src.
             * Not gated by call state: call engine must not control this player.
             */
            onLocalVideoPaused(event) {
                const video = event && event.target;
                if (! video || video !== this.$refs.localVideo || ! video.isConnected) {
                    this.diag('VIDEO_PAUSE_DETACHED', { seq: video ? video.__tvMediaSeq : null });

                    return;
                }
                if (video.ended || this.advancing || video.readyState < 2) {
                    return;
                }
                this.diag('VIDEO_PAUSE_EXTERNAL', {
                    seq: video.__tvMediaSeq,
                    currentTime: video.currentTime,
                    announceActive: this.announceActive,
                });
                if (window.__humanaTvDiag) {
                    window.__humanaTvDiag.push(
                        'VIDEO_PAUSE t=' + Number(video.currentTime).toFixed(2)
                        + ' announce=' + String(this.announceActive)
                    );
                }
                // During effect/TTS, Tizen often pauses video — do not fight the audio pipeline.
                if (this.announceActive) {
                    if (! this.stuckSince) {
                        this.stuckSince = Date.now();
                    }

                    return;
                }
                this.onExternalInterruption();
            },
            onLocalVideoError() {
                this.lastVideoError = 'video-error';
                this.diag('VIDEO_ERROR', this.playbackSnapshot());
                if (this.announceActive) {
                    if (window.__humanaTvDiag) {
                        window.__humanaTvDiag.push('VIDEO_ERROR deferred until announce end');
                    }

                    return;
                }
                this.failCurrent('video-error');
            },
            onExternalInterruption() {
                if (this.announceActive) {
                    return;
                }
                if (this.interruptionTimer) {
                    return;
                }
                if (! this.stuckSince) {
                    this.stuckSince = Date.now();
                }
                const delay = this.interruptionCount < 6 ? 500 : 2000;
                this.interruptionCount += 1;
                const token = this.playbackToken;
                this.interruptionTimer = setTimeout(() => {
                    this.interruptionTimer = null;
                    if (this.playbackToken === token && ! this.announceActive) {
                        this.continueAfterInterruption();
                    }
                }, delay);
            },
            startPauseWatchdog() {
                this.stopPauseWatchdog();
                this.pauseWatchdog = setInterval(() => {
                    if (this.announceActive) {
                        return;
                    }
                    const item = this.current;
                    if (! item || item.type !== 'video' || this.advancing) {
                        this.mediaStuck = false;

                        return;
                    }
                    const video = this.$refs.localVideo;
                    if (! video || ! video.isConnected || video.ended) {
                        return;
                    }
                    if (! video.paused) {
                        this.mediaStuck = false;
                        this.stuckSince = 0;

                        return;
                    }
                    if (! this.stuckSince) {
                        this.stuckSince = Date.now();
                    }
                    this.mediaStuck = (Date.now() - this.stuckSince) > 4000;
                    this.diag('PAUSE_WATCHDOG', this.playbackSnapshot());
                    if (window.__humanaTvDiag) {
                        window.__humanaTvDiag.push('PAUSE_WATCHDOG t=' + Number(video.currentTime).toFixed(2));
                    }
                    this.continueAfterInterruption();
                }, 2000);
            },
            stopPauseWatchdog() {
                if (this.pauseWatchdog) {
                    clearInterval(this.pauseWatchdog);
                    this.pauseWatchdog = null;
                }
            },
            forceResume() {
                this.interruptionCount = 0;
                this.mediaStuck = false;
                this.stuckSince = 0;
                this.continueAfterInterruption();
            },
            continueAfterInterruption() {
                const item = this.current;
                if (! item || this.advancing) {
                    return;
                }

                if (item.type === 'video') {
                    const video = this.$refs.localVideo;
                    if (! video || ! video.isConnected) {
                        return;
                    }
                    if (! video.paused || video.ended) {
                        return;
                    }
                    this.diag('VIDEO_CONTINUE', {
                        seq: video.__tvMediaSeq,
                        currentTime: video.currentTime,
                    });
                    const attempt = video.play();
                    if (attempt && typeof attempt.then === 'function') {
                        attempt.then(() => this.applyMediaAudio()).catch((err) => {
                            this.diag('VIDEO_CONTINUE_REJECTED', {
                                name: err && err.name ? err.name : 'unknown',
                            });
                            // Keep playlist mute config; retry muted only for autoplay policy.
                            video.muted = ! this.itemWantsAudio(item) ? true : video.muted;
                            if (! this.itemWantsAudio(item)) {
                                video.muted = true;
                            } else {
                                video.muted = true;
                                this.autoplayAudioBlocked = true;
                            }
                            video.play().catch(() => {});
                        });
                    } else {
                        this.applyMediaAudio();
                    }

                    return;
                }

                if (item.type === 'youtube' && this.ytPlayer && this.ytMountedId === item.id) {
                    try {
                        const state = typeof this.ytPlayer.getPlayerState === 'function'
                            ? this.ytPlayer.getPlayerState()
                            : null;
                        if (state === 2) {
                            this.diag('YOUTUBE_CONTINUE', { id: item.id });
                            this.ytPlayer.playVideo();
                        }
                    } catch (e) {}
                }
            },
            applyMediaAudio() {
                const item = this.current;
                const wantAudio = this.shouldPlayWithAudio(item);

                if (item && item.type === 'video') {
                    const video = this.$refs.localVideo;
                    if (! video) {
                        return;
                    }
                    if (! wantAudio) {
                        video.muted = true;
                        this.autoplayAudioBlocked = false;

                        return;
                    }
                    video.muted = false;
                    const attempt = video.play();
                    if (attempt && typeof attempt.then === 'function') {
                        attempt.catch(() => {
                            video.muted = true;
                            this.autoplayAudioBlocked = true;
                            video.play().catch(() => {});
                        });
                    }

                    return;
                }

                if (item && item.type === 'youtube' && this.ytPlayer) {
                    try {
                        if (wantAudio) {
                            this.ytPlayer.unMute();
                            if (typeof this.ytPlayer.setVolume === 'function') {
                                this.ytPlayer.setVolume(100);
                            }
                        } else {
                            this.ytPlayer.mute();
                            if (typeof this.ytPlayer.setVolume === 'function') {
                                this.ytPlayer.setVolume(0);
                            }
                            this.autoplayAudioBlocked = false;
                        }
                    } catch (e) {}
                }
            },
            advanceToNextMedia(reason) {
                const allowed = ['IMAGE_TIMEOUT', 'VIDEO_ENDED', 'YOUTUBE_ENDED', 'MEDIA_ERROR'];
                if (! allowed.includes(reason)) {
                    return;
                }
                if (this.advancing) {
                    return;
                }
                this.advancing = true;
                this.diag('ADVANCE', { reason: reason, current: this.current ? this.current.id : null });
                this.clearMediaTimers();

                if (this.pendingItems) {
                    const pending = this.pendingItems;
                    this.pendingItems = null;
                    const previousId = this.current ? this.current.id : null;
                    this.items = pending;
                    this.failedIds = {};
                    this.mountRetries = 0;
                    this.autoplayAudioBlocked = false;
                    this.destroyYouTube();
                    this.clearErrorRecoveryTimer();

                    const playable = this.playableItems;
                    if (playable.length === 0) {
                        this.index = 0;
                        this.advancing = false;
                        this.scheduleErrorRecovery();

                        return;
                    }

                    const idx = previousId === null
                        ? -1
                        : playable.findIndex((entry) => entry.id === previousId);

                    this.index = idx >= 0
                        ? (idx + 1) % playable.length
                        : 0;

                    this.$nextTick(() => this.activateCurrent());

                    return;
                }

                const playable = this.playableItems;
                if (playable.length === 0) {
                    this.advancing = false;
                    this.scheduleErrorRecovery();

                    return;
                }

                if (playable.length === 1) {
                    this.restartSingleItem(reason);

                    return;
                }

                this.destroyYouTube();
                this.mountRetries = 0;
                this.autoplayAudioBlocked = false;

                const previousIndex = this.index;
                this.index = (this.index + 1) % playable.length;

                if (
                    this.index === 0
                    && previousIndex > 0
                    && (reason === 'IMAGE_TIMEOUT' || reason === 'VIDEO_ENDED' || reason === 'YOUTUBE_ENDED')
                ) {
                    this.failedIds = {};
                }

                this.$nextTick(() => this.activateCurrent());
            },
            restartSingleItem(reason) {
                this.clearMediaTimers();
                const item = this.current;
                if (! item) {
                    this.advancing = false;
                    this.scheduleErrorRecovery();

                    return;
                }
                if (item.type === 'image') {
                    this.advancing = false;
                    this.startImageTimer(item);

                    return;
                }
                if (item.type === 'video') {
                    const video = this.$refs.localVideo;
                    if (video) {
                        try {
                            video.currentTime = 0;
                            this.advancing = false;
                            this.onLocalVideoReady();

                            return;
                        } catch (e) {}
                    }
                    this.activateCurrent();

                    return;
                }
                if (item.type === 'youtube') {
                    this.destroyYouTube();
                    this.activateCurrent();

                    return;
                }
                this.advancing = false;
            },
            scheduleErrorRecovery() {
                if (this.errorRecoveryTimer) {
                    return;
                }
                this.errorRecoveryTimer = setTimeout(() => {
                    this.errorRecoveryTimer = null;
                    if ((this.items || []).length === 0) {
                        return;
                    }
                    this.failedIds = {};
                    this.mountRetries = 0;
                    this.index = 0;
                    this.advancing = false;
                    this.activateCurrent();
                }, 30000);
            },
            failCurrent(reason) {
                const item = this.current;
                if (! item) {
                    return;
                }
                this.diag('FAIL', { id: item.id, type: item.type, reason: reason || '' });

                if (reason === 'host-missing' || reason === 'target-gone') {
                    this.destroyYouTube();
                    this.clearMediaTimers();
                    this.mountRetries = 0;
                    this.advancing = false;
                    const token = this.playbackToken;
                    setTimeout(() => {
                        if (this.playbackToken !== token) {
                            return;
                        }
                        if (this.current && this.current.id === item.id) {
                            this.activateCurrent();
                        }
                    }, 250);

                    return;
                }

                this.destroyYouTube();
                this.clearMediaTimers();
                this.failedIds[item.id] = true;

                const remaining = (this.items || []).filter((entry) => entry && ! this.failedIds[entry.id]);
                if (remaining.length === 0) {
                    this.advancing = false;
                    this.scheduleErrorRecovery();

                    return;
                }

                this.advanceToNextMedia('MEDIA_ERROR');
            },
            ensureYouTubeApi() {
                return new Promise((resolve, reject) => {
                    if (window.YT && window.YT.Player) {
                        resolve(window.YT);

                        return;
                    }

                    let settled = false;
                    const finish = (ok) => {
                        if (settled) {
                            return;
                        }
                        settled = true;
                        if (ok && window.YT && window.YT.Player) {
                            resolve(window.YT);
                        } else {
                            reject(new Error('youtube-api'));
                        }
                    };

                    const previous = window.onYouTubeIframeAPIReady;
                    window.onYouTubeIframeAPIReady = () => {
                        if (typeof previous === 'function') {
                            previous();
                        }
                        finish(true);
                    };

                    if (! document.getElementById('youtube-iframe-api')) {
                        const tag = document.createElement('script');
                        tag.id = 'youtube-iframe-api';
                        tag.src = 'https://www.youtube.com/iframe_api';
                        tag.async = true;
                        tag.onerror = () => finish(false);
                        document.head.appendChild(tag);
                    }

                    setTimeout(() => finish(!!(window.YT && window.YT.Player)), 12000);
                });
            },
            destroyYouTube() {
                this.clearLoadWatchdog();
                if (this.ytPlayer && typeof this.ytPlayer.destroy === 'function') {
                    try {
                        this.ytPlayer.destroy();
                    } catch (e) {}
                }
                this.ytPlayer = null;
                this.ytMountedId = null;
                const host = this.youtubeHost();
                if (host) {
                    host.innerHTML = '';
                }
            },
            mountYouTube(item, token = null) {
                const activeToken = token === null ? this.playbackToken : token;
                if (! item || item.type !== 'youtube' || ! item.video_id) {
                    return;
                }
                if (this.playbackToken !== activeToken) {
                    return;
                }
                if (this.ytMountedId === item.id && this.ytPlayer) {
                    this.applyMediaAudio();

                    return;
                }

                const host = this.youtubeHost();
                if (! host) {
                    this.mountRetries += 1;
                    if (this.mountRetries <= 20) {
                        setTimeout(() => {
                            if (this.playbackToken === activeToken) {
                                this.mountYouTube(item, activeToken);
                            }
                        }, 100);

                        return;
                    }
                    this.failCurrent('host-missing');

                    return;
                }

                this.mountRetries = 0;
                this.destroyYouTube();
                this.ytMountedId = item.id;

                const targetId = 'yt-player-' + item.id + '-' + activeToken;
                host.innerHTML = '';
                const target = document.createElement('div');
                target.id = targetId;
                target.style.width = '100%';
                target.style.height = '100%';
                host.appendChild(target);

                this.clearLoadWatchdog();
                this.loadWatchdog = setTimeout(() => {
                    if (this.playbackToken !== activeToken) {
                        return;
                    }
                    this.failCurrent('load-timeout');
                }, 25000);

                this.ensureYouTubeApi().then((YT) => {
                    if (this.playbackToken !== activeToken) {
                        return;
                    }
                    if (! this.current || this.current.id !== item.id || this.current.type !== 'youtube') {
                        return;
                    }
                    if (! document.getElementById(targetId)) {
                        this.failCurrent('target-gone');

                        return;
                    }

                    const startMuted = ! this.shouldPlayWithAudio(item);

                    this.ytPlayer = new YT.Player(targetId, {
                        videoId: item.video_id,
                        width: '100%',
                        height: '100%',
                        playerVars: {
                            autoplay: 1,
                            mute: startMuted ? 1 : 0,
                            controls: 0,
                            rel: 0,
                            modestbranding: 1,
                            playsinline: 1,
                            fs: 0,
                            enablejsapi: 1,
                            origin: window.location.origin,
                        },
                        events: {
                            onReady: (event) => {
                                if (this.playbackToken !== activeToken) {
                                    return;
                                }
                                this.clearLoadWatchdog();
                                try {
                                    if (startMuted) {
                                        event.target.mute();
                                    }
                                    event.target.playVideo();
                                } catch (e) {}
                                this.applyMediaAudio();
                            },
                            onStateChange: (event) => {
                                if (this.playbackToken !== activeToken) {
                                    return;
                                }
                                if (event.data === YT.PlayerState.PLAYING) {
                                    this.clearLoadWatchdog();
                                    if (this.shouldPlayWithAudio(item)) {
                                        try {
                                            event.target.unMute();
                                        } catch (e) {}
                                    } else {
                                        try {
                                            event.target.mute();
                                            if (typeof event.target.setVolume === 'function') {
                                                event.target.setVolume(0);
                                            }
                                        } catch (e) {}
                                    }
                                }
                                if (event.data === YT.PlayerState.PAUSED) {
                                    this.diag('YOUTUBE_PAUSE_EXTERNAL', { id: item.id });
                                    this.onExternalInterruption();
                                }
                                if (event.data === YT.PlayerState.ENDED) {
                                    this.advanceToNextMedia('YOUTUBE_ENDED');
                                }
                            },
                            onError: (event) => {
                                if (this.playbackToken !== activeToken) {
                                    return;
                                }
                                this.failCurrent('yt-error-' + (event && event.data));
                            },
                        },
                    });
                }).catch(() => {
                    if (this.playbackToken === activeToken) {
                        this.failCurrent('api');
                    }
                });
            },
            init() {
                // Only gate resume around announcements — never mute/load/src on call events.
                this.diag('PLAYER_INIT', { instanceId: this.instanceId });
                if (typeof window !== 'undefined') {
                    window.__humanaTvMediaPlayer = this;
                    if (window.__humanaTvDiag) {
                        window.__humanaTvDiag.push('PLAYER_INIT ' + this.instanceId);
                    }
                }
                this._onAnnounceBegin = () => {
                    this.announceActive = true;
                    this.clearInterruptionTimer();
                    if (window.__humanaTvDiag) {
                        window.__humanaTvDiag.push('ANNOUNCE_BEGIN hold-resume');
                    }
                };
                this._onAnnounceEnd = () => {
                    this.announceActive = false;
                    if (window.__humanaTvDiag) {
                        window.__humanaTvDiag.push('ANNOUNCE_END resume');
                    }
                    // Single recovery after effect+TTS — preserves currentTime (no load/src).
                    this.forceResume();
                };
                window.addEventListener('tv-call-audio-begin', this._onAnnounceBegin);
                window.addEventListener('tv-call-audio-end', this._onAnnounceEnd);
                this.startPauseWatchdog();
                this.$nextTick(() => this.activateCurrent());
            },
            destroy() {
                this.stopPauseWatchdog();
                this.clearInterruptionTimer();
                this.clearMediaTimers();
                this.destroyYouTube();
                if (this._onAnnounceBegin) {
                    window.removeEventListener('tv-call-audio-begin', this._onAnnounceBegin);
                }
                if (this._onAnnounceEnd) {
                    window.removeEventListener('tv-call-audio-end', this._onAnnounceEnd);
                }
                if (window.__humanaTvMediaPlayer === this) {
                    window.__humanaTvMediaPlayer = null;
                }
                if (window.__humanaTvDiag) {
                    window.__humanaTvDiag.push('PLAYER_DESTROY ' + this.instanceId);
                }
                this.diag('PLAYER_DESTROY', { instanceId: this.instanceId });
            },
        };
    }
</script>
