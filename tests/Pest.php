<?php

use Lunar\Storefront\Tests\TestCase;

pest()->extends(TestCase::class)->in('Feature', 'Unit');

/** A valid 1x1 PNG — the media collections enforce image mime types and
 *  generate conversions, so the bytes must be a real, loadable image. */
function onePixelPng(): string
{
    $image = imagecreatetruecolor(1, 1);

    ob_start();
    imagepng($image);
    $bytes = ob_get_clean();
    imagedestroy($image);

    return $bytes;
}
