<?php

use App\Models\Country;
use App\Models\User;
use Illuminate\Support\Facades\Hash;

function createCountryForApiTest(array $attributes = []): Country
{
    return Country::create(array_merge([
        'flag_image' => 'https://example.com/flag.svg',
        'name' => 'Test Country',
        'category' => 'Residence',
        'minimum_investment' => '100000.00',
        'key_benefit' => 'Test benefit',
        'processing_time' => '4-9 months',
    ], $attributes));
}

it('lists countries as JSON', function () {
    $country = createCountryForApiTest();

    $this->getJson('/api/countries')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $country->id,
            'name' => 'Test Country',
            'minimum_investment' => '100000.00',
        ]);
});

it('filters countries by category', function () {
    $residence = createCountryForApiTest(['name' => 'Residence Example']);
    createCountryForApiTest([
        'name' => 'Citizenship Example',
        'category' => 'Citizenship',
    ]);

    $this->getJson('/api/countries?category=Residence')
        ->assertOk()
        ->assertJsonCount(1)
        ->assertJsonFragment(['id' => $residence->id]);
});

it('rejects an unsupported category filter', function () {
    $this->getJson('/api/countries?category=Other')
        ->assertUnprocessable()
        ->assertJsonValidationErrors('category');
});

it('returns a country by id', function () {
    $country = createCountryForApiTest([
        'benefits' => 'First benefit'."\n".'Second benefit',
    ]);

    $this->getJson("/api/countries/{$country->id}")
        ->assertOk()
        ->assertJsonPath('id', $country->id)
        ->assertJsonPath('benefits', "First benefit\nSecond benefit");
});

it('returns not found for an unknown country id', function () {
    $this->getJson('/api/countries/999999')->assertNotFound();
});

it('requires authentication to create a country', function () {
    $this->postJson('/api/countries', [
        'flag_image' => 'https://example.com/test-flag.svg',
        'name' => 'API Test Country',
        'category' => 'Citizenship',
        'minimum_investment' => '125000.50',
        'key_benefit' => 'Test benefit',
        'processing_time' => '4-9 months',
    ])->assertUnauthorized();

    $this->assertDatabaseCount('countries', 0);
});

it('creates a country for an authenticated administrator', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->postJson('/api/countries', [
        'flag_image' => 'https://example.com/test-flag.svg',
        'name' => 'API Test Country',
        'category' => 'Citizenship',
        'minimum_investment' => 'USD 125,000.50',
        'key_benefit' => 'Test benefit',
        'processing_time' => '4-9 months',
        'details_intro' => 'Explore the program details.',
        'benefits' => "First benefit\nSecond benefit",
        'requirements' => 'Test requirements',
        'steps' => 'First step',
    ])
        ->assertCreated()
        ->assertHeader('Location', route('api.countries.show', 1))
        ->assertJsonPath('name', 'API Test Country')
        ->assertJsonPath('category', 'Citizenship')
        ->assertJsonPath('minimum_investment', 'USD 125,000.50')
        ->assertJsonPath('details_intro', 'Explore the program details.')
        ->assertJsonPath('benefits', "First benefit\nSecond benefit");

    $this->assertDatabaseHas('countries', [
        'name' => 'API Test Country',
        'minimum_investment' => 'USD 125,000.50',
        'details_intro' => 'Explore the program details.',
    ]);
});

it('validates country fields when creating a country', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->postJson('/api/countries', [
        'flag_image' => 'https://example.com/test-flag.svg',
        'name' => str_repeat('x', 101),
        'category' => 'Other',
        'minimum_investment' => str_repeat('x', 256),
        'key_benefit' => 'Test benefit',
        'processing_time' => '4-9 months',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'name',
            'category',
            'minimum_investment',
        ]);
});

