<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class LegalPagesTest extends TestCase
{
    use RefreshDatabase;

    public function test_privacy_policy_page_is_public(): void
    {
        $this->get(route('legal.privacy'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Legal/PrivacyPolicy')
            );
    }

    public function test_terms_of_service_page_is_public(): void
    {
        $this->get(route('legal.terms'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Legal/TermsOfService')
            );
    }
}
