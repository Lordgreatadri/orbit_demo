<?php

namespace App\Reports;

final class ReportDefinition
{
    /**
     * @param  list<string>  $filters
     * @param  list<ReportFormat>  $formats
     */
    public function __construct(
        public string $key,
        public string $title,
        public string $description,
        public ReportAudience $audience,
        public string $category,
        public array $filters,
        public array $formats,
        public bool $paginated = false,
        public ?string $organisationPerspectiveOnly = null,
    ) {}

    /**
     * @return list<string>
     */
    public function formatValues(): array
    {
        return array_map(fn (ReportFormat $format): string => $format->value, $this->formats);
    }

    public function supports(ReportFormat $format): bool
    {
        foreach ($this->formats as $supported) {
            if ($supported === $format) {
                return true;
            }
        }

        return false;
    }
}
