<?php

return [
    'default' => [
        'title' => 'Admin Help',
        'summary' => 'Use search/filters first, then edit. Keep codes stable, keep payload JSON minimal, and save core data before advanced relations/media.',
        'sections' => [
            [
                'title' => 'Current Admin Workflow',
                'items' => [
                    '1) Filter/select context first (scope + locale).',
                    '2) Save core record data before media operations.',
                    '3) Manage images (main, gallery, banner/icon), then save meta.',
                    '4) Finalize SEO/payload and verify list/search output.',
                ],
            ],
            [
                'title' => 'How To Use This Manual',
                'items' => [
                    'Each page shows route-aware guidance focused on that screen.',
                    'Start from What/Why (summary), then follow Workflow steps.',
                    'When in doubt: keep data explicit, small, and reversible.',
                ],
            ],
        ],
        'bullets' => [
            'Use scope/locale controls to avoid editing in wrong context.',
            'Prefer concise JSON payloads with explicit keys.',
            'After structural changes, clear cache from top-right quick actions when needed.',
        ],
    ],

    'routes' => [
        'admin.dashboard' => [
            'title' => 'Dashboard',
            'summary' => 'Operational overview with KPI window, order pipeline, trend and recent activity.',
            'bullets' => [
                'Use range selector to switch between today, 7d and 30d snapshots.',
                'Order pipeline cards open Orders with prefilled status/date filters.',
                'Loyalty and tracking sections are hidden automatically when their feature flags are off.',
            ],
        ],
        'admin.categories' => [
            'title' => 'Categories Tree',
            'summary' => 'Category structure manager (catalog/blog/page). Root rows are paginated and children load lazily for performance.',
            'sections' => [
                [
                    'title' => 'What This Is',
                    'items' => [
                        'Catalog scope = product taxonomy.',
                        'Blog scope = editorial post grouping.',
                        'Page scope = information page grouping.',
                        'Each scope has isolated tree and URL space.',
                    ],
                ],
                [
                    'title' => 'How To Use',
                    'items' => [
                        '1) Pick scope and locale first.',
                        '2) Use search for direct jump to a node.',
                        '3) Use expand/collapse for structure review.',
                        '4) Open Edit for content/SEO/media updates.',
                    ],
                ],
            ],
        ],
        'admin.categories.create' => [
            'title' => 'Create Category',
            'summary' => 'Create structural category data and locale content in one pass.',
            'bullets' => [
                'Set scope before selecting parent.',
                'Slug must be unique per scope + locale.',
                'Put only non-relational flags in payload JSON.',
                'After first save, use Images panel for icon and banner images.',
            ],
        ],
        'admin.categories.edit' => [
            'title' => 'Edit Category',
            'summary' => 'Update category structure, locale content, and SEO fields.',
            'sections' => [
                [
                    'title' => 'Input Guide',
                    'items' => [
                        'Code: keep stable internal ID (for integrations and imports).',
                        'Slug: public URL segment, unique per scope + locale.',
                        'Payload JSON: keep only non-relational flags (icon, footer visibility, badge).',
                        'Scope: catalog/blog/page are isolated trees.',
                    ],
                ],
                [
                    'title' => 'Workflow',
                    'items' => [
                        '1) Select scope first.',
                        '2) Set parent only within same scope.',
                        '3) Fill locale content (name, slug, SEO).',
                        '4) Save, then upload/manage icon and banner in Images panel.',
                        '5) Return to list for move up/down and lazy tree review.',
                    ],
                ],
            ],
        ],
        'admin.settings.system.runtime' => [
            'title' => 'Runtime Controls',
            'summary' => 'Operational switches for maintenance and cache.',
            'bullets' => [
                'Use maintenance mode only for short maintenance windows.',
                'Clear cache after schema/content structure changes.',
                'Privileged admin role is required for actions.',
            ],
        ],
        'admin.settings.system.admin-appearance-controls' => [
            'title' => 'Admin Appearance Controls',
            'summary' => 'Central source for admin and frontend pagination defaults.',
            'bullets' => [
                'Admin tables read admin_items_per_page.',
                'Category roots tree reads admin_category_roots_per_page.',
                'Frontend category/manufacturer pages will consume front_* values.',
            ],
        ],
        'admin.settings.system.catalog-features' => [
            'title' => 'Catalog Features',
            'summary' => 'Feature flags for optional catalog modules.',
            'bullets' => [
                'Use Blog toggle to expose or hide blog management routes.',
                'Disable unused features to avoid unnecessary admin/front queries.',
                'Enable Options when SKU/price/stock depends on selected option values.',
                'Use Actions & Discounts to enable promotion management module.',
                'Keep flags stable once large data sets depend on them.',
            ],
        ],
        'admin.settings.user.index' => [
            'title' => 'Settings / User',
            'summary' => 'Control user-related runtime modules and loyalty point rules.',
            'bullets' => [
                'Disable User Tracking to stop writing tracking events.',
                'Disable Loyalty System to stop order loyalty settlement updates.',
                'Points per currency and minimum order total control loyalty awarding threshold.',
            ],
        ],
        'admin.users.access' => [
            'title' => 'Users / Roles & Abilities',
            'summary' => 'Manage Bouncer abilities and role permission matrix from one screen.',
            'bullets' => [
                'Ability name is a stable slug (example: users.view).',
                'Matrix checkboxes save immediately.',
                'Use groups to keep table sections readable as ability count grows.',
            ],
        ],
        'admin.actions*' => [
            'title' => 'Catalog / Actions & Discounts',
            'summary' => 'Promotion rule engine for discounts and campaign logic. One action defines condition, audience, target, value and validity window.',
            'sections' => [
                [
                    'title' => 'What This Is',
                    'items' => [
                        'Action = reusable pricing rule, not a one-off price edit.',
                        'Audience limits who can use it (all/users/groups).',
                        'Target limits where it applies (products/categories/manufacturers/cart).',
                        'Schedule defines active period without manual toggling.',
                    ],
                ],
                [
                    'title' => 'Rule Types',
                    'items' => [
                        'Percentage and fixed amount are ready for immediate use.',
                        'Buy X Get Y and Gift On Amount are prepared in schema and admin inputs.',
                        'Use coupon code when action should require explicit code entry.',
                    ],
                ],
                [
                    'title' => 'Workflow',
                    'items' => [
                        '1) Set scope and type first.',
                        '2) Pick target type and assign specific rows if needed.',
                        '3) Set audience (all users, user group, or single user).',
                        '4) Configure value, stacking/exclusivity and coupon behavior.',
                        '5) Add schedule and save with locale title.',
                        '6) Verify on product/order flow with one test user before going live.',
                    ],
                ],
                [
                    'title' => 'Safety Rules',
                    'items' => [
                        'Prefer deactivation over deletion for auditability.',
                        'Avoid overlapping actions with same priority unless intended.',
                        'Keep names explicit (e.g. \"Winter 15% Catalog\").',
                    ],
                ],
            ],
        ],
        'admin.products' => [
            'title' => 'Products',
            'summary' => 'Product index for fast search/filter/sort and operational edits with feature-aware query behavior.',
            'sections' => [
                [
                    'title' => 'What This Is',
                    'items' => [
                        'Core product operations screen (code/SKU/state/price/stock/category/manufacturer).',
                        'Locale controls translated name/slug lookups in list and forms.',
                        'Feature flags switch optional modules on/off without schema removal.',
                    ],
                ],
                [
                    'title' => 'Query Behavior',
                    'items' => [
                        'Feature flags indicate whether optional modules are active.',
                        'Disabled modules should not trigger relation queries.',
                        'Turn off unused modules in Settings > System > Catalog Features.',
                    ],
                ],
                [
                    'title' => 'Workflow',
                    'items' => [
                        '1) Filter by locale + status + stock + relation filters.',
                        '2) Open Edit for core data, translation, SEO and media.',
                        '3) Open Option Values for per-combination SKU/stock/price rows.',
                        '4) Keep code/SKU stable for integrations.',
                    ],
                ],
            ],
        ],
        'admin.products.create' => [
            'title' => 'Create Product',
            'summary' => 'Create core product fields, localized content, and category assignments.',
            'bullets' => [
                'Code must be globally unique and stable for imports.',
                'Slug must be unique per locale.',
                'Category order defines primary category (first selected).',
                'Manufacturer can be assigned when Manufacturers feature is enabled.',
                'After first save, use Images panel for main and gallery images.',
            ],
        ],
        'admin.products.edit' => [
            'title' => 'Edit Product',
            'summary' => 'Update product base data and locale-specific content.',
            'bullets' => [
                'Locale switch loads/saves translation for that locale only.',
                'Payload JSON should stay focused on non-relational metadata.',
                'Assign attributes as grouped specification/filter values when feature is enabled.',
                'Option group assignment is handled in Product Option Values screen.',
                'Manufacturer assignment is part of product core data.',
                'Use Images panel for gallery ordering and copy-to-main behavior.',
            ],
        ],
        'admin.attributes' => [
            'title' => 'Attributes',
            'summary' => 'Reusable grouped attribute values for product filters/specifications.',
            'bullets' => [
                'Use Group Code to keep a stable machine grouping key.',
                'Use Group Name for localized display label.',
                'Attribute assignment is done directly in Product edit form.',
            ],
        ],
        'admin.attributes.create' => [
            'title' => 'Create Attribute',
            'summary' => 'Create one attribute value row inside a stable group.',
            'bullets' => [
                'Code and Group Code should remain integration-safe and stable.',
                'Slug should be unique per locale.',
                'Keep translation payload small and explicit.',
            ],
        ],
        'admin.attributes.edit' => [
            'title' => 'Edit Attribute',
            'summary' => 'Update grouped attribute value and locale content.',
            'bullets' => [
                'Changing Group Name updates localized grouping label.',
                'Disabling attribute keeps historical product links intact.',
                'Use sort order to control list position in admin/front.',
            ],
        ],
        'admin.manufacturers' => [
            'title' => 'Manufacturers',
            'summary' => 'Manage manufacturer (brand) entities and their localized names/slugs.',
            'bullets' => [
                'Keep code stable for imports and API integrations.',
                'Use featured flag for front spotlight lists.',
                'Deactivate instead of deleting when products are already linked.',
            ],
        ],
        'admin.manufacturers.create' => [
            'title' => 'Create Manufacturer',
            'summary' => 'Create manufacturer base record and localized content.',
            'bullets' => [
                'Slug must be unique per locale.',
                'Use concise description/meta content for front listing pages.',
                'Payload JSON should remain small and explicit.',
                'After first save, upload logo/banner in Images panel.',
            ],
        ],
        'admin.manufacturers.edit' => [
            'title' => 'Edit Manufacturer',
            'summary' => 'Update manufacturer status/featured flags and locale content.',
            'bullets' => [
                'Locale switch edits one translation at a time.',
                'Disabling a manufacturer keeps historical product links intact.',
                'Changing slug affects front route path for that locale.',
                'Use Images panel for logo/banner replacement and metadata.',
            ],
        ],
        'admin.orders' => [
            'title' => 'Sales / Orders',
            'summary' => 'Order list view with snapshot totals, status filter, and date window controls.',
            'bullets' => [
                'Search by order number, customer name, email or phone.',
                'Use status and date filters before opening detail page.',
                'Order rows are immutable snapshots, independent from later catalog edits.',
            ],
        ],
        'admin.orders.show' => [
            'title' => 'Order Detail',
            'summary' => 'Review item/address snapshots, run quick actions, manage internal tags, and update timeline notes.',
            'sections' => [
                [
                    'title' => 'Workflow',
                    'items' => [
                        '1) Verify customer, payment and shipping snapshots.',
                        '2) Check line items and total rows.',
                        '3) Use quick status actions when needed (Paid, Sent, Cancelled).',
                        '4) Apply loyalty redemption points if customer wants to spend balance.',
                        '5) Add/remove internal tags for operational routing.',
                        '6) Set target status + note and verify entry in Status Timeline.',
                    ],
                ],
            ],
            'bullets' => [
                'Status updates create history rows for audit traceability.',
                'Paid statuses automatically fill paid_at when empty.',
                'Loyalty redemption writes negative order_redemption ledger rows and updates order totals.',
                'Internal tags are stored in order payload under internal_tags.',
                'Invoice / Print opens a print-focused order document view.',
            ],
        ],
        'admin.orders.invoice' => [
            'title' => 'Order Invoice',
            'summary' => 'Print-focused invoice preview generated from immutable order snapshots.',
            'bullets' => [
                'Use browser print dialog for PDF or paper output.',
                'Data reflects order snapshot at placement/update time.',
                'Totals section uses saved order_total rows when available.',
            ],
        ],
        'admin.users' => [
            'title' => 'Users',
            'summary' => 'Admin user list with search, role and segment filters, and column sorting.',
            'bullets' => [
                'Use role filter for faster access reviews.',
                'Use segment filter to target audience rules (actions, newsletters, B2B).',
                'Sort by created date to audit recently added users.',
                'Edit to update role, verification state, or reset password.',
            ],
        ],
        'admin.users.edit' => [
            'title' => 'Edit User',
            'summary' => 'Update account identity, role, segmentation, profile and billing/shipping addresses.',
            'bullets' => [
                'Set exactly one primary role for predictable access behavior.',
                'Use customer groups for segmentation, not for permission control.',
                'Keep profile data lightweight; checkout-specific data belongs to addresses.',
                'Leave password fields blank if no reset is needed.',
                'Unverified email disables verified-only flows until re-verified.',
            ],
        ],
        'admin.users.show' => [
            'title' => 'User Overview',
            'summary' => 'Read-only summary of account data, groups, profile, addresses and recent activity.',
            'bullets' => [
                'Use this page for quick audits before editing.',
                'Recent admin activity comes from Spatie activity log.',
                'Recent tracking events come from user_tracking_events.',
            ],
        ],
        'admin.users.groups' => [
            'title' => 'User Groups',
            'summary' => 'Segmentation groups for campaigns, pricing scope, and customer targeting.',
            'bullets' => [
                'Use stable group code values for API/integration mapping.',
                'Only one group should be default.',
                'Groups are for segmentation, roles are for permissions.',
            ],
        ],
        'admin.users.activity' => [
            'title' => 'User Activity',
            'summary' => 'Read admin activity log and user tracking events from one screen.',
            'bullets' => [
                'Admin Activity source reads Spatie activity log.',
                'Loyalty Audit source isolates loyalty settlement/reversal/redemption events.',
                'User Tracking source reads front/user interest events.',
                'Use search by user/email/event/url for quick incident tracing.',
            ],
        ],
        'admin.users.loyalty' => [
            'title' => 'User Loyalty',
            'summary' => 'Filterable loyalty ledger with user, type, date and points constraints.',
            'bullets' => [
                'Use user search to isolate one customer balance history.',
                'Type filter separates settlement, reversal, redemption and manual adjustments.',
                'User ID filter and scoped links from Users screen speed up support workflows.',
                'Points min/max helps detect unusual point movements.',
                'Order column links directly to connected order detail when available.',
            ],
        ],
        'admin.products.options' => [
            'title' => 'Product Option Values',
            'summary' => 'Per-product value rows with own SKU/stock/price, including two-option combinations.',
            'sections' => [
                [
                    'title' => 'Input Guide',
                    'items' => [
                        'Single mode: one option group, one row per selected value.',
                        'Linked mode: primary + secondary option values per row (e.g. color + size).',
                        'Combination uniqueness is enforced per product.',
                        'Leave price empty to use product base price.',
                    ],
                ],
                [
                    'title' => 'Workflow',
                    'items' => [
                        '1) Assign option groups in this screen first.',
                        '2) Choose mode (single or linked).',
                        '3) Add rows or generate matrix, then adjust SKU/stock/price.',
                        '4) Save and verify product option behavior on front/API.',
                    ],
                ],
            ],
        ],
        'admin.options' => [
            'title' => 'Options',
            'summary' => 'Reusable option groups for products (size, color, package).',
            'bullets' => [
                'Keep option code stable for integrations.',
                'Use values screen to manage selectable entries per option.',
                'Options are available only when Catalog Features flag is enabled.',
            ],
        ],
        'admin.options.create' => [
            'title' => 'Create Option',
            'summary' => 'Create option structure and locale translation.',
            'bullets' => [
                'Code is internal stable identifier.',
                'Type controls front input behavior.',
                'Use payload for non-relational metadata only.',
            ],
        ],
        'admin.options.edit' => [
            'title' => 'Edit Option',
            'summary' => 'Update option structure and locale text, then maintain values.',
            'bullets' => [
                'Slug should be unique per locale.',
                'Use Manage Values button for value entries.',
                'Disable unused options to keep front filtering lean.',
            ],
        ],
        'admin.options.values' => [
            'title' => 'Option Values',
            'summary' => 'Manage value rows for selected option and locale.',
            'bullets' => [
                'Sort order controls value display order.',
                'Value code should be stable and API-safe.',
                'Locale switch edits value translation only.',
            ],
        ],
        'admin.content.blocks*' => [
            'title' => 'Content Block Building',
            'summary' => 'Step-by-step builder for homepage/section blocks. Create one block, choose where it appears, add content/items, then publish and preview.',
            'sections' => [
                [
                    'title' => 'How To Think About It',
                    'subtitle' => 'Build one section at a time, not one whole page at once.',
                    'explanation' => [
                        'A content block is one visual section on front: hero, categories row, featured products, brand row, blog row, and similar.',
                        'Type answers what the block does. Placement answers where it appears.',
                        'If you keep this separation clear, block setup becomes predictable and fast.',
                    ],
                ],
                [
                    'title' => 'Step By Step Workflow',
                    'subtitle' => 'Recommended order for best results.',
                    'explanation' => [
                        'Step 1: Choose Type first.',
                        'Step 2: Fill Code and Name. Keep code stable after go-live.',
                        'Step 3: Choose Placement. This controls front location.',
                        'Step 4: Keep target fields empty for global blocks. Use target type/ref only for advanced context rendering.',
                        'Step 5: Fill text fields (title/subtitle/CTA) and select items if block type requires items.',
                        'Step 6: Open Blade Template (Ace), adjust output, save, then preview on front.',
                    ],
                ],
                [
                    'title' => 'Block Parameters Guide',
                    'subtitle' => 'What each main field is for.',
                    'params' => [
                        ['key' => 'type', 'value' => 'Defines block behavior and whether item picker is used.'],
                        ['key' => 'code', 'value' => 'Technical identifier. Also maps to per-block template filename.'],
                        ['key' => 'slot_placement', 'value' => 'Front position where this block is rendered.'],
                        ['key' => 'slot_target_type', 'value' => 'Optional advanced scope (category, product, page, blog).'],
                        ['key' => 'slot_target_ref', 'value' => 'Optional specific slug/id inside target_type.'],
                        ['key' => 'selected_item_ids', 'value' => 'Ordered selected entities for item-based block types.'],
                        ['key' => 'bg_css', 'value' => 'Optional style override for this block translation.'],
                        ['key' => 'template_body (Ace)', 'value' => 'Primary rendering template for this exact block instance.'],
                    ],
                ],
                [
                    'title' => 'Template Editing (Ace)',
                    'subtitle' => 'Use this as your main editing surface.',
                    'explanation' => [
                        'Each block has its own Blade instance file at resources/views/front/content-blocks/instances/{code}.blade.php.',
                        'Edits in this file affect only that block instance and are safe for one-off design tweaks.',
                        'Common variables available in template: $block, $translation, $slot, $products, $categories, $manufacturers, $blogs.',
                    ],
                ],
                [
                    'title' => 'When Block Is Not Visible',
                    'subtitle' => 'Run this checklist before deeper debugging.',
                    'explanation' => [
                        'Confirm Block Active = ON and Slot Active = ON.',
                        'Confirm placement is correct for the page section you are testing.',
                        'Confirm start/end dates are valid for current time.',
                        'For global blocks, target type/ref should be empty.',
                        'If output looks stale after template changes, clear cache.',
                    ],
                ],
                [
                    'title' => 'Good Practice',
                    'subtitle' => 'Keep it maintainable for future editors.',
                    'explanation' => [
                        'Use clear block names and one purpose per block.',
                        'Keep code stable to preserve template mapping.',
                        'Prefer item picker UI over hardcoded IDs in templates.',
                        'Use short template comments for non-obvious custom logic.',
                    ],
                ],
            ],
        ],
        'admin.content.blog.*' => [
            'title' => 'Content / Blog',
            'summary' => 'Editorial blog posts with locale-specific slugs and publish scheduling.',
            'bullets' => [
                'Set published date when post should appear on front.',
                'Use featured flag for homepage/article highlights.',
                'Keep excerpt concise for listing cards and SEO snippets.',
                'Use Images panel for cover + gallery and locale alt/caption meta.',
            ],
        ],
        'admin.content.pages.*' => [
            'title' => 'Content / Pages',
            'summary' => 'Static information pages (shipping, returns, about, privacy, etc.).',
            'bullets' => [
                'Use code as stable internal identifier.',
                'Toggle footer visibility only for user-facing utility pages.',
                'Layout value can drive future template variations.',
            ],
        ],
        'admin.content.faqs.*' => [
            'title' => 'Content / FAQs',
            'summary' => 'Frequently asked questions with group, locale question/answer, and sort order.',
            'bullets' => [
                'Use group_code for front grouping and tab/filter sections.',
                'Slug should remain stable per locale when linked publicly.',
                'Answer content can be edited with WYSIWYG editor for cleaner markup.',
            ],
        ],
        'admin.content.comments.*' => [
            'title' => 'Content / Comments',
            'summary' => 'Moderate comments across products, blog posts, info pages and FAQs.',
            'bullets' => [
                'Default workflow: Pending -> Approve/Reject/Spam.',
                'Trash is soft-delete; use restore when needed.',
                'Filter by target type and locale for faster moderation.',
            ],
        ],
        'admin.content.slots*' => [
            'title' => 'Content Slots',
            'summary' => 'Legacy placement manager. Primary workflow is now Content Blocks form, where block + primary slot are configured together.',
            'sections' => [
                [
                    'title' => 'What This Is',
                    'items' => [
                        'Slot key identifies the placement area (e.g. home.hero, category.after_list).',
                        'Target type/ref narrows placement to specific entity rows.',
                        'Sort order controls sequence when multiple slots share the same key.',
                    ],
                ],
                [
                    'title' => 'How To Use',
                    'items' => [
                        '1) Choose slot key and block.',
                        '2) Add target type/ref only when placement is context-specific.',
                        '3) Set active state and date window for campaign control.',
                        '4) Verify ordering when multiple rows share same slot key.',
                    ],
                ],
            ],
        ],
        'admin.settings.api.*' => [
            'title' => 'Settings / API',
            'summary' => 'Manage two API integrations: internal Wholesale API token access and external Luceed connector + full sync action console.',
            'sections' => [
                [
                    'title' => 'Wholesale API',
                    'items' => [
                        'API access toggle per user (hard gate).',
                        'Token list with ability scopes and lifecycle fields.',
                        'Preset scopes for common integration profiles.',
                    ],
                ],
                [
                    'title' => 'Wholesale API Workflow',
                    'items' => [
                        '1) Enable API access for specific user.',
                        '2) Issue token with minimal required abilities.',
                        '3) Copy plain token once and store securely.',
                        '4) Revoke token immediately on client/system change.',
                        '5) Disable user API access to revoke all user tokens at once.',
                    ],
                ],
                [
                    'title' => 'Luceed API',
                    'items' => [
                        'Connection tab configures Luceed DataSnap base URI and auth strategy (Basic/Bearer/Header/Query/None).',
                        'Sync Manager tab exposes full operation set: dictionaries, products, prices, quantities, actions, related items, and order statuses.',
                        'Each action runs manually in admin and writes a run log (status, summary, timestamps, and stats/error details).',
                        'Run history is persistent and visible in History tab for post-run verification.',
                    ],
                ],
                [
                    'title' => 'Luceed Sync Workflow',
                    'items' => [
                        '1) Enable Luceed in Catalog Features (`Use Luceed API`) and enable Luceed connector toggle in Luceed API settings.',
                        '2) Save connection/auth and run Test Connection probe.',
                        '3) Open Sync Manager / Settings tab and define locale, stock warehouse CSV, and partner parameters for B2B pricing.',
                        '4) Run actions in order: categories/manufacturers/payments -> products -> prices/quantities -> order statuses.',
                        '5) Inspect History tab after each run; failed actions keep exact error message for troubleshooting.',
                    ],
                ],
                [
                    'title' => 'Luceed Sync Action Groups',
                    'items' => [
                        'Catalog Foundations: Import/Update categories, Sync category UIDs, Import manufacturers, Sync manufacturer UIDs, Import warehouses, Import payment methods.',
                        'Products And Prices: Import/Update products, Additional product data, Related products, Import actions, Last-30d action prices, Update prices, Update B2B prices, Update VPC prices, Update quantities, Combined price+quantity update.',
                        'Order Status Sync: Update order statuses, Update B2B order statuses, Check order status duration.',
                    ],
                ],
                [
                    'title' => 'Gates And Safety',
                    'items' => [
                        'Catalog Features switch `Use Luceed API` gates the entire Luceed module (menu entry, route access, and runtime usage).',
                        'Connection toggle `Luceed connector enabled` is a second gate; both gates must be ON to execute actions.',
                        'TLS verification should remain enabled in production unless endpoint uses trusted self-signed certificates in controlled environments.',
                    ],
                ],
            ],
        ],
    ],
];
