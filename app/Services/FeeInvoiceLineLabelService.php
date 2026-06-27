<?php

namespace App\Services;

use Illuminate\Support\Str;

class FeeInvoiceLineLabelService
{
    public function labelFor(mixed $child, mixed $invoice = null): string
    {
        $note = $this->meaningfulNote($child);
        if ($note !== '') {
            return $note;
        }

        if ($invoice) {
            $presetLabel = $this->presetTemplateLabel($child, $invoice);
            if ($presetLabel !== '') {
                return $presetLabel;
            }
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

    protected function meaningfulNote(mixed $child): string
    {
        $note = trim((string) ($child->note ?? ''));
        if ($note === '' || $note === '0') {
            return '';
        }

        return $note;
    }

    protected function presetTemplateLabel(mixed $child, mixed $invoice): string
    {
        $unit = $this->resolveUnit($invoice);
        if (! $unit) {
            return '';
        }

        $templateKey = config("fee_receipts.unit_templates.{$unit->code}")
            ?? config("fee_receipts.service_line_templates.{$unit->service_line}")
            ?? config('fee_receipts.default_template');

        $rows = config("fee_receipts.templates.{$templateKey}.rows", []);
        $feesTypeId = (int) ($child->fees_type ?? 0);
        if ($feesTypeId <= 0 || $rows === []) {
            return '';
        }

        if (class_exists(UnitFeePresetService::class)) {
            $svc = app(UnitFeePresetService::class);
            foreach ($rows as $def) {
                try {
                    $resolvedId = (int) $svc->resolveFeesTypeId(
                        (string) ($def['key'] ?? ''),
                        (string) ($def['label'] ?? ''),
                        $def['match'] ?? []
                    );
                } catch (\Throwable) {
                    continue;
                }

                if ($resolvedId === $feesTypeId) {
                    return trim((string) ($def['label'] ?? ''));
                }
            }
        }

        $typeName = Str::lower($this->feesTypeName($child));
        if ($typeName === '') {
            return '';
        }

        foreach ($rows as $def) {
            foreach ($def['match'] ?? [] as $needle) {
                $needle = Str::lower(trim((string) $needle));
                if ($needle !== '' && str_contains($typeName, $needle)) {
                    return trim((string) ($def['label'] ?? ''));
                }
            }
        }

        return '';
    }

    protected function resolveUnit(mixed $invoice): mixed
    {
        if (! $invoice) {
            return null;
        }

        if (($invoice->relationLoaded('unit') || isset($invoice->unit)) && $invoice->unit) {
            return $invoice->unit;
        }

        $unitId = (int) ($invoice->unit_id ?? 0);
        if ($unitId <= 0) {
            return null;
        }

        $unitClass = 'App\\Models\\Unit';
        if (! class_exists($unitClass)) {
            return null;
        }

        return $unitClass::query()->find($unitId);
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
