<?php

namespace App\Http\Controllers\Front;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Front\Concerns\ResolvesFrontendView;
use App\Models\Content\Support\ContactMessage;
use App\Services\Front\StoreNotificationService;
use App\Services\Front\StoreSettingsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class ReturnRequestController extends Controller
{
    use ResolvesFrontendView;

    public function __construct(
        private readonly StoreNotificationService $notifications,
        private readonly StoreSettingsService $storeSettings
    ) {}

    public function create(Request $request): View
    {
        if ($request->boolean('withdrawal')) {
            return app(ContractWithdrawalController::class)->create($request);
        }

        return view($this->frontendView($request, 'returns.create'));
    }

    public function store(Request $request): RedirectResponse
    {
        $captchaSettings = $this->storeSettings->captcha();
        $captchaEnabled = (bool) ($captchaSettings['recaptcha_v3_enabled'] ?? false)
            && trim((string) ($captchaSettings['recaptcha_v3_site_key'] ?? '')) !== ''
            && trim((string) ($captchaSettings['recaptcha_v3_secret_key'] ?? '')) !== '';

        $validated = $request->validate(
            [
                'email' => ['required', 'email', 'max:191'],
                'order_number' => ['required', 'string', 'max:80'],
                'return_items' => ['required', 'string', 'min:2', 'max:5000'],
                'note' => ['nullable', 'string', 'max:8000'],
                'recaptcha_token' => [$captchaEnabled ? 'required' : 'nullable', 'string', 'max:4096'],
            ],
            [
                'required' => __('return_request.validation.required'),
                'email' => __('return_request.validation.email'),
                'min.string' => __('return_request.validation.min_string'),
                'max.string' => __('return_request.validation.max_string'),
            ],
            [
                'email' => __('return_request.form.email'),
                'order_number' => __('return_request.form.order_number'),
                'return_items' => __('return_request.form.return_items'),
                'note' => __('return_request.form.note'),
                'recaptcha_token' => __('return_request.validation.security_check'),
            ]
        );

        if ($captchaEnabled) {
            $this->assertRecaptchaIsValid(
                token: (string) ($validated['recaptcha_token'] ?? ''),
                secret: (string) $captchaSettings['recaptcha_v3_secret_key'],
                minScore: (float) ($captchaSettings['recaptcha_v3_min_score'] ?? 0.5),
                expectedAction: 'return_request_form',
                ip: (string) $request->ip()
            );
        }

        $orderNumber = trim((string) $validated['order_number']);
        $returnItems = trim((string) $validated['return_items']);
        $note = trim((string) ($validated['note'] ?? ''));

        $message = ContactMessage::query()->create([
            'user_id' => $request->user()?->id,
            'name' => (string) $validated['email'],
            'email' => (string) $validated['email'],
            'phone' => null,
            'subject' => __('return_request.mail.subject', ['order' => $orderNumber]),
            'message' => $this->messageBody(
                email: (string) $validated['email'],
                orderNumber: $orderNumber,
                returnItems: $returnItems,
                note: $note
            ),
            'status' => ContactMessage::STATUS_NEW,
            'ip_address' => $request->ip(),
            'user_agent' => (string) $request->userAgent(),
            'payload' => [
                'type' => 'return_request',
                'locale' => app()->getLocale(),
                'url' => $request->fullUrl(),
                'return_request' => [
                    'order_number' => $orderNumber,
                    'return_items' => $returnItems,
                    'note' => $note,
                ],
            ],
        ]);

        $this->notifications->sendReturnRequestNotification($message);

        return redirect()
            ->route('returns.create', ['returnRequestSlug' => __('return_request.slug')])
            ->with('status', __('return_request.sent_status'));
    }

    private function messageBody(string $email, string $orderNumber, string $returnItems, string $note): string
    {
        return implode("\n", [
            __('return_request.mail.email').': '.$email,
            __('return_request.mail.order_number').': '.$orderNumber,
            '',
            __('return_request.mail.return_items').':',
            $returnItems,
            '',
            __('return_request.mail.note').':',
            $note !== '' ? $note : '-',
        ]);
    }

    private function assertRecaptchaIsValid(
        string $token,
        string $secret,
        float $minScore,
        string $expectedAction,
        string $ip
    ): void {
        $minScore = max(0.0, min(1.0, $minScore));
        try {
            $response = Http::asForm()
                ->timeout(8)
                ->post('https://www.google.com/recaptcha/api/siteverify', [
                    'secret' => $secret,
                    'response' => $token,
                    'remoteip' => $ip,
                ]);
        } catch (\Throwable) {
            throw ValidationException::withMessages([
                'recaptcha_token' => __('return_request.captcha_failed'),
            ]);
        }

        if (! $response->ok()) {
            throw ValidationException::withMessages([
                'recaptcha_token' => __('return_request.captcha_failed'),
            ]);
        }

        $json = $response->json();
        $success = (bool) ($json['success'] ?? false);
        $score = (float) ($json['score'] ?? 0.0);
        $action = (string) ($json['action'] ?? '');

        if (! $success || $score < $minScore || ($action !== '' && $action !== $expectedAction)) {
            throw ValidationException::withMessages([
                'recaptcha_token' => __('return_request.captcha_failed'),
            ]);
        }
    }
}
