<?php

namespace App\Enums;

enum OrganizationType: string
{
    case EducationalInstitution = 'educational_institution';
    case Business = 'business';
    case PublicInstitution = 'public_institution';
    case Ngo = 'ngo';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::EducationalInstitution => 'Educational institution',
            self::Business => 'Business',
            self::PublicInstitution => 'Public institution / government agency',
            self::Ngo => 'NGO / non-profit',
            self::Other => 'Other',
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
