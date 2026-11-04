<p align="center"><img src="https://res.cloudinary.com/fayne/image/upload/v1787643269/fayne_logo.png" width="300"></p>

# Laravel Filament Page

简体中文 | [English](docs/README.en.md)

适用于 **PHP 8.4+、Laravel 13、Filament 5** 的页面内容管理包。提供页面、动态组件、Widget、结构模板、修订与多语言管理，并为前端提供内容、草稿预览和冻结构建 API。

## 功能与边界

| 能力 | 说明 |
| --- | --- |
| 页面管理 | 普通页面、首页、404 页面，路径、SEO、meta、启用与变更状态 |
| 动态内容 | Page Builder 区块、嵌套 Widget、固定组件及快捷搭建面板 |
| 定义管理 | Component/Widget 字段、可视化选项与规则、组件库 JSON 导入导出 |
| 模板与修订 | 保存结构模板、暂存与恢复编辑、成功发布后保存冻结内容修订 |
| 中英文与译本 | 简体中文/英语界面，独立内容语言与翻译组 |
| 前端与发布 | 当前公开内容、页面限定草稿 token 和冻结发布快照 |

## 1. 全新项目安装

先按 [CMS Core](../laravel-filament-cms-core/README.md) 配置宿主认证、Panel 与权限。在宿主 Composer 中配置本私有包及 CMS Core、Page Builder 和相关字段包的软件源后安装：

```bash
composer require fayne/laravel-filament-page
php artisan vendor:publish --tag=filament-page-config
php artisan cms:install
php artisan filament:assets
php artisan cms:page:install
```

`cms:page:install` 检查模块表结构是否就绪，默认不生成内容；请先运行 `cms:install`，由 Core 统一执行迁移和权限初始化。仅显式传入 `--demo` 才创建未发布的中英文演示内容，不再询问是否生成示例。生产环境需确认或使用 `--force`。不创建管理员或自动授予账号权限。`app:init`、`admin:create` 是宿主命令。

本包权限由 [`resources/permissions.php`](resources/permissions.php) 自行定义，Service Provider 注册到 Core 的 `PermissionRegistry`；Core 不再硬编码本模块权限。运行 `cms:install` 汇总已注册模块并创建默认角色。已有角色不自动扩权；新增模块后须明确配置新增授权。详见 [权限扩展](../laravel-filament-cms-core/docs/permission-registration.md)。

迁移面向全新项目：修订发布幂等字段 `publication_manifest_key` 及 `(page_id, publication_manifest_key)` 唯一索引直接包含在初始建表迁移中，不再使用单独的增列迁移。

## 2. 注册后台与权限

在普通站点 Panel 注册 Page 插件；多租户时注册在租户工作台。保留 CMS 插件和完整的认证、session、CSRF 及租户中间件。

```php
use Fayne\Page\Filament\PagePlugin;

return $panel->plugins([
    // Keep the host's existing plugins.
    PagePlugin::make(),
]);
```

插件注册页面、模板、组件、Widget、字段与修订资源。通过 Core 角色管理分配查看、创建、修改、删除及相关操作权限；组件定义和正文图片上传也有独立授权。租户工作台使用 `SetTenantContext`，平台超级管理员不会自动绕过租户成员关系。

## 3. 通过配置扩展 Model、Resource 与 URI

宿主可以分别替换模型、后台 Resource 和后台路径，无需修改包源码，也无需复制所有内置页面。对应旧 Nova 的 `models` / `resources` / `uri_key`，Filament 使用 `models` / `resources` / `resource_slugs`。

先创建继承包模型的宿主模型：

```php
namespace App\Models;

class Page extends \Fayne\Page\Models\Page
{
}
```

再继承包 Resource；下面在原表单中追加宿主字段，使用已有 JSON 属性保存：

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

在已发布配置中设置对应键。以下是配置片段；配置使用 Laravel 原生递归合并，宿主同名值优先，未填写的嵌套键保留包默认值：

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

