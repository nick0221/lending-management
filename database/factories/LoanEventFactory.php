<?php

namespace Database\Factories;

use App\Enums\LoanEventType;
use App\Models\Loan;
use App\Models\LoanEvent;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<LoanEvent>
 */
class LoanEventFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'loan_id' => Loan::factory(),
            'user_id' => User::factory(),
            'action' => LoanEventType::Created->value,
            'from_status' => null,
            'to_status' => null,
        ];
    }
}
