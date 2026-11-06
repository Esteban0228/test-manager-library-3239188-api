<?php

namespace Tests\Unit;

use App\Domain\Isbn;
use App\Domain\LoanStatus;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;

/** T-03 y T-05: dominio puro, con reloj fijo y sin base de datos. */
final class LoanStatusTest extends TestCase
{
    private function now(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-11-05T12:00:00Z');
    }

    public function test_open_when_pending_and_not_due(): void
    {
        $items = [['due_at' => $this->now()->modify('+1 day'), 'returned_at' => null]];
        $this->assertSame(LoanStatus::Open, LoanStatus::compute($items, $this->now()));
    }

    public function test_overdue_when_any_pending_is_past_due(): void
    {
        $items = [
            ['due_at' => $this->now()->modify('+5 days'), 'returned_at' => null],
            ['due_at' => $this->now()->modify('-1 second'), 'returned_at' => null],
        ];
        $this->assertSame(LoanStatus::Overdue, LoanStatus::compute($items, $this->now()));
    }

    public function test_closed_when_every_item_is_returned_even_if_late(): void
    {
        $items = [['due_at' => $this->now()->modify('-3 days'), 'returned_at' => $this->now()->modify('-1 day')]];
        $this->assertSame(LoanStatus::Closed, LoanStatus::compute($items, $this->now()));
    }

    public function test_isbn_needs_exactly_13_digits(): void
    {
        $this->assertTrue(Isbn::isValid('9780307474728'));
        $this->assertFalse(Isbn::isValid('978030747472'));
        $this->assertFalse(Isbn::isValid('97803074747289'));
        $this->assertFalse(Isbn::isValid('978-030747472'));
        $this->assertFalse(Isbn::isValid("9780307474728\n"));
    }
}
