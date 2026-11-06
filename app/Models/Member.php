<?php

namespace App\Models;

use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Model;

class Member extends Model
{
    use HasUuidV4;

    protected $table = 'library.member';
    public $timestamps = false;
    protected $guarded = [];
}
