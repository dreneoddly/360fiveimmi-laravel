<?php

use App\Models\Service;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

function createServiceForApiTest(array $attributes = []): Service
{
    return Service::create(array_merge([
        'slug' => 'test-service',
        'title' => 'Test Service',
        'text' => 'A short service summary.',
        'description' => 'A service description.',
        'image' => 'https://example.com/service.jpg',
        'detail_image' => 'https://example.com/service-detail.jpg',
        'paragraphs' => ['First paragraph.', 'Second paragraph.'],
        'benefits' => ['First benefit.', 'Second benefit.'],
    ], $attributes));
}

function fakeServiceImage(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        base64_decode('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs='),
    );
}

it('lists services as JSON', function () {
    $service = createServiceForApiTest();

    $this->getJson('/api/services')
        ->assertOk()
        ->assertJsonFragment([
            'id' => $service->id,
            'slug' => 'test-service',
            'title' => 'Test Service',
            'paragraphs' => ['First paragraph.', 'Second paragraph.'],
            'benefits' => ['First benefit.', 'Second benefit.'],
        ]);
});

it('returns a service by slug', function () {
    $service = createServiceForApiTest();

    $this->getJson('/api/services/test-service')
        ->assertOk()
        ->assertJsonPath('id', $service->id)
        ->assertJsonPath('slug', 'test-service')
        ->assertJsonPath('benefits.0', 'First benefit.');
});

it('returns not found for an unknown service slug', function () {
    $this->getJson('/api/services/unknown-service')->assertNotFound();
});

it('creates a service with valid data in the local environment', function () {
    Storage::fake('public');

    $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->post('/api/services', [
            'slug' => 'real-estate',
            'title' => 'Real Estate',
            'text' => 'Real estate service summary.',
            'description' => 'Real estate service description.',
            'image_file' => fakeServiceImage('real-estate.gif'),
            'detail_image_file' => fakeServiceImage('real-estate-detail.gif'),
            'paragraphs' => ['First paragraph.', 'Second paragraph.'],
            'benefits' => ['First benefit.', 'Second benefit.'],
        ])
        ->assertCreated()
        ->assertHeader('Location', route('api.services.show', 'real-estate'))
        ->assertJsonPath('slug', 'real-estate')
        ->assertJsonPath('paragraphs.1', 'Second paragraph.')
        ->assertJsonPath('benefits.0', 'First benefit.');
    expect($response->json('image'))->toContain('/storage/service-images/');
    expect($response->json('detail_image'))->toContain('/storage/service-images/');

    $service = Service::where('slug', 'real-estate')->firstOrFail();
    Storage::disk('public')->assertExists($service->getRawOriginal('image'));
    Storage::disk('public')->assertExists($service->getRawOriginal('detail_image'));

    $this->assertDatabaseHas('services', [
        'slug' => 'real-estate',
        'title' => 'Real Estate',
    ]);
});

it('validates required service fields and array content', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->postJson('/api/services', [
            'slug' => 'invalid slug',
            'title' => 'Missing details',
            'paragraphs' => 'Paragraphs must be an array.',
            'benefits' => [],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'slug',
            'text',
            'description',
            'image_file',
            'detail_image_file',
            'paragraphs',
            'benefits',
        ]);
});

it('rejects a duplicate service slug', function () {
    Storage::fake('public');
    createServiceForApiTest();

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->withHeaders(['Accept' => 'application/json'])
        ->post('/api/services', [
            'slug' => 'test-service',
            'title' => 'Duplicate Service',
            'text' => 'A short service summary.',
            'description' => 'A service description.',
            'image_file' => fakeServiceImage('service.gif'),
            'detail_image_file' => fakeServiceImage('service-detail.gif'),
            'paragraphs' => ['A paragraph.'],
            'benefits' => ['A benefit.'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('slug');
});

it('requires an admin account to create a service', function () {
    $this->postJson('/api/services', [])->assertUnauthorized();

    $this->assertDatabaseCount('services', 0);
});

it('updates a service including its slug', function () {
    $service = createServiceForApiTest();

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->putJson('/api/services/test-service', [
            'slug' => 'updated-service',
            'title' => 'Updated service',
            'text' => 'Updated summary.',
            'description' => 'Updated description.',
            'paragraphs' => ['Updated paragraph.'],
            'benefits' => ['Updated benefit.'],
        ])
        ->assertOk()
        ->assertJsonPath('slug', 'updated-service')
        ->assertJsonPath('title', 'Updated service');

    $this->assertDatabaseHas('services', ['id' => $service->id, 'slug' => 'updated-service']);
});

it('replaces uploaded service images and deletes stored images when deleting the service', function () {
    Storage::fake('public');
    $service = createServiceForApiTest([
        'image' => 'service-images/original.jpg',
        'detail_image' => 'service-images/original-detail.jpg',
    ]);
    Storage::disk('public')->put('service-images/original.jpg', 'old image');
    Storage::disk('public')->put('service-images/original-detail.jpg', 'old detail image');

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->post('/api/services/test-service', [
            '_method' => 'PUT',
            'slug' => 'test-service',
            'title' => 'Test Service',
            'text' => 'A short service summary.',
            'description' => 'A service description.',
            'image_file' => fakeServiceImage('replacement.gif'),
            'detail_image_file' => fakeServiceImage('replacement-detail.gif'),
            'paragraphs' => ['First paragraph.'],
            'benefits' => ['First benefit.'],
        ])
        ->assertOk();

    Storage::disk('public')->assertMissing('service-images/original.jpg');
    Storage::disk('public')->assertMissing('service-images/original-detail.jpg');
    $service->refresh();
    Storage::disk('public')->assertExists($service->getRawOriginal('image'));
    Storage::disk('public')->assertExists($service->getRawOriginal('detail_image'));

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->deleteJson('/api/services/test-service')
        ->assertNoContent();

    Storage::disk('public')->assertMissing($service->getRawOriginal('image'));
    Storage::disk('public')->assertMissing($service->getRawOriginal('detail_image'));
});

it('deletes a service', function () {
    $service = createServiceForApiTest();

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->deleteJson('/api/services/test-service')
        ->assertNoContent();

    $this->assertDatabaseMissing('services', ['id' => $service->id]);
});
