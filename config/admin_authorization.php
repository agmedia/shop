<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Route Ability Rules
    |--------------------------------------------------------------------------
    |
    | Ordered matching with Str::is. First match wins.
    | Keys:
    | - view: required abilities for GET/HEAD and read-only Livewire calls
    | - mutate: required abilities for state-changing calls
    | - delete: optional stronger abilities for destructive calls
    |
    */
    'route_rules' => [
        'admin.dashboard' => [
            'view' => ['dashboard.view'],
        ],

        'admin.categories.create' => [
            'view' => ['catalog.categories.create'],
            'mutate' => ['catalog.categories.create'],
        ],
        'admin.categories.edit' => [
            'view' => ['catalog.categories.update'],
            'mutate' => ['catalog.categories.update'],
            'delete' => ['catalog.categories.delete'],
        ],
        'admin.categories' => [
            'view' => ['catalog.categories.view'],
            'mutate' => ['catalog.categories.update', 'catalog.categories.create'],
            'delete' => ['catalog.categories.delete'],
        ],

        'admin.products.create' => [
            'view' => ['catalog.products.create'],
            'mutate' => ['catalog.products.create'],
        ],
        'admin.products.edit' => [
            'view' => ['catalog.products.update'],
            'mutate' => ['catalog.products.update'],
            'delete' => ['catalog.products.delete'],
        ],
        'admin.products.options' => [
            'view' => ['catalog.products.options.manage'],
            'mutate' => ['catalog.products.options.manage'],
        ],
        'admin.products' => [
            'view' => ['catalog.products.view'],
        ],

        'admin.attributes.create' => [
            'view' => ['catalog.attributes.create'],
            'mutate' => ['catalog.attributes.create'],
        ],
        'admin.attributes.edit' => [
            'view' => ['catalog.attributes.update'],
            'mutate' => ['catalog.attributes.update'],
            'delete' => ['catalog.attributes.delete'],
        ],
        'admin.attributes' => [
            'view' => ['catalog.attributes.view'],
        ],

        'admin.options.create' => [
            'view' => ['catalog.options.create'],
            'mutate' => ['catalog.options.create'],
        ],
        'admin.options.edit' => [
            'view' => ['catalog.options.update'],
            'mutate' => ['catalog.options.update'],
            'delete' => ['catalog.options.delete'],
        ],
        'admin.options.values' => [
            'view' => ['catalog.options.values.manage'],
            'mutate' => ['catalog.options.values.manage'],
        ],
        'admin.options' => [
            'view' => ['catalog.options.view'],
        ],

        'admin.manufacturers.create' => [
            'view' => ['catalog.manufacturers.create'],
            'mutate' => ['catalog.manufacturers.create'],
        ],
        'admin.manufacturers.edit' => [
            'view' => ['catalog.manufacturers.update'],
            'mutate' => ['catalog.manufacturers.update'],
            'delete' => ['catalog.manufacturers.delete'],
        ],
        'admin.manufacturers' => [
            'view' => ['catalog.manufacturers.view'],
        ],

        'admin.actions.create' => [
            'view' => ['catalog.actions.create'],
            'mutate' => ['catalog.actions.create'],
        ],
        'admin.actions.edit' => [
            'view' => ['catalog.actions.update'],
            'mutate' => ['catalog.actions.update'],
            'delete' => ['catalog.actions.delete'],
        ],
        'admin.actions' => [
            'view' => ['catalog.actions.view'],
        ],

        'admin.orders.invoice' => [
            'view' => ['sales.orders.invoice.view'],
        ],
        'admin.orders.show' => [
            'view' => ['sales.orders.view'],
            'mutate' => ['sales.orders.update'],
        ],
        'admin.orders' => [
            'view' => ['sales.orders.view'],
        ],

        'admin.content.blog.create' => [
            'view' => ['content.blog.create'],
            'mutate' => ['content.blog.create'],
        ],
        'admin.content.blog.edit' => [
            'view' => ['content.blog.update'],
            'mutate' => ['content.blog.update'],
            'delete' => ['content.blog.delete'],
        ],
        'admin.content.blog.*' => [
            'view' => ['content.blog.view'],
        ],

        'admin.content.pages.create' => [
            'view' => ['content.pages.create'],
            'mutate' => ['content.pages.create'],
        ],
        'admin.content.pages.edit' => [
            'view' => ['content.pages.update'],
            'mutate' => ['content.pages.update'],
            'delete' => ['content.pages.delete'],
        ],
        'admin.content.pages.*' => [
            'view' => ['content.pages.view'],
        ],

        'admin.content.faqs.create' => [
            'view' => ['content.faqs.create'],
            'mutate' => ['content.faqs.create'],
        ],
        'admin.content.faqs.edit' => [
            'view' => ['content.faqs.update'],
            'mutate' => ['content.faqs.update'],
            'delete' => ['content.faqs.delete'],
        ],
        'admin.content.faqs.*' => [
            'view' => ['content.faqs.view'],
        ],

        'admin.content.comments.*' => [
            'view' => ['content.comments.view'],
            'mutate' => ['content.comments.moderate'],
            'delete' => ['content.comments.delete'],
        ],

        'admin.content.blocks.create' => [
            'view' => ['content.blocks.create'],
            'mutate' => ['content.blocks.create'],
        ],
        'admin.content.blocks.edit' => [
            'view' => ['content.blocks.update'],
            'mutate' => ['content.blocks.update'],
            'delete' => ['content.blocks.delete'],
        ],
        'admin.content.blocks*' => [
            'view' => ['content.blocks.view'],
            'mutate' => ['content.blocks.update', 'content.blocks.create'],
            'delete' => ['content.blocks.delete'],
        ],
        'admin.content.navigation*' => [
            'view' => ['content.navigation.view'],
            'mutate' => ['content.navigation.update'],
        ],

        'admin.content.slots.create' => [
            'view' => ['content.slots.create'],
            'mutate' => ['content.slots.create'],
        ],
        'admin.content.slots.edit' => [
            'view' => ['content.slots.update'],
            'mutate' => ['content.slots.update'],
            'delete' => ['content.slots.delete'],
        ],
        'admin.content.slots*' => [
            'view' => ['content.slots.view'],
            'mutate' => ['content.slots.update', 'content.slots.create'],
            'delete' => ['content.slots.delete'],
        ],

        'admin.settings.local.*' => [
            'view' => ['settings.local.manage'],
            'mutate' => ['settings.local.manage'],
        ],
        'admin.settings.system.runtime' => [
            'view' => ['settings.system.runtime.manage'],
            'mutate' => ['settings.system.runtime.manage'],
        ],
        'admin.settings.system.admin-appearance-controls' => [
            'view' => ['settings.system.admin_appearance.manage'],
            'mutate' => ['settings.system.admin_appearance.manage'],
        ],
        'admin.settings.system.catalog-features' => [
            'view' => ['settings.system.catalog_features.manage'],
            'mutate' => ['settings.system.catalog_features.manage'],
        ],
        'admin.settings.system.store-settings' => [
            'view' => ['settings.system.store.manage'],
            'mutate' => ['settings.system.store.manage'],
        ],
        'admin.settings.api.*' => [
            'view' => ['settings.api.manage'],
            'mutate' => ['settings.api.manage'],
        ],
        'admin.settings.user.*' => [
            'view' => ['settings.user.manage'],
            'mutate' => ['settings.user.manage'],
        ],

        'admin.users.edit' => [
            'view' => ['users.profile.update'],
            'mutate' => ['users.profile.update'],
        ],
        'admin.users.show' => [
            'view' => ['users.list.view'],
        ],
        'admin.users.groups' => [
            'view' => ['users.groups.manage'],
            'mutate' => ['users.groups.manage'],
            'delete' => ['users.groups.manage'],
        ],
        'admin.users.activity' => [
            'view' => ['users.activity.view'],
        ],
        'admin.users.loyalty' => [
            'view' => ['users.loyalty.view'],
            'mutate' => ['users.loyalty.adjust'],
        ],
        'admin.users.access' => [
            'view' => ['users.access.manage'],
            'mutate' => ['users.access.manage'],
        ],
        'admin.users' => [
            'view' => ['users.list.view'],
        ],

        'admin.profile' => [
            'view' => ['users.profile.update'],
        ],

        'admin.system.cache.clear' => [
            'mutate' => ['settings.system.runtime.manage'],
        ],
        'admin.system.maintenance.on' => [
            'mutate' => ['settings.system.runtime.manage'],
        ],
        'admin.system.maintenance.off' => [
            'mutate' => ['settings.system.runtime.manage'],
        ],

        'admin.ai.preview' => [
            'mutate' => ['ai.admin.use'],
        ],
        'admin.ai.execute' => [
            'mutate' => ['ai.admin.use'],
        ],
    ],

    'livewire_readonly_methods' => [
        'render',
        'mount',
        'backToList',
        'backToProduct',
        'openPreview',
        'closePreview',
        'sort',
        'clearFilters',
        'cancelEdit',
        'toggleGroup',
        'toggleExpand',
        'refreshState',
    ],

    'livewire_delete_keywords' => [
        'delete',
        'remove',
        'spam',
        'reject',
    ],

    'livewire_mutate_keywords' => [
        'save',
        'create',
        'update',
        'toggle',
        'move',
        'make',
        'approve',
        'apply',
        'upload',
        'copy',
        'add',
        'generate',
        'clear',
        'sync',
    ],
];
