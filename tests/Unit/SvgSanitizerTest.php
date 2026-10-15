<?php

declare(strict_types=1);

use App\Modules\Shared\Domain\Exceptions\SvgSecurityViolation;
use App\Modules\Shared\Infrastructure\Services\EnshrinedSvgSanitizer;

it('preserves valid SVG geometry while removing executable and remote content', function (): void {
    $svg = '<svg xmlns="http://www.w3.org/2000/svg" xmlns:xlink="http://www.w3.org/1999/xlink" onload="alert(1)">'
        .'<script>alert(1)</script><rect width="10" height="20"/>'
        .'<use href="https://example.invalid/remote.svg#shape" xlink:href="javascript:alert(1)"/>'
        .'</svg>';

    $cleaned = (new EnshrinedSvgSanitizer)->sanitize($svg);

    expect($cleaned)->toContain('<rect')->toContain('width="10"')
        ->not->toContain('<script')->not->toContain('onload')
        ->not->toContain('javascript:')->not->toContain('example.invalid');
});

it('rejects invalid SVG content through the existing domain error', function (): void {
    (new EnshrinedSvgSanitizer)->sanitize('not an XML document');
})->throws(SvgSecurityViolation::class);

it('does not preserve declarations or unsafe entity references in SVG output', function (): void {
    $svg = '<!DOCTYPE svg [<!ENTITY remote SYSTEM "https://example.invalid/entity">]>'
        .'<svg xmlns="http://www.w3.org/2000/svg"><text>&remote;</text><rect width="1" height="1"/></svg>';

    try {
        $cleaned = (new EnshrinedSvgSanitizer)->sanitize($svg);
    } catch (SvgSecurityViolation) {
        expect(true)->toBeTrue();

        return;
    }

    expect($cleaned)->not->toContain('<!DOCTYPE')->not->toContain('<!ENTITY')
        ->not->toContain('&remote;')->not->toContain('example.invalid');
});
