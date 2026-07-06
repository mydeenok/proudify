@props([
    'messages' => ['Loading…'],
])

<div
    x-data="{
        messages: @js($messages),
        index: 0,
        timer: null,
        start() {
            this.timer = setInterval(() => {
                this.index = (this.index + 1) % this.messages.length;
            }, 1400);
        },
    }"
    x-init="start()"
    x-on:beforeunload.window="clearInterval(timer)"
    {{ $attributes->merge(['class' => 'flex items-center gap-sm']) }}
>
    <span class="inline-block w-4 h-4 border-2 border-primary/30 border-t-primary rounded-full animate-spin shrink-0"></span>
    <template x-for="(message, i) in messages" :key="i">
        <span x-show="index === i" x-transition.opacity class="font-body-sm text-body-sm text-on-surface-variant">
            <span x-text="message"></span>
        </span>
    </template>
</div>
