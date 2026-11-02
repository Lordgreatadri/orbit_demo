<?php

namespace Tests\Feature\Tenancy;

use App\Tenancy\BrandColor;
use App\Tenancy\WorkspaceBranding;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BrandSoftColorTest extends TestCase
{
    #[Test]
    public function the_plum_theme_keeps_dark_soft_washes(): void
    {
        $branding = WorkspaceBranding::platform();
        $light = BrandColor::soft('#0F766E');
        $plum = $branding->plumCssVariables();

        $this->assertStringContainsString('--primary-soft: '.$light, $branding->cssVariables());
        $this->assertStringNotContainsString($light, $plum);
        $this->assertStringContainsString('--primary-soft: '.BrandColor::soft('#0F766E', '#0B1D21', 0.78), $plum);
        $this->assertStringContainsString('--accent-soft: '.BrandColor::soft('#3B6EA5', '#0B1D21', 0.78), $plum);
    }
}
