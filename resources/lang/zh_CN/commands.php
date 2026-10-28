<?php

return [
    'confirm_demo' => '是否创建中英文演示首页？',
    'select_tenant' => '选择创建演示首页的租户（可多选）',
    'tenant_not_found' => '未找到符合条件的启用租户，请先创建租户或检查 --tenant 参数。',
    'tenancy_disabled' => '只有开启租户模式后才能使用 --tenant 参数。',
    'bilingual_locales_required' => '创建双语演示首页前，目标站点或租户必须支持 en 和 zh_CN（或 zh-CN）。',
    'demo_ready' => '演示首页初始化完成；已有首页或根路径页面保持不变，新页面默认未启用、未发布。',
    'multilingual_disabled' => '当前未开启内容多语言，只显示默认语言；可在站点或租户设置中启用多语言。',
    'homepage_config_incompatible' => '示例首页需要可用的 homepage 类型、允许普通组件且未自定义固定组件；请检查首页配置。',
    'definition_conflict' => '已有组件 :slug 与示例结构不一致，已保留原定义，未创建演示首页。',
];
