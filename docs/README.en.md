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
php artisan cms:install
php artisan filament:assets
php artisan cms:page:install
```

`cms:page:install` checks that the module schema is ready and creates no content by default. First run `cms:install`, which owns migrations and global permission initialization. Only explicit `--demo` creates unpublished bilingual demo content; there is no additional demo confirmation prompt. Production requires confirmation or `--force`. No administrator is created or granted privileges. `app:init` and `admin:create` are host commands.

This module owns its permission definitions in [`resources/permissions.php`](../resources/permissions.php). Its service provider registers them with Core's `PermissionRegistry`; Core no longer hardcodes this module's permissions. `cms:install` aggregates registered modules and creates initial roles. Existing roles are not silently expanded when adding a module; explicitly assign new permissions. See [permission extensions](../../laravel-filament-cms-core/docs/permission-registration.en.md).

Migrations target new projects: the revision publication key `publication_manifest_key` and unique `(page_id, publication_manifest_key)` index are included in the initial table migration, without a separate add-column migration.

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

## 3. Extend models, resources, and URIs through configuration

Hosts can replace models, admin Resources, and admin paths independently without editing package source or copying every built-in page. The Nova `models` / `resources` / `uri_key` configuration corresponds to Filament `models` / `resources` / `resource_slugs`.

Create a host model extending the package model:

```php
namespace App\Models;

class Page extends \Fayne\Page\Models\Page
{
}
```

Then extend the package Resource. This example adds a host field to the original form and stores it in an existing JSON attribute:

```php
namespace App\Filament\Resources;

use Fayne\Page\Filament\Resources\PageResource as BasePageResource;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Schema;

