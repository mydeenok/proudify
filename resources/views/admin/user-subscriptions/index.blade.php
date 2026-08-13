<x-layouts.admin-shell title="User Subscriptions">
    <x-page-header
        title="User Subscriptions"
        description="Historical subscription records. Certificates are billed per unit now — nothing here restricts what a user can issue."
    />

    {{--
        Subscription purchasing was retired (see PurchaseController) once
        it became clear nothing in certificate/bulk issuance ever reads
        SubscriptionQuotaService - editing a limit, extending, or
        cancelling a subscription below previously changed nothing about
        what the user could actually do, while "Cancel" still sent them a
        real "your subscription has been cancelled" email implying
        otherwise. This banner makes that explicit instead of leaving
        admins with false confidence that these controls restrict anyone.
    --}}
    <div class="mb-lg rounded-lg border border-amber-300 bg-amber-50 px-md py-sm text-body-sm text-amber-900">
        <strong>Informational only.</strong> Certificates are billed pay-per-certificate now (see Certificate Orders). Editing, extending, or cancelling a subscription below does not change any user's ability to issue certificates.
    </div>

    <livewire:admin.user-subscriptions-table />
</x-layouts.admin-shell>
