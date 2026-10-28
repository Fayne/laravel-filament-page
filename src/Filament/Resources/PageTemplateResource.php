<?php

namespace Fayne\Page\Filament\Resources;

use Fayne\Page\Models\PageTemplate;
use Filament\Actions\Action;
use Filament\Actions\DeleteAction;
use Filament\Actions\EditAction;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Support\Facades\Gate;

class PageTemplateResource extends Resource
{
    public static function getNavigationLabel(): string
    {
        return __('filament-page::navigation.page_templates');
    }

    public static function getNavigationGroup(): string|\UnitEnum|null
    {
        return __('filament-cms::navigation.cms');
    }

    protected static bool $isScopedToTenant = false;

    protected static ?string $model = PageTemplate::class;

    public static function getModelLabel(): string
    {
        return __('filament-page::ui.page_template');
    }

    public static function form(Schema $schema): Schema
    {
        return $schema->components([TextInput::make('name')->label(__('filament-page::ui.name'))->required()->maxLength(255), Textarea::make('description')->label(__('filament-page::ui.description')), Toggle::make('is_active')->label(__('filament-page::ui.enable'))]);
    }

    public static function table(Table $table): Table
    {
        return $table->columns([TextColumn::make('name')->label(__('filament-page::ui.name'))->searchable(), TextColumn::make('type')->label(__('filament-page::ui.page_type')), IconColumn::make('is_active')->label(__('filament-page::ui.enable'))->boolean(), TextColumn::make('description')->label(__('filament-page::ui.description'))->limit(60)])->recordActions([Action::make('structure')->label(__('filament-page::ui.view_structure'))->modalSubmitAction(false)->modalContent(fn (PageTemplate $record) => view('filament-page::template-structure', ['structure' => $record->structure]))->authorize(fn (PageTemplate $record) => Gate::allows('view', $record)), EditAction::make(), DeleteAction::make()]);
    }

    public static function getPages(): array
    {
        return ['index' => PageTemplateResource\Pages\ManagePageTemplates::route('/')];
    }
}
