<?php

return [
    'tenant_requires_demo' => 'The --tenant option requires --demo.',
    'install_core_first' => 'Run cms:install before installing this module.',
    'installed' => 'Page installation is ready. No demo content was created.',
    'tenant_required' => 'Use --tenant with --demo in noninteractive tenant mode.',

    'select_tenant' => 'Select tenants for the demo homepages',
    'tenant_not_found' => 'No matching active tenant was found. Create a tenant first or check --tenant.',
    'tenancy_disabled' => 'The --tenant option requires tenant mode to be enabled.',
    'bilingual_locales_required' => 'The target site or tenant must support en and zh_CN (or zh-CN) before creating bilingual demo homepages.',
    'demo_ready' => 'Demo homepage initialization finished. Existing homepages or root pages were preserved; new demo pages remain inactive and unpublished.',
    'multilingual_disabled' => 'Multilingual content is disabled: only the default language is shown until it is enabled in the site or tenant settings.',
    'homepage_config_incompatible' => 'The demo homepage requires an available homepage type with ordinary components and no custom fixed components.',
    'definition_conflict' => 'The existing component :slug differs from the demo schema. It has been preserved; no demo homepage was created.',
];
