<?php

namespace App\Models;

use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    use HasUuidV4;

    protected $table = 'library.category';
    public $timestamps = false;
    protected $guarded = [];
}
