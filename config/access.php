<?php

return [
    // No implicit default role, inheritance, wildcards, or superuser bypass.
    'roles' => [
        // OWNER: read-only features, plus exclusive AI access.
        'owner' => [
            'kebun.view' => 'any',
            'lahan.view' => 'any',
            'activities.view' => 'any',
            'plantings.view' => 'any',
            'plantings.progress.view' => 'any',
            'harvests.view' => 'any',
            'finance.sales.view' => 'any',
            'finance.purchases.view' => 'any',
            'users.view' => 'any',
            'sops.view' => 'any',
            'forms.view' => 'any',
            'ai.access' => 'any',
        ],

        // SUPER ADMIN KEBUN: user, kebun/lahan, SOP, and form management.
        'superadmin' => [
            'users.view' => 'any',
            'users.create' => 'any',
            'users.update' => 'any',
            'users.delete' => 'any',
            'kebun.view' => 'any',
            'kebun.create' => 'any',
            'kebun.update' => 'any',
            'kebun.delete' => 'any',
            'lahan.view' => 'any',
            'lahan.create' => 'any',
            'lahan.update' => 'any',
            'lahan.delete' => 'any',
            'sops.view' => 'any',
            'sops.create' => 'any',
            'sops.update' => 'any',
            'sops.delete' => 'any',
            'forms.view' => 'any',
            'forms.configure' => 'any',
        ],

        // ADMIN KEBUN: finance and details of assigned kebun.
        'admin' => [
            'kebun.view' => 'own',
            'lahan.view' => 'own',
            'finance.sales.view' => 'own',
            'finance.sales.create' => 'own',
            'finance.purchases.view' => 'own',
            'finance.purchases.create' => 'own',
        ],

        // PETUGAS KEBUN: operations in the single assigned kebun.
        'petugas' => [
            'kebun.view' => 'own',
            'lahan.view' => 'own',
            'activities.view' => 'own',
            'activities.create' => 'own',
            'plantings.view' => 'own',
            'plantings.create' => 'own',
            'plantings.update' => 'own',
            'plantings.progress.view' => 'own',
            'plantings.progress.create' => 'own',
            'plantings.progress.update' => 'own',
            'harvests.view' => 'own',
            'harvests.create' => 'own',
        ],
    ],

    // Global assignment limits for own-scoped access; omitted roles have no cap.
    'kebun_assignment_limits' => [
        'petugas' => 1,
    ],
];
