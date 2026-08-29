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
        if (User::query()->exists()) {
            return;
        }

        $user = User::factory()->create([
            'name' => config('hookscope.demo.name'),
            'email' => config('hookscope.demo.email'),
            'password' => config('hookscope.demo.password'),
        ]);

        $endpoint = Endpoint::factory()->for($user)->create([
            'name' => config('hookscope.demo.endpoint'),
        ]);

        $request = CapturedRequest::factory()->for($endpoint)->create();
        CapturedRequest::factory()->binary()->for($endpoint)->create();
        Replay::factory()->for($request)->create();
    }
}
