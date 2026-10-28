<?php

namespace Fayne\Page\Support;

use Illuminate\Support\Facades\Storage;

final class ComponentPreview
{
    public static function url(?array $asset): ?string
    {
        $asset = isset($asset['path']) ? $asset : ($asset[0] ?? null);
        if (! is_array($asset) || ! isset($asset['path'], $asset['disk']) || ! config('filesystems.disks.'.$asset['disk'])) {
            return null;
        }

        return Storage::disk($asset['disk'])->url($asset['path']);
    }
}
