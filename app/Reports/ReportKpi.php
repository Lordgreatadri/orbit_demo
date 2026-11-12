<?php

namespace App\Reports;

final class ReportKpi
{
    public function __construct(
        public string $label,
        public string $value,
        public ?string $hint = null,
        public string $icon = 'chart',
        public string $tone = 'neutral',
        public ?string $emphasis = null,
        public ?int $meter = null,
    ) {}

    /**
     * @return array{label: string, value: string, hint: ?string, icon: string, tone: string, emphasis: ?string, meter: ?int}
     */
    public function toArray(): array
    {
        return [
            'label' => $this->label,
            'value' => $this->value,
            'hint' => $this->hint,
            'icon' => $this->icon,
            'tone' => $this->tone,
            'emphasis' => $this->emphasis,
            'meter' => $this->meter,
        ];
    }
}
