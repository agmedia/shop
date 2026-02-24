<?php

namespace Tests\Feature\Front;

use Tests\TestCase;

class StorefrontDeviceTemplateTest extends TestCase
{
    public function test_desktop_user_agent_gets_desktop_storefront_template(): void
    {
        $response = $this
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Macintosh; Intel Mac OS X 10_15_7) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/122.0.0.0 Safari/537.36',
            ])
            ->get('/');

        $response->assertOk();
        $response->assertSee('front-theme/scripts/desktop-header-menu.js', false);
        $response->assertDontSee('front-theme/styles/bootstrap.css', false);
    }

    public function test_mobile_user_agent_gets_mobile_storefront_template(): void
    {
        $response = $this
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            ])
            ->get('/');

        $response->assertOk();
        $response->assertSee('front-theme/styles/bootstrap.css', false);
        $response->assertDontSee('front-theme/scripts/desktop-header-menu.js', false);
    }

    public function test_storefront_responses_include_vary_user_agent_header(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertHeader('Vary');
        $this->assertStringContainsStringIgnoringCase('User-Agent', (string) $response->headers->get('Vary'));
    }

    public function test_mobile_user_agent_gets_desktop_template_when_mobile_pwa_feature_is_disabled(): void
    {
        config(['catalog_features.flags.catalog_use_mobile_pwa' => false]);

        $response = $this
            ->withHeaders([
                'User-Agent' => 'Mozilla/5.0 (iPhone; CPU iPhone OS 17_0 like Mac OS X) AppleWebKit/605.1.15 (KHTML, like Gecko) Version/17.0 Mobile/15E148 Safari/604.1',
            ])
            ->get('/');

        $response->assertOk();
        $response->assertSee('front-theme/scripts/desktop-header-menu.js', false);
        $response->assertDontSee('front-theme/styles/bootstrap.css', false);
    }
}
