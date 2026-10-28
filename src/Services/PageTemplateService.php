<?php

namespace Fayne\Page\Services;

use Fayne\CMS\Support\Operations\Operations;
use Fayne\Page\Models\Page;
use Fayne\Page\Models\PageTemplate;
use Fayne\PageBuilder\Contracts\ContentDefinitionRepository;
use Fayne\PageBuilder\Support\ContentStateAdapter;
use Fayne\PageBuilder\Support\EloquentContentDefinitions;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class PageTemplateService
{
    public function skeleton(array $content, ContentDefinitionRepository $definitions, bool $widget = false): array
    {
        $result = [];
        foreach ($content as $block) {
            $attributes = [];
            foreach ($definitions->fields($block['layout'], $widget) as $field) {
                if ($field['resolver'] === 'widget') {
                    $attributes[$field['name']] = $this->skeleton($block['attributes'][$field['name']] ?? [], $definitions, true);
                }
            }
            $item = ['layout' => $block['layout'], 'attributes' => $attributes];
            if (isset($block['internalTitle'])) {
                $item['internalTitle'] = $block['internalTitle'];
            }
            $result[] = $item;
        }

        return $result;
    }

    private function definitions(?string $connection): EloquentContentDefinitions
    {
        return new EloquentContentDefinitions(config('filament-page.models.page_component'), config('filament-page.models.page_widget'), $connection);
    }

    public function save(Page $page, array $data): PageTemplate
    {
        return app(Operations::class)->run('page.template.create', function () use ($page, $data): PageTemplate {
            Gate::authorize('view', $page);
            Gate::authorize('create', config('filament-page.models.page_template'));
            $page = $page->fresh();
            $definitions = $this->definitions($page->getConnectionName());
            $adapter = new ContentStateAdapter($definitions);
            $structure = [];
            foreach (['components', 'fixed_components'] as $attribute) {
                $content = $page->$attribute ?? [];
                $adapter->toBuilder($content);
                $structure[$attribute] = $this->skeleton($content, $definitions);
            }
            $template = (new (config('filament-page.models.page_template')))->setConnection($page->getConnectionName());
            $template->fill(['name' => $data['name'], 'description' => $data['description'] ?? null, 'type' => $page->type, 'structure' => $structure, 'is_active' => true])->save();

            return $template;
        }, ['data' => $data], context: ['source_page_id' => (string) $page->getKey()], connection: $page->getConnectionName());
    }

    private function instantiate(array $blocks, ContentDefinitionRepository $definitions, bool $widget = false): array
    {
        $blocks = $this->skeleton($blocks, $definitions, $widget);
        foreach ($blocks as &$block) {
            $block['key'] = (string) Str::uuid();
            foreach ($definitions->fields($block['layout'], $widget) as $field) {
                if ($field['resolver'] === 'widget') {
                    $block['attributes'][$field['name']] = $this->instantiate($block['attributes'][$field['name']] ?? [], $definitions, true);
                }
            }
        }

        return $blocks;
    }

    public function apply(PageTemplate $template): array
    {
        Gate::authorize('view', $template);
        Gate::authorize('create', config('filament-page.models.page'));
        if (! $template->is_active) {
            throw ValidationException::withMessages(['template' => __('filament-page::ui.template_inactive')]);
        }
        $definitions = $this->definitions($template->getConnectionName());
        $data = ['type' => $template->type];
        foreach (['components', 'fixed_components'] as $attribute) {
            $data[$attribute] = $this->instantiate($template->structure[$attribute] ?? [], $definitions);
            (new ContentStateAdapter($definitions))->toBuilder($data[$attribute]);
        }
        $expected = app(PageContentValidator::class)->initialFixed($template->type);
        if (array_column($expected, 'layout') !== array_column($data['fixed_components'], 'layout') || (in_array($template->type, config('filament-page.excluded_components', []), true) && $data['components'] !== [])) {
            throw ValidationException::withMessages(['template' => __('filament-page::ui.template_structure_invalid')]);
        }

        return $data;
    }
}