class PageResource extends BasePageResource
{
    public static function form(Schema $schema): Schema
    {
        $schema = parent::form($schema);

        return $schema->components([
            ...$schema->getComponents(),
            TextInput::make('extra_data.project_note'),
        ]);
    }
}
```

Set the corresponding keys in the published configuration. This is a configuration fragment. Laravel recursively merges configuration: host values take precedence, while omitted nested keys retain package defaults:

```php
// config/filament-page.php
'models' => [
    'page' => \App\Models\Page::class,
],
'resources' => [
    'page' => \App\Filament\Resources\PageResource::class,
],
'resource_slugs' => [
    'page' => 'content-pages',
],
```

- Models, Resources, and slugs can be replaced independently. `models` selects model classes; configuration is not inferred from a host Resource's `$model` property.
- Supported mapping keys are `page`, `page_template`, `page_revision`, `page_component`, `page_component_field`, `page_widget`, `page_widget_field`. Page Template creation, queries, and authorization also follow `models.page_template`. Custom classes should extend their corresponding package classes and preserve tenant, language, relationship, content, and authorization contracts.
- The plugin registers configured Resources; built-in lists, creation, editing, and relationship entry points follow them. Templates, revisions, and language switching do not redirect back to default Resources. Inherit `getPages()` or override it to replace individual pages.
- `resource_slugs` is empty by default. Without an entry, Filament's native `$slug`, default inference, and Panel ResourceConfiguration remain effective. Explicit configuration takes priority when inheriting the package method. A host overriding `getSlug()` without calling its parent is responsible for honoring configuration itself.
- `content-pages` changes the admin Resource path, for example `/admin/content-pages`, not frontend content paths or Headless APIs. Customize navigation labels, groups, tables, and actions through native Filament overrides.
- Keep registration through `PagePlugin`. When using Resource auto-discovery, avoid discovering/registering both the default and replacement classes. Register custom policies through Laravel, with the appropriate host provider order, without bypassing authorization scopes.

After changes, clear and regenerate configuration/route caches through the deployment process and restart long-running processes. Older published configuration can add these keys; omitted model/Resource defaults are filled automatically. No business-table or content-format changes are required.

## 4. Pages, components, and templates

1. Create or import Component/Widget definitions, then configure field types, options, and validation rules.
2. Create a page, select its type and content language, and enter a path starting with `/` without a query or fragment, a title, and SEO information.
3. Open quick build with the squares icon and return to the content editor with the return arrow; both controls retain tooltips. Add blocks directly or click/drag components into the quick-build panel and reorder them. Left-side cards show component names as image alt text when previews exist, or as visible text otherwise. Edit, move up/down, and remove icons appear on hover or keyboard focus; touch devices show them directly. Drag sorting and move up/down update immediately in the browser without a reorder request; the order is persisted with the page form. Closing the panel does not save; save the page form separately. Fixed components cannot be freely added, removed, or reordered there.
4. Save a template when reusing structure. Templates preserve block/nested-widget structure without copying content attribute values; applying one generates new block keys.
5. Save changes before stashing a published page: the saved draft is stored as a stash and the current page returns to its published content. Restoring the stash retrieves the draft. Stash and restore actions reject unsaved input and prompt you to save first, preventing form data loss. Successful publication events promote frozen content into published revisions.

```php
// config/filament-page.php
'creation_limited_page_types' => ['homepage' => 1, '404_page' => 1],
'page_type_changeable' => false,
'fixed_components' => [],
'excluded_components' => [],
'use_default_frontend_routes' => true,
```

Built-in types are `basic`, `homepage`, and `404_page`. Replace `page_types_class` with a custom class implementing `getSelectOptions()` when needed. Type limits are validated within the current tenant/region and content language. To disable a default limit, set its key to `null` (for example, `'homepage' => null`); an empty array does not clear recursively merged default keys. `meta` stores additional SEO data; the frontend renders the HTML tags. This package does not generate a public website.

## 5. Chinese, English, and content languages

The interface provides `zh_CN` and `en`; the profile's interface language does not change page content language. Configure content languages globally or per tenant in Core:

```php
// config/filament-cms.php
'multilingual_enabled' => true,
'default_locale' => 'en',
'supported_locales' => ['en' => 'English', 'zh_CN' => '简体中文'],
```

The list groups translations, while the edit page switches existing versions or creates missing languages. Create translation is hidden when all supported languages exist. Switching opens a single accessible alternative directly, offers a picker for multiple alternatives, and is hidden when none are available. Unsaved changes must be saved first. Each language has separately edited paths and content without automatic machine translation. Supply localized component names, field labels, and help text yourself. Frontends request a content language through `locale`; v2 detail lists available public translations and does not silently return another language when one is missing.

## 6. Demo homepages and component libraries

```bash
php artisan cms:page:install
php artisan cms:page:install --demo
php artisan cms:page:install --demo --tenant=shop
php artisan cms:page:install --no-interaction
```

Default and unattended installation create no demos. Explicit `--demo` works interactively or in automation. `--tenant` requires `--demo` and accepts an active tenant ID or slug. Unattended tenant demos require a target; interactive runs can use a trusted context or select active tenants. Single-site mode rejects `--tenant` and writes to scope 0. Demo errors return failure without rolling back the completed base installation.

`Fayne\Page\Database\Seeders\DemoHomepageSeeder` creates English and Chinese `/` homepages in one translation group, with `demo-home-hero` and `demo-home-content` definitions. Example content includes a banner, featured products, and shopping services. Pages start inactive, changed, and `no_index`; no real products are created. Supported languages must include `en` and `zh_CN`/`zh-CN`. Existing homepages, root paths, or related published root paths cause a skip; conflicting definitions produce errors instead of being overwritten.

The component list imports uploaded/pasted JSON and exports the current scope's component library. The package includes `resources/defaults/components.json`. For duplicate slugs, choose update or skip. Imports remap nested widget references and retain omitted fields. Images retain disk/path metadata only; copy actual files separately to the matching disk. Component-library import does not import page content.

## 7. Frontend APIs and draft previews

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

## 8. Frozen builds and host responsibilities

Frozen endpoints follow Core's `CMS_HEADLESS_PREFIX` and require the server-side `CMS_BUILD_API_TOKEN` with matching manifest tenant ownership. Only `frozen`/`succeeded` manifests are readable. Legacy manifests without tenant ownership are rejected and must be republished.

```bash
curl -H "Authorization: Bearer $CMS_BUILD_API_TOKEN" \
  'https://cms.example.com/headless/releases/RELEASE_ID/pages?path=/'
