<?php

namespace Fayne\Page\Console;

use Fayne\CMS\Models\Tenant;
use Fayne\CMS\Support\ContentLocale;
use Fayne\CMS\Support\TenantContext;
use Fayne\Page\Database\Seeders\DemoHomepageSeeder;
use Fayne\Page\Database\Seeders\InitializeRolesAndPermissionsSeeder;
use Illuminate\Console\Command;
use Illuminate\Validation\ValidationException;

class InitializeCommand extends Command
{
    protected $signature = 'cms:page:init {--tenant= : Target tenant ID or slug for optional demo data}';

    protected $description = 'Initialize page permissions and optionally create bilingual demo homepages.';

    public function handle(): int
    {
        $status = $this->call('db:seed', ['--class' => InitializeRolesAndPermissionsSeeder::class]);
        if ($status !== self::SUCCESS || ! $this->input->isInteractive() || ! $this->confirm(__('filament-page::commands.confirm_demo'), false)) {
            return $status;
        }

        try {
            if (! config('filament-cms.tenancy.enabled', false)) {
                if ($this->option('tenant') !== null) {
                    $this->error(__('filament-page::commands.tenancy_disabled'));

                    return self::FAILURE;
                }

                return $this->seedDemo();
            }
            $tenants = $this->targetTenants();
            if ($tenants === []) {
                return self::FAILURE;
            }

            foreach ($tenants as $tenant) {
                $this->line($tenant->name.' ('.$tenant->slug.')');
                $status = app(TenantContext::class)->run($tenant, fn (): int => $this->seedDemo());
                if ($status !== self::SUCCESS) {
                    return $status;
                }
            }

            return self::SUCCESS;
        } catch (ValidationException $exception) {
            $this->error(collect($exception->errors())->flatten()->implode(' '));

            return self::FAILURE;
        }
    }

    private function targetTenants(): array
    {
        $target = $this->option('tenant') ?? app(TenantContext::class)->id();
        $query = Tenant::where('is_active', true);
        if ($target !== null) {
            $tenant = ctype_digit((string) $target) ? $query->find($target) : $query->where('slug', $target)->first();
            if (! $tenant) {
                $this->error(__('filament-page::commands.tenant_not_found'));
            }

            return $tenant ? [$tenant] : [];
        }
        $tenants = $query->orderBy('id')->get();
        if ($tenants->isEmpty()) {
            $this->error(__('filament-page::commands.tenant_not_found'));

            return [];
        }
        $labels = $tenants->mapWithKeys(fn (Tenant $tenant): array => [$tenant->id => $tenant->name.' ('.$tenant->slug.')']);
        $selected = $this->choice(__('filament-page::commands.select_tenant'), $labels->values()->all(), multiple: true);

        return $tenants->whereIn('id', $labels->filter(fn ($label) => in_array($label, $selected, true))->keys())->all();
    }

    private function seedDemo(): int
    {
        $status = $this->call('db:seed', ['--class' => DemoHomepageSeeder::class]);
        if ($status === self::SUCCESS) {
            $this->info(__('filament-page::commands.demo_ready'));
            if (! ContentLocale::enabled()) {
                $this->warn(__('filament-page::commands.multilingual_disabled'));
            }
        }

        return $status;
    }
}
