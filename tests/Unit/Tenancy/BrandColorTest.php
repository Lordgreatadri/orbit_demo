<?php

namespace Tests\Unit\Tenancy;

use App\Tenancy\BrandColor;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class BrandColorTest extends TestCase
{
    #[Test]
    public function ink_chooses_black_on_red_so_contrast_meets_4_5(): void
    {
        $ink = BrandColor::ink('#FF0000');

        $this->assertSame('#000000', $ink);
        $this->assertGreaterThanOrEqual(4.5, BrandColor::contrastRatio('#FF0000', $ink));
        $this->assertGreaterThan(BrandColor::contrastRatio('#FF0000', '#FFFFFF'), BrandColor::contrastRatio('#FF0000', $ink));
    }

    #[Test]
    public function ink_chooses_white_on_deep_teal(): void
    {
        $ink = BrandColor::ink('#0F766E');

        $this->assertSame('#FFFFFF', $ink);
        $this->assertGreaterThanOrEqual(4.5, BrandColor::contrastRatio('#0F766E', $ink));
    }
}
