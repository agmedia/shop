<?php

namespace App\Livewire\Admin\Settings\System;

use App\Models\Catalog\Category\Category;
use App\Models\Content\Page\InfoPage;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Livewire\Component;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use Livewire\WithFileUploads;
use Silber\Bouncer\BouncerFacade as Bouncer;

class StoreSettings extends Component
{
    use WithFileUploads;

    public string $tab = 'email';

    /** @var array<string, mixed> */
    public array $form = [
        'store_email_enabled' => false,
        'store_email_mailer' => 'smtp',
        'store_email_smtp_host' => '',
        'store_email_smtp_port' => 587,
        'store_email_smtp_username' => '',
        'store_email_smtp_password' => '',
        'store_email_smtp_encryption' => 'tls',
        'store_email_sendmail_path' => '/usr/sbin/sendmail -bs -i',
        'store_email_from_address' => '',
        'store_email_from_name' => '',
        'store_email_reply_to' => '',
        'store_email_orders_to' => '',
        'store_email_contact_to' => '',

        'store_brand_name' => '',
        'store_footer_phone' => '',
        'store_footer_email_sales' => '',
        'store_footer_email_support' => '',
        'store_footer_hours' => '',
        'store_footer_col_1_title' => '',
        'store_footer_col_1_category_ids' => [],
        'store_footer_col_1_page_ids' => [],
        'store_footer_col_1_custom_links' => '',
        'store_footer_col_2_title' => '',
        'store_footer_col_2_category_ids' => [],
        'store_footer_col_2_page_ids' => [],
        'store_footer_col_2_custom_links' => '',
        'store_footer_col_3_title' => '',
        'store_footer_col_3_category_ids' => [],
        'store_footer_col_3_page_ids' => [],
        'store_footer_col_3_custom_links' => '',
        'store_footer_bottom_link_page_ids' => [],
        'store_footer_bottom_copyright_text' => '',
        'store_social_facebook_url' => '',
        'store_social_instagram_url' => '',
        'store_social_tiktok_url' => '',
        'store_social_youtube_url' => '',
        'store_footer_social_facebook_enabled' => true,
        'store_footer_social_instagram_enabled' => true,
        'store_footer_social_tiktok_enabled' => true,
        'store_footer_social_youtube_enabled' => true,
        'store_brand_logo_path' => '',
        'store_brand_favicon_path' => '',
        'store_brand_favicon_16_path' => '',
        'store_brand_favicon_32_path' => '',
        'store_brand_favicon_180_path' => '',
        'store_brand_favicon_192_path' => '',
        'store_brand_favicon_512_path' => '',
        'store_brand_favicon_ico_path' => '',

        'store_newsletter_provider' => 'none',
        'store_newsletter_mailchimp_api_key' => '',
        'store_newsletter_mailchimp_list_id' => '',
        'store_newsletter_klaviyo_api_key' => '',
        'store_newsletter_klaviyo_list_id' => '',

        'store_captcha_recaptcha_v3_enabled' => false,
        'store_captcha_recaptcha_v3_site_key' => '',
        'store_captcha_recaptcha_v3_secret_key' => '',
        'store_captcha_recaptcha_v3_min_score' => 0.5,

        'store_analytics_enabled' => false,
        'store_analytics_ga4_measurement_id' => '',
        'store_analytics_purchase_event_enabled' => true,
        'store_analytics_purchase_event_name' => 'purchase',
        'store_pricing_prices_include_tax' => false,

        'store_seo_default_title' => '',
        'store_seo_default_description' => '',
        'store_seo_robots' => 'index,follow',
        'store_seo_canonical_policy' => 'self',

        'store_og_default_image_path' => '',
        'store_og_home_image_path' => '',
        'store_og_category_image_path' => '',
        'store_og_product_image_path' => '',
        'store_og_page_image_path' => '',
        'store_og_blog_image_path' => '',

        'store_schema_enabled' => true,
        'store_schema_org_enabled' => true,
        'store_schema_website_enabled' => true,
        'store_schema_breadcrumbs_enabled' => true,
        'store_schema_itemlist_enabled' => true,
        'store_schema_home_enabled' => true,
        'store_schema_category_enabled' => true,
        'store_schema_product_enabled' => true,
        'store_schema_blog_enabled' => true,
        'store_schema_page_enabled' => true,
        'store_schema_faq_enabled' => true,
        'store_schema_org_type' => 'Organization',
        'store_schema_business_name' => '',
        'store_schema_business_phone' => '',
        'store_schema_business_email' => '',
        'store_schema_address_street' => '',
        'store_schema_address_city' => '',
        'store_schema_address_region' => '',
        'store_schema_address_postal_code' => '',
        'store_schema_address_country' => 'HR',
        'store_schema_same_as' => '',
        'store_schema_blog_author_name' => '',
        'store_schema_blog_author_url' => '',
        'store_schema_product_currency' => 'EUR',
        'store_schema_faq_group' => '',
        'store_schema_faq_limit' => 8,
        'store_schema_itemlist_limit' => 12,

        'store_announcement_enabled' => true,
        'store_announcement_text' => '',
        'store_announcement_url' => '',
        'store_announcement_new_tab' => false,
    ];

