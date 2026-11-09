<?php

namespace App\Enums;

/**
 * Presence checks the attendance engine can combine.
 * Ble is a registered beacon identifier supplied to that engine. It is not an
 * automatic Bluetooth scan. Face, fingerprint, and NFC are reserved plugs:
 * the engine names them and always refuses them.
 */
enum VerificationMethod: string
{
    case Manual = 'manual';
    case Qr = 'qr';
    case Gps = 'gps';
    case Ble = 'ble';
    case Wifi = 'wifi';
    case Otp = 'otp';
    case Device = 'device';
    case Face = 'face';
    case Fingerprint = 'fingerprint';
    case Nfc = 'nfc';

    public function label(): string
    {
        return match ($this) {
            self::Manual => 'Authorised manual',
            self::Qr => 'Dynamic QR',
            self::Gps => 'GPS geofence',
            self::Ble => 'Beacon evidence',
            self::Wifi => 'Wi-Fi corroboration',
            self::Otp => 'One-time code',
            self::Device => 'Device binding',
            self::Face => 'Face (not enabled)',
            self::Fingerprint => 'Fingerprint (not enabled)',
            self::Nfc => 'NFC (not enabled)',
        };
    }

    /**
     * Wi-Fi and device binding can support a decision. They cannot confirm
     * attendance on their own. Reserved methods are never standalone.
     */
    public function isStandalone(): bool
    {
        return match ($this) {
            self::Wifi, self::Device, self::Face, self::Fingerprint, self::Nfc => false,
            default => true,
        };
    }

    public function isReserved(): bool
    {
        return match ($this) {
            self::Face, self::Fingerprint, self::Nfc => true,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    public static function operable(): array
    {
        return array_values(array_map(
            fn (self $method) => $method->value,
            array_filter(self::cases(), fn (self $method) => ! $method->isReserved()),
        ));
    }
}
