<?php

namespace Tests\Feature\Mail;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Markdown;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class MailBrandingTest extends TestCase
{
    use RefreshDatabase;

    #[Test]
    public function html_mail_carries_the_orbit_logo_with_an_accessible_alt_text(): void
    {
        $markdown = app(Markdown::class);

        $html = (string) $markdown->render('mail::message', [
            'slot' => 'Hello from the test suite.',
        ]);

        $this->assertStringContainsString('alt="ORBIT"', $html);
        $this->assertStringContainsString('images/brand/orbit-lockup-light.png', $html);
        $this->assertStringContainsString(config('orbit.product_name'), $html);
        $this->assertStringContainsString('All rights reserved', $html);
        $this->assertStringNotContainsString('Laravel</a>', $html);
    }

    #[Test]
    public function text_mail_does_not_include_markup(): void
    {
        $markdown = app(Markdown::class);

        $text = (string) $markdown->renderText('mail::message', [
            'slot' => 'Hello from the test suite.',
        ]);

        $this->assertStringContainsString(config('orbit.product_name'), $text);
        $this->assertStringNotContainsString('<img', $text);
    }
}
