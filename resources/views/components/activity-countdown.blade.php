@props(['completesAt'])

{{--
    Presentational only (ADR-002 Action Item 5): it formats a server-provided
    ISO timestamp and, when the clock runs out, asks the server to re-check.
    It never decides that a session is complete — resolvePending() does, and
    the server stays the source of truth.
--}}
<span
    x-data="{
        remaining: 0,
        fired: false,
        timer: null,
        init() {
            this.tick();
            this.timer = setInterval(() => this.tick(), 1000);
        },
        destroy() {
            clearInterval(this.timer);
        },
        tick() {
            const endsAt = new Date('{{ $completesAt->toIso8601String() }}');
            this.remaining = Math.max(0, Math.round((endsAt - new Date()) / 1000));

            if (this.remaining === 0 && ! this.fired) {
                this.fired = true;
                $wire.$dispatch('activity-completed');
            }
        },
        get display() {
            const m = Math.floor(this.remaining / 60);
            const s = this.remaining % 60;
            return String(m).padStart(2, '0') + ':' + String(s).padStart(2, '0');
        },
    }"
    x-text="display"
    {{ $attributes->merge(['class' => 'tabular-nums']) }}
>--:--</span>
