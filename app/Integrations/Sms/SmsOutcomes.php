<?php

namespace App\Integrations\Sms;

use App\Models\SmsMessage;
use Illuminate\Http\Client\Response;

final class SmsOutcomes
{
    /**
     * @param  list<SmsMessage>  $messages
     * @return list<DeliveryOutcome>|null
     */
    public static function fromTransport(array $messages, Response $response): ?array
    {
        $kind = SmsHttp::transport($response);

        if ($kind === null) {
            return null;
        }

        $result = $kind === 'retry' ? 'retry' : 'failed';
        $error = SmsHttp::transportMessage($response);

        return array_map(
            fn (SmsMessage $message) => new DeliveryOutcome($message->id, $result, error: $error),
            $messages,
        );
    }

    /**
     * @param  list<SmsMessage>  $messages
     * @return list<DeliveryOutcome>
     */
    public static function all(array $messages, string $result, ?string $error = null, ?string $providerStatus = null): array
    {
        return array_map(
            fn (SmsMessage $message) => new DeliveryOutcome(
                $message->id,
                $result,
                providerStatus: $providerStatus,
                error: $error,
            ),
            $messages,
        );
    }
}
