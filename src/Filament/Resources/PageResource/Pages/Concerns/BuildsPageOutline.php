<?php

namespace Fayne\Page\Filament\Resources\PageResource\Pages\Concerns;

use Fayne\Page\Filament\Resources\PageResource;
use Fayne\Page\Support\ComponentPreview;
use Fayne\PageBuilder\Filament\Forms\Components\ContentBuilder;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

trait BuildsPageOutline
{
    protected function quickBuilder(): ContentBuilder
    {
        $record = $this->getRecord();
        Gate::authorize($record ? 'update' : 'create', $record ?? PageResource::getModel());
        abort_if(in_array($this->data['type'] ?? '', config('filament-page.excluded_components', []), true), 403);

        return $this->form->getComponent(fn ($component) => $component instanceof ContentBuilder && $component->getName() === 'components');
    }

    public function quickComponentLibrary(): array
    {
        $model = config('filament-page.models.page_component');
        $query = $model::query();
        if ($this->getRecord()) {
            $query = (new $model)->setConnection($this->getRecord()->getConnectionName())->newQuery();
        }

        return $query->orderBy('weight')->orderBy('id')->get()->mapWithKeys(fn ($component) => [$component->slug => ['name' => $component->name, 'image' => ComponentPreview::url($component->mockup)]])->all();
    }

    public function quickAddComponent(string $slug, ?string $before = null): void
    {
        $builder = $this->quickBuilder();
        if (! array_key_exists($slug, $builder->getContentDefinitions()->layouts())) {
            throw ValidationException::withMessages(['data.components' => __('filament-page::ui.the_component_definition_no_longer_exists_refresh_and_try_again')]);
        }
        $items = $builder->getRawState() ?? [];
        $this->assertQuickTarget($items, $before);
        $key = (string) Str::uuid();
        $items[$key] = ['type' => $slug, 'data' => []];
        $builder->rawState($items);
        $builder->getChildSchema($key)->fill();
        $this->quickMoveComponent($key, $before);
    }

    public function quickMoveComponent(string $key, ?string $before = null): void
    {
        $builder = $this->quickBuilder();
        $items = $builder->getRawState() ?? [];
        $this->assertQuickTarget($items, $key);
        $this->assertQuickTarget($items, $before);
        if ($key === $before) {
            return;
        }
        $item = $items[$key];
        unset($items[$key]);
        $ordered = [];
        foreach ($items as $id => $value) {
            if ($id === $before) {
                $ordered[$key] = $item;
            }
            $ordered[$id] = $value;
        }
        if ($before === null) {
            $ordered[$key] = $item;
        }
        $builder->rawState($ordered);
        $builder->callAfterStateUpdated();
    }

    public function quickRemoveComponent(string $key): void
    {
        $builder = $this->quickBuilder();
        $items = $builder->getRawState() ?? [];
        $this->assertQuickTarget($items, $key);
        unset($items[$key]);
        $builder->rawState($items);
        $builder->callAfterStateUpdated();
    }

    private function assertQuickTarget(array $items, ?string $key): void
    {
        if ($key !== null && ! array_key_exists($key, $items)) {
            throw ValidationException::withMessages(['data.components' => __('filament-page::ui.the_component_position_changed_try_again')]);
        }
    }
}
