<?php

namespace Modules\Fees\Entities;

use App\Models\SmStudent;
use App\Models\Unit;
use App\Scopes\AcademicSchoolScope;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Schema;

class FmFeesInvoice extends Model
{
    use HasFactory;

    protected $casts = [
        'id' => 'integer',
        'full_name' => 'string',
    ];

    protected $fillable = [];

    // Scope to eager load the sums
    public function scopeWithInvoiceDetailsSums($query): void
    {
        $query->with(['invoiceDetails' => function ($query): void {
            $query->selectRaw('fees_invoice_id, sum(amount) as total_amount, sum(weaver) as total_weaver, sum(fine) as total_fine, sum(paid_amount) as total_paid_amount, sum(sub_total) as total_sub_total')
                ->groupBy('fees_invoice_id');
        }]);
    }

    public function studentInfo()
    {
        return $this->belongsTo(SmStudent::class, 'student_id', 'id');
    }

    public function invoiceDetails()
    {
        return $this->hasMany(FmFeesInvoiceChield::class, 'fees_invoice_id');
    }

    /** Latest approved, non-reversed payment for receipt shortcuts. */
    public function lastApprovedTransaction()
    {
        $query = $this->hasOne(FmFeesTransaction::class, 'fees_invoice_id')
            ->where('paid_status', 'approve');

        if (\App\Services\FeeSchemaCache::hasColumn('fm_fees_transactions', 'is_reversal')) {
            $query->where(function ($inner) {
                $inner->where('is_reversal', false)->orWhereNull('is_reversal');
            });
        }

        if (\App\Services\FeeSchemaCache::hasColumn('fm_fees_transactions', 'reversed_at')) {
            $query->whereNull('reversed_at');
        }

        return $query->orderByDesc('fm_fees_transactions.id');
    }

    // Using the pre-loaded sums for efficiency
    public function getTamountAttribute()
    {
        return $this->invoiceDetails()->sum('amount');
    }

    public function getTweaverAttribute()
    {
        return $this->invoiceDetails()->sum('weaver');
    }

    public function getTfineAttribute()
    {
        return $this->invoiceDetails()->sum('fine');
    }

    public function getTpaidamountAttribute()
    {
        return $this->invoiceDetails()->sum('paid_amount');
    }

    public function getTsubtotalAttribute()
    {
        return $this->invoiceDetails()->sum('sub_total');
    }

    public function recordDetail()
    {
        return $this->belongsTo(\App\Models\StudentRecord::class, 'record_id', 'id');
    }

    public function branch()
    {
        return $this->belongsTo(\Modules\Branch\Entities\Branch::class, 'branch_id', 'id');
    }

    public function unit(): BelongsTo
    {
        return $this->belongsTo(Unit::class, 'unit_id', 'id');
    }

    public function resolveBillingUnit(): ?Unit
    {
        if (Schema::hasColumn('fm_fees_invoices', 'unit_id') && $this->unit_id) {
            return $this->relationLoaded('unit') ? $this->unit : Unit::find($this->unit_id);
        }

        $student = $this->studentInfo;
        if (! $student) {
            return null;
        }

        $serviceLine = Schema::hasColumn('fm_fees_invoices', 'service_line')
            ? $this->service_line
            : null;

        if (in_array($serviceLine, ['school', 'academic'], true) && $student->unit_id) {
            return Unit::find($student->unit_id);
        }

        if (in_array($serviceLine, ['hostel', 'mess', 'residential'], true) && $student->hostel_unit_id) {
            return Unit::find($student->hostel_unit_id);
        }

        if ($student->hostel_unit_id && $this->isHostelOnlyStudent($student) && $serviceLine !== 'school') {
            return Unit::find($student->hostel_unit_id);
        }

        if (Schema::hasTable('unit_student')) {
            if ($serviceLine !== 'school' || $this->isHostelOnlyStudent($student)) {
                $residentialId = $student->units()
                    ->wherePivot('enrollment_type', 'residential')
                    ->value('units.id');
                if ($residentialId) {
                    return Unit::find($residentialId);
                }
            }

            $academicId = $student->units()
                ->wherePivot('enrollment_type', 'academic')
                ->value('units.id');
            if ($academicId) {
                return Unit::find($academicId);
            }
        }

        if ($student->unit_id) {
            return Unit::find($student->unit_id);
        }

        if ($student->hostel_unit_id) {
            return Unit::find($student->hostel_unit_id);
        }

        return null;
    }

    public function getUnitNameAttribute(): string
    {
        return $this->resolveBillingUnit()?->name
            ?? generalSetting()->school_name
            ?? '';
    }

    public function getUnitBrandingAttribute(): array
    {
        $unit = $this->resolveBillingUnit();
        $setting = generalSetting();

        if ($unit) {
            return [
                'logo' => $unit->logo ?: $setting->logo,
                'name' => $unit->name,
                'address' => $unit->formatted_address ?: ($setting->address ?? ''),
                'phone' => collect([$unit->phone, $unit->alternate_phone, $setting->phone])->filter()->first(),
                'email' => $unit->code
                    ? strtolower($unit->code).'@vss.ac'
                    : ($unit->email ?: ($setting->email ?? 'team@dnyanda.ac.in')),
            ];
        }

        return [
            'logo' => $setting->logo,
            'name' => $setting->school_name ?? '',
            'address' => $setting->address ?? '',
            'phone' => $setting->phone ?? '',
            'email' => $setting->email ?? 'team@dnyanda.ac.in',
        ];
    }

    protected function isHostelOnlyStudent(SmStudent $student): bool
    {
        if (! Schema::hasTable('unit_student')) {
            return (bool) $student->hostel_unit_id && ! $student->unit_id;
        }

        $types = $student->units()->pluck('unit_student.enrollment_type')->unique();

        return $types->contains('residential') && ! $types->contains('academic');
    }

    protected static function boot()
    {
        parent::boot();
        static::addGlobalScope(new AcademicSchoolScope);
    }

    protected static function newFactory()
    {
        return \Modules\Fees\Database\factories\FmFeesInvoiceFactory::new();
    }
}
