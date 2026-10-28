<?php

namespace Fayne\Page\Filament\Resources\PageWidgetFieldResource\Pages;

use Fayne\Page\Filament\Resources\PageWidgetFieldResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListPageWidgetFields extends ListRecords
{
    protected static string $resource = PageWidgetFieldResource::class;

    protected function getHeaderActions(): array
    {
        return [CreateAction::make()];
    }
}
