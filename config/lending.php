<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Overdue defaults
    |--------------------------------------------------------------------------
    |
    | The number of days an installment may remain past due before the daily
    | `loans:flag-overdue` command marks the loan as defaulted.
    |
    */

    'default_after_days' => (int) env('LENDING_DEFAULT_AFTER_DAYS', 90),

    /*
    |--------------------------------------------------------------------------
    | Due reminders
    |--------------------------------------------------------------------------
    |
    | How many days before an installment is due the `loans:send-due-reminders`
    | command emails the borrower.
    |
    */

    'due_reminder_days' => (int) env('LENDING_DUE_REMINDER_DAYS', 3),

];
