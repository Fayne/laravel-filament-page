<?php

namespace Fayne\Page\Filament\Resources\PageWidgetResource\RelationManagers;

use Fayne\Page\Filament\Resources\PageWidgetFieldResource;
use Fayne\PageBuilder\Filament\RelationManagers\DefinitionFieldsRelationManager;

class FieldsRelationManager extends DefinitionFieldsRelationManager
{
    protected static ?string $relatedResource = PageWidgetFieldResource::class;
}
