<?php

namespace App\Support;

use App\Models\Flat;

class DuesReminder
{
    /**
     * Sanitize a phone number according to Bangladesh conventions.
     */
    public static function sanitizePhone(?string $phone): string
    {
        if ($phone === null) {
            return '';
        }

        $cleaned = preg_replace('/[\s\-\(\)\+]/', '', $phone);

        if ($cleaned === null || $cleaned === '') {
            return '';
        }

        if (str_starts_with($cleaned, '01') && strlen($cleaned) === 11) {
            return '88'.$cleaned;
        }

        if (str_starts_with($cleaned, '8801')) {
            return $cleaned;
        }

        if (str_starts_with($cleaned, '00')) {
            return ltrim($cleaned, '0');
        }

        return $cleaned;
    }

    /**
     * Generate reminder details for a flat's outstanding balance.
     *
     * @return array{
     *     clean_phone: string,
     *     sms_text: string,
     *     whatsapp_url: string
     * }
     */
    public static function for(Flat $flat, string $dueAmount, ?string $locale = null, ?string $phone = null): array
    {
        $targetLocale = $locale ?? app()->getLocale();

        $owner = $flat->owner;
        $cleanPhone = static::sanitizePhone($phone ?? ($owner !== null ? $owner->phone : null));
        $ownerName = $owner !== null ? $owner->name : '';

        $building = $flat->building;
        $buildingName = '';
        if ($building !== null) {
            $buildingName = $targetLocale === 'bn'
                ? ($building->name_bn ?: $building->name)
                : ($building->name ?: ($building->name_bn ?? ''));
        }

        try {
            $url = route('flats.statement', $flat);
        } catch (\Throwable) {
            $url = '';
        }

        $smsText = __('reminders.dues_message', [
            'name' => $ownerName,
            'building' => $buildingName,
            'flat' => $flat->number,
            'amount' => $dueAmount,
            'url' => $url,
        ], $targetLocale);

        $whatsappUrl = $cleanPhone !== ''
            ? 'https://wa.me/'.$cleanPhone.'?text='.rawurlencode($smsText)
            : '';

        return [
            'clean_phone' => $cleanPhone,
            'sms_text' => $smsText,
            'whatsapp_url' => $whatsappUrl,
        ];
    }
}
