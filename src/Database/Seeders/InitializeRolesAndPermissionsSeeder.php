<?php

namespace Fayne\Page\Database\Seeders;

use Fayne\CMS\Support\Authorization\PermissionCatalog;
use Illuminate\Database\Seeder;

class InitializeRolesAndPermissionsSeeder extends Seeder
{
    public function run(): void
    {
        PermissionCatalog::seed();
    }
}
