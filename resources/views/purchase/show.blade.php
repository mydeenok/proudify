@php $currencySymbol = $currency === 'INR' ? '₹' : '$'; @endphp
<x-layouts.user-shell title="Checkout">
    <div class="max-w-[960px] mx-auto grid grid-cols-1 lg:grid-cols-2 gap-gutter">
        <div>
            <x-page-header
                title="Complete your purchase"
                description="You're subscribing to the {{ $subscription->name }} plan."
            />

            <div class="card-surface p-lg shadow-card-sm space-y-md">
                <div class="flex items-center gap-md pb-md border-b border-outline-variant">
                    <div class="w-12 h-12 rounded-lg bg-primary-container/10 text-primary-container flex items-center justify-center">
                        <span class="material-symbols-outlined">stars</span>
                    </div>
                    <div>
                        <p class="font-headline-md text-headline-md text-on-surface">{{ $subscription->name }}</p>
                        <p class="font-body-sm text-body-sm text-on-surface-variant capitalize">{{ $period }} billing</p>
                    </div>
                </div>

                <div class="space-y-sm">
                    <div class="flex justify-between items-center py-2">
                        <span class="font-body-md text-body-md text-on-surface-variant">Subtotal</span>
                        <span class="font-label-md text-label-md text-on-surface">{{ $currencySymbol }}{{ number_format($amount, 2) }}</span>
                    </div>
                    <div class="flex justify-between items-center py-2 border-t border-outline-variant pt-md">
                        <span class="font-label-md text-label-md text-on-surface font-semibold">Total due today</span>
                        <span class="font-headline-lg text-headline-lg text-on-surface font-bold">{{ $currencySymbol }}{{ number_format($amount, 2) }}</span>
                    </div>
                </div>
            </div>
        </div>

        <div class="card-surface p-lg shadow-card h-fit">
            <h2 class="font-headline-md text-headline-md text-on-surface mb-md">Payment</h2>

            <div id="checkout-error" class="hidden bg-error/10 text-error rounded-lg px-md py-sm font-body-sm text-body-sm mb-lg border border-error/20"></div>

            <button id="pay-button" type="button" class="btn-primary w-full">
                <span id="pay-button-icon" class="material-symbols-outlined text-[18px]">lock</span>
                <span id="pay-button-label">Pay with Razorpay</span>
            </button>

            <div class="mt-lg pt-lg border-t border-outline-variant flex items-center gap-sm text-on-surface-variant">
                <span class="material-symbols-outlined text-[18px]" style="font-variation-settings: 'FILL' 1;">shield</span>
                <p class="font-body-sm text-body-sm">256-bit AES encryption. Payments processed securely by Razorpay.</p>
            </div>
        </div>
    </div>

    <script src="https://checkout.razorpay.com/v1/checkout.js"></script>
    <script>
        const payButton = document.getElementById('pay-button');
        const payButtonLabel = document.getElementById('pay-button-label');
        const errorBox = document.getElementById('checkout-error');
        const defaultLabel = payButtonLabel.textContent;

        function setBusy(isBusy, label) {
            payButton.disabled = isBusy;
            payButton.classList.toggle('opacity-70', isBusy);
            payButton.classList.toggle('cursor-not-allowed', isBusy);
            payButtonLabel.textContent = label ?? defaultLabel;
        }

        function showError(message) {
            errorBox.textContent = message;
            errorBox.classList.remove('hidden');
        }

        payButton.addEventListener('click', async function () {
            if (payButton.disabled) return;

            errorBox.classList.add('hidden');
            setBusy(true, 'Starting checkout…');

            let order;
            try {
                const orderResponse = await fetch('{{ route('purchase.create-order', $subscription) }}?period={{ $period }}', {
                    method: 'POST',
                    headers: {
                        'X-CSRF-TOKEN': '{{ csrf_token() }}',
                        Accept: 'application/json',
                    },
                });
                order = await orderResponse.json();

                if (!orderResponse.ok) {
                    showError(order.message ?? 'Unable to start checkout.');
                    setBusy(false);
                    return;
                }
            } catch (error) {
                showError('Unable to reach the server. Please check your connection and try again.');
                setBusy(false);
                return;
            }

            const razorpay = new Razorpay({
                key: order.key,
                order_id: order.id,
                amount: order.amount,
                currency: order.currency,
                name: '{{ config('app.name') }}',
                description: '{{ $subscription->name }} ({{ $period }})',
                theme: { color: '#d92727' },
                prefill: {
                    name: '{{ auth()->user()->name }}',
                    email: '{{ auth()->user()->email }}',
                },
                modal: {
                    ondismiss: function () {
                        setBusy(false);
                    },
                },
                handler: async function (response) {
                    setBusy(true, 'Verifying payment…');

                    try {
                        const verifyResponse = await fetch('{{ route('purchase.verify-payment', $subscription) }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'X-CSRF-TOKEN': '{{ csrf_token() }}',
                                Accept: 'application/json',
                            },
                            body: JSON.stringify({
                                razorpay_order_id: response.razorpay_order_id,
                                razorpay_payment_id: response.razorpay_payment_id,
                                razorpay_signature: response.razorpay_signature,
                                period: '{{ $period }}',
                            }),
                        });
                        const result = await verifyResponse.json();

                        if (verifyResponse.ok && result.redirect) {
                            window.location.href = result.redirect;
                            return;
                        }

                        showError(result.message ?? 'Payment verification failed.');
                    } catch (error) {
                        showError('Unable to reach the server to verify your payment. Please contact support if you were charged.');
                    } finally {
                        setBusy(false);
                    }
                },
            });

            razorpay.open();
        });
    </script>
</x-layouts.user-shell>
