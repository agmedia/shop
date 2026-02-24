<div class="space-y-6">
    <div class="admin-panel admin-search-panel p-6">
        <h1 class="text-xl font-semibold tracking-tight">{{ __('Store Settings') }}</h1>
        <p class="mt-2 text-sm text-slate-600">{{ __('Central place for storefront email, branding, newsletter, and announcement bar settings.') }}</p>
    </div>

    <div class="admin-panel admin-form-panel p-6">
        <div class="mb-4 flex flex-wrap gap-2">
            @foreach ([
                'email' => 'Email',
                'branding' => 'Branding & Footer',
                'newsletter' => 'Newsletter',
                'integrations' => 'Integrations',
                'pricing' => 'Pricing',
                'seo' => 'SEO',
                'og' => 'OG / Twitter',
                'schema' => 'Schema Markup',
                'announcement' => 'Announcement bar',
            ] as $tabKey => $tabLabel)
                <button type="button" wire:click="$set('tab', '{{ $tabKey }}')" class="rounded-xl border px-3 py-1.5 text-xs font-semibold {{ $tab === $tabKey ? 'border-cyan-700 bg-cyan-700 text-white' : 'border-slate-300 text-slate-700 hover:bg-slate-100' }}">
                    {{ __($tabLabel) }}
                </button>
            @endforeach
        </div>

        <form wire:submit="save" class="space-y-4">
            @if ($tab === 'email')
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 md:col-span-2">
                        <input type="checkbox" wire:model="form.store_email_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                        {{ __('Enable store email notifications') }}
                    </label>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Mailer') }}</label>
                        <select wire:model="form.store_email_mailer" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            <option value="smtp">{{ __('SMTP') }}</option>
                            <option value="sendmail">{{ __('Sendmail') }}</option>
                            <option value="log">{{ __('Log') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('From Address') }}</label>
                        <input type="email" wire:model="form.store_email_from_address" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @error('form.store_email_from_address') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('From Name') }}</label>
                        <input type="text" wire:model="form.store_email_from_name" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Reply-To') }}</label>
                        <input type="email" wire:model="form.store_email_reply_to" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @error('form.store_email_reply_to') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Orders To') }}</label>
                        <input type="email" wire:model="form.store_email_orders_to" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @error('form.store_email_orders_to') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Contact Forms To') }}</label>
                        <input type="email" wire:model="form.store_email_contact_to" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @error('form.store_email_contact_to') <p class="mt-1 text-xs text-rose-600">{{ $message }}</p> @enderror
                    </div>
                    <div class="md:col-span-2 grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('SMTP Host') }}</label>
                            <input type="text" wire:model="form.store_email_smtp_host" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('SMTP Port') }}</label>
                            <input type="number" wire:model="form.store_email_smtp_port" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Encryption') }}</label>
                            <select wire:model="form.store_email_smtp_encryption" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                                <option value="">{{ __('None') }}</option>
                                <option value="tls">{{ __('TLS') }}</option>
                                <option value="ssl">{{ __('SSL') }}</option>
                            </select>
                        </div>
                    </div>
                    <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('SMTP Username') }}</label>
                        <input type="text" wire:model="form.store_email_smtp_username" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('SMTP Password') }}</label>
                        <input type="password" wire:model="form.store_email_smtp_password" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Sendmail Path') }}</label>
                        <input type="text" wire:model="form.store_email_sendmail_path" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                </div>
            @endif

            @if ($tab === 'branding')
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Store Name') }}</label>
                        <input type="text" wire:model="form.store_brand_name" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Footer Phone') }}</label>
                        <input type="text" wire:model="form.store_footer_phone" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Footer Sales Email') }}</label>
                        <input type="email" wire:model="form.store_footer_email_sales" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Footer Support Email') }}</label>
                        <input type="email" wire:model="form.store_footer_email_support" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Footer Working Hours') }}</label>
                        <input type="text" wire:model="form.store_footer_hours" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <h3 class="text-sm font-bold text-slate-800">{{ __('Footer Link Columns') }}</h3>
                        <p class="mt-1 text-xs text-slate-600">{{ __('Configure 3 footer columns: title + product categories + pages + custom links.') }}</p>
                    </div>
                    @foreach ([1, 2, 3] as $col)
                        <div class="md:col-span-2 rounded-xl border border-slate-200 p-4">
                            <p class="mb-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Column') }} {{ $col }}</p>
                            <div class="grid gap-3 md:grid-cols-4">
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Title') }}</label>
                                    <input type="text" wire:model="form.store_footer_col_{{ $col }}_title" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Product Categories') }}</label>
                                    <div class="max-h-40 space-y-1 overflow-auto rounded-xl border border-slate-300 p-2 text-sm">
                                        @foreach (($catalogCategoryOptions ?? []) as $option)
                                            <label class="flex items-center gap-2">
                                                <input type="checkbox" wire:model="form.store_footer_col_{{ $col }}_category_ids" value="{{ (int) $option['id'] }}" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500">
                                                <span>{{ (string) $option['label'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Pages') }}</label>
                                    <div class="max-h-40 space-y-1 overflow-auto rounded-xl border border-slate-300 p-2 text-sm">
                                        @foreach (($pageOptions ?? []) as $option)
                                            <label class="flex items-center gap-2">
                                                <input type="checkbox" wire:model="form.store_footer_col_{{ $col }}_page_ids" value="{{ (int) $option['id'] }}" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500">
                                                <span>{{ (string) $option['label'] }}</span>
                                            </label>
                                        @endforeach
                                    </div>
                                </div>
                                <div>
                                    <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Custom Links') }}</label>
                                    <textarea wire:model="form.store_footer_col_{{ $col }}_custom_links" rows="6" class="w-full rounded-xl border border-slate-300 px-3 py-2 font-mono text-xs" placeholder="{{ __('Blog|/blog&#10;FAQ|/page/faq&#10;Kontakt|/contact') }}"></textarea>
                                    <p class="mt-1 text-xs text-slate-500">{{ __('One per line:') }} <code>Label|URL</code></p>
                                </div>
                            </div>
                        </div>
                    @endforeach
                    <div class="md:col-span-2 rounded-xl border border-slate-200 p-4">
                        <p class="mb-3 text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Bottom Footer Bar') }}</p>
                        <div class="grid gap-3 md:grid-cols-2">
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Copyright text') }}</label>
                                <input type="text" wire:model="form.store_footer_bottom_copyright_text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="{{ __('Sva prava pridržana.') }}" />
                            </div>
                            <div>
                                <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Bottom links (pages)') }}</label>
                                <div class="max-h-40 space-y-1 overflow-auto rounded-xl border border-slate-300 p-2 text-sm">
                                    @foreach (($pageOptions ?? []) as $option)
                                        <label class="flex items-center gap-2">
                                            <input type="checkbox" wire:model="form.store_footer_bottom_link_page_ids" value="{{ (int) $option['id'] }}" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500">
                                            <span>{{ (string) $option['label'] }}</span>
                                        </label>
                                    @endforeach
                                </div>
                                <p class="mt-1 text-xs text-slate-500">{{ __('Order in this list = display order in footer.') }}</p>
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Logo') }}</label>
                        <input type="file" wire:model="logoUpload" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        <p class="mt-1 text-xs text-slate-500">{{ __('Allowed: JPG, PNG, WEBP, AVIF, SVG') }}</p>
                        @if ($form['store_brand_logo_path'])
                            <p class="mt-1 text-xs text-slate-500">{{ __('Current:') }} {{ $form['store_brand_logo_path'] }}</p>
                        @endif
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Favicon') }}</label>
                        <input type="file" wire:model="faviconUpload" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        <p class="mt-1 text-xs text-slate-500">{{ __('Auto-generate: 16, 32, 180, 192, 512 and ICO.') }}</p>
                        @if ($form['store_brand_favicon_path'])
                            <p class="mt-1 text-xs text-slate-500">{{ __('Current:') }} {{ $form['store_brand_favicon_path'] }}</p>
                        @endif
                    </div>
                    <div class="md:col-span-2 grid gap-3 md:grid-cols-2">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Facebook URL') }}</label>
                            <input type="url" wire:model="form.store_social_facebook_url" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                            <label class="mt-2 inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                                <input type="checkbox" wire:model="form.store_footer_social_facebook_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                                {{ __('Show in footer') }}
                            </label>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Instagram URL') }}</label>
                            <input type="url" wire:model="form.store_social_instagram_url" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                            <label class="mt-2 inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                                <input type="checkbox" wire:model="form.store_footer_social_instagram_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                                {{ __('Show in footer') }}
                            </label>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('TikTok URL') }}</label>
                            <input type="url" wire:model="form.store_social_tiktok_url" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                            <label class="mt-2 inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                                <input type="checkbox" wire:model="form.store_footer_social_tiktok_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                                {{ __('Show in footer') }}
                            </label>
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('YouTube URL') }}</label>
                            <input type="url" wire:model="form.store_social_youtube_url" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                            <label class="mt-2 inline-flex items-center gap-2 text-xs font-semibold text-slate-700">
                                <input type="checkbox" wire:model="form.store_footer_social_youtube_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                                {{ __('Show in footer') }}
                            </label>
                        </div>
                    </div>
                </div>
            @endif

            @if ($tab === 'newsletter')
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Provider') }}</label>
                        <select wire:model="form.store_newsletter_provider" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm md:w-72">
                            <option value="none">{{ __('None') }}</option>
                            <option value="mailchimp">{{ __('Mailchimp') }}</option>
                            <option value="klaviyo">{{ __('Klaviyo') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Mailchimp API Key') }}</label>
                        <input type="text" wire:model="form.store_newsletter_mailchimp_api_key" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Mailchimp List ID') }}</label>
                        <input type="text" wire:model="form.store_newsletter_mailchimp_list_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Klaviyo API Key') }}</label>
                        <input type="text" wire:model="form.store_newsletter_klaviyo_api_key" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Klaviyo List ID') }}</label>
                        <input type="text" wire:model="form.store_newsletter_klaviyo_list_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                </div>
            @endif

            @if ($tab === 'integrations')
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <h3 class="text-sm font-bold text-slate-800">{{ __('reCAPTCHA v3') }}</h3>
                        <p class="mt-1 text-xs text-slate-600">{{ __('Protect forms (contact, checkout later) from spam bots.') }}</p>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 md:col-span-2">
                        <input type="checkbox" wire:model="form.store_captcha_recaptcha_v3_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                        {{ __('Enable reCAPTCHA v3') }}
                    </label>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Site key') }}</label>
                        <input type="text" wire:model="form.store_captcha_recaptcha_v3_site_key" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Secret key') }}</label>
                        <input type="password" wire:model="form.store_captcha_recaptcha_v3_secret_key" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div class="md:col-span-2 md:w-56">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Min score') }}</label>
                        <input type="number" step="0.1" min="0" max="1" wire:model="form.store_captcha_recaptcha_v3_min_score" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>

                    <div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4 mt-2">
                        <h3 class="text-sm font-bold text-slate-800">{{ __('Google Analytics (GA4)') }}</h3>
                        <p class="mt-1 text-xs text-slate-600">{{ __('Inject global gtag script and push purchase event on checkout success.') }}</p>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 md:col-span-2">
                        <input type="checkbox" wire:model="form.store_analytics_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                        {{ __('Enable GA4 tracking') }}
                    </label>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('GA4 Measurement ID') }}</label>
                        <input type="text" wire:model="form.store_analytics_ga4_measurement_id" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="G-XXXXXXXXXX" />
                    </div>
                    <div></div>
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 md:col-span-2">
                        <input type="checkbox" wire:model="form.store_analytics_purchase_event_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                        {{ __('Enable purchase event on successful checkout') }}
                    </label>
                    <div class="md:w-72">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Purchase event name') }}</label>
                        <input type="text" wire:model="form.store_analytics_purchase_event_name" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="purchase" />
                    </div>

                </div>
            @endif

            @if ($tab === 'pricing')
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2 rounded-xl border border-slate-200 bg-slate-50 p-4">
                        <h3 class="text-sm font-bold text-slate-800">{{ __('Pricing') }}</h3>
                        <p class="mt-1 text-xs text-slate-600">{{ __('Choose if stored catalog prices are entered with VAT included or VAT excluded.') }}</p>
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 md:col-span-2">
                        <input type="checkbox" wire:model="form.store_pricing_prices_include_tax" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                        {{ __('Product prices include VAT') }}
                    </label>
                </div>
            @endif

            @if ($tab === 'seo')
                <div class="grid gap-4 md:grid-cols-2">
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Default Title') }}</label>
                        <input type="text" wire:model="form.store_seo_default_title" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Default Meta Description') }}</label>
                        <textarea wire:model="form.store_seo_default_description" rows="3" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Robots') }}</label>
                        <input type="text" wire:model="form.store_seo_robots" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="index,follow" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Canonical policy') }}</label>
                        <select wire:model="form.store_seo_canonical_policy" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            <option value="self">{{ __('Self URL') }}</option>
                            <option value="none">{{ __('Disabled') }}</option>
                        </select>
                    </div>
                </div>
            @endif

            @if ($tab === 'og')
                <div class="grid gap-4 md:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Default OG image') }}</label>
                        <input type="file" wire:model="ogDefaultImageUpload" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @if ($form['store_og_default_image_path'])
                            <p class="mt-1 text-xs text-slate-500">{{ __('Current:') }} {{ $form['store_og_default_image_path'] }}</p>
                        @endif
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Home OG override') }}</label>
                        <input type="file" wire:model="ogHomeImageUpload" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @if ($form['store_og_home_image_path'])
                            <p class="mt-1 text-xs text-slate-500">{{ __('Current:') }} {{ $form['store_og_home_image_path'] }}</p>
                        @endif
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Category OG override') }}</label>
                        <input type="file" wire:model="ogCategoryImageUpload" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @if ($form['store_og_category_image_path'])
                            <p class="mt-1 text-xs text-slate-500">{{ __('Current:') }} {{ $form['store_og_category_image_path'] }}</p>
                        @endif
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Product OG override') }}</label>
                        <input type="file" wire:model="ogProductImageUpload" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @if ($form['store_og_product_image_path'])
                            <p class="mt-1 text-xs text-slate-500">{{ __('Current:') }} {{ $form['store_og_product_image_path'] }}</p>
                        @endif
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Page OG override') }}</label>
                        <input type="file" wire:model="ogPageImageUpload" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @if ($form['store_og_page_image_path'])
                            <p class="mt-1 text-xs text-slate-500">{{ __('Current:') }} {{ $form['store_og_page_image_path'] }}</p>
                        @endif
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Blog OG override') }}</label>
                        <input type="file" wire:model="ogBlogImageUpload" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        @if ($form['store_og_blog_image_path'])
                            <p class="mt-1 text-xs text-slate-500">{{ __('Current:') }} {{ $form['store_og_blog_image_path'] }}</p>
                        @endif
                    </div>
                </div>
            @endif

            @if ($tab === 'schema')
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 md:col-span-2">
                        <input type="checkbox" wire:model="form.store_schema_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                        {{ __('Enable schema markup JSON-LD') }}
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" wire:model="form.store_schema_org_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                        {{ __('Organization schema') }}
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" wire:model="form.store_schema_website_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                        {{ __('WebSite + SearchAction') }}
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" wire:model="form.store_schema_breadcrumbs_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                        {{ __('BreadcrumbList') }}
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" wire:model="form.store_schema_itemlist_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                        {{ __('ItemList (shop/category/blog list)') }}
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" wire:model="form.store_schema_home_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                        {{ __('Home WebPage') }}
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" wire:model="form.store_schema_category_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                        {{ __('Category CollectionPage') }}
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" wire:model="form.store_schema_product_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                        {{ __('Product schema') }}
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" wire:model="form.store_schema_blog_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                        {{ __('Blog / BlogPosting') }}
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" wire:model="form.store_schema_page_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                        {{ __('Generic Page schema') }}
                    </label>
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700">
                        <input type="checkbox" wire:model="form.store_schema_faq_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                        {{ __('FAQ schema (home)') }}
                    </label>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Organization type') }}</label>
                        <select wire:model="form.store_schema_org_type" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm">
                            <option value="Organization">{{ __('Organization') }}</option>
                            <option value="LocalBusiness">{{ __('LocalBusiness') }}</option>
                            <option value="Store">{{ __('Store') }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Business Name') }}</label>
                        <input type="text" wire:model="form.store_schema_business_name" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Business Phone') }}</label>
                        <input type="text" wire:model="form.store_schema_business_phone" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Business Email') }}</label>
                        <input type="email" wire:model="form.store_schema_business_email" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>

                    <div class="md:col-span-2 grid gap-3 md:grid-cols-3">
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Street') }}</label>
                            <input type="text" wire:model="form.store_schema_address_street" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('City') }}</label>
                            <input type="text" wire:model="form.store_schema_address_city" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Region') }}</label>
                            <input type="text" wire:model="form.store_schema_address_region" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Postal code') }}</label>
                            <input type="text" wire:model="form.store_schema_address_postal_code" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Country code') }}</label>
                            <input type="text" wire:model="form.store_schema_address_country" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="HR" />
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Product currency') }}</label>
                            <input type="text" wire:model="form.store_schema_product_currency" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="EUR" />
                        </div>
                    </div>

                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('SameAs URLs (one per line)') }}</label>
                        <textarea wire:model="form.store_schema_same_as" rows="4" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm"></textarea>
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Blog author name (default)') }}</label>
                        <input type="text" wire:model="form.store_schema_blog_author_name" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Blog author profile URL') }}</label>
                        <input type="url" wire:model="form.store_schema_blog_author_url" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>

                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('FAQ group code (optional)') }}</label>
                        <input type="text" wire:model="form.store_schema_faq_group" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" placeholder="support" />
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('FAQ max items') }}</label>
                        <input type="number" min="1" max="20" wire:model="form.store_schema_faq_limit" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('ItemList max products/posts') }}</label>
                        <input type="number" min="1" max="48" wire:model="form.store_schema_itemlist_limit" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm md:w-48" />
                    </div>
                </div>
            @endif

            @if ($tab === 'announcement')
                <div class="grid gap-4 md:grid-cols-2">
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 md:col-span-2">
                        <input type="checkbox" wire:model="form.store_announcement_enabled" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                        {{ __('Show top announcement bar') }}
                    </label>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Text') }}</label>
                        <input type="text" wire:model="form.store_announcement_text" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <div class="md:col-span-2">
                        <label class="mb-1 block text-xs font-semibold uppercase tracking-[0.14em] text-slate-500">{{ __('Link URL (optional)') }}</label>
                        <input type="url" wire:model="form.store_announcement_url" class="w-full rounded-xl border border-slate-300 px-3 py-2 text-sm" />
                    </div>
                    <label class="inline-flex items-center gap-2 text-sm font-semibold text-slate-700 md:col-span-2">
                        <input type="checkbox" wire:model="form.store_announcement_new_tab" class="rounded border-slate-300 text-cyan-700 focus:ring-cyan-500" />
                        {{ __('Open link in new tab') }}
                    </label>
                </div>
            @endif

            <div class="pt-2">
                <button type="submit" class="rounded-xl bg-cyan-700 px-4 py-2 text-sm font-semibold text-white hover:bg-cyan-800">{{ __('Save Store Settings') }}</button>
            </div>
        </form>
    </div>
</div>
