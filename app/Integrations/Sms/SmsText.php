<?php

namespace App\Integrations\Sms;

final class SmsText
{
    /**
     * Keep operator-facing errors free of credentials, tokens, and request URLs.
     */
    public static function safe(?string $message): string
    {
        $text = trim((string) $message);
        $text = preg_replace('/https?:\/\/\S+/i', '[url]', $text) ?? $text;
        $text = preg_replace('/(api[_-]?key|token|secret|authorization|password|username)(["\'\s:=]+)[^\s,\'"]+/i', '$1$2[redacted]', $text) ?? $text;
        $text = preg_replace('/\b(?:sk_|AC|MG)[A-Za-z0-9]{8,}\b/', '[redacted]', $text) ?? $text;

        return mb_substr($text, 0, 300);
    }
}
