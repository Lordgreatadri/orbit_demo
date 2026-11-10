<?php

namespace App\Workforce\Communication;

use App\Models\NotificationTemplate;
use App\Models\Organization;

/**
 * Replaces {{placeholders}} with server-supplied values. Unknown tokens are
 * removed. The replacement is text only and never evaluated as code.
 */
final class NotificationCopy
{
    /**
     * @param  array<string, scalar|null>  $vars
     * @return array{0: string, 1: string}
     */
    public static function compose(Organization $organization, string $event, string $subject, string $body, array $vars): array
    {
        $template = NotificationTemplate::withoutTenantScope()
            ->where('organization_id', $organization->getKey())
            ->where('event', $event)
            ->where('channel', 'all')
            ->first();

        return self::apply($template, $subject, $body, $vars);
    }

    /**
     * @param  array<string, scalar|null>  $vars
     * @return array{0: string, 1: string}
     */
    public static function apply(?NotificationTemplate $template, string $subject, string $body, array $vars): array
    {
        if ($template === null) {
            return [$subject, $body];
        }

        $renderedSubject = trim(self::fill($template->subject, $vars));
        $renderedBody = trim(self::fill($template->body, $vars));

        return [
            $renderedSubject !== '' ? mb_substr($renderedSubject, 0, 140) : $subject,
            $renderedBody !== '' ? mb_substr($renderedBody, 0, 1000) : $body,
        ];
    }

    /**
     * @param  array<string, scalar|null>  $vars
     */
    public static function fill(string $template, array $vars): string
    {
        $safe = [];

        foreach ($vars as $key => $value) {
            if (! is_string($key) || preg_match('/^[a-z0-9_]+$/', $key) !== 1) {
                continue;
            }

            if (! is_scalar($value) && $value !== null) {
                continue;
            }

            $safe[$key] = mb_substr(trim((string) $value), 0, 300);
        }

        $rendered = preg_replace_callback(
            '/\{\{\s*([a-z0-9_]+)\s*\}\}/',
            fn (array $match): string => $safe[$match[1]] ?? '',
            $template,
        );
        $rendered = preg_replace('/\{\{.*?\}\}/s', '', $rendered ?? $template);

        return str_replace(['<?', '{!!', '@php'], '', $rendered ?? $template);
    }
}
