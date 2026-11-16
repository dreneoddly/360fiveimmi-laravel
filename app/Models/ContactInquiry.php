<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class ContactInquiry extends Model
{
    protected $fillable = [
        'program_interest',
        'preferred_contact_method',
        'message',
        'first_name',
        'last_name',
        'telephone',
        'email',
        'nationality',
        'residence_country',
        'consent',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'consent' => 'boolean',
        ];
    }
}
