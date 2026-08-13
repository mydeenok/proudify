<?php

namespace App\Support;

use Carbon\Carbon;
use PhpOffice\PhpSpreadsheet\Shared\Date as ExcelDate;

/**
 * Bulk-upload spreadsheets hand back dates in several shapes depending on
 * how the source file was authored: Excel's own serial-number encoding, or
 * one of a handful of common string formats. Ported from the reference
 * app's date-parsing logic, which handles this correctly.
 */
class DateParser
{
    private const STRING_FORMATS = ['d-m-Y', 'Y-m-d', 'd/m/Y', 'm/d/Y'];

    public static function parse(mixed $value): ?Carbon
    {
        if ($value === null || $value === '') {
            return null;
        }

        if (is_numeric($value)) {
            try {
                return Carbon::instance(ExcelDate::excelToDateTimeObject((float) $value));
            } catch (\Throwable) {
                // Fall through to string parsing below.
            }
        }

        $value = trim((string) $value);

        foreach (self::STRING_FORMATS as $format) {
            $date = \DateTime::createFromFormat('!'.$format, $value);

            if ($date === false) {
                continue;
            }

            // DateTime::createFromFormat() is lenient by default and
            // silently overflows an invalid calendar date instead of
            // rejecting it - "31/02/2024" (Feb has no 31st) quietly
            // becomes 2 March with no error, so a bulk-upload CSV typo
            // would otherwise issue a certificate with a wrong date and no
            // validation failure anywhere. A genuinely valid date always
            // round-trips back through the same format exactly; one that
            // overflowed won't, so this rejects it and lets the next
            // candidate format (or the Carbon::parse() fallback below) try
            // instead.
            //
            // This does NOT resolve the separate d/m/Y vs m/d/Y ambiguity
            // for a date where both readings are validly-formed (e.g.
            // "03/04/2024") - d/m/Y is tried first and wins by convention
            // when both are possible, since there's no way to know the
            // source spreadsheet's locale from the string alone.
            if ($date->format($format) === $value) {
                return Carbon::instance($date);
            }
        }

        try {
            return Carbon::parse($value);
        } catch (\Throwable) {
            return null;
        }
    }
}
