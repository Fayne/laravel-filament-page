<?php

use Fayne\Page\Models\Page;
use Fayne\Page\Services\ComponentTransferService;
use Illuminate\Validation\ValidationException;

it('validates an import fully before replacing stored components', function () {
    $page = Page::create(['name' => 'Home', 'path' => '/', 'components' => [['layout' => 'old', 'key' => 'old', 'attributes' => []]]]);
    $before = $page->fresh()->components;
    try {
        (new ComponentTransferService)->import($page, [['layout' => 'hero', 'key' => 'dup', 'attributes' => []], ['layout' => 'hero', 'key' => 'dup', 'attributes' => []]]);
        $this->fail('Duplicate keys accepted.');
    } catch (InvalidArgumentException) {
        expect($page->fresh()->components)->toBe($before);
    }
});

it('round trips export and import without resolving media references', function () {
    $content = [['layout' => 'hero', 'key' => 'stable', 'internalTitle' => '标题', 'attributes' => ['image' => ['disk' => 'public', 'path' => 'x.jpg']]]];
    $page = Page::create(['name' => 'Home', 'path' => '/', 'components' => $content]);
    $service = new ComponentTransferService;
    $export = $service->export($page);
    $page->update(['components' => []]);
    $service->import($page, $export);
    expect($page->fresh()->components)->toBe($content);
});

it('refuses imports excluded by the page type without changing stored content', function () {
    config(['filament-page.excluded_components' => ['homepage']]);
    $page = Page::create(['name' => 'Home', 'path' => '/', 'type' => 'homepage', 'locale' => 'en', 'components' => []]);
    try {
        (new ComponentTransferService)->import($page, [['layout' => 'hero', 'key' => 'a', 'attributes' => []]]);
        $this->fail('Excluded import accepted.');
    } catch (ValidationException) {
        expect($page->fresh()->components)->toBe([]);
    }
});
