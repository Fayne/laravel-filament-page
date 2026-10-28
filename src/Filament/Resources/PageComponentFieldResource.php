<?php

namespace Fayne\Page\Filament\Resources;

use Fayne\CMS\Support\ContentFieldTypes;
use Fayne\Page\Models\PageComponentField;
use Fayne\PageBuilder\Filament\Forms\FieldDefinitionSchema;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;

class PageComponentFieldResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('filament-page::navigation.component_fields');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament-cms::navigation.cms');
    }

    protected static bool $isScopedToTenant = false;

    protected static ?string $model = PageComponentField::class;

    public static function getModelLabel(): string
    {
        return __('filament-page::ui.field_model_pagecomponentfield');
    }

    public static function getModel(): string
    {
        return config('filament-page.models.page_component_field', static::$model);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([
            FieldDefinitionSchema::owner('page_component_id', config('filament-page.models.page_component'), PageComponentResource::class, __('filament-page::ui.owning_component')),
            ...static::fieldFormComponents(),
        ]);
    }

    public static function fieldFormComponents(): array
    {
        return FieldDefinitionSchema::components(config('filament-page.models.page_widget'));
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('title')->label(__('filament-page::ui.field_title'))->searchable(), TextColumn::make('resolver')->label(__('filament-page::translations.field_type'))->formatStateUsing(fn ($state, $record) => ContentFieldTypes::label($state, $record->options)), TextColumn::make('weight')->label(__('filament-page::ui.field_weight'))->sortable()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => PageComponentFieldResource\Pages\ListPageComponentFields::route('/'), 'create' => PageComponentFieldResource\Pages\CreatePageComponentField::route('/create'), 'edit' => PageComponentFieldResource\Pages\EditPageComponentField::route('/{record}/edit')];
    }
}
