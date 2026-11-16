<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\UpdateArticle;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;

class UpdateController extends Controller
{
    public function index()
    {
        return UpdateArticle::query()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get();
    }

    public function show(UpdateArticle $update): UpdateArticle
    {
        return $update;
    }

    public function store(Request $request)
    {
        $validated = $request->validate($this->updateRules());
        $validated['image'] = $this->storeImage($request);
        unset($validated['image_file']);

        $update = UpdateArticle::create($validated);

        return response()
            ->json($update, 201)
            ->header('Location', route('api.updates.show', $update));
    }

    public function update(Request $request, UpdateArticle $update)
    {
        $validated = $request->validate($this->updateRules(requiredImage: false));
        $oldImage = $update->getRawOriginal('image');
        $newImage = null;

        if ($request->hasFile('image_file')) {
            $newImage = $this->storeImage($request);
            $validated['image'] = $newImage;
        }
        unset($validated['image_file']);

        $update->update($validated);
        if ($newImage) {
            $this->deleteStoredImage($oldImage);
        }

        return response()->json($update);
    }

    public function destroy(UpdateArticle $update)
    {
        $image = $update->getRawOriginal('image');
        $update->delete();
        $this->deleteStoredImage($image);

        return response()->noContent();
    }

    private function updateRules(bool $requiredImage = true): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'published_at' => ['nullable', 'date'],
            'image_file' => [$requiredImage ? 'required' : 'nullable', 'image', 'max:5120'],
            'description' => ['required', 'string'],
            'paragraphs' => ['required', 'array', 'min:1'],
            'paragraphs.*' => ['required', 'string'],
        ];
    }

    private function storeImage(Request $request): string
    {
        $path = $request->file('image_file')->store('update-images', 'public');
        if (! is_string($path)) {
            throw new \RuntimeException('Unable to store the uploaded update image.');
        }

        return $path;
    }

    private function deleteStoredImage(?string $path): void
    {
        if ($path && str_starts_with($path, 'update-images/')) {
            Storage::disk('public')->delete($path);
        }
    }
}
