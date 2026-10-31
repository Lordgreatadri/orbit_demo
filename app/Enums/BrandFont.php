<?php

namespace App\Enums;

enum BrandFont: string
{
    case InstrumentSans = 'instrument-sans';
    case SystemUi = 'system-ui';
    case Georgia = 'georgia';

    public function label(): string
    {
        return match ($this) {
            self::InstrumentSans => 'Instrument Sans (ORBIT default)',
            self::SystemUi => 'System UI',
            self::Georgia => 'Georgia (serif, documents)',
        };
    }

    public function cssStack(): string
    {
        return match ($this) {
            self::InstrumentSans => "'Instrument Sans', ui-sans-serif, system-ui, sans-serif",
            self::SystemUi => 'ui-sans-serif, system-ui, sans-serif',
            self::Georgia => "Georgia, 'Times New Roman', serif",
        };
    }

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $options = [];

        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
