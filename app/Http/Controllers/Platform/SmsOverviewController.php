<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Integrations\Sms\SmsProviderRegistry;
use App\Models\SmsMessage;
use App\Models\SmsProvider;
use App\Platform\SmsTransmissionOverview;
use Illuminate\Contracts\View\View;

class SmsOverviewController extends Controller
{
    public function __invoke(SmsProviderRegistry $registry, SmsTransmissionOverview $overview): View
    {
        $this->authorize('viewAny', SmsProvider::class);
        $registry->ensureRows();

        return view('platform.sms.overview', [
            ...$overview(),
            'messages' => SmsMessage::query()
                ->with('provider:id,name,key')
                ->latest('id')
                ->paginate(15, ['id', 'uuid', 'sms_provider_id', 'to', 'purpose', 'status', 'provider_status', 'created_at'], 'messages')
                ->withQueryString(),
        ]);
    }
}
