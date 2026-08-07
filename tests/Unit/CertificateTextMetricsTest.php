<?php

namespace Tests\Unit;

use App\Services\CertificateTextMetrics;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CertificateTextMetricsTest extends TestCase
{
    #[DataProvider('fabricCharSpacingProvider')]
    public function test_it_converts_fabric_char_spacing_thousandths_of_em_to_pixels(
        float $fabricCharSpacing,
        float $fontSize,
        float $expectedPx,
    ): void {
        $this->assertSame(
            $expectedPx,
            CertificateTextMetrics::letterSpacingPx($fabricCharSpacing, $fontSize),
        );
    }

    public static function fabricCharSpacingProvider(): array
    {
        return [
            'zero stays zero' => [0.0, 24.0, 0.0],
            // Fabric UI value 20 at 24px ≈ 0.48px — NOT 20px.
            'subtle tracking' => [20.0, 24.0, 0.48],
            'one full em' => [1000.0, 16.0, 16.0],
            '100 at 20px is 2px' => [100.0, 20.0, 2.0],
        ];
    }
}
