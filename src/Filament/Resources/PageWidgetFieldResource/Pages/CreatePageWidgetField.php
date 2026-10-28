<?php

namespace Fayne\Page\Filament\Resources\PageWidgetFieldResource\Pages;

use Fayne\CMS\Filament\Operations\CreateRecord;
use Fayne\Page\Filament\Concerns\ResolvesConfiguredResource;
use Fayne\Page\Filament\Resources\PageWidgetFieldResource;

class CreatePageWidgetField extends CreateRecord
{
    use ResolvesConfiguredResource;

    protected static string $resource = PageWidgetFieldResource::class;
}