it('updates and deletes countries for authenticated users', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $country = createCountryForApiTest();
    $countryData = [
        'flag_image' => 'https://example.com/updated-flag.svg',
        'name' => 'Updated Country',
        'category' => 'Citizenship',
        'minimum_investment' => '250000.00',
        'key_benefit' => 'Updated benefit',
        'processing_time' => '3-6 months',
        'details_intro' => 'Updated introduction.',
        'benefits' => 'Updated benefits',
        'requirements' => 'Updated requirements',
        'steps' => 'Updated steps',
    ];

    $this->putJson("/api/countries/{$country->id}", $countryData)
        ->assertOk()
        ->assertJsonPath('name', 'Updated Country')
        ->assertJsonPath('category', 'Citizenship')
        ->assertJsonPath('details_intro', 'Updated introduction.');
    $this->assertDatabaseHas('countries', [
        'id' => $country->id,
        'name' => 'Updated Country',
        'details_intro' => 'Updated introduction.',
    ]);

    $this->deleteJson("/api/countries/{$country->id}")->assertNoContent();
    $this->assertDatabaseMissing('countries', ['id' => $country->id]);
});

it('returns the signed-in user from the session endpoint', function () {
    $user = User::factory()->create(['is_admin' => true]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])
        ->assertOk()
        ->assertJsonPath('user.id', $user->id)
        ->assertJsonMissingPath('user.password');

    $this->getJson('/api/auth/me')
        ->assertOk()
        ->assertJsonPath('user.email', $user->email);

    $this->postJson('/api/auth/logout')->assertNoContent();
    $this->getJson('/api/auth/me')->assertUnauthorized();
});

it('rejects invalid credentials at the session endpoint', function () {
    $user = User::factory()->create();

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'incorrect-password',
    ])
        ->assertUnprocessable()
        ->assertJsonPath('errors.email.0', 'Email or password is incorrect.');

    $this->getJson('/api/auth/me')->assertUnauthorized();
});

it('registers a regular user and returns safe user details', function () {
    $response = $this->postJson('/api/auth/register', [
        'name' => 'Postman User',
        'email' => 'postman@example.com',
        'password' => 'A-strong-password-2026',
        'password_confirmation' => 'A-strong-password-2026',
    ]);

    $response
        ->assertCreated()
        ->assertJsonPath('user.name', 'Postman User')
        ->assertJsonPath('user.email', 'postman@example.com')
        ->assertJsonMissingPath('user.password')
        ->assertJsonMissingPath('user.is_admin');

    $this->assertDatabaseHas('users', [
        'email' => 'postman@example.com',
        'is_admin' => false,
    ]);
    expect(Hash::check(
        'A-strong-password-2026',
        User::where('email', 'postman@example.com')->value('password'),
    ))->toBeTrue();
});

it('validates API user registration fields', function () {
    $this->postJson('/api/auth/register', [
        'name' => 'Postman User',
        'email' => 'not-an-email',
        'password' => 'password',
        'password_confirmation' => 'different-password',
    ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'password']);
});

it('does not allow non-admin users to sign in to the admin session', function () {
    $user = User::factory()->create(['is_admin' => false]);

    $this->postJson('/api/auth/login', [
        'email' => $user->email,
        'password' => 'password',
    ])->assertForbidden();

    $this->getJson('/api/auth/me')->assertUnauthorized();
    $this->postJson('/api/countries', [])->assertUnauthorized();
});

it('denies admin endpoints to an authenticated non-admin user', function () {
    $this->actingAs(User::factory()->create(['is_admin' => false]));

    $this->getJson('/api/auth/me')->assertForbidden();
    $this->getJson('/api/users')->assertForbidden();
    $this->postJson('/api/countries', [])->assertForbidden();
});

it('lists users without exposing passwords to administrators', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $user = User::factory()->create([
        'name' => 'Listed User',
        'email' => 'listed@example.com',
        'is_admin' => false,
    ]);

    $this->getJson('/api/users')
        ->assertOk()
        ->assertJsonPath('data.0.id', 1)
        ->assertJsonFragment([
            'id' => $user->id,
            'name' => 'Listed User',
            'email' => 'listed@example.com',
        ])
        ->assertJsonMissingPath('data.0.password');
});

it('lists admin accounts separately from regular users', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));
    $admin = User::factory()->create(['is_admin' => true, 'email' => 'another-admin@example.com']);
    User::factory()->create(['is_admin' => false, 'email' => 'regular-user@example.com']);

    $this->getJson('/api/admins')
        ->assertOk()
        ->assertJsonFragment(['id' => $admin->id, 'email' => 'another-admin@example.com', 'is_admin' => true])
        ->assertJsonMissing(['email' => 'regular-user@example.com']);
});

