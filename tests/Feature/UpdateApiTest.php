<?php

use App\Models\UpdateArticle;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

function fakeUpdateImage(string $name): UploadedFile
{
    return UploadedFile::fake()->createWithContent(
        $name,
        base64_decode('R0lGODlhAQABAAD/ACwAAAAAAQABAAACADs='),
    );
}

it('lists updates ordered by publication date', function () {
    $earlier = UpdateArticle::create([
        'title' => 'Earlier update',
        'published_at' => '2026-05-01',
        'image' => 'https://example.com/earlier.jpg',
        'description' => 'Earlier update description.',
        'paragraphs' => ['Earlier paragraph.'],
    ]);
    $later = UpdateArticle::create([
        'title' => 'Later update',
        'published_at' => '2026-06-18',
        'image' => 'https://example.com/later.jpg',
        'description' => 'Later update description.',
        'paragraphs' => ['Later paragraph.'],
    ]);

    $this->getJson('/api/updates')
        ->assertOk()
        ->assertJsonPath('0.id', $later->id)
        ->assertJsonPath('1.id', $earlier->id);
});

it('returns an update by numeric database id', function () {
    $update = UpdateArticle::create([
        'title' => 'Research and Publications',
        'image' => 'https://example.com/research.jpg',
        'description' => 'Research update.',
        'paragraphs' => ['First paragraph.'],
    ]);

    $this->getJson("/api/updates/{$update->id}")
        ->assertOk()
        ->assertJsonPath('id', $update->id)
        ->assertJsonPath('title', 'Research and Publications');
});

it('returns not found for an unknown numeric update id', function () {
    $this->getJson('/api/updates/999999')->assertNotFound();
});

it('creates an update with a valid uploaded image', function () {
    Storage::fake('public');

    $response = $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->post('/api/updates', [
            'title' => 'Research and Publications',
            'published_at' => '2026-06-18',
            'image_file' => fakeUpdateImage('research.gif'),
            'description' => 'Research and publications update.',
            'paragraphs' => ['First paragraph.', 'Second paragraph.'],
        ])
        ->assertCreated()
        ->assertJsonPath('id', 1)
        ->assertHeader('Location', route('api.updates.show', 1))
        ->assertJsonPath('published_at', '2026-06-18')
        ->assertJsonPath('paragraphs.0', 'First paragraph.')
        ->assertJsonPath('paragraphs.1', 'Second paragraph.');
    expect($response->json('image'))->toContain('/storage/update-images/');

    $update = UpdateArticle::firstOrFail();
    expect($update->paragraphs)->toBe(['First paragraph.', 'Second paragraph.']);
    Storage::disk('public')->assertExists($update->getRawOriginal('image'));
});

it('validates required update fields and paragraph arrays', function () {
    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->postJson('/api/updates', [
            'title' => 'Invalid update',
            'paragraphs' => 'Paragraphs must be an array.',
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors([
            'image_file',
            'description',
            'paragraphs',
        ]);
});

it('requires an admin account to create an update', function () {
    $this->postJson('/api/updates', [])
        ->assertUnauthorized();

    $this->assertDatabaseCount('updates', 0);
});

it('updates an update article and preserves its image if no replacement is sent', function () {
    Storage::fake('public');
    $update = UpdateArticle::create([
        'title' => 'Research',
        'published_at' => '2026-06-18',
        'image' => 'https://example.com/research.jpg',
        'description' => 'Original description.',
        'paragraphs' => ['Original paragraph.'],
    ]);

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->putJson("/api/updates/{$update->id}", [
            'title' => 'Updated research',
            'published_at' => '2026-06-20',
            'description' => 'Updated description.',
            'paragraphs' => ['Updated paragraph.'],
        ])
        ->assertOk()
        ->assertJsonPath('title', 'Updated research')
        ->assertJsonPath('image', 'https://example.com/research.jpg');

    $this->assertDatabaseHas('updates', [
        'id' => $update->id,
        'title' => 'Updated research',
    ]);
});

it('replaces and deletes uploaded update images with their article', function () {
    Storage::fake('public');
    $update = UpdateArticle::create([
        'title' => 'Research',
        'image' => 'update-images/original.gif',
        'description' => 'Article description.',
        'paragraphs' => ['Article paragraph.'],
    ]);
    Storage::disk('public')->put('update-images/original.gif', 'old image');

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->post("/api/updates/{$update->id}", [
            '_method' => 'PUT',
            'title' => 'Research',
            'description' => 'Updated description.',
            'paragraphs' => ['Updated paragraph.'],
            'image_file' => fakeUpdateImage('replacement.gif'),
        ])
        ->assertOk();
    Storage::disk('public')->assertMissing('update-images/original.gif');

    $update->refresh();
    $storedImage = $update->getRawOriginal('image');
    Storage::disk('public')->assertExists($storedImage);

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->deleteJson("/api/updates/{$update->id}")
        ->assertNoContent();
    Storage::disk('public')->assertMissing($storedImage);
});
