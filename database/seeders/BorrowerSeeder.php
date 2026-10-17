<?php

namespace Database\Seeders;

use App\Models\Borrower;
use Illuminate\Database\Seeder;

class BorrowerSeeder extends Seeder
{
    /**
     * Seed the application's borrowers.
     */
    public function run(): void
    {
        Borrower::factory()->count(10)->create();
    }
}
