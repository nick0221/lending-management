@component('mail::message')
# Loan statement

**Borrower:** {{ $borrower->name }}<br>
**Loan:** #{{ $loan->id }}@if ($product) · {{ $product }}@endif<br>
**Status:** {{ $loan->status->label() }}<br>
**Principal:** {{ number_format((float) $loan->principal, 2, '.', '') }}

@component('mail::table')
| # | Due date | Due | Paid | Outstanding |
| :- | :-- | -: | -: | -: |
@foreach ($rows as $row)
| {{ $row['sequence'] }} | {{ $row['due_date'] }} | {{ $row['due'] }} | {{ $row['paid'] }} | {{ $row['outstanding'] }} |
@endforeach
| | **Total** | **{{ $totals['due'] }}** | | **{{ $totals['outstanding'] }}** |
@endcomponent

If you have any questions about this statement, please contact us.

Thanks,<br>
{{ config('app.name') }}
@endcomponent
