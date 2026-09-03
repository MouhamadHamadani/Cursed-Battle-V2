@props(['completesAt', 'event' => 'activity-completed'])

{{--
    Presentational only (ADR-002 Action Item 5): it formats a server-provided
    ISO timestamp and, when the clock runs out, asks the server to re-check.
    It never decides that a session is complete — resolvePending() does, and
    the server stays the source of truth.

    Also drives the regen countdowns in the status bar, which need a different
    wake-up event than a finished work/train session — hence the `event` prop.
    The name is historical; this is a general server-anchored countdown.
--}}
<span
    x-data="{
        remaining: 0,
        fired: false,
        timer: null,
        init() {
            this.tick();
            // Sub-second tick so two countdowns on the same page (status bar
            // and page panel) never sit a second apart because their intervals
            // happen to fire at different phases.
            this.timer = setInterval(() => this.tick(), 250);
        },
        destroy() {
            clearInterval(this.timer);
        },
        tick() {
            const endsAt = new Date('{{ $completesAt->toIso8601String() }}');
            // ceil, not round: the clock reads 00:00 only once the session has
            // genuinely ended, so it never reports completion up to half a
            // second early.
            this.remaining = Math.max(0, Math.ceil((endsAt - new Date()) / 1000));

            if (this.remaining === 0 && ! this.fired) {
                this.fired = true;
                $wire.$dispatch('{{ $event }}');
            }
        },
        get display() {
            const h = Math.floor(this.remaining / 3600);
            const m = Math.floor(this.remaining % 3600 / 60);
            const s = this.remaining % 60;
            const mm = String(m).padStart(2, '0');
            const ss = String(s).padStart(2, '0');
            // Hours only once there are any: a refilling energy bar can be
            // hours out (50 max energy at 1/tick is over four), and 250:00
            // reads as a bug rather than a duration.
            return h > 0 ? h + ':' + mm + ':' + ss : mm + ':' + ss;
        },
    }"
    x-text="display"
    {{ $attributes->merge(['class' => 'tabular-nums']) }}
>--:--</span>
