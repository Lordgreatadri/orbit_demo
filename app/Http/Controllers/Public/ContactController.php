<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Http\Requests\Public\ContactRequest;
use App\Models\ContactMessage;
use App\Notifications\ContactMessageReceived;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Notification;

class ContactController extends Controller
{
    public function show(): View
    {
        return view('public.contact');
    }

    public function store(ContactRequest $request): RedirectResponse
    {
        // Honeypot: bots fill the hidden field. Pretend success, store nothing.
        if ($request->isSpam()) {
            return redirect()->route('contact')->with('status', __('Thank you. Your message has been received.'));
        }

        $message = ContactMessage::create([
            ...$request->safe()->only(['name', 'email', 'organization_name', 'subject', 'message']),
            'ip_address' => $request->ip(),
        ]);

        if ($recipient = config('orbit.contact_recipient')) {
            Notification::route('mail', $recipient)->notify(new ContactMessageReceived($message));
        }

        return redirect()
            ->route('contact')
            ->with('status', __('Thank you. Your message has been received and we will respond to :email.', ['email' => $message->email]));
    }
}