- 模型、Resource 和 slug 可独立配置，不要求三者同时替换。`models` 是模型选择的配置入口，不依赖宿主 Resource 的 `$model` 属性猜测配置。
- 两个映射支持的键为：`page`、`page_template`、`page_revision`、`page_component`、`page_component_field`、`page_widget`、`page_widget_field`。Page Template 的创建、查询和权限也遵循 `models.page_template`。自定义类应继承对应包类，并保留原有租户、语言、关联、内容及权限契约。
- 插件注册配置的 Resource；内置列表、创建、编辑及关联入口跟随新类。模板、修订和语言切换不会回跳默认 Resource。宿主可以继承 `getPages()`，也可覆写它以替换个别页面。
- `resource_slugs` 默认为空。未配置时保留 Filament 原生 `$slug`、默认推导及 Panel ResourceConfiguration；继承包方法时，显式配置优先。自行覆写 `getSlug()` 且不调用父方法时，由宿主实现负责处理配置。
- `content-pages` 修改的是后台 Resource 路径，例如 `/admin/content-pages`；不修改前端内容路径或 Headless API。导航标签、分组、表格和操作可用 Filament 原生覆写方式定制。
- 继续通过 `PagePlugin` 注册；若宿主启用了 Resource 自动发现，避免同时发现/注册默认类与替代类。自定义 Policy 使用 Laravel 标准注册方式，并在宿主中确保注册顺序，不绕过原有授权范围。

修改后清理并按部署流程重新生成配置/路由缓存，重启长驻进程。旧版已发布配置可以补充上述新键，缺失模型/Resource 默认键会自动补齐；不需要改动业务表或内容结构。

## 4. 页面、组件与模板

1. 先创建或导入 Component/Widget 定义，再配置字段类型、选项和验证规则。
2. 创建页面，选择类型和内容语言，填写以 `/` 开头且不含查询串/片段的路径、标题和 SEO 信息。
3. 通过方块组合图标打开快捷搭建，使用返回箭头图标返回内容编辑；两个入口均保留悬停提示。添加内容区块；可在快捷搭建面板点击或拖入组件，并调整顺序。左侧卡片有预览图时仅在图片 alt 中保留组件名称，无图时显示名称；悬停或键盘聚焦卡片后显示编辑、上移、下移和移除图标，触屏设备直接显示操作。拖拽排序和上移、下移在浏览器中立即生效，不发起排序请求；顺序随页面表单保存。关闭面板不会保存，仍需保存页面表单。固定组件不能在该入口任意增删排序。
4. 需要复用结构时保存为模板。模板保留区块/嵌套 Widget 结构，不复制正文属性值；应用时生成新的区块 key。
5. 修改发布过的页面后，可先保存再暂存：已保存的草稿收进暂存记录，当前页面恢复到上次发布内容；恢复暂存可取回草稿。暂存和恢复都会在存在未保存输入时拒绝执行并提示先保存，避免覆盖表单。发布成功事件会将冻结内容提升为发布修订。

```php
// config/filament-page.php
'creation_limited_page_types' => ['homepage' => 1, '404_page' => 1],
'page_type_changeable' => false,
'fixed_components' => [],
'excluded_components' => [],
'use_default_frontend_routes' => true,
```

默认类型为 `basic`、`homepage`、`404_page`；可用实现 `getSelectOptions()` 的自定义类型类替换 `page_types_class`。类型数量上限按当前租户/区域及内容语言校验。禁用默认类型上限时，将对应键设为 `null`（例如 `'homepage' => null`）；空数组不会清除递归合并的默认键。`meta` 保存附加 SEO 数据，HTML 标签由前端渲染，本包不生成公开网站。

## 5. 中英文与内容语言

界面提供 `zh_CN` 与 `en`；管理员个人资料中的界面语言不改变页面内容语言。内容语言在 Core 全局或租户中配置：

```php
// config/filament-cms.php
'multilingual_enabled' => true,
'default_locale' => 'en',
'supported_locales' => ['en' => 'English', 'zh_CN' => '简体中文'],
```