```

Static builds should read pages, menus, and settings from the same release ID rather than mixing live endpoints. Frozen entries' `projection` values are build inputs; `frozen` does not mean deployment succeeded. Ordinary public page output still resolves against current definitions; use frozen endpoints for immutable build inputs.

The package supplies snapshots and revision promotion after successful publication, not complete deployment orchestration, scheduled publishing, or an external build service. The host integrates publication, signed callbacks, failure handling, and rollback. Disabling `use_default_frontend_routes` does not disable the separately registered draft and frozen endpoints.

## 9. Configuration, tests, and deployment

`filament-page.models` and `tables` replace models and table names; preserve tenant, language, revision, and content contracts in custom models. See [Page Builder](../../laravel-filament-page-builder/docs/README.en.md) for complex fields, file storage, HTTP, and entity resolution.

Run in this package directory:

```bash
composer install
composer test
composer format
node --test tests/Browser/quick-builder-drag.test.cjs
```

Before deployment, verify component import, save/reopen, actual dragging, both languages, public/draft APIs, region and tenant isolation, and a complete build/callback for one release. Check real storage URLs, queues, and caches. Ship all dependencies, refresh configuration/route caches, and restart long-running processes. Package tests are not production-flow acceptance.

The library does not commit `composer.lock`; local generated lock files are ignored. Host applications should retain their own lock file for production deployment.

## Contributing

1. Create a feature branch.
2. Implement the change and add tests for new behavior or bug fixes.
3. Verify the relevant tests and state any outstanding environment acceptance.
4. Update both language versions when public usage changes.
5. Submit a merge request.

## License

This software uses a [proprietary license](../LICENSE.md). Use and distribution are subject to its authorization restrictions.

## Whole-site frozen API v2

With Core publication routing configured, `/headless/releases/{release_id}/pages` reads the immutable public bundle, independent of current region, locale or routing settings. Authenticate with the build Bearer token through the owning tenant domain. Page list enumeration accepts `target_id` and `scope_id`. A `source_path` query across multiple scopes requires `scope_id`; final `path` queries that match multiple scopes also return 422 without it. Unknown or incompatible scope/target values return 422. Rows expose frozen final URLs and translation URLs, with no raw promotion snapshots or connection identifiers. Whole-site promotion finishes all regional sources before success; interrupted promotion can be retried without duplicate revisions.

Built-in component previews use neutral SVG wireframes that inherit the admin theme primary color.

Default component mockups use the automatically registered `page-component-mockups` disk. It reads bundled SVGs and provides an image route without host filesystem configuration or a storage symlink. Hosts may override the disk by configuring the same name.

Page lists provide a detail action with mockup layout preview, formatted JSON, and compact JSON modes. Demo homepage components include theme-aware wireframes. Rerunning the demo seeder fills missing previews.

Detail pages use the same Basic information, Page content, SEO settings, and Advanced settings tabs as the editor.


Permission labels and groups are declared by this package in `resources/permissions.php`, with English and Simplified Chinese translations in `resources/lang`. CMS Core resolves the registered metadata without business-name matching.

## Automatic CMS module registration

With CMS Core installed, this package registers its module through its Laravel ServiceProvider. Panels extending `Fayne\CMS\Providers\AdminPanelProvider` / `TenantPanelProvider` automatically connect installed modules: business UI belongs to the ordinary panel in single-site mode and the workspace in multi-tenant mode. Installation grants no account permissions; policies still control navigation and access.

In a fully custom PanelProvider, configure explicit plugins first, then return `app(\Fayne\CMS\Support\Modules\ModuleRegistry::class)->applyTo($panel)`. An explicitly configured plugin with the same ID is preserved. Do not register the same business plugin again after applying the registry. Earlier manual Plugin examples remain applicable to standalone or fully custom panels.

Registered modules are enabled by default in this phase. There is no module-management UI, online installation/removal or runtime API/queue disabling. 
