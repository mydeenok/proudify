@props([
    'checked' => false,
    'action',
    'method' => 'PATCH',
    'label' => null,
    'name' => 'toggle',
])

<form method="POST" action="{{ $action }}" {{ $attributes->only('class') }}>
    @csrf
    @method($method)
    <label class="group inline-flex items-center gap-2 cursor-pointer select-none">
        <input
            type="checkbox"
            name="{{ $name }}"
            value="1"
            class="sr-only"
            @checked($checked)
            onchange="this.form.submit()"
        >
        <span
            class="relative inline-flex h-6 w-10 shrink-0 rounded-full bg-secondary-container transition-colors group-has-[:checked]:bg-primary"
            aria-hidden="true"
        >
            <span class="absolute top-1 left-1 h-4 w-4 rounded-full bg-white shadow transition-transform group-has-[:checked]:translate-x-4"></span>
        </span>
        @if ($label !== null)
            <span class="font-label-sm text-label-sm text-on-surface-variant">{{ $label }}</span>
        @endif
    </label>
</form>
