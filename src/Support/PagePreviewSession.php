<?php

namespace Fayne\Page\Support;

use Closure;
use Fayne\CMS\Models\Admin;
use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Support\Authorization\AuthorizationContext;
use Fayne\CMS\Support\TenantContext;
use Fayne\Page\Models\Page;
use Illuminate\Contracts\Cache\Repository;
use Illuminate\Support\Facades\Context;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;

final class PagePreviewSession
{
    public function __construct(private Repository $cache) {}

    public function issue(Page $page, Admin $admin): string
    {
        Gate::forUser($admin)->authorize('view', $page);
        $token = Str::random(64);
        $this->cache->put($this->key($token), ['tenant_id' => $page->tenant_id, 'page_id' => $page->getKey(), 'path' => $page->path, 'connection' => $page->getConnectionName(), 'admin_id' => $admin->getKey(), 'locale' => $page->locale], 600);

        return $token;
    }

    public function resolve(string $token, string $path): ?Page
    {
        return $this->run($token, $path, fn (Page $page) => $page);
    }

    public function run(string $token, string $path, Closure $callback): mixed
    {
        $session = $this->cache->get($this->key($token));
        if (! is_array($session) || ! isset($session['tenant_id']) || ! hash_equals($session['path'], $path)) {
            return null;
        }

        $resolve = function () use ($session, $path, $callback) {
            // The token pins its connection; ignore the current session/request region.
            return Context::scope(fn () => app(AuthorizationContext::class)->run((int) $session['tenant_id'], function () use ($session, $path, $callback) {
                $admin = Admin::find($session['admin_id']);
                $class = config('filament-page.models.page');
                $page = (new $class)->setConnection($session['connection'])->newQuery()->whereKey($session['page_id'])->where('path', $path)->where('locale', $session['locale'])->first();

                return $admin && $page && Gate::forUser($admin)->allows('view', $page) ? $callback($page) : null;
            }), ['filament-cms.region_id' => '']);
        };

        if (! config('filament-cms.tenancy.enabled', false)) {
            return (int) $session['tenant_id'] === 0 ? $resolve() : null;
        }

        $tenant = Tenant::query()->where('is_active', true)->find($session['tenant_id']);

        return $tenant ? app(TenantContext::class)->run($tenant, $resolve) : null;
    }

    private function key(string $token): string
    {
        return 'filament-page:preview:'.hash('sha256', $token);
    }
}
