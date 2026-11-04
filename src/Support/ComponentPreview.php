<?php

namespace Fayne\Page\Support;

use Illuminate\Support\Facades\Storage;

final class ComponentPreview
{
    public static function wireframe(?array $asset): ?string
    {
        $asset = isset($asset['path']) ? $asset : ($asset[0] ?? null);
        if (! in_array($asset['disk'] ?? null, ['popover', 'page-component-mockups'], true)) {
            return null;
        }
        $name = pathinfo($asset['path'] ?? '', PATHINFO_FILENAME);
        $name = preg_replace('/-wireframe$/', '', $name);
        if (! in_array($name, [
            'hero-banner',
            'demo-home-hero',
            'demo-home-content',
            'simple-header-with-media',
            'simple-header',
            'single-card-1-1',
            'two-cards-1-2',
            'three-cards-1-3',
            'three-more-cards-with-slider',
            'fullscreen-image',
            'image-slider',
            'media-and-text',
            'media-and-text-with-image-slider',
        ], true)) {
            return null;
        }

        return file_get_contents(__DIR__.'/../../resources/images/mockup/'.$name.'-wireframe.svg');
    }

    public static function url(?array $asset): ?string
    {
        $asset = isset($asset['path']) ? $asset : ($asset[0] ?? null);
        if (! is_array($asset) || ! isset($asset['path'], $asset['disk']) || ! config('filesystems.disks.'.$asset['disk'])) {
            return null;
        }

        return Storage::disk($asset['disk'])->url($asset['path']);
    }
}
