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
php artisan migrate
php artisan filament:assets
php artisan cms:page:init
```

`cms:page:init` 初始化权限目录并询问是否创建演示首页，不创建管理员或自动给账号授予超级管理员权限。`app:init`、`admin:create` 及宿主默认组件库导入不是本包自动提供的命令。

迁移面向全新数据库，默认保留 `nova_pages` 等表名；可通过 `tables` 配置映射。旧项目应单独评估数据升级，不能在业务数据库使用 `migrate:fresh`。

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

## 3. 页面、组件与模板

1. 先创建或导入 Component/Widget 定义，再配置字段类型、选项和验证规则。
2. 创建页面，选择类型和内容语言，填写以 `/` 开头且不含查询串/片段的路径、标题和 SEO 信息。
3. 添加内容区块；可在快捷搭建面板点击或拖入组件，并调整顺序。关闭面板不会保存，仍需保存页面表单。固定组件不能在该入口任意增删排序。
4. 需要复用结构时保存为模板。模板保留区块/嵌套 Widget 结构，不复制正文属性值；应用时生成新的区块 key。
5. 修改发布过的页面后，可暂存并恢复编辑；发布成功事件会将冻结内容提升为发布修订。

```php
// config/filament-page.php
'creation_limited_page_types' => ['homepage' => 1, '404_page' => 1],
'page_type_changeable' => false,
'fixed_components' => [],
'excluded_components' => [],
'use_default_frontend_routes' => true,
```

默认类型为 `basic`、`homepage`、`404_page`；可用实现 `getSelectOptions()` 的自定义类型类替换 `page_types_class`。类型数量上限按当前租户/区域及内容语言校验。`meta` 保存附加 SEO 数据，HTML 标签由前端渲染，本包不生成公开网站。

## 4. 中英文与内容语言

界面提供 `zh_CN` 与 `en`；管理员个人资料中的界面语言不改变页面内容语言。内容语言在 Core 全局或租户中配置：

```php
// config/filament-cms.php
'multilingual_enabled' => true,
'default_locale' => 'en',
'supported_locales' => ['en' => 'English', 'zh_CN' => '简体中文'],
```

列表按翻译组展示，编辑页切换已有译本或创建缺失语言。不同语言版本分别编辑路径和内容，不自动机器翻译。自定义组件名称、字段标签和帮助文字需要自行提供多语言。前端使用 `locale` 请求具体内容；v2 详情提供可用公开译本，缺失语言不会静默返回另一语言。

## 5. 演示首页与组件库

```bash
php artisan cms:page:init
php artisan cms:page:init --tenant=shop
php artisan cms:page:init --no-interaction
```

交互默认不创建演示数据。`--tenant` 接受启用租户的 ID 或 slug；未指定时优先沿用可信上下文，否则多选租户。单站不传此参数。非交互模式只初始化权限。

`Fayne\Page\Database\Seeders\DemoHomepageSeeder` 创建同一翻译组的中英文 `/` 首页，并创建 `demo-home-hero`、`demo-home-content` 定义。示例包括横幅、精选商品与购物服务，默认停用、标记变更并设置 `no_index`；不创建实际商品。支持语言需包含 `en` 及 `zh_CN`/`zh-CN`。已有首页、根路径或相关已发布根路径时跳过，定义冲突会报错而不是覆盖。

组件列表支持上传/粘贴 JSON 导入，以及导出当前范围的组件库。可使用包内 `resources/defaults/components.json`。重复 slug 时选择更新或跳过；导入会重映射嵌套 Widget 引用并保留未提交字段。图片仅保留 disk/path 元数据，实际文件需另行复制到相应磁盘；组件库导入不导入页面正文。

## 6. 前端 API 与草稿预览

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

## 7. 冻结构建与宿主职责

冻结接口跟随 Core 的 `CMS_HEADLESS_PREFIX`，要求服务端 `CMS_BUILD_API_TOKEN` 与清单租户匹配。只允许 `frozen`/`succeeded` 清单；旧无租户归属清单拒绝读取，需重新发布。

```bash
curl -H "Authorization: Bearer $CMS_BUILD_API_TOKEN" \
  'https://cms.example.com/headless/releases/RELEASE_ID/pages?path=/'
```

静态构建应从同一 release ID 读取页面、菜单和设置，而不是混用实时接口。冻结条目的 `projection` 是构建输入；`frozen` 不是已部署成功。普通公开页面仍用当前定义解析内容，需要不可变构建输入时使用冻结接口。

本包提供快照及发布成功后的修订处理，不独立提供完整部署编排、预约发布或外部构建服务。宿主需接入发布流程、签名回调、失败处理与回滚。关闭 `use_default_frontend_routes` 不会关闭独立注册的 draft 和冻结接口。

## 8. 配置扩展、测试与上线

`filament-page.models` 与 `tables` 可替换模型和表名；自定义模型需保留租户、语言、修订和内容契约。复杂字段、文件磁盘、HTTP 与实体解析见 [Page Builder](../laravel-filament-page-builder/README.md)。

在本包目录执行：

```bash
composer install
composer test
composer format
node --test tests/Browser/quick-builder-drag.test.cjs
```

上线前验证组件导入、保存重开、真实拖拽、两种语言、公开/草稿 API、区域与租户隔离，以及同一 release 的构建/回调。检查实际存储 URL、队列和缓存；部署全部依赖并刷新配置/路由缓存，重启长驻进程。包测试不等于生产链路验收。

## 贡献代码

1. 创建功能分支。
2. 实现所需改动，并为新增功能或缺陷修复补充测试。
3. 确认相关测试通过，注明尚未完成的环境验收。
4. 修改公开用法时，同步更新中英文文档。
5. 提交合并请求。

## 许可证

本软件采用[专有许可证](LICENSE.md)，使用与分发须遵守其中的授权限制。
