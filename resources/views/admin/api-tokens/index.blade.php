<x-layouts.admin-shell title="API Keys">
    <x-page-header
        title="API Keys"
        description="Every self-service key tenants have generated to pull their own certificate data programmatically."
    />

    <div class="card-surface p-lg shadow-card mb-lg">
        <h3 class="font-label-md text-label-md text-on-surface uppercase tracking-wide mb-sm">API Reference</h3>
        @php $apiBaseUrl = rtrim(config('app.url'), '/').'/api/v1'; @endphp
        <div class="grid grid-cols-1 md:grid-cols-2 gap-md">
            <div>
                <p class="font-body-sm text-body-sm text-on-surface-variant">Base URL</p>
                <code class="font-body-sm text-body-sm text-on-surface">{{ $apiBaseUrl }}</code>
                <div class="font-body-sm text-body-sm text-on-surface space-y-1 mt-sm">
                    <p><code>GET /certificates</code> &mdash; list a tenant's certificates</p>
                    <p><code>GET /certificates/{id}</code> &mdash; by uuid or Credential ID</p>
                    <p><code>GET /certificates/{id}/image|download|qr</code> &mdash; assets</p>
                </div>
            </div>
            <div>
                <p class="font-body-sm text-body-sm text-on-surface-variant mb-xs">Every key is scoped to its own tenant only &mdash; the "Website" column below is a self-reported label, not an enforced restriction.</p>
                <code class="block bg-surface-container-low border border-outline-variant rounded-lg px-md py-sm font-body-sm text-body-sm text-on-surface break-all">curl -H "Authorization: Bearer TENANT_KEY" {{ $apiBaseUrl }}/certificates</code>
            </div>
        </div>
    </div>

    <livewire:admin.api-tokens-table />
</x-layouts.admin-shell>
