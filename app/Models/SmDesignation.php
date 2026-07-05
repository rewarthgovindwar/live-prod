<?php

namespace App\Models;

use App\Scopes\ActiveStatusSchoolScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class SmDesignation extends Model
{
    use HasFactory;

    protected $fillable = [
        'title',
        'active_status',
        'school_id',
        'branch_id',
        'is_saas',
        'created_by',
        'updated_by',
    ];

    public function branch()
    {
        return $this->belongsTo(\Modules\Branch\Entities\Branch::class, 'branch_id', 'id');
    }

    protected static function boot()
    {
        parent::boot();

        static::addGlobalScope(new ActiveStatusSchoolScope);
    }
}
