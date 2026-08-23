 
@php
    $scheduleJson = json_encode($month_schedule ?? []);
    $startIndex = (int) ($schedule_start_index ?? 0);
    $dueRange = $initial_due_range ?? '—';
@endphp
@foreach ($lines as $key => $line)
    <tr>
        <td>{{ $key + 1 }}</td>
        <td>{{ $line['label'] }} @if(!empty($line['note']))<small class="text-muted">({{ $line['note'] }})</small>@endif</td>
        <input type="hidden" name="groups[{{ $key }}][feesType]" value="{{ $line['fees_type_id'] }}">
        <input type="hidden" name="groups[{{ $key }}][preset_line]" value="1">
        <td>
            <div class="primary_input">
                <input class="primary_input_field form-control amount fee-preset-amount fee-monthly-input" min="0" type="number" step="0.01"
                    name="groups[{{ $key }}][amount]" value="{{ $line['amount'] }}"
                    data-monthly="{{ $line['amount'] }}" title="Monthly fee (per month)">
            </div>
        </td>
        <td>
            <div class="primary_input">
                <input class="primary_input_field form-control weaver" min="0" type="number" step="0.01"
                    name="groups[{{ $key }}][weaver]" value="0" title="Fee waiver / discount (for period)">
            </div>
        </td>
        <td class="period-total subTotal text-muted">{{ number_format($line['amount'], 2) }}</td>
        <input type="hidden" name="groups[{{ $key }}][sub_total]" class="inputSubTotal" value="{{ $line['amount'] }}">
        <input type="hidden" name="groups[{{ $key }}][note]" value="{{ $line['label'] }}">
        <td class="fee-next-due-display text-nowrap">{{ $dueRange }}</td>
        <input type="hidden" class="paidAmount" name="groups[{{ $key }}][paid_amount]" value="">
        <td>
            <button class="primary-btn icon-only fix-gr-bg" type="button" data-tooltip="tooltip" title="@lang('common.delete')" id="deleteField">
                <span class="ti-trash"></span>
            </button>
            <input type="hidden" class="fees" value="typ{{ $line['fees_type_id'] }}">
        </td>
    </tr>
@endforeach
<tr class="fee-preset-month-note">
    <td colspan="7" class="text-muted pt-0 pb-10" style="border-top:none;">
        <small><em>Amounts are per month; period total = monthly × invoice months. Next due date follows the academic year schedule.</em></small>
    </td>
</tr>
<script type="application/json" id="feeMonthScheduleData">{!! $scheduleJson !!}</script>
<input type="hidden" id="feeScheduleStartIndex" value="{{ $startIndex }}">
<input type="hidden" id="feeScheduleFromIndex" value="{{ (int) ($schedule_from_index ?? ($startIndex + 1)) }}">
