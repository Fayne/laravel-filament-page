<?php

namespace Fayne\Page\Policies;

use Fayne\CMS\Models\Admin;
use Fayne\CMS\Support\Authorization\Authorization;
use Fayne\CMS\Support\TenantContext;
use Illuminate\Database\Eloquent\Model;

class PagePolicy
{
    public function before($admin, string $ability, $record = null): ?bool
    {
        if ($record instanceof Model
            && (app(TenantContext::class)->id() === null
                || (string) $record->getAttribute('tenant_id') !== (string) app(TenantContext::class)->id())) {
            return false;
        }

        return null;
    }

    public function viewAny(Admin $admin): bool
    {
        return $this->allows($admin, 'viewAny');
    }

    public function view(Admin $admin): bool
    {
        return $this->allows($admin, 'view');
    }

    public function create(Admin $admin): bool
    {
        return $this->allows($admin, 'create');
    }

    public function update(Admin $admin): bool
    {
        return $this->allows($admin, 'update');
    }

    public function delete(Admin $admin): bool
    {
        return $this->allows($admin, 'delete');
    }

    public function replicate(Admin $admin): bool
    {
        return $this->allows($admin, 'replicate');
    }

    public function viewSensitiveContent(Admin $admin): bool
    {
        return $this->allows($admin, 'viewSensitiveContent');
    }

    public function executeContentHttp(Admin $admin): bool
    {
        return $this->allows($admin, 'executeContentHttp');
    }

    private function allows(Admin $admin, string $ability): bool
    {
        return $admin->is_active && Authorization::allows($admin, 'tenant.'.$ability.'Page');
    }
}
