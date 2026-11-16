<?php

use App\Models\ContactInquiry;
use App\Models\User;

function validContactInquiryPayload(array $overrides = []): array
{
    return array_merge([
        'program_interest' => 'Residence by Investment',
        'preferred_contact_method' => 'Email',
        'message' => 'I would like more information.',
        'first_name' => 'Taylor',
        'last_name' => 'Example',
        'telephone' => '+1 555 0100',
        'email' => 'taylor@example.com',
        'nationality' => 'Canadian',
        'residence_country' => 'Canada',
        'consent' => 'on',
    ], $overrides);
}

it('stores a visitor contact inquiry without requiring authentication', function () {
    $this->postJson('/api/contact-inquiries', validContactInquiryPayload())
        ->assertCreated()
        ->assertJsonPath('message', 'Your enquiry has been received.')
        ->assertJsonStructure(['id', 'message']);

    $inquiry = ContactInquiry::query()->firstOrFail();

    expect($inquiry->first_name)->toBe('Taylor')
        ->and($inquiry->email)->toBe('taylor@example.com')
        ->and($inquiry->consent)->toBeTrue()
        ->and($inquiry->status)->toBe('new');
});

it('validates required contact details and consent', function () {
    $this->postJson('/api/contact-inquiries', validContactInquiryPayload([
        'email' => 'not-an-email',
        'consent' => false,
        'program_interest' => 'Other',
    ]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['email', 'consent', 'program_interest']);

    expect(ContactInquiry::query()->count())->toBe(0);
});

it('restricts inquiry listing and status updates to administrators', function () {
    $inquiry = ContactInquiry::create(validContactInquiryPayload(['consent' => true]));

    $this->getJson('/api/contact-inquiries')->assertUnauthorized();
    $this->patchJson("/api/contact-inquiries/{$inquiry->id}", ['status' => 'resolved'])
        ->assertUnauthorized();

    $this->actingAs(User::factory()->create(['is_admin' => false]))
        ->getJson('/api/contact-inquiries')
        ->assertForbidden();

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->getJson('/api/contact-inquiries')
        ->assertOk()
        ->assertJsonPath('data.0.id', $inquiry->id)
        ->assertJsonPath('data.0.status', 'new');
});

it('allows administrators to view and update inquiry status', function () {
    $inquiry = ContactInquiry::create(validContactInquiryPayload(['consent' => true]));

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->getJson("/api/contact-inquiries/{$inquiry->id}")
        ->assertOk()
        ->assertJsonPath('email', 'taylor@example.com')
        ->assertJsonPath('status', 'new');

    $this->actingAs(User::factory()->create(['is_admin' => true]))
        ->patchJson("/api/contact-inquiries/{$inquiry->id}", ['status' => 'resolved'])
        ->assertOk()
        ->assertJsonPath('status', 'resolved');

    expect($inquiry->fresh()->status)->toBe('resolved');
});
