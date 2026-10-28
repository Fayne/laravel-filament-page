<?php

namespace Fayne\Page\Filament\Concerns;

use Filament\Panel;

trait HasResourceConfiguration
{
    public static function configuredResource(): string
    {
        return config('filament-page.resources.'.static::$configurationKey, static::class);
    }

    public static function getSlug(?Panel $panel = null): string
    {
        return config('filament-page.resource_slugs.'.static::$configurationKey) ?? parent::getSlug($panel);
    }
}
