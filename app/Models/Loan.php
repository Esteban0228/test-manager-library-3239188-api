<?php

namespace App\Models;

use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Loan extends Model
{
    use HasUuidV4;

    protected $table = 'library.loan';
    public $timestamps = false;
    protected $guarded = [];
    protected $dateFormat = 'Y-m-d H:i:sP';
    protected $casts = ['loaned_at' => 'datetime'];

    public function items(): HasMany
    {
        return $this->hasMany(LoanItem::class, 'loan_id')->orderBy('book_title')->orderBy('id');
    }
}
