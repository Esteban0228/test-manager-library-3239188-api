<?php

namespace App\Models;

use App\Models\Concerns\HasUuidV4;
use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    use HasUuidV4;

    protected $table = 'library.user';
    public $timestamps = false;
    protected $guarded = [];
    protected $hidden = ['password_hash'];
}
