<?php

namespace Fayne\Page\Filament\Resources\PageComponentResource\Pages;

use Fayne\CMS\Filament\Operations\EditRecord;
use Fayne\Page\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Page\Filament\Resources\PageComponentResource;

class EditPageComponent extends EditRecord
{
    use ResolvesConfiguredResource;

    protected static string $resource = PageComponentResource::class;
}
