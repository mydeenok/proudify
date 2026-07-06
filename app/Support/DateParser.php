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

            if ($date !== false) {
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
