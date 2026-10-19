<?php

declare(strict_types=1);
require_once __DIR__.'/FinancialPilotTest.php';

/** Standalone intended-outcome repros, selected with --filter independent_repro. */
final class NewDefectReproTest extends FinancialPilotTest
{
    public function test_independent_repro_rq_a_001_webhook_scope_must_be_retrievable(): void
    {
        $this->test_s_c_payments_db566d3009_duplicate_callback_has_no_financial_or_event_effect();
    }

    public function test_independent_repro_rq_a_002_competing_sale_holds_must_not_exceed_stock(): void
    {
        $this->test_s_c_booking_320197067b_two_processes_cannot_oversell_one_unit();
    }
}
