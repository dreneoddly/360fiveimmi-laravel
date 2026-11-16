<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class ServiceController extends Controller
{
    public function index()
    {
        return Service::query()
            ->orderBy('title')
            ->get();
    }

    public function show(Service $service): Service
    {
        return $service;
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->serviceRules());
        $validated['image'] = $this->storeImage($request, 'image_file');
        $validated['detail_image'] = $this->storeImage($request, 'detail_image_file');
        unset($validated['image_file'], $validated['detail_image_file']);

        $service = Service::create($validated);

        return response()
            ->json($service, 201)
            ->header('Location', route('api.services.show', $service->slug));
    }

    public function update(Request $request, Service $service)
    {
        $validated = $request->validate($this->serviceRules($service));
        $oldImages = [
            'image' => $service->getRawOriginal('image'),
            'detail_image' => $service->getRawOriginal('detail_image'),
        ];
        $newImages = [];

        foreach (['image' => 'image_file', 'detail_image' => 'detail_image_file'] as $column => $field) {
            if ($request->hasFile($field)) {
                $newImages[$column] = $this->storeImage($request, $field);
                $validated[$column] = $newImages[$column];
            }
        }
        unset($validated['image_file'], $validated['detail_image_file']);

        $service->update($validated);
        foreach ($newImages as $column => $path) {
            if ($oldImages[$column] !== $path) {
                $this->deleteStoredImage($oldImages[$column]);
            }
        }

        return response()->json($service);
    }

    public function destroy(Service $service)
    {
        $images = [
            $service->getRawOriginal('image'),
            $service->getRawOriginal('detail_image'),
        ];
        $service->delete();
        foreach ($images as $image) {
            $this->deleteStoredImage($image);
        }

        return response()->noContent();
    }

    private function serviceRules(?Service $service = null): array
    {
        return [
            'slug' => [
                'required',
                'string',
                'max:255',
                'alpha_dash',
                Rule::unique('services', 'slug')->ignore($service?->id),
            ],
            'title' => ['required', 'string', 'max:255'],
            'text' => ['required', 'string'],
            'description' => ['required', 'string'],
            'image_file' => [$service ? 'nullable' : 'required', 'image', 'max:5120'],
            'detail_image_file' => [$service ? 'nullable' : 'required', 'image', 'max:5120'],
            'paragraphs' => ['required', 'array', 'min:1'],
            'paragraphs.*' => ['required', 'string'],
            'benefits' => ['required', 'array', 'min:1'],
            'benefits.*' => ['required', 'string'],
        ];
    }

    private function storeImage(Request $request, string $field): string
    {
        $path = $request->file($field)->store('service-images', 'public');
        if (! is_string($path)) {
            throw new \RuntimeException("Unable to store the uploaded service image: {$field}.");
        }

        return $path;
    }

    private function deleteStoredImage(?string $path): void
    {
        if ($path && str_starts_with($path, 'service-images/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
