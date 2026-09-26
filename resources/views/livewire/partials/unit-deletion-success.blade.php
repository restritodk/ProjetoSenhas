<div x-data="{
    timer: null,
    init() {
        this.timer = setTimeout(() => this.$wire.dismissDeletionOutcome(), 1800);
    },
    destroy() {
        clearTimeout(this.timer);
        this.timer = null;
    },
}">
    <svg class="mx-auto size-14 text-success" viewBox="0 0 24 24" fill="none" aria-hidden="true">
        <circle cx="12" cy="12" r="9" stroke="currentColor" stroke-width="1.5" class="opacity-25"></circle>
        <path class="hc-check" d="M8 12.5l2.5 2.5L16 9.5" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"></path>
    </svg>
    <p class="mt-4 font-medium text-text">Os dados vinculados foram tratados com sucesso.</p>
</div>
