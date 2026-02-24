<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Front\Concerns\ResolvesFrontendView;
use App\Models\Sales\Order\Order;
use App\Models\User;
use App\Models\User\UserAddress;
use App\Models\User\UserProfile;
use App\Services\Front\AddressDirectoryService;
use App\Services\Front\CartService;
use App\Services\Front\CheckoutService;
use App\Services\Front\StoreNotificationService;
use App\Services\Payments\BankTransferUpiService;
use App\Services\Payments\CorvusPayFormService;
use App\Services\Payments\KeksPayService;
use App\Services\Payments\WSPayFormService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Http\JsonResponse;
use App\Support\Currency;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CheckoutController extends Controller
{
    use ResolvesFrontendView;

    public function __construct(
        private readonly CartService $cart,
        private readonly CheckoutService $checkout,
        private readonly StoreNotificationService $notifications
    ) {
    }

    public function create(Request $request): View|RedirectResponse
    {
        $summary = $this->cart->summary();

        if ($summary['line_count'] === 0) {
            return redirect()->route('cart.index')->with('status', 'Your cart is empty.');
        }

        $user = $request->user();
        $user?->loadMissing(['profile', 'addresses']);

        $billing = $user?->addresses?->firstWhere('type', UserAddress::TYPE_BILLING);
        $shipping = $user?->addresses?->firstWhere('type', UserAddress::TYPE_SHIPPING);
        $shippingCountry = (string) ($shipping?->country_code ?: $billing?->country_code ?: 'HR');
        $shippingState = (string) ($shipping?->state ?: $billing?->state ?: '');
        $shippingPostal = (string) ($shipping?->postal_code ?: $billing?->postal_code ?: '');
        $billingCountry = (string) ($billing?->country_code ?: $shippingCountry);
        $billingState = (string) ($billing?->state ?: '');
        $billingPostal = (string) ($billing?->postal_code ?: '');

        $addressDirectory = app(AddressDirectoryService::class);
        $regionOptionsByCountry = $addressDirectory->regionsByCountry((string) app()->getLocale());
        $defaultShippingCode = (string) ($shippingMethods = $this->checkout->availableShippingMethods(
            (float) ($summary['subtotal_after_discount'] ?? $summary['subtotal']),
            $shippingCountry,
            $shippingState,
            $shippingPostal
        ))->first()?->code;
        $defaultPaymentCode = (string) ($paymentMethods = $this->checkout->availablePaymentMethods(
            (float) ($summary['subtotal_after_discount'] ?? $summary['subtotal']),
            $billingCountry,
            $billingState,
            $billingPostal
        ))->first()?->code;
        $checkoutTotals = $this->checkout->estimateCheckoutTotals(
            (float) ($summary['subtotal'] ?? 0),
            (float) ($summary['discount_total'] ?? 0),
            (float) ($summary['subtotal_after_discount'] ?? $summary['subtotal'] ?? 0),
            (float) ($summary['tax_total'] ?? 0),
            $defaultShippingCode,
            $defaultPaymentCode,
            $shippingCountry,
            $shippingState,
            $shippingPostal,
            $billingCountry,
            $billingState,
            $billingPostal
        );

        return view($this->frontendView($request, 'checkout.create'), [
            'lines' => $this->cart->lines(),
            'summary' => $summary,
            'shippingMethods' => $shippingMethods,
            'paymentMethods' => $paymentMethods,
            'checkoutTotals' => $checkoutTotals,
            'countryOptions' => $addressDirectory->countries((string) app()->getLocale()),
            'countyOptions' => array_values(array_map(
                static fn (array $row): string => (string) ($row['name'] ?? ''),
                $regionOptionsByCountry['HR'] ?? []
            )),
            'regionOptionsByCountry' => $regionOptionsByCountry,
            'placesAssetUrl' => $addressDirectory->placesAssetUrl(),
            'prefill' => [
                'name' => (string) ($user?->name ?? ''),
                'email' => (string) ($user?->email ?? ''),
                'first_name' => (string) ($user?->profile?->first_name ?? ''),
                'last_name' => (string) ($user?->profile?->last_name ?? ''),
                'phone' => (string) ($user?->profile?->phone ?? ''),
                'newsletter_opt_in' => (bool) ($user?->profile?->newsletter_opt_in ?? false),
                'billing' => [
                    'first_name' => (string) ($billing?->first_name ?? ''),
                    'last_name' => (string) ($billing?->last_name ?? ''),
                    'company' => (string) ($billing?->company ?? ''),
                    'oib' => (string) ($billing?->oib ?? ''),
                    'vat_id' => (string) ($billing?->vat_id ?? ''),
                    'address_line_1' => (string) ($billing?->address_line_1 ?? ''),
                    'address_line_2' => (string) ($billing?->address_line_2 ?? ''),
                    'postal_code' => (string) ($billing?->postal_code ?? ''),
                    'city' => (string) ($billing?->city ?? ''),
                    'state' => (string) ($billing?->state ?? ''),
                    'country_code' => (string) ($billing?->country_code ?? 'HR'),
                ],
                'shipping' => [
                    'first_name' => (string) ($shipping?->first_name ?? ''),
                    'last_name' => (string) ($shipping?->last_name ?? ''),
                    'company' => (string) ($shipping?->company ?? ''),
                    'oib' => (string) ($shipping?->oib ?? ''),
                    'vat_id' => (string) ($shipping?->vat_id ?? ''),
                    'address_line_1' => (string) ($shipping?->address_line_1 ?? ''),
                    'address_line_2' => (string) ($shipping?->address_line_2 ?? ''),
                    'postal_code' => (string) ($shipping?->postal_code ?? ''),
                    'city' => (string) ($shipping?->city ?? ''),
                    'state' => (string) ($shipping?->state ?? ''),
                    'country_code' => (string) ($shipping?->country_code ?? 'HR'),
                ],
            ],
        ]);
    }

    public function store(Request $request): RedirectResponse|JsonResponse
    {
        if (! $this->cart->hasItems()) {
            return redirect()->route('cart.index')->with('status', 'Your cart is empty.');
        }

        $validated = $request->validate([
            'customer_first_name' => ['nullable', 'string', 'max:120'],
            'customer_last_name' => ['nullable', 'string', 'max:120'],
            'customer_email' => ['required', 'email', 'max:191'],
            'customer_phone' => ['required', 'string', 'max:25', 'regex:/^\+?[0-9][0-9\s\-\/]{5,24}$/'],

            'billing_first_name' => ['required', 'string', 'max:120'],
            'billing_last_name' => ['required', 'string', 'max:120'],
            'billing_company' => ['nullable', 'string', 'max:191'],
            'billing_oib' => ['nullable', 'string', 'max:60'],
            'billing_vat_id' => ['nullable', 'string', 'max:60'],
            'billing_address_line_1' => ['required', 'string', 'max:191'],
            'billing_address_line_2' => ['nullable', 'string', 'max:191'],
            'billing_postal_code' => ['required', 'string', 'max:32'],
            'billing_city' => ['required', 'string', 'max:120'],
            'billing_state' => ['required_if:billing_country_code,HR', 'nullable', 'string', 'max:120'],
            'billing_country_code' => ['required', 'string', 'size:2'],

            'use_billing_for_shipping' => ['nullable', 'boolean'],
            'ship_to_different_address' => ['nullable', 'boolean'],

            'shipping_first_name' => ['nullable', 'string', 'max:120'],
            'shipping_last_name' => ['nullable', 'string', 'max:120'],
            'shipping_company' => ['nullable', 'string', 'max:191'],
            'shipping_oib' => ['nullable', 'string', 'max:60'],
            'shipping_vat_id' => ['nullable', 'string', 'max:60'],
            'shipping_address_line_1' => ['nullable', 'string', 'max:191'],
            'shipping_address_line_2' => ['nullable', 'string', 'max:191'],
            'shipping_postal_code' => ['nullable', 'string', 'max:32'],
            'shipping_city' => ['nullable', 'string', 'max:120'],
            'shipping_state' => ['nullable', 'string', 'max:120'],
            'shipping_country_code' => ['nullable', 'string', 'size:2'],
            'shipping_boxnow_locker_id' => ['nullable', 'string', 'max:80'],
            'shipping_boxnow_locker_name' => ['nullable', 'string', 'max:255'],
            'shipping_boxnow_address_line_1' => ['nullable', 'string', 'max:255'],
            'shipping_boxnow_postal_code' => ['nullable', 'string', 'max:32'],
            'shipping_boxnow_city' => ['nullable', 'string', 'max:120'],

            'shipping_method_code' => ['required', 'string', 'max:60'],
            'payment_method_code' => ['required', 'string', 'max:60'],
            'customer_note' => ['nullable', 'string', 'max:2000'],
            'newsletter_opt_in' => ['nullable', 'boolean'],
            'register_account' => ['nullable', 'boolean'],
            'register_password' => ['nullable', 'string', 'min:8', 'confirmed'],
            'accept_terms' => ['accepted'],
        ], [
            'required' => __('ui.checkout.validation.required'),
            'customer_email.required' => __('ui.checkout.validation.customer_email_required'),
            'customer_email.email' => __('ui.checkout.validation.customer_email_invalid'),
            'customer_email.max' => __('ui.checkout.validation.customer_email_max'),
            'customer_phone.required' => __('ui.checkout.validation.customer_phone_required'),
            'customer_phone.regex' => __('ui.checkout.validation.customer_phone_invalid'),
            'customer_phone.max' => __('ui.checkout.validation.customer_phone_max'),
            'billing_state.required_if' => __('ui.checkout.validation.billing_state_required_hr'),
            'register_password.required' => __('ui.checkout.validation.register_password_required'),
            'register_password.min' => __('ui.checkout.validation.register_password_min'),
            'register_password.confirmed' => __('ui.checkout.validation.register_password_confirmed'),
            'accept_terms.accepted' => __('ui.checkout.validation.accept_terms'),
        ]);

        $shippingFromBilling = array_key_exists('ship_to_different_address', $validated)
            ? ! ((bool) $validated['ship_to_different_address'])
            : (bool) ($validated['use_billing_for_shipping'] ?? false);

        if ($shippingFromBilling) {
            $validated['shipping_first_name'] = $validated['billing_first_name'];
            $validated['shipping_last_name'] = $validated['billing_last_name'];
            $validated['shipping_company'] = $validated['billing_company'] ?? null;
            $validated['shipping_oib'] = $validated['billing_oib'] ?? null;
            $validated['shipping_vat_id'] = $validated['billing_vat_id'] ?? null;
            $validated['shipping_address_line_1'] = $validated['billing_address_line_1'];
            $validated['shipping_address_line_2'] = $validated['billing_address_line_2'] ?? null;
            $validated['shipping_postal_code'] = $validated['billing_postal_code'];
            $validated['shipping_city'] = $validated['billing_city'];
            $validated['shipping_state'] = $validated['billing_state'] ?? null;
            $validated['shipping_country_code'] = $validated['billing_country_code'];
        }

        $validated['customer_first_name'] = (string) ($validated['billing_first_name'] ?? $validated['customer_first_name'] ?? '');
        $validated['customer_last_name'] = (string) ($validated['billing_last_name'] ?? $validated['customer_last_name'] ?? '');

        $checkoutUser = $request->user();
        $registerAccount = (bool) ($validated['register_account'] ?? false);

        if (! $checkoutUser && $registerAccount) {
            $request->validate([
                'customer_email' => ['required', 'email', 'max:191', 'unique:users,email'],
                'register_password' => ['required', 'string', 'min:8', 'confirmed'],
            ], [
                'required' => __('ui.checkout.validation.required'),
                'customer_email.required' => __('ui.checkout.validation.customer_email_required'),
                'customer_email.email' => __('ui.checkout.validation.customer_email_invalid'),
                'customer_email.max' => __('ui.checkout.validation.customer_email_max'),
                'customer_email.unique' => __('ui.checkout.validation.customer_email_unique'),
                'register_password.required' => __('ui.checkout.validation.register_password_required'),
                'register_password.min' => __('ui.checkout.validation.register_password_min'),
                'register_password.confirmed' => __('ui.checkout.validation.register_password_confirmed'),
            ]);

            $checkoutUser = User::query()->create([
                'name' => trim(((string) $validated['customer_first_name']).' '.((string) $validated['customer_last_name'])),
                'email' => (string) $validated['customer_email'],
                'password' => Hash::make((string) $validated['register_password']),
            ]);

            Auth::login($checkoutUser);
            $request->setUserResolver(static fn () => $checkoutUser);
        }

        $order = $this->checkout->placeOrder($validated, $checkoutUser);
        $wspay = app(WSPayFormService::class);
        $corvus = app(CorvusPayFormService::class);
        $keks = app(KeksPayService::class);
        $isDeferredGateway = $wspay->isWspayCode((string) $order->payment_method_code)
            || $corvus->isCorvusCode((string) $order->payment_method_code)
            || $keks->isKeksCode((string) $order->payment_method_code);
        if (! $isDeferredGateway) {
            $this->notifications->sendOrderNotification($order);
        }

        $this->cart->clear();
        $request->session()->put('front.checkout.last_order_id', (int) $order->id);
        $request->session()->put('front.checkout.last_order_number', (string) $order->order_number);

        $this->syncCustomerData($request, $validated);

        $successUrl = route('checkout.success', ['orderNumber' => $order->order_number]);
        if ($wspay->isWspayCode((string) $order->payment_method_code)) {
            $successUrl = route('checkout.wspay.start', ['orderNumber' => $order->order_number]);
        } elseif ($corvus->isCorvusCode((string) $order->payment_method_code)) {
            $successUrl = route('checkout.corvus.start', ['orderNumber' => $order->order_number]);
        } elseif ($keks->isKeksCode((string) $order->payment_method_code)) {
            $successUrl = route('checkout.keks.start', ['orderNumber' => $order->order_number]);
        }

        if (
            $request->boolean('_ajax')
            || $request->expectsJson()
            || $request->wantsJson()
            || $request->ajax()
        ) {
            return response()->json([
                'redirect' => $successUrl,
                'status' => 'ok',
            ])->header('X-Checkout-Redirect', $successUrl);
        }

        return redirect()
            ->to($successUrl)
            ->with('status', 'Order created successfully.');
    }

    public function options(Request $request): JsonResponse
    {
        $summary = $this->cart->summary();
        $subtotal = (float) ($summary['subtotal_after_discount'] ?? $summary['subtotal'] ?? 0);

        $billingCountry = strtoupper((string) $request->query('billing_country_code', 'HR'));
        $billingState = (string) $request->query('billing_state', '');
        $billingPostal = (string) $request->query('billing_postal_code', '');

        $shipToDifferent = filter_var((string) $request->query('ship_to_different_address', '0'), FILTER_VALIDATE_BOOL);
        $shippingCountry = strtoupper((string) $request->query('shipping_country_code', $billingCountry));
        $shippingState = (string) $request->query('shipping_state', '');
        $shippingPostal = (string) $request->query('shipping_postal_code', '');

        if (!$shipToDifferent) {
            $shippingCountry = $billingCountry;
            $shippingState = $billingState;
            $shippingPostal = $billingPostal;
        }

        $shippingMethods = $this->checkout
            ->availableShippingMethods($subtotal, $shippingCountry, $shippingState, $shippingPostal)
            ->values();

        $paymentMethods = $this->checkout
            ->availablePaymentMethods($subtotal, $billingCountry, $billingState, $billingPostal)
            ->values();
        $selectedShippingCode = (string) $request->query('shipping_method_code', '');
        $selectedPaymentCode = (string) $request->query('payment_method_code', '');
        $totals = $this->checkout->estimateCheckoutTotals(
            (float) ($summary['subtotal'] ?? 0),
            (float) ($summary['discount_total'] ?? 0),
            $subtotal,
            (float) ($summary['tax_total'] ?? 0),
            $selectedShippingCode,
            $selectedPaymentCode,
            $shippingCountry,
            $shippingState,
            $shippingPostal,
            $billingCountry,
            $billingState,
            $billingPostal
        );

        return response()->json([
            'shipping_methods' => $shippingMethods->map(fn ($method) => [
                'code' => (string) $method->code,
                'name' => (string) $method->name,
                'price' => round((float) $method->price, 2),
                'price_formatted' => Currency::format((float) $method->price),
                'is_boxnow' => in_array(strtolower((string) $method->code), ['boxnow', 'box_now'], true),
                'boxnow_partner_id' => (string) ((is_array($method->settings ?? null) ? ($method->settings['boxnow_partner_id'] ?? '') : '') ?: ''),
            ])->all(),
            'payment_methods' => $paymentMethods->map(fn ($method) => [
                'code' => (string) $method->code,
                'name' => (string) $method->name,
            ])->all(),
            'totals' => [
                'subtotal' => round((float) $totals['subtotal'], 2),
                'discount_total' => round((float) $totals['discount_total'], 2),
                'tax_total' => round((float) $totals['tax_total'], 2),
                'shipping_total' => round((float) $totals['shipping_total'], 2),
                'payment_fee_total' => round((float) $totals['payment_fee_total'], 2),
                'grand_total' => round((float) $totals['grand_total'], 2),
                'shipping_method_code' => (string) ($totals['shipping_method_code'] ?? ''),
                'payment_method_code' => (string) ($totals['payment_method_code'] ?? ''),
                'subtotal_formatted' => Currency::format((float) $totals['subtotal']),
                'discount_total_formatted' => Currency::format((float) $totals['discount_total']),
                'tax_total_formatted' => Currency::format((float) $totals['tax_total']),
                'shipping_total_formatted' => Currency::format((float) $totals['shipping_total']),
                'payment_fee_total_formatted' => Currency::format((float) $totals['payment_fee_total']),
                'grand_total_formatted' => Currency::format((float) $totals['grand_total']),
            ],
        ]);
    }

    public function login(Request $request): RedirectResponse
    {
        $credentials = $request->validate([
            'email' => ['required', 'email'],
            'password' => ['required', 'string'],
            'remember' => ['nullable', 'boolean'],
        ]);

        if (! Auth::attempt([
            'email' => (string) $credentials['email'],
            'password' => (string) $credentials['password'],
        ], (bool) ($credentials['remember'] ?? false))) {
            throw ValidationException::withMessages([
                'email' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->route('checkout.create');
    }

    public function success(Request $request, string $orderNumber): View
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->with(['status', 'items', 'totals'])
            ->firstOrFail();

        $allowedBySession = ((int) $request->session()->get('front.checkout.last_order_id', 0)) === (int) $order->id;
        $allowedByUser = $request->user() && ((int) $request->user()->id === (int) $order->user_id);

        abort_unless($allowedBySession || $allowedByUser, 404);

        $bankTransfer = app(BankTransferUpiService::class)->ensureForOrder($order);

        return view($this->frontendView($request, 'checkout.success'), [
            'order' => $order,
            'bankTransfer' => $bankTransfer,
        ]);
    }

    public function successLatest(Request $request): RedirectResponse
    {
        $orderId = (int) $request->session()->get('front.checkout.last_order_id', 0);
        if ($orderId <= 0) {
            return redirect()->route('checkout.create');
        }

        $order = Order::query()->find($orderId);
        if (! $order) {
            return redirect()->route('checkout.create');
        }

        return redirect()->route('checkout.success', ['orderNumber' => $order->order_number]);
    }

    public function wspayStart(Request $request, string $orderNumber): View|RedirectResponse
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $allowedBySession = ((int) $request->session()->get('front.checkout.last_order_id', 0)) === (int) $order->id;
        $allowedByUser = $request->user() && ((int) $request->user()->id === (int) $order->user_id);

        abort_unless($allowedBySession || $allowedByUser, 404);

        $formData = app(WSPayFormService::class)->buildFormData($order);
        if (! is_array($formData)) {
            return redirect()
                ->route('checkout.success', ['orderNumber' => $order->order_number])
                ->with('status', __('ui.checkout.wspay.missing_config'));
        }

        return view($this->frontendView($request, 'checkout.wspay-redirect'), [
            'order' => $order,
            'formData' => $formData,
        ]);
    }

    public function wspayReturn(Request $request, string $orderNumber): RedirectResponse
    {
        return $this->handleWspayCallback($request, $orderNumber, 'return');
    }

    public function wspayError(Request $request, string $orderNumber): RedirectResponse
    {
        return $this->handleWspayCallback($request, $orderNumber, 'error');
    }

    public function wspayCancel(Request $request, string $orderNumber): RedirectResponse
    {
        return $this->handleWspayCallback($request, $orderNumber, 'cancel');
    }

    public function corvusStart(Request $request, string $orderNumber): View|RedirectResponse
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $allowedBySession = ((int) $request->session()->get('front.checkout.last_order_id', 0)) === (int) $order->id;
        $allowedByUser = $request->user() && ((int) $request->user()->id === (int) $order->user_id);
        abort_unless($allowedBySession || $allowedByUser, 404);

        $formData = app(CorvusPayFormService::class)->buildFormData($order);
        if (! is_array($formData)) {
            return redirect()
                ->route('checkout.success', ['orderNumber' => $order->order_number])
                ->with('status', __('ui.checkout.corvus.missing_config'));
        }

        return view($this->frontendView($request, 'checkout.corvus-redirect'), [
            'order' => $order,
            'formData' => $formData,
        ]);
    }

    public function corvusSuccess(Request $request, string $orderNumber): RedirectResponse
    {
        return $this->handleCorvusCallback($request, $orderNumber, 'success');
    }

    public function corvusCancel(Request $request, string $orderNumber): RedirectResponse
    {
        return $this->handleCorvusCallback($request, $orderNumber, 'cancel');
    }

    public function corvusSuccessStatic(Request $request): RedirectResponse
    {
        $orderNumber = $this->resolveCorvusOrderNumber($request);
        abort_unless($orderNumber !== '', 404);

        return $this->handleCorvusCallback($request, $orderNumber, 'success');
    }

    public function corvusCancelStatic(Request $request): RedirectResponse
    {
        $orderNumber = $this->resolveCorvusOrderNumber($request);
        abort_unless($orderNumber !== '', 404);

        return $this->handleCorvusCallback($request, $orderNumber, 'cancel');
    }

    public function keksStart(Request $request, string $orderNumber): View|RedirectResponse
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->with('items')
            ->firstOrFail();

        $allowedBySession = ((int) $request->session()->get('front.checkout.last_order_id', 0)) === (int) $order->id;
        $allowedByUser = $request->user() && ((int) $request->user()->id === (int) $order->user_id);
        abort_unless($allowedBySession || $allowedByUser, 404);

        $sellData = app(KeksPayService::class)->buildSellData($order);
        if (! is_array($sellData)) {
            return redirect()
                ->route('checkout.success', ['orderNumber' => $order->order_number])
                ->with('status', __('ui.checkout.keks.missing_config'));
        }

        return view($this->frontendView($request, 'checkout.keks-redirect'), [
            'order' => $order,
            'sellData' => $sellData,
        ]);
    }

    public function keksSuccess(Request $request): RedirectResponse
    {
        $orderNumber = trim((string) $request->query('bill_id', ''));
        if ($orderNumber === '') {
            $orderNumber = trim((string) $request->session()->get('front.checkout.last_order_number', ''));
        }
        abort_unless($orderNumber !== '', 404);

        return redirect()
            ->route('checkout.success', ['orderNumber' => $orderNumber])
            ->with('status', __('ui.checkout.keks.status.pending'));
    }

    public function keksFail(Request $request): RedirectResponse
    {
        $orderNumber = trim((string) $request->query('bill_id', ''));
        if ($orderNumber === '') {
            $orderNumber = trim((string) $request->session()->get('front.checkout.last_order_number', ''));
        }
        abort_unless($orderNumber !== '', 404);

        $order = Order::query()->where('order_number', $orderNumber)->with('items')->first();
        if ($order) {
            app(KeksPayService::class)->handleFailureEffects($order);
            $this->restoreCartFromOrder($order);
        }

        return redirect()
            ->route('cart.index')
            ->with('status', __('ui.checkout.keks.status.cancelled_to_cart'));
    }

    public function keksAdvice(Request $request): JsonResponse
    {
        $payload = $request->json()->all();
        if (! is_array($payload) || $payload === []) {
            $payload = $request->all();
        }

        $result = app(KeksPayService::class)->handleAdvice($payload, $request);
        $order = $result['order'] ?? null;
        if ($order instanceof Order && ((int) ($payload['status'] ?? -1) === 0)) {
            $this->sendKeksNotificationOnce($order);
        } elseif ($order instanceof Order) {
            app(KeksPayService::class)->handleFailureEffects($order);
        }

        return response()->json([
            'status' => (int) ($result['status'] ?? -1),
            'message' => (string) ($result['message'] ?? 'Rejected'),
        ]);
    }

    /**
     * @param array<string, mixed> $validated
     */
    private function syncCustomerData(Request $request, array $validated): void
    {
        $user = $request->user();
        if (! $user) {
            return;
        }

        $user->forceFill([
            'name' => trim(((string) $validated['customer_first_name']).' '.((string) $validated['customer_last_name'])),
            'email' => (string) $validated['customer_email'],
        ])->save();

        UserProfile::query()->updateOrCreate(
            ['user_id' => $user->id],
            [
                'first_name' => (string) $validated['customer_first_name'],
                'last_name' => (string) $validated['customer_last_name'],
                'phone' => (string) ($validated['customer_phone'] ?? ''),
                'newsletter_opt_in' => (bool) ($validated['newsletter_opt_in'] ?? false),
            ]
        );

        UserAddress::query()->updateOrCreate(
            ['user_id' => $user->id, 'type' => UserAddress::TYPE_BILLING],
            [
                'first_name' => (string) $validated['billing_first_name'],
                'last_name' => (string) $validated['billing_last_name'],
                'company' => (string) ($validated['billing_company'] ?? ''),
                'oib' => (string) ($validated['billing_oib'] ?? ''),
                'vat_id' => (string) ($validated['billing_vat_id'] ?? ''),
                'phone' => (string) ($validated['customer_phone'] ?? ''),
                'address_line_1' => (string) $validated['billing_address_line_1'],
                'address_line_2' => (string) ($validated['billing_address_line_2'] ?? ''),
                'postal_code' => (string) $validated['billing_postal_code'],
                'city' => (string) $validated['billing_city'],
                'state' => (string) ($validated['billing_state'] ?? ''),
                'country_code' => (string) $validated['billing_country_code'],
                'is_default' => true,
            ]
        );

        UserAddress::query()->updateOrCreate(
            ['user_id' => $user->id, 'type' => UserAddress::TYPE_SHIPPING],
            [
                'first_name' => (string) ($validated['shipping_first_name'] ?? ''),
                'last_name' => (string) ($validated['shipping_last_name'] ?? ''),
                'company' => (string) ($validated['shipping_company'] ?? ''),
                'oib' => (string) ($validated['shipping_oib'] ?? ''),
                'vat_id' => (string) ($validated['shipping_vat_id'] ?? ''),
                'phone' => (string) ($validated['customer_phone'] ?? ''),
                'address_line_1' => (string) ($validated['shipping_address_line_1'] ?? ''),
                'address_line_2' => (string) ($validated['shipping_address_line_2'] ?? ''),
                'postal_code' => (string) ($validated['shipping_postal_code'] ?? ''),
                'city' => (string) ($validated['shipping_city'] ?? ''),
                'state' => (string) ($validated['shipping_state'] ?? ''),
                'country_code' => (string) ($validated['shipping_country_code'] ?? 'HR'),
                'is_default' => true,
            ]
        );
    }

    private function handleWspayCallback(Request $request, string $orderNumber, string $context): RedirectResponse
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $wspay = app(WSPayFormService::class);
        $result = $wspay->handleCallback($order, $request->all(), $context);

        if (strtolower($context) === 'cancel' && ! (bool) ($result['paid'] ?? false)) {
            $wspay->handleCancellationEffects($order);
            $freshOrder = Order::query()->with('items')->find($order->id);
            if ($freshOrder) {
                $this->restoreCartFromOrder($freshOrder);
            }
        }

        if ((bool) ($result['paid'] ?? false)) {
            $freshOrder = Order::query()->find($order->id);
            if ($freshOrder) {
                $this->sendWspayNotificationOnce($freshOrder);
            }
        }

        $request->session()->put('front.checkout.last_order_id', (int) $order->id);

        $statusKey = match ((string) ($result['status'] ?? '')) {
            'approved' => 'ui.checkout.wspay.status.approved',
            'cancelled' => 'ui.checkout.wspay.status.cancelled',
            'error' => 'ui.checkout.wspay.status.error',
            'invalid_signature' => 'ui.checkout.wspay.status.invalid_signature',
            default => 'ui.checkout.wspay.status.declined',
        };

        if (strtolower($context) === 'cancel') {
            return redirect()
                ->route('cart.index')
                ->with('status', __('ui.checkout.wspay.status.cancelled_to_cart'));
        }

        return redirect()
            ->route('checkout.success', ['orderNumber' => $order->order_number])
            ->with('status', __($statusKey));
    }

    private function restoreCartFromOrder(Order $order): void
    {
        $lines = [];
        foreach ($order->items as $item) {
            $qty = max(0, (int) $item->quantity);
            if ($qty <= 0) {
                continue;
            }

            $productId = (int) ($item->product_id ?? 0);
            if ($productId <= 0) {
                continue;
            }

            $optionValueId = (int) ($item->product_option_value_id ?? 0);
            $lines[] = [
                'product_id' => $productId,
                'product_option_value_id' => $optionValueId > 0 ? $optionValueId : null,
                'quantity' => $qty,
            ];
        }

        $couponCode = (string) ($order->payload['coupon_code'] ?? '');
        $this->cart->replaceRaw($lines, $couponCode !== '' ? $couponCode : null);
    }

    private function sendWspayNotificationOnce(Order $order): void
    {
        $payload = is_array($order->payload) ? $order->payload : [];
        $wspayPayload = is_array($payload['wspay'] ?? null) ? $payload['wspay'] : [];
        if (! empty($wspayPayload['notification_sent_at'])) {
            return;
        }

        $this->notifications->sendOrderNotification($order);

        $wspayPayload['notification_sent_at'] = now()->toIso8601String();
        $payload['wspay'] = $wspayPayload;
        $order->forceFill(['payload' => $payload])->save();
    }

    private function handleCorvusCallback(Request $request, string $orderNumber, string $context): RedirectResponse
    {
        $order = Order::query()
            ->where('order_number', $orderNumber)
            ->firstOrFail();

        $corvus = app(CorvusPayFormService::class);
        $result = $corvus->handleCallback($order, $request->all(), $context);

        if (strtolower($context) === 'cancel' && ! (bool) ($result['paid'] ?? false)) {
            $corvus->handleCancellationEffects($order);
            $freshOrder = Order::query()->with('items')->find($order->id);
            if ($freshOrder) {
                $this->restoreCartFromOrder($freshOrder);
            }
        }

        if ((bool) ($result['paid'] ?? false)) {
            $freshOrder = Order::query()->find($order->id);
            if ($freshOrder) {
                $this->sendCorvusNotificationOnce($freshOrder);
            }
        }

        $request->session()->put('front.checkout.last_order_id', (int) $order->id);

        $statusKey = match ((string) ($result['status'] ?? '')) {
            'approved' => 'ui.checkout.corvus.status.approved',
            'cancelled' => 'ui.checkout.corvus.status.cancelled',
            'invalid_signature' => 'ui.checkout.corvus.status.invalid_signature',
            default => 'ui.checkout.corvus.status.declined',
        };

        if (strtolower($context) === 'cancel') {
            return redirect()
                ->route('cart.index')
                ->with('status', __('ui.checkout.corvus.status.cancelled_to_cart'));
        }

        return redirect()
            ->route('checkout.success', ['orderNumber' => $order->order_number])
            ->with('status', __($statusKey));
    }

    private function sendCorvusNotificationOnce(Order $order): void
    {
        $payload = is_array($order->payload) ? $order->payload : [];
        $corvusPayload = is_array($payload['corvuspay'] ?? null) ? $payload['corvuspay'] : [];
        if (! empty($corvusPayload['notification_sent_at'])) {
            return;
        }

        $this->notifications->sendOrderNotification($order);

        $corvusPayload['notification_sent_at'] = now()->toIso8601String();
        $payload['corvuspay'] = $corvusPayload;
        $order->forceFill(['payload' => $payload])->save();
    }

    private function sendKeksNotificationOnce(Order $order): void
    {
        $payload = is_array($order->payload) ? $order->payload : [];
        $keksPayload = is_array($payload['kekspay'] ?? null) ? $payload['kekspay'] : [];
        if (! empty($keksPayload['notification_sent_at'])) {
            return;
        }

        $this->notifications->sendOrderNotification($order);

        $keksPayload['notification_sent_at'] = now()->toIso8601String();
        $payload['kekspay'] = $keksPayload;
        $order->forceFill(['payload' => $payload])->save();
    }

    private function resolveCorvusOrderNumber(Request $request): string
    {
        return trim((string) ($request->input('order_number', '')));
    }
}
