@php
    $apiBaseUrl = rtrim(config('app.url'), '/').'/api/v1';
@endphp

{{-- Generate new key --}}
<div class="col-span-1 md:col-span-12 card-surface p-lg shadow-card">
    <h2 class="font-headline-md text-headline-md text-on-surface">API Access</h2>
    <p class="font-body-sm text-body-sm text-on-surface-variant mt-xs mb-lg">
        Generate a key to list and fetch your own issued certificates from your own systems.
    </p>

    @if (session('status') === 'api-token-created' && session('plainTextToken'))
        <div
            x-data="{ revealed: false, copied: false }"
            class="bg-emerald-50 border border-emerald-200 rounded-xl p-lg space-y-sm mb-lg"
        >
            <div class="flex items-center gap-xs">
                <span class="material-symbols-outlined text-emerald-700 text-[20px]">check_circle</span>
                <p class="font-label-md text-label-md text-emerald-800">New API key generated</p>
            </div>
            <p class="font-body-sm text-body-sm text-emerald-900">
                Copy this now &mdash; for your security, it won't be shown again.
            </p>
            <div class="flex items-center gap-xs">
                <code
                    class="flex-1 bg-white border border-emerald-200 rounded-lg px-md py-sm font-body-sm text-body-sm text-on-surface break-all"
                    :class="revealed ? '' : 'blur-sm select-none'"
                >{{ session('plainTextToken') }}</code>
                <button
                    type="button"
                    @click="revealed = !revealed"
                    class="shrink-0 w-10 h-10 flex items-center justify-center rounded-lg border border-emerald-200 bg-white text-emerald-800 hover:bg-emerald-50 transition-colors"
                    :title="revealed ? 'Hide' : 'Reveal'"
                >
                    <span class="material-symbols-outlined text-[18px]" x-text="revealed ? 'visibility_off' : 'visibility'"></span>
                </button>
                <button
                    type="button"
                    @click="navigator.clipboard.writeText(@js(session('plainTextToken'))); copied = true; setTimeout(() => copied = false, 2000)"
                    class="shrink-0 h-10 px-md flex items-center gap-xs rounded-lg border border-emerald-200 bg-white text-emerald-800 hover:bg-emerald-50 font-label-sm text-label-sm transition-colors"
                >
                    <span class="material-symbols-outlined text-[16px]" x-text="copied ? 'check' : 'content_copy'"></span>
                    <span x-text="copied ? 'Copied' : 'Copy'"></span>
                </button>
            </div>
        </div>
    @endif

    <form method="POST" action="{{ route('profile.api-tokens.store') }}" class="space-y-md">
        @csrf
        <div class="grid grid-cols-1 sm:grid-cols-3 gap-md">
            <div>
                <x-input-label for="token_name" value="Key name" />
                <x-text-input id="token_name" name="name" type="text" placeholder="e.g. My CRM integration" class="mt-1 block w-full" required />
                <x-input-error :messages="$errors->get('name')" class="mt-xs" />
            </div>
            <div>
                <x-input-label for="website_name" value="Website / app name" />
                <x-text-input id="website_name" name="website_name" type="text" placeholder="e.g. Acme CRM (optional)" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('website_name')" class="mt-xs" />
            </div>
            <div>
                <x-input-label for="website_url" value="Website URL" />
                <x-text-input id="website_url" name="website_url" type="url" placeholder="https://example.com (optional)" class="mt-1 block w-full" />
                <x-input-error :messages="$errors->get('website_url')" class="mt-xs" />
            </div>
        </div>
        <div class="flex items-center justify-between gap-md pt-xs">
            <p class="font-body-sm text-body-sm text-on-surface-variant/70">
                The website fields are labels only &mdash; they don't restrict where the key can be used from.
            </p>
            <x-primary-button class="shrink-0">
                <span class="material-symbols-outlined text-[18px] mr-1">key</span>
                Generate
            </x-primary-button>
        </div>
    </form>
</div>

