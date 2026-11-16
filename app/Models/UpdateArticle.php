<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class UpdateArticle extends Model
{
    protected $table = 'updates';

    protected $fillable = [
        'title',
        'published_at',
        'image',
        'description',
        'paragraphs',
    ];

    protected function image(): Attribute
    {
        return Attribute::get(function (?string $value): ?string {
            if (! $value || ! str_starts_with($value, 'update-images/')) {
                return $value;
            }

            return url(Storage::disk('public')->url($value));
        });
    }

    protected function casts(): array
    {
        return [
            'published_at' => 'date:Y-m-d',
            'paragraphs' => 'array',
        ];
    }
}
