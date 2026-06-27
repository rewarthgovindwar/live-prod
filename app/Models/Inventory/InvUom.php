<?php

namespace App\Models\Inventory;

use Illuminate\Database\Eloquent\Model;

class InvUom extends Model
{
    protected $guarded = ['id'];

    protected $casts = [
        'is_active' => 'boolean',
        'decimal_places' => 'integer',
    ];
}
