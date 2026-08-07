<?php

namespace App\Services;

/**
 * Shared conversion helpers for text style values that Fabric.js and the
 * two server-side renderers (LayoutToHtmlRenderer / CertificateCanvas-
 * RenderService) would otherwise interpret differently.
 *
 * Fabric's Textbox.charSpacing is documented as "additional space between
 * characters in thousandths of an em" — so a UI value of 20 means 0.02em,
 * not 20 CSS pixels. Both HTML and Skia need real pixels; converting at
 * this single boundary keeps the three surfaces visually aligned.
 */
class CertificateTextMetrics
{
    /**
     * Convert a Fabric charSpacing value into CSS/canvas pixels for the
     * given design-space font size (before any print DPI scale).
     */
    public static function letterSpacingPx(float $fabricCharSpacing, float $fontSize): float
    {
        return round(($fabricCharSpacing / 1000) * $fontSize, 2);
    }
}