{{-- Existing keys --}}
<div class="col-span-1 md:col-span-12 card-surface p-lg shadow-card">
    <div class="flex items-center justify-between mb-md">
        <h2 class="font-headline-md text-headline-md text-on-surface">Your keys</h2>
        <span class="font-body-sm text-body-sm text-on-surface-variant">{{ $apiTokens->count() }} total</span>
    </div>

    @if ($apiTokens->isEmpty())
            <p class="font-body-sm text-body-sm text-on-surface-variant/70 p-2xl text-center">
                No API keys yet &mdash; generate one above to get started.
            </p>
        @else
            <div class="overflow-x-auto">
                <table class="data-table w-full text-left border-collapse min-w-[720px]">
                    <thead>
                        <tr>
                            <th>Key</th>
                            <th>Website</th>
                            <th>Status</th>
                            <th>Created</th>
                            <th>Last used</th>
                            <th class="text-right pr-lg">Actions</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach ($apiTokens as $token)
                            <tr>
                                <td>
                                    <div class="flex items-center gap-sm">
                                        <div class="w-8 h-8 rounded-full bg-surface-container-high text-primary flex items-center justify-center shrink-0 border border-outline-variant">
                                            <span class="material-symbols-outlined text-[16px]">key</span>
                                        </div>
                                        <span class="font-label-md text-label-md text-on-surface font-semibold">{{ $token->name }}</span>
                                    </div>
                                </td>
                                <td>
                                    @if ($token->website_name || $token->website_url)
                                        <div class="flex flex-col">
                                            <span class="text-on-surface">{{ $token->website_name ?? '—' }}</span>
                                            @if ($token->website_url)
                                                <a href="{{ $token->website_url }}" target="_blank" rel="noopener" class="text-secondary hover:underline text-[11px] truncate max-w-[180px]">{{ $token->website_url }}</a>
                                            @endif
                                        </div>
                                    @else
                                        <span class="text-on-surface-variant/70">—</span>
                                    @endif
                                </td>
                                <td>
                                    @if ($token->last_used_at)
                                        <x-status-badge status="active">In use</x-status-badge>
                                    @else
                                        <x-status-badge status="default">Unused</x-status-badge>
                                    @endif
                                </td>
                                <td>{{ $token->created_at->format('d M Y') }}</td>
                                <td>
                                    @if ($token->last_used_at)
                                        {{ $token->last_used_at->diffForHumans() }}
                                    @else
                                        <span class="text-on-surface-variant/70">Never</span>
                                    @endif
                                </td>
                                <td class="text-right pr-lg">
                                    <form method="POST" action="{{ route('profile.api-tokens.destroy', $token->id) }}" onsubmit="return confirm('Revoke this API key? Anything using it will stop working immediately.');" class="inline">
                                        @csrf
                                        @method('DELETE')
                                        <button type="submit" class="h-8 px-3 text-[11px] font-medium text-error bg-surface border border-error/30 rounded-md hover:bg-error/5 transition-colors">Revoke</button>
                                    </form>
                                </td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    </div>

{{-- API reference — kept as separate cards so short content (Base URL /
     Example) doesn't sit in a card sized for the longer Endpoints list. --}}
<div class="col-span-1 md:col-span-6 card-surface p-lg shadow-card">
    <h2 class="font-headline-md text-headline-md text-on-surface mb-md">Base URL</h2>
    <code class="block bg-surface-container-low border border-outline-variant rounded-lg px-md py-sm font-body-sm text-body-sm text-on-surface break-all">{{ $apiBaseUrl }}</code>
</div>

<div x-data="{ copied: false }" class="col-span-1 md:col-span-6 card-surface p-lg shadow-card">
    <h2 class="font-headline-md text-headline-md text-on-surface mb-md">Example request</h2>
    <div class="flex items-start gap-xs">
        <code class="flex-1 block bg-surface-container-low border border-outline-variant rounded-lg px-md py-sm font-body-sm text-body-sm text-on-surface break-all">curl -H "Authorization: Bearer YOUR_KEY" {{ $apiBaseUrl }}/certificates</code>
        <button
            type="button"
            @click="navigator.clipboard.writeText(@js('curl -H "Authorization: Bearer YOUR_KEY" '.$apiBaseUrl.'/certificates')); copied = true; setTimeout(() => copied = false, 2000)"
            class="shrink-0 w-10 h-10 flex items-center justify-center rounded-lg border border-outline-variant bg-surface text-on-surface-variant hover:bg-surface-container-low transition-colors"
            title="Copy"
        >
            <span class="material-symbols-outlined text-[18px]" x-text="copied ? 'check' : 'content_copy'"></span>
        </button>
    </div>
</div>

<div class="col-span-1 md:col-span-12 card-surface p-lg shadow-card">
    <h2 class="font-headline-md text-headline-md text-on-surface mb-md">Endpoints</h2>
    <div class="divide-y divide-outline-variant">
        @foreach ([
            ['GET', '/certificates', 'List your certificates (paginated)'],
            ['GET', '/certificates/{id}', 'Fetch one, by uuid or Credential ID'],
            ['GET', '/certificates/{id}/image', 'Certificate image'],
            ['GET', '/certificates/{id}/download', 'Certificate PDF'],
            ['GET', '/certificates/{id}/qr', 'QR code image'],
        ] as [$method, $path, $description])
            <div class="flex items-center gap-md px-lg py-sm">
                <span class="font-label-sm text-label-sm font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 rounded px-2 py-0.5 shrink-0 w-14 text-center">{{ $method }}</span>
                <code class="font-body-sm text-body-sm text-on-surface shrink-0 w-56">{{ $path }}</code>
                <span class="font-body-sm text-body-sm text-on-surface-variant">{{ $description }}</span>
            </div>
        @endforeach
    </div>
</div>
