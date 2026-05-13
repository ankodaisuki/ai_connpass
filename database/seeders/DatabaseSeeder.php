<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $testUser = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $otherUsers = User::factory(5)->create();

        $allUsers = collect([$testUser])->merge($otherUsers);

        $allUsers->each(function (User $user) {
            \App\Models\Event::factory(3)->create(['user_id' => $user->id]);
        });
    }
}
