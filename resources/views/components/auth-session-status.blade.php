@props(['status'])

@if ($status)
    <div {{ $attributes->merge(['class' => 'font-body-sm text-body-sm text-emerald-700 bg-emerald-500/10 rounded-lg px-md py-sm']) }}>
        {{ $status }}
    </div>
@endif
