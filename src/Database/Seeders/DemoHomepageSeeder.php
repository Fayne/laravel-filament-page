<?php

namespace Fayne\Page\Database\Seeders;

use Fayne\CMS\Support\ContentLocale;
use Fayne\PageBuilder\Support\ContentStateAdapter;
use Fayne\PageBuilder\Support\EloquentContentDefinitions;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class DemoHomepageSeeder extends Seeder
{
    public function run(): void
    {
        $model = config('filament-page.models.page');
        (new $model)->getConnection()->transaction(function () use ($model): void {
            if ($model::where(fn ($query) => $query->where('type', 'homepage')->orWhere('path', '/')
                ->orWhereHas('currentPublishedRevision', fn ($revision) => $revision->where('path', '/')))->exists()) {
                return;
            }
            $english = ContentLocale::normalize('en');
            $chinese = ContentLocale::normalize('zh_CN') ?? ContentLocale::normalize('zh-CN');
            if ($english === null || $chinese === null) {
                throw ValidationException::withMessages(['locale' => __('filament-page::commands.bilingual_locales_required')]);
            }
            if (! array_key_exists('homepage', config('filament-page.page_types_class')::getSelectOptions())
                || config('filament-page.fixed_components.homepage', []) !== []
                || in_array('homepage', config('filament-page.excluded_components', []), true)
                || (config('filament-page.creation_limited_page_types.homepage') !== null && config('filament-page.creation_limited_page_types.homepage') < 1)) {
                throw ValidationException::withMessages(['type' => __('filament-page::commands.homepage_config_incompatible')]);
            }
            $this->createDefinitions();
            $definitions = new EloquentContentDefinitions(config('filament-page.models.page_component'), config('filament-page.models.page_widget'));
            $group = (string) Str::uuid();
            foreach ([$english, $chinese] as $language => $locale) {
                $content = $this->content($language);
                (new ContentStateAdapter($definitions))->toBuilder($content);
                $model::create([
                    'name' => $language === 0 ? 'Demo store homepage' : '演示商城首页',
                    'title' => $language === 0 ? 'Welcome to our demo store' : '欢迎来到演示商城',
                    'description' => $language === 0 ? 'An example shopping homepage with featured products and service information.' : '包含精选商品和购物服务说明的示例商城首页。',
                    'type' => 'homepage', 'path' => '/', 'locale' => $locale, 'translation_group_id' => $group,
                    'components' => $content, 'fixed_components' => [], 'is_active' => false, 'changed' => true, 'no_index' => true,
                ]);
            }
        });
    }

    private function createDefinitions(): void
    {
        $model = config('filament-page.models.page_component');
        foreach ([
            'demo-home-hero' => ['Demo homepage hero', ['title' => 'text', 'subtitle' => 'text', 'button_label' => 'text', 'button_url' => 'text']],
            'demo-home-content' => ['Demo homepage content', ['body' => 'rich_text']],
        ] as $slug => [$name, $fields]) {
            $component = $model::firstOrCreate(['slug' => $slug], ['name' => $name]);
            if (! $component->wasRecentlyCreated) {
                $existing = $component->fields()->get()->mapWithKeys(fn ($field) => [$field->slug => $field->resolver])->all();
                ksort($existing);
                $expected = $fields;
                ksort($expected);
                if ($existing !== $expected || $component->fields()->get()->contains(fn ($field) => ! empty($field->options) || ! empty($field->rules))) {
                    throw ValidationException::withMessages(['components' => __('filament-page::commands.definition_conflict', ['slug' => $slug])]);
                }

                continue;
            }
            foreach ($fields as $field => $resolver) {
                $component->fields()->create(['title' => Str::headline($field), 'slug' => $field, 'resolver' => $resolver, 'options' => [], 'rules' => []]);
            }
        }
    }

    private function content(int $language): array
    {
        $hero = $language === 0
            ? ['title' => 'Discover your next favorite', 'subtitle' => 'Explore everyday essentials and new arrivals in our demo store.', 'button_label' => 'Shop now', 'button_url' => '/categories']
            : ['title' => '发现你的下一件心仪好物', 'subtitle' => '探索演示商城的生活好物与上新精选。', 'button_label' => '立即选购', 'button_url' => '/categories'];
        $products = $language === 0
            ? '<h2>Featured products</h2><p>Example products for this demo homepage.</p><ul><li><p>Everyday smartphone — $199</p></li><li><p>Wireless headphones — $59</p></li><li><p>Travel bottle — $29</p></li></ul>'
            : '<h2>精选商品</h2><p>以下商品仅用于演示首页内容。</p><ul><li><p>日常智能手机 — ¥1299</p></li><li><p>无线耳机 — ¥399</p></li><li><p>旅行保温杯 — ¥99</p></li></ul>';
        $services = $language === 0
            ? '<h2>Shopping services</h2><p>Discover our shipping, returns and customer support information.</p><p><a href="/help/shipping">Shipping guide</a> · <a href="/returns">Returns</a> · <a href="/contact">Contact us</a></p>'
            : '<h2>购物服务</h2><p>了解配送、退换货及客户服务说明。</p><p><a href="/help/shipping">配送指南</a> · <a href="/returns">退换货政策</a> · <a href="/contact">联系我们</a></p>';

        return [
            ['layout' => 'demo-home-hero', 'key' => (string) Str::uuid(), 'attributes' => $hero],
            ['layout' => 'demo-home-content', 'key' => (string) Str::uuid(), 'attributes' => ['body' => $products]],
            ['layout' => 'demo-home-content', 'key' => (string) Str::uuid(), 'attributes' => ['body' => $services]],
        ];
    }
}