列表按翻译组展示，编辑页切换已有译本或创建缺失语言。所有支持语言均已创建时隐藏“创建翻译”；只有一个可切换译本时直接跳转，多个时弹窗选择，没有可访问译本时隐藏“切换语言”。未保存修改时需先保存。不同语言版本分别编辑路径和内容，不自动机器翻译。自定义组件名称、字段标签和帮助文字需要自行提供多语言。前端使用 `locale` 请求具体内容；v2 详情提供可用公开译本，缺失语言不会静默返回另一语言。

## 6. 演示首页与组件库

```bash
php artisan cms:page:install
php artisan cms:page:install --demo
php artisan cms:page:install --demo --tenant=shop
php artisan cms:page:install --no-interaction
```

默认安装和非交互安装均不生成演示数据。显式 `--demo` 可用于交互或自动化。`--tenant` 必须与 `--demo` 一起使用，接受启用租户的 ID 或 slug；多租户非交互运行未指定目标时返回失败；交互运行可沿用可信上下文或多选启用租户。单租户不传 `--tenant`，内容写入作用域 0。示例内容失败会返回失败状态，已完成的基础安装不会回滚。

`Fayne\Page\Database\Seeders\DemoHomepageSeeder` 创建同一翻译组的中英文 `/` 首页，并创建 `demo-home-hero`、`demo-home-content` 定义。示例包括横幅、精选商品与购物服务，默认停用、标记变更并设置 `no_index`；不创建实际商品。支持语言需包含 `en` 及 `zh_CN`/`zh-CN`。已有首页、根路径或相关已发布根路径时跳过，定义冲突会报错而不是覆盖。

组件列表支持上传/粘贴 JSON 导入，以及导出当前范围的组件库。可使用包内 `resources/defaults/components.json`。重复 slug 时选择更新或跳过；导入会重映射嵌套 Widget 引用并保留未提交字段。图片仅保留 disk/path 元数据，实际文件需另行复制到相应磁盘；组件库导入不导入页面正文。

## 7. 前端 API 与草稿预览

| 用途 | 请求 | 行为 |
| --- | --- | --- |
| 页面列表 | `GET /modules/pages/slugs?locale=en` | 页面名称与公开路径数组 |
| 页面详情 | `GET /modules/pages/detail/{path?}?locale=en` | 根路径可省略 `path`；路径可包含 `/` |
| v2 页面列表 | `GET /modules/pages/v2/slugs?locale=en` | 指定内容语言的页面列表 |
| v2 页面详情 | `GET /modules/pages/v2/detail/{path?}?locale=en` | 额外返回 `translations` 和 `language` 元数据，不自动回退到另一语言 |
| 草稿预览 | `GET /headless/preview/draft?page=/about` | 页面限定的 preview Bearer；响应 `private, no-store` |
| 冻结构建输入 | `GET /headless/releases/{releaseId}/pages?path=/about` | `data` 中的冻结快照条目；`path` 可省略以读取全部条目 |

公开接口只读取启用页面：存在发布修订时使用修订内容，旧数据没有修订时回退到当前内容。可传配置白名单中的 `region`。多租户按启用域名解析请求 Host，未知/停用域名返回 404；单站不需要域名映射。

设置前端预览地址后，后台显示预览入口：

```dotenv
FILAMENT_PAGE_PREVIEW_URL=https://frontend.example.com/preview
```

入口在前端 URL 中传递 `path` 与 `preview_token`。前端用 token 作为 Bearer 请求 CMS：

```bash
curl -H "Authorization: Bearer $PREVIEW_TOKEN" \
  'https://cms.example.com/headless/preview/draft?page=/about'
```

token 有效期 600 秒、可重复读取，绑定租户、页面、路径、语言和数据库连接；每次重新验证管理员状态、成员关系及查看权限。仅已保存到服务器的编辑可被读取，不包含浏览器未保存状态。响应正文为 `data`，包含 `entity_type`、`entity_id`、`revision_id`、`path`、`locale` 与 `projection`。避免将 token 写入访问日志或分析系统。

