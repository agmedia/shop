<?php

namespace Tests\Unit\Support;

use App\Support\Consent\StorefrontConsent;
use Illuminate\Http\Request;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class StorefrontConsentTest extends TestCase
{
    public function test_valid_marketing_cookie_records_only_boolean_and_consent_time(): void
    {
        $this->freezeTime();
        $cookie = $this->validCookie();
        $request = Request::create('/checkout', 'POST');
        $request->cookies->set('cc_cookie', json_encode($cookie));

        $this->assertSame([
            'marketing_consent' => true,
            'marketing_consent_at' => now()->subMinute()->toIso8601String(),
        ], StorefrontConsent::fromRequest($request));

        $request->cookies->set('cc_cookie', rawurlencode(json_encode($cookie)));
        $this->assertTrue(StorefrontConsent::fromRequest($request)['marketing_consent']);
    }

    #[DataProvider('invalidCookies')]
    public function test_missing_denied_malformed_or_expired_cookie_defaults_to_denied(string $case): void
    {
        $this->freezeTime();
        $cookie = $this->validCookie();
        switch ($case) {
            case 'missing': $cookie = null;
                break;
            case 'malformed': $cookie = '{invalid';
                break;
            case 'no marketing': $cookie['categories'] = ['necessary', 'analytics'];
                break;
            case 'no necessary': $cookie['categories'] = ['marketing'];
                break;
            case 'wrong revision': $cookie['revision'] = 1;
                break;
            case 'string revision': $cookie['revision'] = '0';
                break;
            case 'missing id': unset($cookie['consentId']);
                break;
            case 'missing time': unset($cookie['lastConsentTimestamp']);
                break;
            case 'invalid time': $cookie['lastConsentTimestamp'] = '2026-02-30T10:00:00Z';
                break;
            case 'future time': $cookie['lastConsentTimestamp'] = now()->addDay()->toIso8601String();
                break;
            case 'inverted time': $cookie['consentTimestamp'] = now()->toIso8601String();
                break;
            case 'expired': $cookie['expirationTime'] = now()->subSecond()->getTimestampMs();
                break;
            case 'missing expiry': unset($cookie['expirationTime']);
                break;
            case 'forged long expiry': $cookie['expirationTime'] = now()->addYear()->getTimestampMs();
                break;
            case 'expired age':
                $cookie['consentTimestamp'] = now()->subDays(184)->toIso8601String();
                $cookie['lastConsentTimestamp'] = now()->subDays(183)->toIso8601String();
                break;
        }

        $request = Request::create('/checkout', 'POST', ['marketing_consent' => true]);
        if ($cookie !== null) {
            $request->cookies->set('cc_cookie', is_string($cookie) ? $cookie : json_encode($cookie));
        }

        $this->assertSame(['marketing_consent' => false, 'marketing_consent_at' => null], StorefrontConsent::fromRequest($request));
    }

    public static function invalidCookies(): array
    {
        return array_map(fn ($case) => [$case], [
            'missing', 'malformed', 'no marketing', 'no necessary', 'wrong revision',
            'string revision', 'missing id', 'missing time', 'invalid time', 'future time',
            'inverted time', 'expired', 'missing expiry', 'forged long expiry', 'expired age',
        ]);
    }

    private function validCookie(): array
    {
        return [
            'categories' => ['necessary', 'analytics', 'marketing'],
            'revision' => 0,
            'consentId' => 'not-persisted-client-id',
            'consentTimestamp' => now()->subDay()->toIso8601String(),
            'lastConsentTimestamp' => now()->subMinute()->toIso8601String(),
            'expirationTime' => now()->subMinute()->addDays(182)->getTimestampMs(),
            'services' => ['necessary' => [], 'analytics' => [], 'marketing' => []],
        ];
    }
}
