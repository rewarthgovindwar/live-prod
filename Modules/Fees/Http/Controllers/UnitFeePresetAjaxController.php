 
<?php

namespace Modules\Fees\Http\Controllers;

use App\Models\StudentRecord;
use App\Models\Unit;
use App\Services\FeeMonthlyPlanService;
use App\Services\HostelPlacementService;
use App\Services\UnitFeePresetService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;

class UnitFeePresetAjaxController extends Controller
{
    public function options(int $recordId, UnitFeePresetService $service): JsonResponse
    {
        $record = StudentRecord::where('id', $recordId)
            ->where('school_id', auth()->user()->school_id)
            ->where('academic_id', getAcademicId())
            ->firstOrFail();

        $packages = $service->packagesForStudentRecord($record);
        $hasIit = $service->studentHasIitFoundation((int) $record->student_id);

        return response()->json([
            'packages' => array_map(fn ($p) => [
                'unit_id' => $p['unit_id'],
                'unit_name' => $p['unit_name'],
                'service_line' => $p['service_line'],
                'total' => $p['total'],
            ], $packages),
            'has_iit' => $hasIit,
        ]);
    }

    public function rows(Request $request, int $recordId, UnitFeePresetService $service)
    {
        $record = StudentRecord::where('id', $recordId)
            ->where('school_id', auth()->user()->school_id)
            ->where('academic_id', getAcademicId())
            ->firstOrFail();

        $unitId = (int) $request->query('unit_id');
        $serviceLine = (string) $request->query('service_line', 'school');

        $packages = $service->packagesForStudentRecord($record);
        $package = collect($packages)->first(fn ($p) => $p['unit_id'] === $unitId && $p['service_line'] === $serviceLine);

        if (! $package) {
            $lines = $service->linesForUnit($unitId, (int) $record->class_id, (int) $record->student_id);
            if ($lines === []) {
                return response('', 404);
            }
            $serviceLine = $service->serviceLineForUnitId($unitId);

            return view('fees::_unitFeePresetRows', $this->presetRowViewData(
                $lines,
                $unitId,
                $serviceLine,
                (int) $record->id,
                $this->scheduleFromRequest($request)
            ));
        }

        return view('fees::_unitFeePresetRows', $this->presetRowViewData(
            $package['lines'],
            $unitId,
            $serviceLine,
            (int) $record->id,
            $this->scheduleFromRequest($request)
        ));
    }

    public function rowsForUnit(Request $request, UnitFeePresetService $service)
    {
        $unitId = (int) $request->query('unit_id');
        $classId = (int) $request->query('class_id');
        $studentId = $request->query('student_id') ? (int) $request->query('student_id') : null;
        $serviceLine = (string) $request->query('service_line', '');

        $recordId = null;
        if ($request->query('record_id')) {
            $record = StudentRecord::where('id', (int) $request->query('record_id'))
                ->where('school_id', auth()->user()->school_id)
                ->first();
            if ($record) {
                $studentId = (int) $record->student_id;
                $recordId = (int) $record->id;
            }
        }

        $lines = $service->linesForUnit($unitId, $classId, $studentId, true);
        if ($lines === []) {
            return response()->json([
                'message' => $service->diagnoseEmptyLines($unitId, $classId),
            ], 422);
        }

        $serviceLine = $service->serviceLineForUnitId($unitId);

        return view('fees::_unitFeePresetRows', $this->presetRowViewData(
            $lines,
            $unitId,
            $serviceLine,
            $recordId,
            $this->scheduleFromRequest($request)
        ));
    }

    /** @param array<int, mixed> $lines */
    protected function presetRowViewData(array $lines, int $unitId, string $serviceLine, ?int $recordId, ?array $schedule = null): array
    {
        $planService = app(FeeMonthlyPlanService::class);
        $schedule = $planService->normalizeSchedule($schedule);
        $academicId = (int) getAcademicId();
        $maxMonths = $planService->academicMonthCount($academicId);
        $monthSchedule = $planService->defaultAcademicSchedule($academicId, 1, $maxMonths, $schedule['due_day']);
        $startIndex = max(0, $schedule['from_index'] - 1);

        return [
            'lines' => $lines,
            'unit_id' => $unitId,
            'service_line' => $serviceLine,
            'month_schedule' => $monthSchedule,
            'schedule_start_index' => $startIndex,
            'initial_due_range' => $planService->formatInvoiceDueRange($monthSchedule, 1, $startIndex),
            'schedule_from_index' => $schedule['from_index'],
            'schedule_to_index' => $schedule['to_index'],
            'fee_due_day' => $schedule['due_day'],
        ];
    }

    /** @return array{from_index: int, to_index: int, due_day: int} */
    protected function scheduleFromRequest(Request $request): array
    {
        return app(FeeMonthlyPlanService::class)->normalizeSchedule([
            'from_index' => $request->query('schedule_from_index'),
            'to_index' => $request->query('schedule_to_index'),
            'due_day' => $request->query('fee_due_day'),
        ]);
    }

    public function bulkPreview(Request $request, UnitFeePresetService $service): JsonResponse
    {
        $classId = (int) $request->query('class_id');
        $sectionId = $request->query('section_id') ? (int) $request->query('section_id') : null;
        $unitId = (int) $request->query('unit_id');
        $payMonths = max(1, (int) $request->query('pay_months', 1));

        $unit = Unit::where('school_id', auth()->user()->school_id)->findOrFail($unitId);
        $students = $service->bulkStudentPreview($classId, $sectionId, $unitId, $payMonths);

        return response()->json([
            'count' => count($students),
            'students' => $students,
            'service_line' => $service->serviceLineForUnit($unit),
            'unit_name' => app(HostelPlacementService::class)->unitLabel($unit),
            'has_iit_any' => collect($students)->contains(fn ($s) => $s['has_iit']),
        ]);
    }
}
