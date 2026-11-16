<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use InvalidArgumentException;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $email = env('ADMIN_EMAIL');
        $password = env('ADMIN_PASSWORD');

        if (! $email && ! $password) {
            return;
        }

        if (! $email || ! $password || ! filter_var($email, FILTER_VALIDATE_EMAIL) || strlen($password) < 12) {
            throw new InvalidArgumentException(
                'Set a valid ADMIN_EMAIL and an ADMIN_PASSWORD of at least 12 characters before seeding.',
            );
        }

        $user = User::firstOrNew(['email' => $email]);
        $user->forceFill([
            'name' => env('ADMIN_NAME', 'Administrator'),
            'password' => $password,
            'is_admin' => true,
        ])->save();
    }
}
