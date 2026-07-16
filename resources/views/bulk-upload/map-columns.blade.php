@php
$fields = [
    'title' => 'Certificate Title',
    'recipient_name' => 'Recipient Name',
    'recipient_email' => 'Recipient Email',
    'description' => 'Description (optional)',
    'date_of_issue' => 'Issue Date',
    'date_of_expiry' => 'Expiry Date (optional)',
];
$required = ['title', 'recipient_name', 'recipient_email', 'date_of_issue'];

$isAdmin = auth()->user()->isAdmin();
$shell = $isAdmin ? 'layouts.admin-shell' : 'layouts.contextual-shell';
@endphp

<x-dynamic-component :component="$shell" title="Map Columns">
    <div class="max-w-3xl mx-auto">
        <div class="mb-xl text-center">
            <h1 class="font-headline-xl text-headline-xl text-on-background mb-xs">Bulk Issue Certificates</h1>
            <p class="font-body-md text-body-md text-on-surface-variant">Match your spreadsheet columns to certificate fields.</p>
        </div>

        <x-bulk-upload-stepper current="Map Columns" />

        <form method="POST" action="{{ route('bulk-upload.map-columns.store', $batch) }}" class="card-surface p-lg bento-shadow-sm space-y-md">
            @csrf

            @foreach ($fields as $field => $label)
                <div>
                    <x-input-label :for="$field" :value="$label" />
                    @php $defaultIndex = old("mapping.$field", $suggestedMapping[$field] ?? null); @endphp
                    <select id="{{ $field }}" name="mapping[{{ $field }}]" @required(in_array($field, $required))
                        class="form-input">
                        @if (! in_array($field, $required) || $defaultIndex === null)
                            <option value="" @selected($defaultIndex === null)>{{ in_array($field, $required) ? '— Select a column —' : '— None —' }}</option>
                        @endif
                        @foreach ($headers as $index => $header)
                            <option value="{{ $index }}" @selected($defaultIndex !== null && (int) $defaultIndex === $index)>{{ $header ?: 'Column '.($index + 1) }}</option>
                        @endforeach
                    </select>
                    <x-input-error :messages="$errors->get(\"mapping.$field\")" />
                </div>
            @endforeach

            <x-input-error :messages="$errors->get('file')" />

            <div class="pt-md flex justify-between items-center border-t border-outline-variant">
                <a href="{{ $isAdmin ? route('admin.bulk-upload.create') : route('bulk-upload.create', ['template' => $batch->template_id]) }}" class="btn-secondary">Back</a>
                <x-primary-button class="btn-primary">
                    Continue to Review
                    <span class="material-symbols-outlined text-[18px]">arrow_forward</span>
                </x-primary-button>
            </div>
        </form>
    </div>
</x-dynamic-component>