    public ?TemporaryUploadedFile $logoUpload = null;
    public ?TemporaryUploadedFile $faviconUpload = null;
    public ?TemporaryUploadedFile $ogDefaultImageUpload = null;
    public ?TemporaryUploadedFile $ogHomeImageUpload = null;
    public ?TemporaryUploadedFile $ogCategoryImageUpload = null;
    public ?TemporaryUploadedFile $ogProductImageUpload = null;
    public ?TemporaryUploadedFile $ogPageImageUpload = null;
    public ?TemporaryUploadedFile $ogBlogImageUpload = null;

    public function mount(): void
    {
        $this->authorizeAccess();

        $settings = app(SystemSettingsService::class);
        foreach ($this->form as $key => $default) {
            $this->form[$key] = $settings->get($key, $default);
        }

        foreach ([1, 2, 3] as $col) {
            $categoryKey = 'store_footer_col_'.$col.'_category_ids';
            $pageKey = 'store_footer_col_'.$col.'_page_ids';
            $this->form[$categoryKey] = $this->normalizeIdList($this->form[$categoryKey] ?? []);
            $this->form[$pageKey] = $this->normalizeIdList($this->form[$pageKey] ?? []);
        }
        $this->form['store_footer_bottom_link_page_ids'] = $this->normalizeIdList($this->form['store_footer_bottom_link_page_ids'] ?? []);

        if (trim((string) $this->form['store_announcement_text']) === '') {
            $this->form['store_announcement_text'] = (string) __('ui.front.desktop.promo_bar');
        }
    }

