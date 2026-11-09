<?php

namespace App\Workforce;

use chillerlan\QRCode\QRCode;
use chillerlan\QRCode\QROptions;
use Throwable;

final class QrSvg
{
    public static function render(string $payload): string
    {
        try {
            $options = new QROptions([
                'outputBase64' => false,
                'svgAddXmlHeader' => false,
                'scale' => 6,
                'drawLightModules' => false,
            ]);

            $svg = (string) (new QRCode($options))->render($payload);

            // The library omits width and height. The page gives the code a
            // fixed square, and these percentages fill that square.
            return preg_replace(
                '/<svg\b/',
                '<svg width="100%" height="100%"',
                $svg,
                1,
            ) ?? $svg;
        } catch (Throwable) {
            return '';
        }
    }
}
