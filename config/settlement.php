<?php

declare(strict_types=1);

return [

    /*
    |--------------------------------------------------------------------------
    | Refund SLA Days
    |--------------------------------------------------------------------------
    |
    | The number of business days within which a customer wallet refund credit
    | is expected to be posted. Shown as `expected_at` on pending refund ledger
    | entries in the customer wallet transaction API response.
    |
    */
    'refund_sla_days' => (int) env('SETTLEMENT_REFUND_SLA_DAYS', 5),

];
