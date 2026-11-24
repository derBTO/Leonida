<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class PinCode extends Model
{
    public $timestamps = false;

    protected $fillable = ['label', 'code', 'description', 'sort_order'];
}
