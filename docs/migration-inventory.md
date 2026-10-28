# 工程迁移对照

此表只说明文件职责去向，功能是否完成另见状态文件。`docs/legacy-reference` 是原 Nova 使用资料，不应作为新包安装指引。

| 原文件/目录 | 目标与处理 |
| --- | --- |
| `.gitignore` | 适配独立依赖及测试缓存 |
| `LICENSE.md` | 原文保留 |
| `README.md` | 新安装说明；原文归档到 docs/legacy-reference |
| `composer.json` | PHP 8.4 / Laravel 13 / Filament 5 与独立测试工具链 |
| `config` | 新 filament 配置；旧 Nova Resource 类引用移除 |
| `docs` | 原文归档到 docs/legacy-reference/docs；新规格/状态在 packages/docs |
| `package-lock.json` | 旧 Nova 依赖锁不适用，未复制 |
| `package.json` | 旧 Nova 前端构建不适用；当前 UI 无新增独立前端编译入口 |
| `resources` | 语言文件保留；Nova/Vue 界面由原生 Builder 或 Blade 树替代 |
| `routes` | 保留公开业务路径，预览增加独立授权 |
| `src` | 按模型/服务/策略/Filament Resource 职责迁移，功能核对继续进行 |
| `tests` | 原契约归入包内测试，新增 Testbench/Pest 独立环境 |
