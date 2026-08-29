<?php

namespace Database\Seeders;

use App\Models\CapturedRequest;
use App\Models\Endpoint;
use App\Models\Replay;
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
        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $endpoint = Endpoint::factory()->for($user)->create([
            'name' => 'Demo',
        ]);

        $request = CapturedRequest::factory()->for($endpoint)->create();
        CapturedRequest::factory()->binary()->for($endpoint)->create();
        Replay::factory()->for($request)->create();
    }
}
