<?php

namespace Tests\Feature\Public;

use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class PublicPagesTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function publicPages(): array
    {
        return [
            'home' => ['home', 'Register your organisation'],
            'about' => ['about', 'How ORBIT works'],
            'guide' => ['guide', 'User guide'],
            'contact' => ['contact', 'Send a message'],
            'terms' => ['terms', 'Terms and Conditions'],
            'privacy' => ['privacy', 'Privacy Policy'],
            'login' => ['login', 'Sign in'],
            'register' => ['register', 'Responsible contact'],
            'password.request' => ['password.request', 'Send reset link'],
        ];
    }

    #[Test]
    #[DataProvider('publicPages')]
    public function public_pages_render_for_visitors(string $routeName, string $expectedText): void
    {
        $this->get(route($routeName))
            ->assertOk()
            ->assertSee($expectedText);
    }

    #[Test]
    public function home_page_has_calls_to_action_and_theme_toggle(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(route('register'), escape: false)
            ->assertSee(route('login'), escape: false)
            ->assertSee(route('contact'), escape: false)
            ->assertSee('x-data="themeToggle"', escape: false)
            ->assertSee('orbit-theme', escape: false)
            ->assertSee('Skip to content');
    }

    #[Test]
    public function pages_use_the_orbit_icon_in_the_browser_tab(): void
    {
        $this->get(route('home'))
            ->assertOk()
            ->assertSee(asset('favicon.ico'), escape: false)
            ->assertSee(asset('images/brand/icon-32.png'), escape: false)
            ->assertSee(asset('images/brand/apple-touch-icon.png'), escape: false);
    }

    #[Test]
    public function home_page_does_not_claim_live_metrics_or_customers(): void
    {
        $response = $this->get(route('home'))->assertOk();

        $response->assertSee('Illustrative');
        $response->assertDontSee('Trusted by');
        $response->assertDontSee('customers worldwide');
    }

    #[Test]
    public function legal_pages_are_marked_as_drafts_for_review(): void
    {
        $this->get(route('terms'))->assertSee('Draft for review');
        $this->get(route('privacy'))->assertSee('Draft for review');
    }

    #[Test]
    public function navigation_links_to_every_public_page(): void
    {
        $this->get(route('home'))
            ->assertSee(route('about'), escape: false)
            ->assertSee(route('guide'), escape: false)
            ->assertSee(route('contact'), escape: false)
            ->assertSee(route('terms'), escape: false)
            ->assertSee(route('privacy'), escape: false);
    }

    #[Test]
    public function contact_page_has_an_expandable_question_and_answer_section(): void
    {
        $this->get(route('contact'))
            ->assertOk()
            ->assertSee('id="faq"', escape: false)
            ->assertSee('Frequently asked questions')
            ->assertSee('<details', escape: false)
            ->assertSee('<summary', escape: false)
            ->assertSee('Does ORBIT track where people are all day?')
            ->assertSeeInOrder(['Send a message', 'Frequently asked questions']);
    }

    #[Test]
    public function user_guide_covers_daily_workflows_and_release_scope(): void
    {
        $this->get(route('guide'))
            ->assertOk()
            ->assertSee('id="attendance"', escape: false)
            ->assertSee('id="schedules"', escape: false)
            ->assertSee('id="leave"', escape: false)
            ->assertSee('id="notifications"', escape: false)
            ->assertSee('id="analytics"', escape: false)
            ->assertSee('id="education"', escape: false)
            ->assertSee('Not in this release')
            ->assertSee('not written by an AI model');
    }

    #[Test]
    public function password_fields_offer_an_accessible_show_toggle(): void
    {
        $this->get(route('login'))
            ->assertOk()
            ->assertSee('type="password"', escape: false)
            ->assertSee('aria-label="Show password"', escape: false)
            ->assertSee('aria-controls="password"', escape: false);

        $this->get(route('register'))
            ->assertOk()
            ->assertSee('aria-label="Show confirm password"', escape: false);
    }

    #[Test]
    public function unknown_pages_render_custom_not_found_page(): void
    {
        $this->get('/this-page-does-not-exist')
            ->assertNotFound()
            ->assertSee('Page not found');
    }

    #[Test]
    public function health_endpoint_is_available(): void
    {
        $this->get('/up')->assertOk();
    }
}
