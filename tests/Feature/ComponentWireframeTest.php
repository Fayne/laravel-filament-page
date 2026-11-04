<?php

use Fayne\Page\Support\ComponentPreview;
use Illuminate\Support\Facades\Storage;

it('provides a theme-aware wireframe for every bundled component', function () {
    $components = json_decode(file_get_contents(__DIR__.'/../../resources/defaults/components.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach ($components as $component) {
        $svg = ComponentPreview::wireframe($component['mockup']);
        expect($svg)->not->toBeNull();
        $document = new DOMDocument;
        expect($document->loadXML($svg))->toBeTrue();
        expect($document->documentElement->localName)->toBe('svg');
    }
});

it('only inlines bundled wireframes from the popover disk', function () {
    expect(ComponentPreview::wireframe(['disk' => 'public', 'path' => 'hero-banner.jpg']))->toBeNull()
        ->and(ComponentPreview::wireframe(['disk' => 'popover', 'path' => 'custom.svg']))->toBeNull()
        ->and(ComponentPreview::wireframe(null))->toBeNull();
});

it('serves bundled mockups through the automatically configured disk', function () {
    $components = json_decode(file_get_contents(__DIR__.'/../../resources/defaults/components.json'), true, flags: JSON_THROW_ON_ERROR);
    foreach ($components as $component) {
        $asset = $component['mockup'];
        expect(Storage::disk($asset['disk'])->exists($asset['path']))->toBeTrue();
        $this->get(ComponentPreview::url($asset))
            ->assertOk()->assertHeader('Content-Type', 'image/svg+xml');
    }
    $this->get('/filament-page/mockups/missing-wireframe.svg')->assertNotFound();
});
