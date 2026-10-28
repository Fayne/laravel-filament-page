<?php

namespace Fayne\Page\Filament\Resources\PageResource\Pages;

use Fayne\CMS\Filament\Operations\CreateRecord;
use Fayne\CMS\Support\ContentLocale;
use Fayne\Page\Filament\Resources\PageResource;
use Fayne\Page\Filament\Resources\PageResource\Pages\Concerns\BuildsPageOutline;
use Fayne\Page\Models\PageTemplate;
use Fayne\Page\Services\PageContentValidator;
use Fayne\Page\Services\PageTemplateService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

class CreatePage extends CreateRecord
{
    use BuildsPageOutline;

    protected function fillForm(): void
    {
        $this->callHook('beforeFill');
        $type = config('filament-page.default_page_type', 'basic');
        $this->form->fill(['type' => $type, 'locale' => ContentLocale::default(), 'is_active' => false, 'no_index' => false, 'no_follow' => false, 'components' => [], 'fixed_components' => app(PageContentValidator::class)->initialFixed($type)]);
        $this->callHook('afterFill');
    }

    protected function getHeaderActions(): array
    {
        return [Action::make('useTemplate')->label(__('filament-page::ui.create_from_template'))
            ->modalDescription(__('filament-page::ui.apply_template_help'))->requiresConfirmation()
            ->schema([Select::make('template')->label(__('filament-page::ui.page_template'))->required()->searchable()->options(fn () => PageTemplate::query()->where('is_active', true)->get()->filter(fn ($record) => Gate::allows('view', $record))->pluck('name', 'id')->all())])
            ->action(function (array $data): void {
                $template = PageTemplate::query()->findOrFail($data['template']);
                $values = app(PageTemplateService::class)->apply($template);
                $state = $this->data;
                foreach (['components', 'fixed_components'] as $attribute) {
                    unset($state[$attribute]);
                }
                $this->form->fill([...$state, ...$values]);
            })];
    }

    protected function mutateFormDataBeforeCreate(array $data): array
    {
        try {
            $data['locale'] = ContentLocale::resolve($data['locale'] ?? null);
            app(PageContentValidator::class)->validate($data);
        } catch (ValidationException $exception) {
            throw ValidationException::withMessages(collect($exception->errors())->mapWithKeys(fn ($messages, $field) => ['data.'.$field => $messages])->all());
        }

        return $data;
    }

    protected static string $resource = PageResource::class;
}