it('requires an administrator to manage admin accounts', function () {
    $this->getJson('/api/admins')->assertUnauthorized();
    $this->postJson('/api/admins', [])->assertUnauthorized();
});

it('allows administrators to create, update, and remove other admin accounts', function () {
    $currentAdmin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($currentAdmin);

    $createResponse = $this->postJson('/api/admins', [
        'name' => 'New Admin',
        'email' => 'new-admin@example.com',
        'password' => 'a-strong-password-123',
        'password_confirmation' => 'a-strong-password-123',
    ]);

    $createResponse
        ->assertCreated()
        ->assertJsonPath('user.name', 'New Admin')
        ->assertJsonPath('user.is_admin', true)
        ->assertJsonMissingPath('user.password');
    $newAdminId = $createResponse->json('user.id');
    expect(Hash::check(
        'a-strong-password-123',
        User::findOrFail($newAdminId)->password,
    ))->toBeTrue();

    $this->putJson("/api/admins/{$newAdminId}", [
        'name' => 'Updated Admin',
        'email' => 'updated-admin@example.com',
    ])
        ->assertOk()
        ->assertJsonPath('user.name', 'Updated Admin')
        ->assertJsonPath('user.email', 'updated-admin@example.com');

    $this->getJson('/api/admins')
        ->assertOk()
        ->assertJsonFragment(['email' => 'updated-admin@example.com'])
        ->assertJsonMissingPath('data.0.password');

    $this->deleteJson("/api/admins/{$newAdminId}")->assertNoContent();
    $this->assertDatabaseMissing('users', ['id' => $newAdminId]);
});

it('prevents deleting the current or last admin account', function () {
    $admin = User::factory()->create(['is_admin' => true]);
    $this->actingAs($admin);

    $this->deleteJson("/api/admins/{$admin->id}")
        ->assertUnprocessable()
        ->assertJsonPath('message', 'You cannot remove your own admin account.');
    $this->assertDatabaseHas('users', ['id' => $admin->id]);

    $this->putJson("/api/admins/{$admin->id}", [
        'name' => $admin->name,
        'email' => $admin->email,
    ])->assertOk();
});

it('rejects weak passwords when creating admin accounts', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]));

    $this->postJson('/api/admins', [
        'name' => 'Weak Password Admin',
        'email' => 'weak-admin@example.com',
        'password' => 'short',
        'password_confirmation' => 'short',
    ])->assertUnprocessable()->assertJsonValidationErrors('password');
});

it('allows the configured frontend origin to call the API', function () {
    config(['cors.allowed_origins' => ['http://localhost:5173']]);

    $this->getJson('/api/countries', ['Origin' => 'http://localhost:5173'])
        ->assertOk()
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
        ->assertHeader('Access-Control-Allow-Credentials', 'true');
});

it('allows the frontend CSRF header in credentialed preflight requests', function () {
    config(['cors.allowed_origins' => ['http://127.0.0.1:5173']]);

    $this->call('OPTIONS', '/api/auth/login', [], [], [], [
        'HTTP_ORIGIN' => 'http://127.0.0.1:5173',
        'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
        'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'content-type,x-csrf-token',
    ])
        ->assertNoContent()
        ->assertHeader('Access-Control-Allow-Origin', 'http://127.0.0.1:5173')
        ->assertHeader('Access-Control-Allow-Credentials', 'true')
        ->assertHeader(
            'Access-Control-Allow-Headers',
            'accept, content-type, x-requested-with, x-csrf-token, x-xsrf-token',
        );
});

it('issues a CSRF cookie to the configured frontend', function () {
    $this->get('/api/auth/csrf', ['Origin' => 'http://localhost:5173'])
        ->assertOk()
        ->assertJsonStructure(['csrf_token'])
        ->assertCookie('XSRF-TOKEN')
        ->assertHeader('Access-Control-Allow-Origin', 'http://localhost:5173')
        ->assertHeader('Access-Control-Allow-Credentials', 'true');
});
