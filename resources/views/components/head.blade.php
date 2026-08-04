@props(['title' => null, 'suffix' => null])

<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<meta name="csrf-token" content="{{ csrf_token() }}">
<link rel="icon" type="image/png" href="{{ asset('images/favicon.png') }}">
<link rel="apple-touch-icon" href="{{ asset('images/favicon.png') }}">
<title>
    @if ($title)
        {{ $title }}{{ $suffix ? " $suffix" : '' }} -
    @endif
    {{ config('app.name') }}
</title>
@vite(['resources/css/app.css', 'resources/js/app.js'])
@livewireStyles
@livewireScriptConfig
