<?php

namespace App\Services;

use App\Models\BillingSetting;

class CertificatePricingService
{
    /**
     * Pay-per-certificate pricing: unit price times count, with a
     * bulk discount applied to the whole total once the count exceeds the
     * admin-configured threshold. Both the discount and threshold are read
     * fresh from BillingSetting so an admin change takes effect on the next
     * order - never re-applied retroactively to an order already created,
     * since callers store this result as a price snapshot.
     *
     * @return array{unit_price: float, quantity: int, subtotal: float, discount_percent: float, discount_amount: float, total: float}
     */
    public function calculate(int $certificateCount): array
    {
        $settings = BillingSetting::current();

        $unitPrice = (float) $settings->price_per_certificate_inr;
        $subtotal = round($unitPrice * $certificateCount, 2);

        $discountPercent = $certificateCount > $settings->bulk_discount_threshold
            ? (float) $settings->bulk_discount_percent
            : 0.0;

        $discountAmount = round($subtotal * $discountPercent / 100, 2);
        $total = round($subtotal - $discountAmount, 2);

        return [
            'unit_price' => $unitPrice,
            'quantity' => $certificateCount,
            'subtotal' => $subtotal,
            'discount_percent' => $discountPercent,
            'discount_amount' => $discountAmount,
            'total' => $total,
        ];
    }
}
