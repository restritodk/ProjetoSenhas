<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Fast watch (lightweight TV poll)
    |--------------------------------------------------------------------------
    |
    | When enabled, the public TV panel polls a lean JSON endpoint to detect
    | new TicketCall rows quickly, then triggers Livewire refreshFeed for
    | audio enqueue + full sync. The existing wire:poll remains as fallback.
    |
    | Disable with TV_FAST_WATCH_ENABLED=false to return to Livewire-only mode
    | without a code change.
    |
    */

    'fast_watch_enabled' => (bool) env('TV_FAST_WATCH_ENABLED', true),

    'fast_watch_interval_ms' => max(250, (int) env('TV_FAST_WATCH_INTERVAL_MS', 400)),

    /*
    |--------------------------------------------------------------------------
    | Livewire recovery poll
    |--------------------------------------------------------------------------
    */

    'livewire_poll_ms' => max(1000, (int) env('TV_LIVEWIRE_POLL_MS', 3000)),

    /*
    |--------------------------------------------------------------------------
    | Recall cooldown (server-side)
    |--------------------------------------------------------------------------
    |
    | Minimum seconds between accepted RECALL TicketCall rows for the same
    | ticket. Concurrent tabs share the same DB lock on the ticket row.
    |
    */

    'recall_cooldown_seconds' => max(1, (int) env('TV_RECALL_COOLDOWN_SECONDS', 3)),

];
