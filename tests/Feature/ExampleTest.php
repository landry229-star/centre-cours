<?php

namespace Tests\Feature;

use App\Models\User;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    public function test_homepage_displays_the_centre_identity_and_a_login_link(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee(config('centre.name'))
            ->assertSee('Toute la vie scolaire')
            ->assertSee(route('login'));
    }

    public function test_authenticated_homepage_links_to_the_users_dashboard(): void
    {
        $this->actingAs(User::factory()->admin()->create())
            ->get('/')
            ->assertSee('Mon espace')
            ->assertSee(route('dashboard'));
    }
}
