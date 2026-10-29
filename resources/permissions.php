<?php

return [
    'resources' => ['Page', 'PageRevision', 'PageComponent', 'PageComponentField', 'PageWidget', 'PageWidgetField'],
    'permissions' => [
        'editor' => ['tenant.viewSensitiveContentPage'],
        'restricted' => ['tenant.executeContentHttpPage'],
    ],
];
