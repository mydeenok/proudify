@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'font-body-sm text-body-sm text-error space-y-1 mt-xs']) }}>
        @foreach ((array) $messages as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
