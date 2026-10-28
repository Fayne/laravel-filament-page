<?php

use Fayne\CMS\Models\Admin;
use Fayne\Page\Models\Page;
use Fayne\Page\Support\PagePreviewSession;
use Fayne\Page\Support\PagePreviewUrl;

it('scopes a short lived preview token to its page and an authorized active administrator', function () {
    $page = Page::create(['name' => 'Draft', 'path' => '/draft', 'is_active' => false, 'components' => []]);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'preview@example.test', 'password' => 'password', 'is_active' => true]);
    test()->grantPermissions($admin, test()->permission('viewPage'));
    $sessions = app(PagePreviewSession::class);
    $token = $sessions->issue($page, $admin);
    $this->getJson('/headless/preview/draft?page=/draft', ['Authorization' => 'Bearer '.$token])->assertOk()->assertJsonPath('data.projection.name', 'Draft');
    $this->getJson('/headless/preview/draft?page=/other', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
    $admin->update(['is_active' => false]);
    $this->getJson('/headless/preview/draft?page=/draft', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
});

it('rejects expired or missing preview tokens', function () {
    $page = Page::create(['name' => 'Draft', 'path' => '/draft']);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'preview@example.test', 'password' => 'password', 'is_active' => true]);
    test()->grantPermissions($admin, test()->permission('viewPage'));
    $token = app(PagePreviewSession::class)->issue($page, $admin);
    $this->travel(601)->seconds();
    $this->getJson('/headless/preview/draft?page=/draft', ['Authorization' => 'Bearer '.$token])->assertUnauthorized();
    $this->getJson('/headless/preview/draft?page=/draft')->assertUnauthorized();
});

it('builds frontend preview links with scoped credentials and existing query options', function () {
    config(['filament-page.page_preview_url' => 'https://frontend.example.test/preview?theme=dark']);
    $page = Page::create(['name' => 'Draft', 'path' => '/draft']);
    $admin = Admin::create(['name' => 'Editor', 'email' => 'url@example.test', 'password' => 'password', 'is_active' => true]);
    test()->grantPermissions($admin, test()->permission('viewPage'));
    $url = app(PagePreviewUrl::class)->for($page, $admin);
    parse_str(parse_url($url, PHP_URL_QUERY), $query);
    expect($query['theme'])->toBe('dark')->and($query['path'])->toBe('/draft');
    expect(app(PagePreviewSession::class)->resolve($query['preview_token'], '/draft')?->id)->toBe($page->id);
});
