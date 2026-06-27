<?php

namespace App\Services;

class FeeInvoiceLineLabelService
{
    public function labelFor(mixed $child): string
    {
        $note = trim((string) ($child->note ?? ''));
        if ($note !== '' && $note !== '0') {
            return $note;
        }

        $typeName = $this->feesTypeName($child);
        if ($typeName !== '') {
            return $typeName;
        }

        $feesTypeId = (int) ($child->fees_type ?? 0);
        if ($feesTypeId === 0) {
            return 'Custom fee';
        }

        return 'Fee #'.$feesTypeId;
    }

    protected function feesTypeName(mixed $child): string
    {
        $related = $child->feesType ?? null;
        if ($related && trim((string) ($related->name ?? '')) !== '') {
            return trim((string) $related->name);
        }

        $feesTypeId = (int) ($child->fees_type ?? 0);
        if ($feesTypeId <= 0) {
            return '';
        }

        $typeClass = 'Modules\\Fees\\Entities\\FmFeesType';
        if (! class_exists($typeClass)) {
            return '';
        }

        try {
            $type = $typeClass::query()->withoutGlobalScopes()->find($feesTypeId);
            if ($type && trim((string) ($type->name ?? '')) !== '') {
                return trim((string) $type->name);
            }
        } catch (\Throwable) {
            // Fall through to empty name.
        }

        return '';
    }
}
