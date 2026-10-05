<?php

namespace Tests\Feature;

use Tests\TestCase;

class PrivacyPolicyTest extends TestCase
{
    public function test_privacy_policy_page_is_accessible_to_guests(): void
    {
        $response = $this->get(route('privacy.policy'));

        $response->assertOk();
        $response->assertSee('Privacy Policy');
        $response->assertSee('frabbi727@gmail.com');
        $response->assertSee('Google Firebase Cloud Messaging');
    }

    public function test_privacy_url_redirects_to_privacy_policy(): void
    {
        $response = $this->get('/privacy');

        $response->assertRedirect(route('privacy.policy'));
    }

    public function test_login_page_contains_link_to_privacy_policy(): void
    {
        $response = $this->get(route('login'));

        $response->assertOk();
        $response->assertSee(route('privacy.policy'));
    }
}