    public function save(): void
    {
        $this->authorizeAccess();

        $validated = $this->validate($this->rules());
        $payload = $validated['form'];
        $payload['store_schema_product_currency'] = strtoupper((string) ($payload['store_schema_product_currency'] ?? 'EUR'));
        $payload['store_schema_address_country'] = strtoupper((string) ($payload['store_schema_address_country'] ?? 'HR'));
        $payload['store_analytics_purchase_event_name'] = trim((string) ($payload['store_analytics_purchase_event_name'] ?? 'purchase')) ?: 'purchase';
        foreach ([1, 2, 3] as $col) {
            $payload['store_footer_col_'.$col.'_category_ids'] = $this->normalizeIdList($payload['store_footer_col_'.$col.'_category_ids'] ?? []);
            $payload['store_footer_col_'.$col.'_page_ids'] = $this->normalizeIdList($payload['store_footer_col_'.$col.'_page_ids'] ?? []);
        }
        $payload['store_footer_bottom_link_page_ids'] = $this->normalizeIdList($payload['store_footer_bottom_link_page_ids'] ?? []);

        if ($this->logoUpload) {
            $payload['store_brand_logo_path'] = $this->logoUpload->store('store-settings', 'public');
        }
        if ($this->faviconUpload) {
            $payload = array_merge($payload, $this->processFaviconUpload($this->faviconUpload));
        }
        if ($this->ogDefaultImageUpload) {
            $payload['store_og_default_image_path'] = $this->ogDefaultImageUpload->store('store-settings', 'public');
        }
        if ($this->ogHomeImageUpload) {
            $payload['store_og_home_image_path'] = $this->ogHomeImageUpload->store('store-settings', 'public');
        }
        if ($this->ogCategoryImageUpload) {
            $payload['store_og_category_image_path'] = $this->ogCategoryImageUpload->store('store-settings', 'public');
        }
        if ($this->ogProductImageUpload) {
            $payload['store_og_product_image_path'] = $this->ogProductImageUpload->store('store-settings', 'public');
        }
        if ($this->ogPageImageUpload) {
            $payload['store_og_page_image_path'] = $this->ogPageImageUpload->store('store-settings', 'public');
        }
        if ($this->ogBlogImageUpload) {
            $payload['store_og_blog_image_path'] = $this->ogBlogImageUpload->store('store-settings', 'public');
        }

        app(SystemSettingsService::class)->putMany($payload);
        $this->form = array_merge($this->form, $payload);

        $this->logoUpload = null;
        $this->faviconUpload = null;
        $this->ogDefaultImageUpload = null;
        $this->ogHomeImageUpload = null;
        $this->ogCategoryImageUpload = null;
        $this->ogProductImageUpload = null;
        $this->ogPageImageUpload = null;
        $this->ogBlogImageUpload = null;

        $this->dispatch('notify', type: 'success', message: __('Store settings saved.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'form.store_email_enabled' => ['required', 'boolean'],
            'form.store_email_mailer' => ['required', 'string', 'in:smtp,sendmail,log'],
            'form.store_email_smtp_host' => ['nullable', 'string', 'max:191'],
            'form.store_email_smtp_port' => ['required', 'integer', 'min:1', 'max:65535'],
            'form.store_email_smtp_username' => ['nullable', 'string', 'max:191'],
            'form.store_email_smtp_password' => ['nullable', 'string', 'max:191'],
            'form.store_email_smtp_encryption' => ['nullable', 'string', 'in:,tls,ssl'],
            'form.store_email_sendmail_path' => ['nullable', 'string', 'max:255'],
            'form.store_email_from_address' => ['nullable', 'email', 'max:191'],
            'form.store_email_from_name' => ['nullable', 'string', 'max:191'],
            'form.store_email_reply_to' => ['nullable', 'email', 'max:191'],
            'form.store_email_orders_to' => ['nullable', 'email', 'max:191'],
            'form.store_email_contact_to' => ['nullable', 'email', 'max:191'],

            'form.store_brand_name' => ['nullable', 'string', 'max:191'],
            'form.store_footer_phone' => ['nullable', 'string', 'max:120'],
            'form.store_footer_email_sales' => ['nullable', 'email', 'max:191'],
            'form.store_footer_email_support' => ['nullable', 'email', 'max:191'],
            'form.store_footer_hours' => ['nullable', 'string', 'max:255'],
            'form.store_footer_col_1_title' => ['nullable', 'string', 'max:120'],
            'form.store_footer_col_1_category_ids' => ['nullable', 'array'],
            'form.store_footer_col_1_category_ids.*' => ['integer', Rule::exists('categories', 'id')->where(fn ($q) => $q->where('scope', Category::SCOPE_CATALOG))],
            'form.store_footer_col_1_page_ids' => ['nullable', 'array'],
            'form.store_footer_col_1_page_ids.*' => ['integer', 'exists:content_info_pages,id'],
            'form.store_footer_col_1_custom_links' => ['nullable', 'string', 'max:5000'],
            'form.store_footer_col_2_title' => ['nullable', 'string', 'max:120'],
            'form.store_footer_col_2_category_ids' => ['nullable', 'array'],
            'form.store_footer_col_2_category_ids.*' => ['integer', Rule::exists('categories', 'id')->where(fn ($q) => $q->where('scope', Category::SCOPE_CATALOG))],
            'form.store_footer_col_2_page_ids' => ['nullable', 'array'],
            'form.store_footer_col_2_page_ids.*' => ['integer', 'exists:content_info_pages,id'],
            'form.store_footer_col_2_custom_links' => ['nullable', 'string', 'max:5000'],
            'form.store_footer_col_3_title' => ['nullable', 'string', 'max:120'],
            'form.store_footer_col_3_category_ids' => ['nullable', 'array'],
            'form.store_footer_col_3_category_ids.*' => ['integer', Rule::exists('categories', 'id')->where(fn ($q) => $q->where('scope', Category::SCOPE_CATALOG))],
            'form.store_footer_col_3_page_ids' => ['nullable', 'array'],
            'form.store_footer_col_3_page_ids.*' => ['integer', 'exists:content_info_pages,id'],
            'form.store_footer_col_3_custom_links' => ['nullable', 'string', 'max:5000'],
            'form.store_footer_bottom_link_page_ids' => ['nullable', 'array'],
            'form.store_footer_bottom_link_page_ids.*' => ['integer', 'exists:content_info_pages,id'],
            'form.store_footer_bottom_copyright_text' => ['nullable', 'string', 'max:255'],
            'form.store_social_facebook_url' => ['nullable', 'url', 'max:2048'],
            'form.store_social_instagram_url' => ['nullable', 'url', 'max:2048'],
            'form.store_social_tiktok_url' => ['nullable', 'url', 'max:2048'],
            'form.store_social_youtube_url' => ['nullable', 'url', 'max:2048'],
            'form.store_footer_social_facebook_enabled' => ['required', 'boolean'],
            'form.store_footer_social_instagram_enabled' => ['required', 'boolean'],
            'form.store_footer_social_tiktok_enabled' => ['required', 'boolean'],
            'form.store_footer_social_youtube_enabled' => ['required', 'boolean'],

            'form.store_newsletter_provider' => ['required', 'string', 'in:none,mailchimp,klaviyo'],
            'form.store_newsletter_mailchimp_api_key' => ['nullable', 'string', 'max:255'],
            'form.store_newsletter_mailchimp_list_id' => ['nullable', 'string', 'max:255'],
            'form.store_newsletter_klaviyo_api_key' => ['nullable', 'string', 'max:255'],
            'form.store_newsletter_klaviyo_list_id' => ['nullable', 'string', 'max:255'],

            'form.store_captcha_recaptcha_v3_enabled' => ['required', 'boolean'],
            'form.store_captcha_recaptcha_v3_site_key' => ['nullable', 'string', 'max:255'],
            'form.store_captcha_recaptcha_v3_secret_key' => ['nullable', 'string', 'max:255'],
            'form.store_captcha_recaptcha_v3_min_score' => ['required', 'numeric', 'min:0', 'max:1'],

            'form.store_analytics_enabled' => ['required', 'boolean'],
            'form.store_analytics_ga4_measurement_id' => ['nullable', 'string', 'max:64'],
            'form.store_analytics_purchase_event_enabled' => ['required', 'boolean'],
            'form.store_analytics_purchase_event_name' => ['nullable', 'string', 'max:64'],
            'form.store_pricing_prices_include_tax' => ['required', 'boolean'],

            'form.store_seo_default_title' => ['nullable', 'string', 'max:191'],
            'form.store_seo_default_description' => ['nullable', 'string', 'max:320'],
            'form.store_seo_robots' => ['nullable', 'string', 'max:120'],
            'form.store_seo_canonical_policy' => ['required', 'string', 'in:self,none'],

            'form.store_schema_enabled' => ['required', 'boolean'],
            'form.store_schema_org_enabled' => ['required', 'boolean'],
            'form.store_schema_website_enabled' => ['required', 'boolean'],
            'form.store_schema_breadcrumbs_enabled' => ['required', 'boolean'],
            'form.store_schema_itemlist_enabled' => ['required', 'boolean'],
            'form.store_schema_home_enabled' => ['required', 'boolean'],
            'form.store_schema_category_enabled' => ['required', 'boolean'],
            'form.store_schema_product_enabled' => ['required', 'boolean'],
            'form.store_schema_blog_enabled' => ['required', 'boolean'],
            'form.store_schema_page_enabled' => ['required', 'boolean'],
            'form.store_schema_faq_enabled' => ['required', 'boolean'],
            'form.store_schema_org_type' => ['required', 'string', 'in:Organization,LocalBusiness,Store'],
            'form.store_schema_business_name' => ['nullable', 'string', 'max:191'],
            'form.store_schema_business_phone' => ['nullable', 'string', 'max:120'],
            'form.store_schema_business_email' => ['nullable', 'email', 'max:191'],
            'form.store_schema_address_street' => ['nullable', 'string', 'max:191'],
            'form.store_schema_address_city' => ['nullable', 'string', 'max:120'],
            'form.store_schema_address_region' => ['nullable', 'string', 'max:120'],
            'form.store_schema_address_postal_code' => ['nullable', 'string', 'max:32'],
            'form.store_schema_address_country' => ['nullable', 'string', 'max:2'],
            'form.store_schema_same_as' => ['nullable', 'string', 'max:5000'],
            'form.store_schema_blog_author_name' => ['nullable', 'string', 'max:191'],
            'form.store_schema_blog_author_url' => ['nullable', 'url', 'max:2048'],
            'form.store_schema_product_currency' => ['required', 'string', 'size:3'],
            'form.store_schema_faq_group' => ['nullable', 'string', 'max:120'],
            'form.store_schema_faq_limit' => ['required', 'integer', 'min:1', 'max:20'],
            'form.store_schema_itemlist_limit' => ['required', 'integer', 'min:1', 'max:48'],

            'form.store_announcement_enabled' => ['required', 'boolean'],
            'form.store_announcement_text' => ['nullable', 'string', 'max:191'],
            'form.store_announcement_url' => ['nullable', 'url', 'max:2048'],
            'form.store_announcement_new_tab' => ['required', 'boolean'],

            'logoUpload' => ['nullable', 'file', 'max:4096', 'mimes:jpg,jpeg,png,webp,avif,svg'],
            'faviconUpload' => ['nullable', 'image', 'max:2048'],
            'ogDefaultImageUpload' => ['nullable', 'image', 'max:4096'],
            'ogHomeImageUpload' => ['nullable', 'image', 'max:4096'],
            'ogCategoryImageUpload' => ['nullable', 'image', 'max:4096'],
            'ogProductImageUpload' => ['nullable', 'image', 'max:4096'],
            'ogPageImageUpload' => ['nullable', 'image', 'max:4096'],
            'ogBlogImageUpload' => ['nullable', 'image', 'max:4096'],
        ];
    }

    public function render()
    {
        $locale = (string) app()->getLocale();
        $fallbackLocale = (string) config('app.locale');

        $catalogCategoryOptions = Category::query()
            ->where('scope', Category::SCOPE_CATALOG)
            ->where('is_active', true)
            ->withDepth()
            ->defaultOrder()
            ->with([
                'translations' => fn ($q) => $q
                    ->where('scope', Category::SCOPE_CATALOG)
                    ->whereIn('locale', [$locale, $fallbackLocale]),
            ])
            ->get();

        $categoryNameById = $catalogCategoryOptions->mapWithKeys(function (Category $category) use ($locale, $fallbackLocale): array {
            $translation = $category->translations->firstWhere('locale', $locale)
                ?? $category->translations->firstWhere('locale', $fallbackLocale)
                ?? $category->translations->first();

            return [
                (int) $category->id => (string) ($translation?->name ?? $category->code ?? ('Category #'.$category->id)),
            ];
        });
        $categoryMap = $catalogCategoryOptions->keyBy(fn (Category $category): int => (int) $category->id);

        $catalogCategoryOptions = $catalogCategoryOptions
            ->map(function (Category $category) use ($categoryNameById, $categoryMap): array {
                $parts = [];
                $cursor = $category;
                $guard = 0;

                while ($cursor && $guard < 32) {
                    $parts[] = (string) ($categoryNameById[(int) $cursor->id] ?? ('Category #'.$cursor->id));
                    $parentId = (int) ($cursor->parent_id ?? 0);
                    $cursor = $parentId > 0 ? $categoryMap->get($parentId) : null;
                    $guard++;
                }

                return [
                    'id' => (int) $category->id,
                    'label' => implode(' > ', array_reverse($parts)),
                ];
            })
            ->values()
            ->all();

        $pageOptions = InfoPage::query()
            ->where('is_active', true)
            ->with(['translations' => fn ($q) => $q->whereIn('locale', [$locale, $fallbackLocale])])
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->map(function (InfoPage $page) use ($locale, $fallbackLocale): array {
                $translation = $page->translations->firstWhere('locale', $locale)
                    ?? $page->translations->firstWhere('locale', $fallbackLocale)
                    ?? $page->translations->first();

                return [
                    'id' => (int) $page->id,
                    'label' => (string) ($translation?->title ?? $page->code),
                ];
            })
            ->values()
            ->all();

        return view('livewire.admin.settings.system.store-settings', [
            'catalogCategoryOptions' => $catalogCategoryOptions,
            'pageOptions' => $pageOptions,
        ]);
    }

    private function authorizeAccess(): void
    {
        $user = auth()->user();
        abort_unless(
            $user && (Bouncer::is($user)->an('superadmin') || $user->can('settings.system.store.manage')),
            403
        );
    }

    /**
     * @return array<string, string>
     */
    private function processFaviconUpload(TemporaryUploadedFile $upload): array
    {
        $extension = strtolower((string) $upload->getClientOriginalExtension());
        $storedOriginalPath = $upload->store('store-settings/favicon', 'public');
        $payload = ['store_brand_favicon_path' => $storedOriginalPath];

        $source = $this->createImageResourceFromUpload($upload);
        if (! $source) {
            return $payload;
        }

        $targets = [
            16 => 'store_brand_favicon_16_path',
            32 => 'store_brand_favicon_32_path',
            180 => 'store_brand_favicon_180_path',
            192 => 'store_brand_favicon_192_path',
            512 => 'store_brand_favicon_512_path',
        ];

        foreach ($targets as $size => $settingKey) {
            $pngPath = 'store-settings/favicon/'.uniqid('favicon-'.$size.'-', true).'.png';
            $pngBinary = $this->renderSquarePng($source, $size);
            if ($pngBinary === null) {
                continue;
            }

            Storage::disk('public')->put($pngPath, $pngBinary);
            $payload[$settingKey] = $pngPath;
        }

        $icoPath = 'store-settings/favicon/'.uniqid('favicon-ico-', true).'.ico';
        $icoBinary = $this->renderIcoFromImage($source, 32);
        if ($icoBinary !== null) {
            Storage::disk('public')->put($icoPath, $icoBinary);
            $payload['store_brand_favicon_ico_path'] = $icoPath;
        }

        imagedestroy($source);

        if (! in_array($extension, ['png', 'jpg', 'jpeg', 'webp', 'gif', 'avif'], true)) {
            $payload['store_brand_favicon_path'] = $payload['store_brand_favicon_32_path'] ?? $storedOriginalPath;
        }

        return $payload;
    }

    private function createImageResourceFromUpload(TemporaryUploadedFile $upload): mixed
    {
        $realPath = $upload->getRealPath();
        if (! is_string($realPath) || $realPath === '') {
            return null;
        }

        $mime = strtolower((string) $upload->getMimeType());

        return match ($mime) {
            'image/png' => @imagecreatefrompng($realPath) ?: null,
            'image/jpeg', 'image/jpg' => @imagecreatefromjpeg($realPath) ?: null,
            'image/gif' => @imagecreatefromgif($realPath) ?: null,
            'image/webp' => function_exists('imagecreatefromwebp') ? (@imagecreatefromwebp($realPath) ?: null) : null,
            'image/avif' => function_exists('imagecreatefromavif') ? (@imagecreatefromavif($realPath) ?: null) : null,
            default => null,
        };
    }

    /**
     * @param mixed $value
     * @return array<int, int>
     */
    private function normalizeIdList(mixed $value): array
    {
        if (is_string($value)) {
            $decoded = json_decode($value, true);
            $value = is_array($decoded) ? $decoded : [];
        }

        if (! is_array($value)) {
            return [];
        }

        $normalized = [];
        foreach ($value as $id) {
            $intId = (int) $id;
            if ($intId > 0) {
                $normalized[] = $intId;
            }
        }

        return array_values(array_unique($normalized));
    }

    private function renderSquarePng(mixed $source, int $size): ?string
    {
        $target = imagecreatetruecolor($size, $size);
        if (! $target) {
            return null;
        }

        imagealphablending($target, false);
        imagesavealpha($target, true);
        $transparent = imagecolorallocatealpha($target, 0, 0, 0, 127);
        imagefill($target, 0, 0, $transparent);

        $srcW = (int) imagesx($source);
        $srcH = (int) imagesy($source);
        $srcMin = max(1, min($srcW, $srcH));
        $srcX = (int) floor(($srcW - $srcMin) / 2);
        $srcY = (int) floor(($srcH - $srcMin) / 2);

        imagecopyresampled($target, $source, 0, 0, $srcX, $srcY, $size, $size, $srcMin, $srcMin);

        ob_start();
        imagepng($target, null, 9);
        $binary = ob_get_clean();
        imagedestroy($target);

        return is_string($binary) ? $binary : null;
    }

    private function renderIcoFromImage(mixed $source, int $size): ?string
    {
        $pngBinary = $this->renderSquarePng($source, $size);
        if ($pngBinary === null) {
            return null;
        }

        $widthByte = $size >= 256 ? 0 : $size;
        $heightByte = $size >= 256 ? 0 : $size;
        $dataSize = strlen($pngBinary);
        $offset = 6 + 16;

        $header = pack('vvv', 0, 1, 1);
        $directory = pack(
            'CCCCvvVV',
            $widthByte,
            $heightByte,
            0,
            0,
            1,
            32,
            $dataSize,
            $offset
        );

        return $header.$directory.$pngBinary;
    }
}
