<?php

namespace App\Integrations\Sms;

use App\Integrations\Sms\Exceptions\RetryableSmsException;
use App\Models\SmsProvider;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

final class SmsHttp
{
    public static function client(SmsProvider $provider): PendingRequest
    {
        $timeout = max(3, min(30, (int) $provider->timeout_seconds));

        return Http::timeout($timeout)
            ->connectTimeout(min(5, $timeout))
            ->withOptions(['allow_redirects' => false, 'http_errors' => false])
            ->acceptJson();
    }

    public static function send(PendingRequest $request, string $method, string $url, array $payload = []): Response
    {
        try {
            $pending = strtolower($method) === 'get'
                ? $request->get($url, $payload)
                : $request->{strtolower($method)}($url, $payload);
        } catch (ConnectionException) {
            throw new RetryableSmsException('The provider did not respond before the timeout.');
        }

        return $pending;
    }

    /**
     * @return 'retry'|'permanent'|null Null means the HTTP call succeeded.
     */
    public static function transport(Response $response): ?string
    {
        $status = $response->status();

        if ($status >= 200 && $status < 300) {
            return null;
        }

        if (in_array($status, [408, 409, 425, 429], true) || $status >= 500 || $status === 0) {
            return 'retry';
        }

        return 'permanent';
    }

    public static function transportMessage(Response $response): string
    {
        $status = $response->status();

        return match (true) {
            $status === 401, $status === 403 => 'The provider refused the credentials.',
            $status === 429 => 'The provider asked us to slow down.',
            $status >= 500, $status === 0 => 'The provider was unavailable.',
            default => 'The provider rejected the request.',
        };
    }
}
