<?php

namespace Fayne\Page\Services;

use Fayne\CMS\Support\Operations\Operations;
use Fayne\Page\Models\Page;
use Fayne\PageBuilder\Support\ContentStateAdapter;
use Fayne\PageBuilder\Support\EloquentContentDefinitions;

final class ComponentTransferService
{
    public function export(Page $page): array
    {
        return $page->components ?? [];
    }

    public function import(Page $page, array $content): void
    {
        app(Operations::class)->run('page.components.import', function () use ($page, $content): void {
            $definitions = new EloquentContentDefinitions(config('filament-page.models.page_component'), config('filament-page.models.page_widget'), $page->getConnectionName());
            (new ContentStateAdapter($definitions))->toBuilder($content);
            app(PageContentValidator::class)->validate([...$page->contentSnapshot(), 'components' => $content], $page);
            $page->getConnection()->transaction(fn () => $page->forceFill(['components' => $content, 'changed' => true])->save());
        }, ['content' => $content], $page, []);
    }
}
