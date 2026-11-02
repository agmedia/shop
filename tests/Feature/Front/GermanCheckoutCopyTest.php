<?php

namespace Tests\Feature\Front;

use App\Models\Catalog\Product\Product;
use App\Models\Sales\Order\Order;
use App\Models\Settings\Local\Language;
use App\Models\Settings\Local\OrderStatus;
use App\Services\Front\CartService;
use App\Services\Settings\SystemSettingsService;
use App\Support\CheckoutSuccessCopy;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\ViewErrorBag;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class GermanCheckoutCopyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Queue::fake();
        config(['app.locale' => 'hr', 'app.fallback_locale' => 'hr']);
        foreach (['hr', 'de'] as $index => $locale) {
            Language::query()->updateOrCreate(['code' => $locale], [
                'name' => $locale, 'is_active' => true, 'is_default' => $index === 0,
            ]);
        }
        app(SystemSettingsService::class)->put('catalog_use_mobile_view', true);
    }

    public function test_german_checkout_and_cart_popup_receive_localized_ui_labels(): void
    {
        $product = Product::query()->create([
            'code' => 'COPY', 'sku' => 'COPY', 'base_price' => 25,
            'stock_qty' => 10, 'is_active' => true,
        ]);
        $product->translations()->create(['locale' => 'de', 'name' => 'Testartikel', 'slug' => 'testartikel']);
        app(CartService::class)->add($product);

        $this->get('/checkout?lang=de')->assertOk()
            ->assertSee('Ich habe bereits ein Konto, bitte anmelden')
            ->assertSee('Ich möchte mich registrieren')
            ->assertDontSee('I already have an account, log me in')
            ->assertDontSee('I want to register');

        $this->get('/product/testartikel?lang=de')->assertOk()
            ->assertSee('data-modal-continue="Weiter einkaufen"', false)
            ->assertSee('data-modal-go-cart="Zum Warenkorb"', false);
        $this->get('/shop?lang=de')->assertOk()
            ->assertSee('data-modal-continue="Weiter einkaufen"', false)
            ->assertSee('data-modal-go-cart="Zum Warenkorb"', false);
        $this->assertDatabaseCount('orders', 0);
        Mail::assertNothingSent();
    }

    #[DataProvider('footerDisplays')]
    public function test_footer_localizes_a_saved_croatian_return_link_without_changing_saved_settings(string $device, string $locale): void
    {
        $savedLinks = "Obrazac za povrat|/forma-za-povrat-i-reklamacije\nVertrag widerrufen|/rucksendungen-und-reklamationen?withdrawal=1\nEigener Link|/contact";
        app(SystemSettingsService::class)->putMany([
            'store_footer_col_2_custom_links' => $savedLinks,
            'store_footer_col_2_custom_links_translations' => ['de' => $savedLinks],
        ]);
        $this->device($device);
        $response = $this->get('/contact?lang='.$locale)->assertOk()
            ->assertSee('Vertrag widerrufen')
            ->assertSee('Eigener Link');
        if ($locale === 'de') {
            $response->assertSee('Rücksendeformular')->assertDontSee('Obrazac za povrat');
        } else {
            $response->assertSee('Obrazac za povrat');
        }
        if ($locale === 'de' && $device === 'desktop') {
            $response->assertSee('Kostenloser Versand nach Deutschland und Österreich ab 80 €')
                ->assertDontSee('Kostenloser Versand innerhalb Kroatiens ab 49,99 €');
        }
        $this->assertSame($savedLinks, app(SystemSettingsService::class)->get('store_footer_col_2_custom_links_translations')['de']);

        if ($locale === 'hr' && $device === 'desktop') {
            $response->assertSee('Besplatna dostava iznad 49,99 €');
        }
    }

    #[DataProvider('devices')]
    public function test_german_confirmation_localizes_copy_but_preserves_all_payment_data(string $device): void
    {
        // Render an unsaved order so no purchase, notification or payment action is triggered.
        app()->setLocale('de');
        $order = new Order([
            'order_number' => 'TEST-123', 'currency_code' => 'EUR', 'grand_total' => 36.5,
            'item_qty' => 0, 'payload' => [],
        ]);
        $order->setRelation('status', new OrderStatus(['code' => 'new', 'name' => 'Novo']));
        $order->setRelation('items', collect());
        $order->setRelation('totals', collect());
        $bankTransfer = [
            'receiver_name' => 'Bali d.o.o.', 'receiver_iban' => 'HR1210010051863000160',
            'model' => 'HR00', 'reference' => '12326', 'amount' => 36.5,
            'description' => 'Web narudžba TEST-123',
            'qr_image_mime' => 'image/png', 'qr_image_base64' => 'dGVzdC1xci1wYXlsb2Fk',
        ];
        $original = $bankTransfer;
        $view = $this->view('front.'.$device.'.checkout.success', [
            'order' => $order, 'bankTransfer' => $bankTransfer, 'errors' => new ViewErrorBag,
        ]);
        foreach ([
            'Vielen Dank für Ihre Bestellung', 'Neu', 'Zahlung per Banküberweisung',
            'Schließen Sie Ihre Bestellung mit den unten angegebenen Bankdaten ab oder scannen Sie den QR-Code.',
            'Empfänger', 'Zahlungsmodell', 'Referenz', 'Betrag', 'Verwendungszweck',
            'Online-Bestellung TEST-123', 'Weiter einkaufen',
            'HR1210010051863000160', 'HR00', '12326', 'EUR 36.50',
            'data:image/png;base64,dGVzdC1xci1wYXlsb2Fk',
        ] as $text) {
            $view->assertSee($text, false);
        }
        if ($device === 'desktop') {
            $view->assertSee('Bestellung aufgegeben')->assertSee('Bestellnummer');
        } else {
            $view->assertSee('Ihre Bestellung TEST-123 wurde bestätigt.');
        }
        $view->assertDontSee('Thank you for your order')->assertDontSee('Web narudžba TEST-123');
        $this->assertSame($original, $bankTransfer);
        $this->assertFalse($order->exists);
        $this->assertDatabaseCount('orders', 0);
        Mail::assertNothingSent();
    }

    public function test_confirmation_display_preserves_custom_text_and_croatian_copy(): void
    {
        app()->setLocale('de');
        $this->assertSame('Online-Bestellung 123', CheckoutSuccessCopy::bankDescription('Web narudzba 123'));
        $this->assertSame('Sonderauftrag 123', CheckoutSuccessCopy::bankDescription('Sonderauftrag 123'));
        $this->assertSame('In Bearbeitung', CheckoutSuccessCopy::status('In Bearbeitung'));
        app()->setLocale('hr');
        $this->assertSame('Web narudžba 123', CheckoutSuccessCopy::bankDescription('Web narudžba 123'));
        $this->assertSame('Novo', CheckoutSuccessCopy::status('Novo'));
    }

    private function device(string $device): void
    {
        $this->withHeader('User-Agent', $device === 'mobile'
            ? 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X)'
            : 'Mozilla/5.0 (Macintosh; Intel Mac OS X)');
    }

    public static function devices(): array
    {
        return [['desktop'], ['mobile']];
    }

    public static function footerDisplays(): array
    {
        return [['desktop', 'de'], ['mobile', 'de'], ['desktop', 'hr'], ['mobile', 'hr']];
    }
}
