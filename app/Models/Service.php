<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Service extends Model
{
    protected $fillable = [
        'slug',
        'title',
        'text',
        'description',
        'image',
        'detail_image',
        'paragraphs',
        'benefits',
    ];

    protected function image(): Attribute
    {
        return $this->storedImageAttribute();
    }

    protected function detailImage(): Attribute
    {
        return $this->storedImageAttribute();
    }

    private function storedImageAttribute(): Attribute
    {
        return Attribute::get(function (?string $value): ?string {
            if (! $value || ! str_starts_with($value, 'service-images/')) {
                return $value;
            }

            return url(Storage::disk('public')->url($value));
        });
    }

    protected function casts(): array
    {
        return [
            'paragraphs' => 'array',
            'benefits' => 'array',
        ];
    }
}
