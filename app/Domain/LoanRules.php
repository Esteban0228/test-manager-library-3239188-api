<?php

namespace App\Domain;

/** Reglas de negocio constantes (spec RN-2). Sin dependencias del framework. */
final class LoanRules
{
    public const LOAN_DAYS = 14;
    public const MAX_OPEN_ITEMS_PER_MEMBER = 3;
    public const MAX_ITEMS_PER_LOAN = 3;
}
