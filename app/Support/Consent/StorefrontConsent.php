<?php

namespace App\Support\Consent;

use App\Models\Sales\Order\Order;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Throwable;

final class StorefrontConsent
{
    // Keep these in sync with the CookieConsent v3 configuration.
    private const REVISION = 0;

    private const LIFETIME_DAYS = 182;

    /** @return array{marketing_consent: bool, marketing_consent_at: ?string} */
    public static function fromRequest(Request $request): array
    {
        $denied = ['marketing_consent' => false, 'marketing_consent_at' => null];
        $raw = $request->cookie('cc_cookie');
        if (! is_string($raw) || strlen($raw) > 16384) {
            return $denied;
        }

        try {
            // PHP normally URL-decodes Cookie headers already; raw decoding also
            // supports environments that preserve CookieConsent's encoded value.
            $cookie = json_decode(str_starts_with($raw, '%7B') ? rawurldecode($raw) : $raw, true, 16, JSON_THROW_ON_ERROR);
            if (! is_array($cookie)
                || ($cookie['revision'] ?? null) !== self::REVISION
                || ! is_string($cookie['consentId'] ?? null) || trim($cookie['consentId']) === ''
                || ! is_array($cookie['categories'] ?? null)
                || ! array_is_list($cookie['categories'])
                || ! in_array('necessary', $cookie['categories'], true)
                || ! in_array('marketing', $cookie['categories'], true)) {
                return $denied;
            }

            $first = self::timestamp($cookie['consentTimestamp'] ?? null);
            $last = self::timestamp($cookie['lastConsentTimestamp'] ?? null);
            $expiry = $cookie['expirationTime'] ?? null;
            $now = CarbonImmutable::now();
            if (! $first || ! $last || $first->greaterThan($last) || $last->greaterThan($now)
                || $last->lessThanOrEqualTo($now->subDays(self::LIFETIME_DAYS))
                || ! is_int($expiry) || $expiry <= $now->getTimestampMs()
                || $expiry > $last->addDays(self::LIFETIME_DAYS)->getTimestampMs() + 1000) {
                return $denied;
            }

            return ['marketing_consent' => true, 'marketing_consent_at' => $last->toIso8601String()];
        } catch (Throwable) {
            return $denied;
        }
    }

    public static function orderAllowsMarketing(Order $order): bool
    {
        if (data_get($order->payload, 'privacy.marketing_consent') !== true || ! $order->placed_at) {
            return false;
        }

        $consentAt = self::timestamp(data_get($order->payload, 'privacy.marketing_consent_at'));
        if (! $consentAt) {
            return false;
        }

        // Historical sync needs evidence that was valid when the order was
        // placed; it must never infer opt-in from an email or newsletter signup.
        return $consentAt->lessThanOrEqualTo($order->placed_at)
            && $consentAt->greaterThan($order->placed_at->toImmutable()->subDays(self::LIFETIME_DAYS));
    }

    private static function timestamp(mixed $value): ?CarbonImmutable
    {
        if (! is_string($value) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $value)) {
            return null;
        }

        try {
            $parts = date_parse($value);
            if ($parts['warning_count'] || $parts['error_count']) {
                return null;
            }

            return CarbonImmutable::parse($value);
        } catch (Throwable) {
            return null;
        }
    }
}
