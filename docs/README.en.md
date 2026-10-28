<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Laravel Filament Page

[简体中文](../README.md) | English

Page content management for **PHP 8.4+, Laravel 13, and Filament 5**. Provides pages, dynamic components, widgets, structural templates, revisions, and translation management, with content, draft-preview, and frozen-build APIs for frontend applications.

## Features and scope

| Capability | Description |
| --- | --- |
| Page management | Basic, homepage, and 404 types; paths, SEO, meta, active state, and change tracking |
| Dynamic content | Page Builder blocks, nested widgets, fixed components, and a quick-build panel |
| Definition management | Component/widget fields, visual options and rules, and component-library JSON import/export |
| Templates and revisions | Structural templates, stashing/restoring edits, and revisions promoted from frozen content after successful publication |
| Chinese, English, and translations | Simplified Chinese/English UI, separate content languages, and translation groups |
| Frontend and releases | Current public content, page-scoped draft tokens, and frozen release snapshots |

## 1. Install in a new project

Configure host authentication, panels, and permissions using [CMS Core](../../laravel-filament-cms-core/docs/README.en.md). Register Composer repositories for this private package, CMS Core, Page Builder, and their field-package dependencies, then install:

```bash
composer require fayne/laravel-filament-page
php artisan vendor:publish --tag=filament-page-config
php artisan migrate
php artisan filament:assets
php artisan cms:page:init
```

`cms:page:init` initializes the permission catalog and asks whether to create demo homepages. It does not create administrators or automatically grant super-admin access. `app:init`, `admin:create`, and the host's default component-library importer are not commands automatically supplied by this package.

## 2. Register the panel and permissions

Register the Page plugin on the site panel or tenant workspace when multi-tenancy is enabled. Keep the CMS plugin and complete authentication, session, CSRF, and tenant middleware.

```php
use Fayne\Page\Filament\PagePlugin;

return $panel->plugins([
    // Keep the host's existing plugins.
    PagePlugin::make(),
]);
```

The plugin registers page, template, component, widget, field, and revision resources. Assign view, create, update, delete, and action permissions through Core roles; definition management and inline image uploads also have authorization checks. Tenant workspaces use `SetTenantContext`; platform super-admin access does not automatically bypass tenant membership.

## 3. Pages, components, and templates

1. Create or import Component/Widget definitions, then configure field types, options, and validation rules.
2. Create a page, select its type and content language, and enter a path starting with `/` without a query or fragment, a title, and SEO information.
3. Add blocks directly or click/drag components into the quick-build panel and reorder them. Closing the panel does not save; save the page form separately. Fixed components cannot be freely added, removed, or reordered there.
4. Save a template when reusing structure. Templates preserve block/nested-widget structure without copying content attribute values; applying one generates new block keys.
5. Changes to a published page can be stashed and restored. Successful publication events promote frozen content into published revisions.

```php
// config/filament-page.php
'creation_limited_page_types' => ['homepage' => 1, '404_page' => 1],
'page_type_changeable' => false,
'fixed_components' => [],
'excluded_components' => [],
'use_default_frontend_routes' => true,
```

Built-in types are `basic`, `homepage`, and `404_page`. Replace `page_types_class` with a custom class implementing `getSelectOptions()` when needed. Type limits are validated within the current tenant/region and content language. `meta` stores additional SEO data; the frontend renders the HTML tags. This package does not generate a public website.

## 4. Chinese, English, and content languages

The interface provides `zh_CN` and `en`; the profile's interface language does not change page content language. Configure content languages globally or per tenant in Core:

```php
// config/filament-cms.php
'multilingual_enabled' => true,
'default_locale' => 'en',
'supported_locales' => ['en' => 'English', 'zh_CN' => '简体中文'],
```

The list groups translations, while the edit page switches existing versions or creates missing languages. Each language has separately edited paths and content without automatic machine translation. Supply localized component names, field labels, and help text yourself. Frontends request a content language through `locale`; v2 detail lists available public translations and does not silently return another language when one is missing.

## 5. Demo homepages and component libraries

```bash
php artisan cms:page:init
php artisan cms:page:init --tenant=shop
php artisan cms:page:init --no-interaction
```

Interactive demo creation defaults to no. `--tenant` accepts an active tenant ID or slug; without it, a trusted current context takes priority, otherwise multiple tenants can be selected. Omit it for a single site. Non-interactive mode only initializes permissions.

