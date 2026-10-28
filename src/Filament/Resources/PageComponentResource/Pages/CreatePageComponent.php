<?php

namespace Fayne\Page\Filament\Resources\PageComponentResource\Pages;

use Fayne\CMS\Filament\Operations\CreateRecord;
use Fayne\Page\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Page\Filament\Resources\PageComponentResource;

class CreatePageComponent extends CreateRecord
{
    use ResolvesConfiguredResource;

    protected static string $resource = PageComponentResource::class;
}
