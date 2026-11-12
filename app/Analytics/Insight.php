<?php

namespace App\Analytics;

/**
 * A deterministic operational observation. It is calculated in the application
 * from stored metrics. It is not a completion from an external model.
 */
final class Insight
{
    public function __construct(
        public string $code,
        public string $title,
        public string $body,
        public string $period,
        public string $metric,
        public string $action,
        public ?string $actionUrl,
        public string $band = 'planning',
    ) {}

    /**
     * @return array{code: string, title: string, body: string, period: string, metric: string, action: string, action_url: ?string, source: string, band: string}
     */
    public function toArray(): array
    {
        return [
            'code' => $this->code,
            'title' => $this->title,
            'body' => $this->body,
            'period' => $this->period,
            'metric' => $this->metric,
            'action' => $this->action,
            'action_url' => $this->actionUrl,
            'source' => 'deterministic',
            'band' => $this->band === 'action' ? 'action' : 'planning',
        ];
    }
}
