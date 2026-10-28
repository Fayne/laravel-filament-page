<?php

namespace Fayne\Page\Filament\Resources;

use Fayne\Page\Filament\Forms\ComponentPreviewUpload;
use Fayne\Page\Models\PageComponent;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Str;

class PageComponentResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('filament-page::navigation.page_components');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament-cms::navigation.cms');
    }

    protected static bool $isScopedToTenant = false;

    protected static ?string $model = PageComponent::class;

    public static function getModelLabel(): string
    {
        return __('filament-page::ui.field_model_pagecomponent');
    }

    public static function getModel(): string
    {
        return config('filament-page.models.page_component', static::$model);
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->label(__('filament-page::ui.field_name'))->required()->live()->afterStateUpdated(function (Get $get, Set $set, ?string $old, ?string $state): void {
            if (($get('slug') ?? '') !== Str::slug($old ?? '')) {
                return;
            }

            $set('slug', Str::slug($state ?? ''));
        }), TextInput::make('slug')->label(__('filament-page::ui.field_slug'))->required()->unique(ignoreRecord: true), Textarea::make('description')->label(__('filament-page::ui.field_description')), TextInput::make('weight')->label(__('filament-page::ui.field_weight'))->numeric()->default(0), ComponentPreviewUpload::make('mockup')->label(__('filament-page::ui.field_mockup'))]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('id')->label(__('filament-page::ui.field_id'))->sortable(), TextColumn::make('name')->label(__('filament-page::ui.field_name'))->searchable()])->recordActions([EditAction::make(), DeleteAction::make()]);
    }

    public static function getRelations(): array
    {
        return [PageComponentResource\RelationManagers\FieldsRelationManager::class];
    }

    public static function getPages(): array
    {
        return ['index' => PageComponentResource\Pages\ListPageComponents::route('/'), 'create' => PageComponentResource\Pages\CreatePageComponent::route('/create'), 'edit' => PageComponentResource\Pages\EditPageComponent::route('/{record}/edit')];
    }
}
