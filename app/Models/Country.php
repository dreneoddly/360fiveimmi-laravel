<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

class Country extends Model
{
    protected $fillable = [
        'flag_image',
        'name',
        'category',
        'minimum_investment',
        'key_benefit',
        'processing_time',
        'details_intro',
        'benefits',
        'requirements',
        'steps',
    ];

    protected function flagImage(): Attribute
    {
        return Attribute::get(function (?string $value): ?string {
            if (!$value || !str_starts_with($value, 'country-flags/')) {
                return $value;
            }

            return url(Storage::disk('public')->url($value));
        });
    }

}
