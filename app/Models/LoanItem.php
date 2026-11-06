<?php

namespace App\Models;

use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Model;

class LoanItem extends Model
{
    use HasUuidV4;

    protected $table = 'library.loan_item';
    public $timestamps = false;
    protected $guarded = [];
    protected $dateFormat = 'Y-m-d H:i:sP';
    protected $casts = ['due_at' => 'datetime', 'returned_at' => 'datetime'];
}
