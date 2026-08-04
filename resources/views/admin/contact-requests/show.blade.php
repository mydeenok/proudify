@php
    $badgeStatus = match ($contactRequest->status) {
        'open' => 'pending',
        'in_progress' => 'active',
        'closed' => 'expired',
        default => 'default',
    };
@endphp

<x-layouts.admin-shell title="Contact Request">
    <div class="mb-md">
        <a href="{{ route('admin.contact-requests.index') }}" wire:navigate class="inline-flex items-center gap-xs font-label-md text-label-md text-on-surface-variant hover:text-primary">
            <span class="material-symbols-outlined text-[18px]">arrow_back</span>
            Back to inbox
        </a>
    </div>

    <header class="mb-lg space-y-sm">
        <div class="flex flex-wrap items-center gap-sm">
            <x-status-badge :status="$badgeStatus">
                {{ str_replace('_', ' ', ucfirst($contactRequest->status)) }}
            </x-status-badge>
            <span class="font-body-sm text-body-sm text-on-surface-variant">
                {{ $contactRequest->created_at->format('d M Y, h:i A') }}
            </span>
        </div>
        <h1 class="font-headline-lg text-headline-lg text-on-surface tracking-tight break-words max-w-4xl">
            {{ $contactRequest->subject }}
        </h1>
        <p class="font-body-md text-body-md text-on-surface-variant">
            From {{ $contactRequest->name }}
            @if ($contactRequest->organization)
                · {{ $contactRequest->organization }}
            @endif
        </p>
    </header>

    <div class="grid grid-cols-1 lg:grid-cols-3 gap-gutter items-start">
        <section class="lg:col-span-2 card-surface shadow-card-sm p-lg">
            <h2 class="font-label-md text-label-md font-semibold text-on-surface-variant uppercase tracking-wider mb-md">Message</h2>
            <div class="font-body-md text-body-md text-on-surface whitespace-pre-wrap leading-relaxed">
                {{ $contactRequest->message }}
            </div>
        </section>

        <aside class="flex flex-col gap-md">
            <div class="card-surface shadow-card-sm p-lg">
                <h2 class="font-label-md text-label-md font-semibold text-on-surface-variant uppercase tracking-wider mb-md">Sender</h2>
                <dl class="space-y-md">
                    <div>
                        <dt class="field-label mb-0">Name</dt>
                        <dd class="font-body-md text-body-md text-on-surface font-semibold">{{ $contactRequest->name }}</dd>
                    </div>
                    <div>
                        <dt class="field-label mb-0">Email</dt>
                        <dd>
                            <a href="mailto:{{ $contactRequest->email }}" class="font-body-md text-body-md text-on-surface hover:text-primary underline-offset-2 hover:underline break-all">
                                {{ $contactRequest->email }}
                            </a>
                        </dd>
                    </div>
                    @if ($contactRequest->organization)
                        <div>
                            <dt class="field-label mb-0">Organization</dt>
                            <dd class="font-body-md text-body-md text-on-surface">{{ $contactRequest->organization }}</dd>
                        </div>
                    @endif
                    @if ($contactRequest->user)
                        <div>
                            <dt class="field-label mb-0">Linked account</dt>
                            <dd class="font-body-md text-body-md text-on-surface break-all">{{ $contactRequest->user->email }}</dd>
                        </div>
                    @endif
                    @if ($contactRequest->handler)
                        <div>
                            <dt class="field-label mb-0">Last handled by</dt>
                            <dd class="font-body-md text-body-md text-on-surface">{{ $contactRequest->handler->name }}</dd>
                        </div>
                    @endif
                </dl>
            </div>

            <form method="POST" action="{{ route('admin.contact-requests.status', $contactRequest) }}" class="card-surface shadow-card-sm p-lg space-y-md">
                @csrf
                @method('PATCH')
                <h2 class="font-label-md text-label-md font-semibold text-on-surface-variant uppercase tracking-wider">Update status</h2>
                <x-filter-select name="status" class="w-full h-10">
                    <option value="open" @selected($contactRequest->status === 'open')>Open</option>
                    <option value="in_progress" @selected($contactRequest->status === 'in_progress')>In progress</option>
                    <option value="closed" @selected($contactRequest->status === 'closed')>Closed</option>
                </x-filter-select>
                <x-primary-button class="w-full">Save status</x-primary-button>
            </form>
        </aside>
    </div>
</x-layouts.admin-shell>
