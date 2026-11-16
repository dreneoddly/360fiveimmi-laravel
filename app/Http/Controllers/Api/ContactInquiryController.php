<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactInquiry;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactInquiryController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json(
            ContactInquiry::query()
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->paginate(20),
        );
    }

    public function show(ContactInquiry $contactInquiry): ContactInquiry
    {
        return $contactInquiry;
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'program_interest' => [
                'required',
                'string',
                Rule::in([
                    'Residence by Investment',
                    'Citizenship by Investment',
                    'Global Mobility',
                ]),
            ],
            'preferred_contact_method' => ['required', Rule::in(['Email', 'Telephone'])],
            'message' => ['nullable', 'string', 'max:10000'],
            'first_name' => ['required', 'string', 'max:100'],
            'last_name' => ['required', 'string', 'max:100'],
            'telephone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'nationality' => ['nullable', 'string', 'max:100'],
            'residence_country' => ['required', 'string', 'max:150'],
            'consent' => ['accepted'],
        ]);

        $inquiry = ContactInquiry::create([
            ...$validated,
            'consent' => true,
        ]);

        return response()->json([
            'message' => 'Your enquiry has been received.',
            'id' => $inquiry->id,
        ], 201);
    }

    public function updateStatus(Request $request, ContactInquiry $contactInquiry): ContactInquiry
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['new', 'in_progress', 'resolved'])],
        ]);

        $contactInquiry->update($validated);

        return $contactInquiry;
    }
}
