<?php

namespace Fayne\Page\Filament\Concerns;

trait ResolvesConfiguredResource
{
    public static function getResource(): string
    {
        return static::$resource::configuredResource();
    }
}
