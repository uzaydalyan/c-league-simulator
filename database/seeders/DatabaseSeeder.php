<?php

namespace Database\Seeders;

use App\Models\Team;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        foreach (config('league.teams') as $team) {
            Team::firstOrCreate(['name' => $team['name']], $team);
        }
    }
}
