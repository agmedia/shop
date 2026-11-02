<?php

namespace Tests\Feature\Front;

use App\Models\Catalog\Product\Product;
use App\Models\Catalog\Product\ProductTranslation;
use App\Models\Settings\Local\PaymentMethod;
use App\Models\Settings\Local\ShippingMethod;
use App\Models\Settings\Local\TaxRate;
use App\Models\User;
use App\Models\User\UserAddress;
use App\Services\Front\CartService;
use App\Services\Settings\SystemSettingsService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CheckoutCountryRestrictionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        app(SystemSettingsService::class)->put('store_pricing_prices_include_tax', true);
        $tax = TaxRate::query()->create([
            'code' => 'VAT25', 'name' => 'VAT25', 'rate' => 25, 'rate_type' => 'percent',
            'is_default' => true, 'is_active' => true,
        ]);
        $product = Product::query()->create([
            'code' => 'COUNTRY', 'sku' => 'COUNTRY', 'base_price' => 25, 'stock_qty' => 10,
            'is_active' => true, 'tax_rate_id' => $tax->id,
        ]);
        ProductTranslation::query()->create([
            'product_id' => $product->id, 'locale' => 'hr', 'name' => 'Test product', 'slug' => 'country-product',
        ]);
        // Unrestricted methods make the country rule independent of method geo zones.
        ShippingMethod::query()->create(['code' => 'standard', 'name' => 'Standard', 'price' => 10, 'is_active' => true]);
        PaymentMethod::query()->create(['code' => 'bank', 'name' => 'Bank', 'is_active' => true]);
        app(CartService::class)->add($product);
    }

    #[DataProvider('allowedCountries')]
    public function test_supported_countries_are_available_for_checkout_options_and_order_submission(string $country): void
    {
        $this->getJson('/checkout/options?'.http_build_query([
            'billing_country_code' => strtolower($country), 'shipping_country_code' => strtolower($country),
            'shipping_method_code' => 'standard', 'payment_method_code' => 'bank',
        ]))->assertOk()->assertJsonPath('totals.shipping_method_code', 'standard');

        $this->postJson('/checkout', $this->payload($country))->assertOk();
        $this->assertDatabaseHas('orders', ['billing_country_code' => $country, 'shipping_country_code' => $country]);
    }

    public static function allowedCountries(): array
    {
        return [['HR'], ['DE'], ['AT']];
    }

    #[DataProvider('unsupportedInputs')]
    public function test_options_reject_unsupported_country_inputs_before_calculating_a_quote(string $field, mixed $country): void
    {
        $this->getJson('/checkout/options?'.http_build_query([
            'billing_country_code' => 'HR', 'shipping_country_code' => 'HR',
            'ship_to_different_address' => '1', $field => $country,
        ]))->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('orders', 0);
    }

    #[DataProvider('unsupportedInputs')]
    public function test_direct_order_submission_cannot_bypass_country_restrictions(string $field, mixed $country): void
    {
        $payload = $this->payload('HR');
        $payload['ship_to_different_address'] = '1';
        $payload['use_billing_for_shipping'] = '0';
        $payload[$field] = $country;

        $this->postJson('/checkout', $payload)->assertUnprocessable()->assertJsonValidationErrors($field);
        $this->assertDatabaseCount('orders', 0);
        $this->assertDatabaseCount('order_items', 0);
    }

    public static function unsupportedInputs(): array
    {
        return [
            'French billing' => ['billing_country_code', 'FR'],
            'French delivery' => ['shipping_country_code', 'FR'],
            'UK billing' => ['billing_country_code', 'GB'],
            'unknown delivery' => ['shipping_country_code', 'ZZ'],
            'array billing' => ['billing_country_code', ['DE']],
            'array delivery' => ['shipping_country_code', ['AT']],
        ];
    }

    public function test_unsupported_hidden_delivery_country_is_rejected_even_when_billing_would_replace_it(): void
    {
        $payload = $this->payload('HR');
        $payload['shipping_country_code'] = 'FR';
        $this->postJson('/checkout', $payload)->assertUnprocessable()->assertJsonValidationErrors('shipping_country_code');
        $this->getJson('/checkout/options?billing_country_code=HR&shipping_country_code=FR')
            ->assertUnprocessable()->assertJsonValidationErrors('shipping_country_code');
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_checkout_options_keep_the_existing_croatian_default_when_countries_are_omitted(): void
    {
        $this->getJson('/checkout/options')->assertOk()->assertJsonPath('totals.shipping_method_code', 'standard');
    }

    #[DataProvider('localeDefaults')]
    public function test_checkout_country_and_initial_vat_follow_the_language_default_on_both_templates(string $locale, string $country, bool $mobile): void
    {
        config(['app.locale' => $locale]);
        app()->setLocale($locale);
        app(SystemSettingsService::class)->put('catalog_use_mobile_view', $mobile);
        $response = $this->withHeader('User-Agent', $mobile ? 'Mozilla/5.0 iPhone Mobile' : 'Mozilla/5.0 Macintosh')
            ->get('/checkout')->assertOk()
            ->assertViewHas('selectedCountryCodes', ['billing' => $country, 'shipping' => $country])
            ->assertViewHas('prefill', fn ($prefill) => $prefill['billing']['country_code'] === $country && $prefill['shipping']['country_code'] === $country);

        $dom = new \DOMDocument;
        @$dom->loadHTML('<?xml encoding="utf-8" ?>'.$response->getContent());
        $xpath = new \DOMXPath($dom);
        foreach (['billing_country_code', 'shipping_country_code'] as $field) {
            $this->assertSame($country, $xpath->evaluate('string(//select[@name="'.$field.'"]/option[@selected]/@value)'));
        }
        $this->assertSame($country === 'DE' ? 23.8 : 25.0, $response->viewData('displayPricing')['subtotal']);
        $this->getJson('/checkout/options')->assertOk()
            ->assertJsonPath('display_pricing.subtotal', $country === 'DE' ? 23.8 : 25);
    }

    public static function localeDefaults(): array
    {
        return [
            'German desktop' => ['de', 'DE', false], 'German mobile' => ['de', 'DE', true],
            'Croatian desktop' => ['hr', 'HR', false], 'Croatian mobile' => ['hr', 'HR', true],
            'English desktop' => ['en', 'HR', false], 'English mobile' => ['en', 'HR', true],
        ];
    }

    public function test_german_language_preserves_explicit_countries_and_their_vat(): void
    {
        config(['app.locale' => 'de']);
        app()->setLocale('de');
        $this->withSession(['_old_input' => [
            'billing_country_code' => 'AT', 'shipping_country_code' => 'HR', 'ship_to_different_address' => '1',
        ]])->get('/checkout')->assertOk()
            ->assertViewHas('selectedCountryCodes', ['billing' => 'AT', 'shipping' => 'HR'])
            ->assertViewHas('displayPricing', fn ($pricing) => $pricing['subtotal'] === 25.0);
        $this->getJson('/checkout/options?billing_country_code=AT&shipping_country_code=AT&ship_to_different_address=1')
            ->assertOk()->assertJsonPath('display_pricing.subtotal', 24);
    }

    public function test_german_language_keeps_saved_supported_address_countries(): void
    {
        config(['app.locale' => 'de']);
        app()->setLocale('de');
        $user = User::factory()->create();
        foreach ([UserAddress::TYPE_BILLING => 'HR', UserAddress::TYPE_SHIPPING => 'AT'] as $type => $country) {
            UserAddress::query()->create([
                'user_id' => $user->id, 'type' => $type, 'first_name' => 'Test', 'last_name' => 'Customer',
                'address_line_1' => 'Test street 1', 'postal_code' => '10000', 'city' => 'Test', 'country_code' => $country,
            ]);
        }
        $this->actingAs($user)->get('/checkout')->assertOk()
            ->assertViewHas('selectedCountryCodes', ['billing' => 'HR', 'shipping' => 'AT'])
            ->assertViewHas('displayPricing', fn ($pricing) => $pricing['subtotal'] === 25.0);
        foreach ([UserAddress::TYPE_BILLING => 'HR', UserAddress::TYPE_SHIPPING => 'AT'] as $type => $country) {
            $this->assertDatabaseHas('user_addresses', ['user_id' => $user->id, 'type' => $type, 'country_code' => $country]);
        }
    }

    public function test_create_filters_country_options_and_sanitizes_invalid_old_countries(): void
    {
        $this->withSession(['_old_input' => [
            'billing_country_code' => 'FR', 'shipping_country_code' => 'GB', 'ship_to_different_address' => '1',
        ]])->get('/checkout')->assertOk()
            ->assertViewHas('countryOptions', fn ($options) => count($options) === 3 && collect($options)->pluck('code')->sort()->values()->all() === ['AT', 'DE', 'HR'])
            ->assertViewHas('selectedCountryCodes', ['billing' => 'HR', 'shipping' => 'HR']);
    }

    public function test_create_preserves_valid_old_country_selection(): void
    {
        $this->withSession(['_old_input' => [
            'billing_country_code' => 'DE', 'shipping_country_code' => 'AT', 'ship_to_different_address' => '1',
        ]])->get('/checkout')->assertOk()
            ->assertViewHas('selectedCountryCodes', ['billing' => 'DE', 'shipping' => 'AT']);
    }

    public function test_create_does_not_use_unsupported_query_parameters_as_selected_countries(): void
    {
        $this->get('/checkout?billing_country_code=FR&shipping_country_code=GB&ship_to_different_address=1')
            ->assertOk()->assertViewHas('selectedCountryCodes', ['billing' => 'HR', 'shipping' => 'HR']);
    }

    public function test_unsupported_country_errors_use_the_storefront_language(): void
    {
        config(['app.locale' => 'de']);
        app()->setLocale('de');
        $message = 'Bitte wählen Sie Kroatien, Deutschland oder Österreich.';
        $this->getJson('/checkout/options?billing_country_code=FR')
            ->assertUnprocessable()->assertJsonPath('errors.billing_country_code.0', $message);
        $payload = $this->payload('HR');
        $payload['shipping_country_code'] = 'FR';
        $this->postJson('/checkout', $payload)
            ->assertUnprocessable()->assertJsonPath('errors.shipping_country_code.0', $message);
        $this->assertDatabaseCount('orders', 0);
    }

    public function test_create_sanitizes_unsupported_saved_addresses_without_modifying_them(): void
    {
        $user = User::factory()->create();
        foreach ([UserAddress::TYPE_BILLING => 'FR', UserAddress::TYPE_SHIPPING => 'GB'] as $type => $country) {
            UserAddress::query()->create([
                'user_id' => $user->id, 'type' => $type, 'first_name' => 'Test', 'last_name' => 'Customer',
                'address_line_1' => 'Test street 1', 'postal_code' => '10000', 'city' => 'Test', 'country_code' => $country,
            ]);
        }
        $this->actingAs($user)->get('/checkout')->assertOk()
            ->assertViewHas('selectedCountryCodes', ['billing' => 'HR', 'shipping' => 'HR'])
            ->assertViewHas('prefill', fn ($prefill) => $prefill['billing']['country_code'] === 'HR' && $prefill['shipping']['country_code'] === 'HR');
        $this->assertDatabaseHas('user_addresses', ['user_id' => $user->id, 'type' => UserAddress::TYPE_BILLING, 'country_code' => 'FR']);
        $this->assertDatabaseHas('user_addresses', ['user_id' => $user->id, 'type' => UserAddress::TYPE_SHIPPING, 'country_code' => 'GB']);
    }

    private function payload(string $country): array
    {
        return [
            'customer_email' => 'country@example.test', 'customer_phone' => '+38591000002',
            'billing_first_name' => 'Test', 'billing_last_name' => 'Customer',
            'billing_address_line_1' => 'Test street 1', 'billing_postal_code' => '10000',
            'billing_city' => 'Test', 'billing_state' => 'Grad Zagreb', 'billing_country_code' => $country,
            'use_billing_for_shipping' => '1', 'shipping_country_code' => $country,
            'shipping_method_code' => 'standard', 'payment_method_code' => 'bank', 'accept_terms' => '1',
        ];
    }
}
