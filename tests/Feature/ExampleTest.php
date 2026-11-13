<?php

declare(strict_types=1);

namespace Tests\Feature;

use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_the_home_page_redirects_to_the_review_page(): void
    {
        $this->get('/')->assertRedirect('/admin/reviews');
    }

    public function test_a_guest_following_the_redirect_lands_on_the_login_page(): void
    {
        $this->followingRedirects()
            ->get('/')
            ->assertOk()
            ->assertSee('Log in');
    }
}
