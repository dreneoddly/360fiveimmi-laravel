<?php

use App\Models\UpdateArticle;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('stores update content and casts article paragraphs to an array', function () {
    $update = UpdateArticle::create([
        'slug' => 'research-and-publications',
        'title' => 'Research and Publications',
        'published_at' => '2026-06-18',
        'image' => 'https://example.com/research.jpg',
        'description' => 'Research and publications update.',
        'paragraphs' => ['First paragraph.', 'Second paragraph.'],
    ]);

    expect($update->paragraphs)
        ->toBeArray()
        ->toBe(['First paragraph.', 'Second paragraph.'])
        ->and($update->published_at->format('Y-m-d'))
        ->toBe('2026-06-18');

    $this->assertDatabaseHas('updates', [
        'id' => $update->id,
        'title' => 'Research and Publications',
    ]);
});
