@props(['messages'])

@if ($messages)
    <ul {{ $attributes->merge(['class' => 'font-body-sm text-body-sm text-error space-y-1 mt-xs']) }}>
        {{-- $errors->get('field.*') returns messages nested per matched key
             (e.g. ['field.0' => ['msg']]), not a flat list, so this must be
             flattened before echoing or htmlspecialchars() chokes on an array. --}}
        @foreach (\Illuminate\Support\Arr::flatten((array) $messages) as $message)
            <li>{{ $message }}</li>
        @endforeach
    </ul>
@endif
