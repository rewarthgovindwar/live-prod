<?php

namespace App\Models;

use App\Scopes\SchoolScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmItem extends Model
{
    use HasFactory;

    protected $guarded = ['id'];

    protected $casts = [
        'total_in_stock' => 'float',
        'reorder_level' => 'float',
        'reorder_qty' => 'float',
        'last_unit_cost' => 'float',
    ];

    public function category()
    {
        return $this->belongsTo(SmItemCategory::class, 'item_category_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(\Modules\Branch\Entities\Branch::class, 'branch_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new SchoolScope);
    }
}
