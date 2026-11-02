<?php

namespace App\Http\Controllers\Platform;

use App\Http\Controllers\Controller;
use App\Models\SmsMessage;
use Illuminate\Contracts\View\View;

class SmsMessageController extends Controller
{
    public function show(SmsMessage $smsMessage): View
    {
        $this->authorize('view', $smsMessage);

        $smsMessage->load([
            'provider:id,uuid,name,key',
            'organization:id,uuid,name',
        ]);

        return view('platform.sms.show', [
            'message' => $smsMessage,
            'events' => $smsMessage->events()
                ->with('provider:id,name')
                ->orderBy('id')
                ->paginate(25, ['*'], 'events')
                ->withQueryString(),
        ]);
    }
}
