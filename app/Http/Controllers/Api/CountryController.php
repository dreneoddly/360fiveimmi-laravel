<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class CountryController extends Controller
{
    public function index(Request $request)
    {
        $validated = $request->validate([
            'category' => ['sometimes', 'required', Rule::in(['Residence', 'Citizenship'])],
        ]);

        return Country::query()
            ->when(
                $validated['category'] ?? null,
                fn ($query, $category) => $query->where('category', $category),
            )
            ->orderBy('name')
            ->get();
    }

    public function show(Country $country): Country
    {
        return $country;
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->countryRules(
            requiredImage: !$request->hasFile('flag_image_file'),
        ));

        if ($request->hasFile('flag_image_file')) {
            $validated['flag_image'] = $request->file('flag_image_file')->store('country-flags', 'public');
        }
        unset($validated['flag_image_file'], $validated['remove_flag_image']);

        $country = Country::create($validated);

        return response()
            ->json($country, 201)
            ->header('Location', route('api.countries.show', $country));
    }

    public function update(Request $request, Country $country)
    {
        $validated = $request->validate($this->countryRules());
        $oldFlagImage = $country->getRawOriginal('flag_image');
        $shouldDeleteOldFlag = false;

        if ($request->hasFile('flag_image_file')) {
            $validated['flag_image'] = $request->file('flag_image_file')->store('country-flags', 'public');
            $shouldDeleteOldFlag = true;
        } elseif ($request->boolean('remove_flag_image')) {
            $validated['flag_image'] = '';
            $shouldDeleteOldFlag = true;
        } elseif (blank($validated['flag_image'] ?? null)) {
            unset($validated['flag_image']);
        } elseif ($validated['flag_image'] === $country->flag_image) {
            unset($validated['flag_image']);
        } else {
            $shouldDeleteOldFlag = true;
        }

        unset($validated['flag_image_file'], $validated['remove_flag_image']);
        $country->update($validated);
        if ($shouldDeleteOldFlag) {
            $this->deleteStoredFlag($oldFlagImage);
        }

        return response()->json($country);
    }

    public function destroy(Country $country)
    {
        $flagImage = $country->getRawOriginal('flag_image');
        $country->delete();
        $this->deleteStoredFlag($flagImage);

        return response()->noContent();
    }

    private function countryRules(bool $requiredImage = false): array
    {
        return [
            'flag_image' => [
                $requiredImage ? 'required' : 'nullable',
                'string',
                'max:255',
            ],
            'flag_image_file' => ['nullable', 'image', 'max:5120'],
            'remove_flag_image' => ['sometimes', 'boolean'],
            'name' => ['required', 'string', 'max:100'],
            'category' => ['required', Rule::in(['Residence', 'Citizenship'])],
            'minimum_investment' => ['required', 'string', 'max:255'],
            'key_benefit' => ['required', 'string', 'max:255'],
            'processing_time' => ['required', 'string', 'max:50'],
            'details_intro' => ['nullable', 'string', 'max:1000'],
            'benefits' => ['nullable', 'string'],
            'requirements' => ['nullable', 'string'],
            'steps' => ['nullable', 'string'],
        ];
    }

    private function deleteStoredFlag(?string $flagImage): void
    {
        if ($flagImage && str_starts_with($flagImage, 'country-flags/')) {
            Storage::disk('public')->delete($flagImage);
        }
    }
}
