<?php

namespace App\Integrations\Sms\Webhooks;

use App\Models\SmsProvider;
use Illuminate\Http\Request;
use Spatie\WebhookClient\SignatureValidator\SignatureValidator;
use Spatie\WebhookClient\WebhookConfig;

/**
 * Twilio signs the exact URL plus the POST fields with HMAC-SHA1 of the auth token.
 * The path token is checked as well so a leaked auth token alone is not enough.
 */
class TwilioWebhookValidator implements SignatureValidator
{
    public function isValid(Request $request, WebhookConfig $config): bool
    {
        $provider = SmsProvider::query()->where('key', 'twilio')->first();
        $token = $provider?->credential('webhook_token');
        $authToken = $provider?->credential('auth_token');
        $givenToken = (string) $request->route('token');
        $signature = (string) $request->header('X-Twilio-Signature');

        if (! is_string($token) || $token === '' || $givenToken === '' || ! is_string($authToken) || $authToken === '' || $signature === '') {
            return false;
        }

        if (! hash_equals($token, $givenToken)) {
            return false;
        }

        $expected = self::signature($request->fullUrl(), $request->request->all(), $authToken);

        return hash_equals($expected, $signature);
    }

    /**
     * @param  array<string, mixed>  $params
     */
    public static function signature(string $url, array $params, string $authToken): string
    {
        ksort($params);
        $data = $url;

        foreach ($params as $key => $value) {
            if (is_array($value)) {
                continue;
            }

            $data .= $key.$value;
        }

        return base64_encode(hash_hmac('sha1', $data, $authToken, true));
    }
}
