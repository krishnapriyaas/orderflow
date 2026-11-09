<?php

declare(strict_types=1);

namespace Tests\Feature\Cart;

use App\Models\Cart;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class CreateCartTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_an_empty_cart_with_a_uuid_token(): void
    {
        $response = $this->postJson('/api/carts');

        $response->assertCreated()
            ->assertJsonPath('data.items', [])
            ->assertJsonPath('data.total_cents', 0);

        $token = $response->json('data.token');
        $this->assertIsString($token);
        $this->assertTrue(Str::isUuid($token));
        $this->assertDatabaseHas('carts', ['token' => $token]);
        $this->assertSame(1, Cart::query()->count());
    }

    public function test_it_does_not_expose_the_internal_id(): void
    {
        $this->postJson('/api/carts')
            ->assertCreated()
            ->assertJsonMissingPath('data.id');
    }
}