`Fayne\Page\Database\Seeders\DemoHomepageSeeder` creates English and Chinese `/` homepages in one translation group, with `demo-home-hero` and `demo-home-content` definitions. Example content includes a banner, featured products, and shopping services. Pages start inactive, changed, and `no_index`; no real products are created. Supported languages must include `en` and `zh_CN`/`zh-CN`. Existing homepages, root paths, or related published root paths cause a skip; conflicting definitions produce errors instead of being overwritten.

The component list imports uploaded/pasted JSON and exports the current scope's component library. The package includes `resources/defaults/components.json`. For duplicate slugs, choose update or skip. Imports remap nested widget references and retain omitted fields. Images retain disk/path metadata only; copy actual files separately to the matching disk. Component-library import does not import page content.

## 6. Frontend APIs and draft previews

| Purpose | Request | Behavior |
| --- | --- | --- |
| Page list | `GET /modules/pages/slugs?locale=en` | Array of page names and public paths |
| Page detail | `GET /modules/pages/detail/{path?}?locale=en` | Omit `path` for the root; paths may contain `/` |
| v2 page list | `GET /modules/pages/v2/slugs?locale=en` | Page list for the requested content language |
| v2 page detail | `GET /modules/pages/v2/detail/{path?}?locale=en` | Adds `translations` and `language` metadata without automatic fallback to another language |
| Draft preview | `GET /headless/preview/draft?page=/about` | Page-scoped preview Bearer; returns `private, no-store` |
| Frozen build input | `GET /headless/releases/{releaseId}/pages?path=/about` | Frozen entries under `data`; omit `path` to read all entries |

Public endpoints only read active pages. They use published revision content when available and fall back to current content for legacy pages without revisions. `region` must belong to the configured allowlist. In tenant mode, Host resolves an active domain; unknown/inactive domains return 404. Single sites do not require domain mapping.

Configure the frontend preview URL to enable the editor preview action:

```dotenv
FILAMENT_PAGE_PREVIEW_URL=https://frontend.example.com/preview
```

The frontend URL receives `path` and `preview_token`. Use the token as a Bearer when requesting the CMS:

```bash
curl -H "Authorization: Bearer $PREVIEW_TOKEN" \
  'https://cms.example.com/headless/preview/draft?page=/about'
```

Tokens last 600 seconds and may be reused, binding tenant, page, path, language, and database connection. Each request rechecks administrator status, membership, and view permission. Only server-saved edits are readable; unsaved browser form state is not included. The response has a `data` envelope containing `entity_type`, `entity_id`, `revision_id`, `path`, `locale`, and `projection`. Keep tokens out of access logs and analytics.

## 7. Frozen builds and host responsibilities

Frozen endpoints follow Core's `CMS_HEADLESS_PREFIX` and require the server-side `CMS_BUILD_API_TOKEN` with matching manifest tenant ownership. Only `frozen`/`succeeded` manifests are readable. Legacy manifests without tenant ownership are rejected and must be republished.

```bash
curl -H "Authorization: Bearer $CMS_BUILD_API_TOKEN" \
  'https://cms.example.com/headless/releases/RELEASE_ID/pages?path=/'
```

Static builds should read pages, menus, and settings from the same release ID rather than mixing live endpoints. Frozen entries' `projection` values are build inputs; `frozen` does not mean deployment succeeded. Ordinary public page output still resolves against current definitions; use frozen endpoints for immutable build inputs.

The package supplies snapshots and revision promotion after successful publication, not complete deployment orchestration, scheduled publishing, or an external build service. The host integrates publication, signed callbacks, failure handling, and rollback. Disabling `use_default_frontend_routes` does not disable the separately registered draft and frozen endpoints.

## 8. Configuration, tests, and deployment

`filament-page.models` and `tables` replace models and table names; preserve tenant, language, revision, and content contracts in custom models. See [Page Builder](../../laravel-filament-page-builder/docs/README.en.md) for complex fields, file storage, HTTP, and entity resolution.

Run in this package directory:

```bash
composer install
composer test
composer format
node --test tests/Browser/quick-builder-drag.test.cjs
```

Before deployment, verify component import, save/reopen, actual dragging, both languages, public/draft APIs, region and tenant isolation, and a complete build/callback for one release. Check real storage URLs, queues, and caches. Ship all dependencies, refresh configuration/route caches, and restart long-running processes. Package tests are not production-flow acceptance.

## Contributing

1. Create a feature branch.
2. Implement the change and add tests for new behavior or bug fixes.
3. Verify the relevant tests and state any outstanding environment acceptance.
4. Update both language versions when public usage changes.
5. Submit a merge request.

## License

This software uses a [proprietary license](../LICENSE.md). Use and distribution are subject to its authorization restrictions.