## 8. 冻结构建与宿主职责

冻结接口跟随 Core 的 `CMS_HEADLESS_PREFIX`，要求服务端 `CMS_BUILD_API_TOKEN` 与清单租户匹配。只允许 `frozen`/`succeeded` 清单；旧无租户归属清单拒绝读取，需重新发布。

```bash
curl -H "Authorization: Bearer $CMS_BUILD_API_TOKEN" \
  'https://cms.example.com/headless/releases/RELEASE_ID/pages?path=/'
```

静态构建应从同一 release ID 读取页面、菜单和设置，而不是混用实时接口。冻结条目的 `projection` 是构建输入；`frozen` 不是已部署成功。普通公开页面仍用当前定义解析内容，需要不可变构建输入时使用冻结接口。

本包提供快照及发布成功后的修订处理，不独立提供完整部署编排、预约发布或外部构建服务。宿主需接入发布流程、签名回调、失败处理与回滚。关闭 `use_default_frontend_routes` 不会关闭独立注册的 draft 和冻结接口。

## 9. 配置扩展、测试与上线

`filament-page.models` 与 `tables` 可替换模型和表名；自定义模型需保留租户、语言、修订和内容契约。复杂字段、文件磁盘、HTTP 与实体解析见 [Page Builder](../laravel-filament-page-builder/README.md)。

在本包目录执行：

```bash
composer install
composer test
composer format
node --test tests/Browser/quick-builder-drag.test.cjs
```

上线前验证组件导入、保存重开、真实拖拽、两种语言、公开/草稿 API、区域与租户隔离，以及同一 release 的构建/回调。检查实际存储 URL、队列和缓存；部署全部依赖并刷新配置/路由缓存，重启长驻进程。包测试不等于生产链路验收。

开发包的 `composer.lock` 不提交，允许本地生成并忽略；宿主应用应保留自己的锁文件用于生产部署。

## 贡献代码

1. 创建功能分支。
2. 实现所需改动，并为新增功能或缺陷修复补充测试。
3. 确认相关测试通过，注明尚未完成的环境验收。
4. 修改公开用法时，同步更新中英文文档。
5. 提交合并请求。

## 许可证

本软件采用[专有许可证](LICENSE.md)，使用与分发须遵守其中的授权限制。

## 整站冻结 API v2

启用 Core 发布路由后，`/headless/releases/{release_id}/pages` 从不可变公共清单读取，不依赖当前区域、语言或路由配置。通过所属租户域名及构建 Bearer token 鉴权。页面枚举支持 `target_id`、`scope_id`；多范围 source_path 查询必须指定 scope，最终 path 匹配多个范围时也需 scope。未知或不兼容范围/目标返回 422。响应携带冻结最终 URL、翻译 URL，不暴露原始晋升内容和连接标识。整站页面在所有区域晋升完成后才记录成功，部分失败可重试且不重复创建发布版本。

内置组件预览采用中性 SVG 线框示意图，并自动继承后台主题主色。

默认组件的 mockup 使用包自动注册的 `page-component-mockups` 磁盘，读取包内 SVG 并提供图片访问路由，无需编辑宿主的 `config/filesystems.php` 或创建存储软链接。宿主可通过同名磁盘配置覆盖默认设置。

页面列表提供详情入口，组件内容支持效果视图（按组件顺序堆叠 mockup）、格式化 JSON 和紧凑 JSON 三种模式。Demo 首页组件包含主题色线框预览，重新运行 Demo Seeder 会补齐缺失的预览。

详情页使用与编辑页一致的基础信息、页面内容、SEO 设置和高级设置四个标签页。


本包权限名称、分组及标准操作由 `resources/permissions.php` 声明，中英文译文由本包 `resources/lang` 提供。CMS Core 仅汇总和解析，不再维护本包的业务名称映射。
