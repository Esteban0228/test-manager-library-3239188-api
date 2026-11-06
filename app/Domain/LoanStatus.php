<?php

namespace App\Domain;

use DateTimeInterface;

/** El estado de un préstamo se calcula, nunca se almacena (artículo VII, CA-08.3). */
enum LoanStatus: string
{
    case Open = 'open';
    case Overdue = 'overdue';
    case Closed = 'closed';

    /**
     * @param iterable<array{due_at: DateTimeInterface, returned_at: ?DateTimeInterface}> $items
     */
    public static function compute(iterable $items, DateTimeInterface $now): self
    {
        $pending = false;
        foreach ($items as $item) {
            if ($item['returned_at'] === null) {
                if ($item['due_at'] < $now) {
                    return self::Overdue;
                }
                $pending = true;
            }
        }

        return $pending ? self::Open : self::Closed;
    }
}
