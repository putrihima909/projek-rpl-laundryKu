<?php

namespace App\Helpers;

class PhoneHelper
{
    /**
     * Normalize a phone number to standard Indonesian E.164 without leading plus (e.g. 6281234567890).
     *
     * @param string|null $phone
     * @return string|null Normalized phone or null if empty
     */
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        // Remove all non-digit characters except leading plus if any
        $cleaned = preg_replace('/[^\d+]/', '', trim($phone));
        if ($cleaned === '') {
            return null;
        }

        // If starts with +62, strip plus
        if (str_starts_with($cleaned, '+62')) {
            $cleaned = substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '+')) {
            // Other country code with plus, strip plus
            $cleaned = substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '0')) {
            // Indonesian local prefix (08...) -> convert to 628...
            $cleaned = '62' . substr($cleaned, 1);
        } elseif (str_starts_with($cleaned, '8')) {
            // Missing country code and leading zero (812...) -> convert to 628...
            $cleaned = '62' . $cleaned;
        }

        return $cleaned;
    }

    /**
     * Validate whether a phone number is a valid Indonesian mobile number.
     * Valid numbers:
     * - Normalized format starts with '628'
     * - Length between 10 and 15 digits
     *
     * @param string|null $phone
     * @return bool
     */
    public static function isValid(?string $phone): bool
    {
        $normalized = self::normalize($phone);
        if ($normalized === null) {
            return false;
        }

        // Must be digits only, start with 628, length between 10 and 15
        if (!preg_match('/^628\d{7,12}$/', $normalized)) {
            return false;
        }

        return true;
    }

    /**
     * Format to international E.164 with leading plus (e.g. +6281234567890).
     *
     * @param string|null $phone
     * @return string|null
     */
    public static function formatInternational(?string $phone): ?string
    {
        $normalized = self::normalize($phone);
        if ($normalized === null) {
            return null;
        }
        return '+' . $normalized;
    }

    /**
     * Format to Indonesian national format (e.g. 0812-3456-7890).
     *
     * @param string|null $phone
     * @return string|null
     */
    public static function formatNational(?string $phone): ?string
    {
        $normalized = self::normalize($phone);
        if ($normalized === null) {
            return null;
        }

        if (str_starts_with($normalized, '62')) {
            $local = '0' . substr($normalized, 2);
            // Format 0812-3456-7890 if length >= 10
            if (strlen($local) >= 10) {
                return substr($local, 0, 4) . '-' . substr($local, 4, 4) . '-' . substr($local, 8);
            }
            return $local;
        }

        return $normalized;
    }
}

