 
@if(config('fee_presets.simple_menu', true) && !moduleStatusCheck('University'))
<script>
$(function () {
    var baseUrl = ($('#url').val() || '').replace(/\/$/, '');
    var bulkMode = false;
    var canEditInvoice = @json(feeCanEditInvoiceNumber());
    var canManageAllocation = @json(app(\App\Services\FeeManualAllocationService::class)->canManageAllocation());
    var isEditMode = @json(isset($invoiceInfo));
    var isQuickForm = $('#fiwQuickForm').length > 0;
    var dueDateManual = isEditMode;
    var searchDebounce = null;
    var searchActiveIndex = -1;
    var searchResults = [];
    var DRAFT_KEY = 'fiw_invoice_draft_v1';
    var allocationMode = 'auto';
    var invoicePrefs = {};
    try { invoicePrefs = JSON.parse($('#fiwInvoicePrefs').text() || '{}'); } catch (e) {}
    var feeUnitAbbrevMap = {};
    try { feeUnitAbbrevMap = JSON.parse($('#feeUnitAbbreviations').text() || '{}'); } catch (e) {}

    function getNiceSelectValue($select) {
        if (!$select.length) return '';
        var v = $select.val();
        if (v !== null && v !== '') return v;
        var $nice = $select.next('.nice-select');
        if ($nice.length) {
            var $sel = $nice.find('.option.selected');
            if ($sel.length && !$sel.hasClass('disabled')) return $sel.data('value');
        }
        return '';
    }

    function niceUpdate($el) {
        if (!$el.length) return;
        if ($el.next('.nice-select').length) $el.niceSelect('update');
        else $el.niceSelect();
    }

    function getPayMonthsValue() {
        return parseInt(getNiceSelectValue($('#payMonths')), 10) || parseInt($('#payMonths').val(), 10) || 1;
    }

    function getScheduleFromValue() {
        return parseInt(getNiceSelectValue($('#scheduleFromIndex')), 10) || parseInt($('#scheduleFromIndex').val(), 10) || 1;
    }

    function formatRs(n) {
        return '₹' + Number(n || 0).toLocaleString('en-IN', { maximumFractionDigits: 2 });
    }

    function refreshTotals() {
        if (isQuickForm) {
            var monthly = monthlyTotalFromRows();
            var invoiceTotal = invoiceTotalFromRows();
            if (typeof currency_format === 'function') {
                $('.showTotalAmount').text(currency_format(monthly));
                $('.showSubTotalDiscount').text(currency_format(invoiceTotal));
            } else {
                $('.showTotalAmount').text(formatRs(monthly));
                $('.showSubTotalDiscount').text(formatRs(invoiceTotal));
            }
            updateQuickSummary();
            if ((parseFloat($('#fiwCollectAmount').val()) || 0) > 0) {
                syncCollectNow();
            }
            return;
        }
        if (typeof window.feesModuleRecalculate === 'function') window.feesModuleRecalculate();
        else $('.fee-preset-amount, .amount').first().trigger('keyup');
    }

    function monthlyTotalFromRows() {
        var total = 0;
        $('.allFeesTypes .fee-preset-amount').each(function () {
            total += parseFloat($(this).val()) || parseFloat($(this).data('monthly')) || 0;
        });
        return total;
    }

    function invoiceTotalFromRows() {
        var total = 0;
        $('.allFeesTypes .inputSubTotal').each(function () {
            total += parseFloat($(this).val()) || 0;
        });
        return total;
    }

    var feeMonthSchedule = [];
    var feeScheduleStartIndex = 0;
    var academicYearMeta = {};

    function getAcademicMeta() {
        if (academicYearMeta && academicYearMeta.months) return academicYearMeta;
        try { academicYearMeta = JSON.parse($('#academicYearMonthsData').text() || '{}'); }
        catch (e) { academicYearMeta = {}; }
        return academicYearMeta;
    }

    function pad2(n) { return (n < 10 ? '0' : '') + n; }
    function formatIsoDate(d) {
        return d.getFullYear() + '-' + pad2(d.getMonth() + 1) + '-' + pad2(d.getDate());
    }
    function formatDisplayDate(d) {
        var months = ['Jan','Feb','Mar','Apr','May','Jun','Jul','Aug','Sep','Oct','Nov','Dec'];
        return pad2(d.getDate()) + ' ' + months[d.getMonth()] + ' ' + d.getFullYear();
    }

    function academicMonthTotal() {
        var meta = getAcademicMeta();
        return meta.month_count ? parseInt(meta.month_count, 10) : ((meta.months || []).length || 12);
    }

    function syncScheduleIndices() {
        var from = getScheduleFromValue();
        var months = getPayMonthsValue();
        var totalMonths = academicMonthTotal();
        $('#scheduleToIndex').val(Math.min(totalMonths, from + months - 1));
        feeScheduleStartIndex = Math.max(0, from - 1);
    }

    function buildClientSchedule() {
        var meta = getAcademicMeta();
        var dueDay = Math.min(28, Math.max(1, parseInt($('#feeDueDay').val(), 10) || meta.default_due_day || 25));
        if (!meta.start_date) { feeMonthSchedule = []; return []; }
        syncScheduleIndices();
        var parts = meta.start_date.split('-');
        var start = new Date(parseInt(parts[0], 10), parseInt(parts[1], 10) - 1, 1);
        var schedule = [];
        for (var i = 0; i < academicMonthTotal(); i++) {
            var period = new Date(start.getFullYear(), start.getMonth() + i, 1);
            var dim = new Date(period.getFullYear(), period.getMonth() + 1, 0).getDate();
            var due = new Date(period.getFullYear(), period.getMonth(), Math.min(dueDay, dim));
            schedule.push({
                month_index: i + 1,
                month_label: (meta.months[i] && meta.months[i].label) ? meta.months[i].label : ('Month ' + (i + 1)),
                due_date: formatIsoDate(due),
                due_display: formatDisplayDate(due),
                status: 'pending'
            });
        }
        feeMonthSchedule = schedule;
        syncPayMonthsOptions();
        return schedule;
    }

    function syncPayMonthsOptions() {
        var from = getScheduleFromValue();
        var maxMonths = Math.max(1, academicMonthTotal() - from + 1);
        var current = getPayMonthsValue();
        var $pay = $('#payMonths');
        if (!$pay.length) return;
        if ($pay.find('option').length !== maxMonths) {
            $pay.empty();
            for (var m = 1; m <= maxMonths; m++) {
                $pay.append($('<option>', { value: m, text: m + ' month' + (m > 1 ? 's' : '') }));
            }
        }
        $pay.val(Math.min(Math.max(1, current), maxMonths));
        niceUpdate($pay);
        syncScheduleIndices();
    }

    function scheduleQueryParams() {
        return {
            schedule_from_index: getScheduleFromValue(),
            schedule_to_index: $('#scheduleToIndex').val(),
            fee_due_day: $('#feeDueDay').val()
        };
    }

    function updateQuickSummary() {
        if (!isQuickForm) return;
        var months = getPayMonthsValue();
        var monthly = monthlyTotalFromRows();
        var lineTotal = invoiceTotalFromRows() || (monthly * months);
        var due = $('#fiwDueDisplay').text().trim() || $('#due_date').val() || '—';
        var $line = $('#feeSummaryLine');
        if (!isEditMode) {
            if (!getNiceSelectValue($('#selectStudent')) || !getNiceSelectValue($('#feeUnitSelect'))) {
                $line.text('Search or select student and unit to see total');
                return;
            }
        }
        if (monthly <= 0 && lineTotal <= 0) {
            $line.text(months + ' month' + (months > 1 ? 's' : '') + ' · fees loading…');
            return;
        }
        $line.html('<strong>' + formatRs(lineTotal) + '</strong> · ' + months + ' month' + (months > 1 ? 's' : '') + ' · due ' + due);
        updateLivePreview(lineTotal, months, due);
    }

    function invoicePreviewPattern(unitId) {
        var base = ($('#feeInvoicePattern').text() || '').trim() || 'Auto';
        if (!unitId || !feeUnitAbbrevMap[unitId]) return base;
        var unitLen = parseInt($('#feeInvoicePattern').data('unitLen'), 10) || 4;
        if (base.length >= unitLen) {
            return String(feeUnitAbbrevMap[unitId]) + base.slice(unitLen);
        }
        return String(feeUnitAbbrevMap[unitId]);
    }

    function updateLivePreview(lineTotal, months, due) {
        if (!isQuickForm || !$('#fiwPreviewPanel').length) return;
        months = months || getPayMonthsValue();
        lineTotal = lineTotal || invoiceTotalFromRows() || 0;
        due = due || $('#fiwDueDisplay').text().trim() || $('#due_date').val() || '—';
        var monthly = monthlyTotalFromRows();
        var perMonth = months > 0 ? Math.round((lineTotal / months) * 100) / 100 : lineTotal;
        $('#fiwPreviewTotal').text(formatRs(lineTotal));
        var unitId = getNiceSelectValue($('#feeUnitSelect')) || $('#preset_unit_id').val();
        $('#fiwPreviewInvoiceNo').text(invoicePreviewPattern(unitId));
        var $list = $('#fiwPreviewMonths').empty();
        if (!feeMonthSchedule.length || monthly <= 0) {
            $list.append('<li class="text-muted">Fee lines will appear after unit loads</li>');
            return;
        }
        var slice = feeMonthSchedule.slice(feeScheduleStartIndex, feeScheduleStartIndex + months);
        slice.forEach(function (m, i) {
            var amt = i === slice.length - 1
                ? Math.round((lineTotal - perMonth * (slice.length - 1)) * 100) / 100
                : perMonth;
            $list.append('<li><span>' + escapeHtml(m.month_label) + '</span><span>' + formatRs(amt) + ' · due ' + escapeHtml(m.due_display || m.due_date) + '</span></li>');
        });
        updateAdvancedHint(months);
    }

    function updateAdvancedHint(months) {
        var from = getScheduleFromValue();
        var meta = getAcademicMeta();
        var label = (meta.months[from - 1] && meta.months[from - 1].label) ? meta.months[from - 1].label : ('Month ' + from);
        $('.fiw-advanced-hint').text('— ' + months + ' month' + (months > 1 ? 's' : '') + ', ' + label);
    }

    function showStudentPill(item) {
        if (!item) {
            $('#fiwStudentPill').hide().empty();
            return;
        }
        var html = '<strong>' + escapeHtml(item.full_name) + '</strong> · ' +
            escapeHtml(item.class_name) + ' · ' + escapeHtml(item.section_name);
        if (item.admission_no) html += ' · Adm ' + escapeHtml(String(item.admission_no));
        $('#fiwStudentPill').html(html).show();
    }

    function checkDuplicateMonths() {
        if (!isQuickForm || isEditMode) return;
        var recordId = getNiceSelectValue($('#selectStudent'));
        var unitId = getNiceSelectValue($('#feeUnitSelect'));
        if (!recordId || recordId === 'all_student' || !unitId) {
            $('#fiwDuplicateWarning').hide().empty();
            return;
        }
        $.get(baseUrl + '/fees/invoice-exists', {
            record_id: recordId,
            unit_id: unitId,
            schedule_from_index: getScheduleFromValue(),
            pay_months: getPayMonthsValue(),
            fee_due_day: $('#feeDueDay').val()
        }).done(function (data) {
            var $warn = $('#fiwDuplicateWarning');
            if (!data || !data.has_duplicates) {
                $warn.hide().empty();
                return;
            }
            var labels = (data.duplicates || []).map(function (d) { return d.month_label; }).join(', ');
            $warn.html('<strong>Invoice already exists</strong> for: ' + escapeHtml(labels) +
                '. Deselect those months or choose a different start month.').show();
        });
    }

    function saveDraft() {
        if (!isQuickForm || isEditMode) return;
        try {
            localStorage.setItem(DRAFT_KEY, JSON.stringify({
                class_id: getNiceSelectValue($('#select_class')),
                section_id: getNiceSelectValue($('#select_section')),
                record_id: getNiceSelectValue($('#selectStudent')),
                unit_id: getNiceSelectValue($('#feeUnitSelect')),
                pay_months: getPayMonthsValue(),
                schedule_from_index: getScheduleFromValue(),
                fee_due_day: $('#feeDueDay').val(),
                due_date: $('#due_date').val(),
                collect_amount: $('#fiwCollectAmount').val() || '',
                collect_method: getNiceSelectValue($('#fiwCollectMethod')),
                collect_bank: getNiceSelectValue($('#fiwCollectBank')),
                saved_at: Date.now()
            }));
        } catch (e) {}
    }

    function restoreDraft() {
        if (!isQuickForm || isEditMode) return;
        try {
            var raw = localStorage.getItem(DRAFT_KEY);
            if (!raw) return;
            var draft = JSON.parse(raw);
            if (!draft || !draft.record_id) return;
            if (draft.saved_at && (Date.now() - draft.saved_at) > 7 * 24 * 60 * 60 * 1000) {
                localStorage.removeItem(DRAFT_KEY);
                return;
            }
            if (draft.class_id) {
                $('#select_class').val(draft.class_id);
                niceUpdate($('#select_class'));
                loadSectionsAndThen(function () {
                    if (draft.section_id) {
                        $('#select_section').val(draft.section_id);
                        niceUpdate($('#select_section'));
                    }
                    loadStudentsAndThen(function () {
                        if (draft.record_id) {
                            $('#selectStudent').val(draft.record_id);
                            niceUpdate($('#selectStudent'));
                        }
                        if (draft.unit_id) {
                            $('#feeUnitSelect').val(draft.unit_id);
                            niceUpdate($('#feeUnitSelect'));
                        }
                        if (draft.pay_months) {
                            $('#payMonths').val(draft.pay_months);
                            niceUpdate($('#payMonths'));
                        }
                        if (draft.schedule_from_index) {
                            $('#scheduleFromIndex').val(draft.schedule_from_index);
                            niceUpdate($('#scheduleFromIndex'));
                        }
                        if (draft.fee_due_day) {
                            $('#feeDueDay').val(draft.fee_due_day);
                            $('#fiwDueDayInput').val(draft.fee_due_day);
                        }
                        if (draft.collect_amount) $('#fiwCollectAmount').val(draft.collect_amount);
                        if (draft.collect_method) {
                            $('#fiwCollectMethod').val(draft.collect_method);
                            niceUpdate($('#fiwCollectMethod'));
                            $('#fiwCollectBankWrap').toggle(draft.collect_method === 'Bank');
                        }
                        if (draft.collect_bank) {
                            $('#fiwCollectBank').val(draft.collect_bank);
                            niceUpdate($('#fiwCollectBank'));
                        }
                        tryAutoLoad();
                    });
                });
            }
        } catch (e) {}
    }

    function clearDraft() {
        try { localStorage.removeItem(DRAFT_KEY); } catch (e) {}
    }

    function totalPaidFromRows() {
        var total = 0;
        $('.allFeesTypes .paidAmount').each(function () {
            total += parseFloat($(this).val()) || 0;
        });
        return Math.round(total * 100) / 100;
    }

    function updateAllocationSummary() {
        var collect = parseFloat($('#fiwCollectAmount').val()) || 0;
        var allocated = totalPaidFromRows();
        var remaining = Math.round((collect - allocated) * 100) / 100;
        var $summary = $('#fiwAllocationSummary');
        if (!$summary.length) return;

        if (collect <= 0) {
            $summary.text('').removeClass('text-danger');
            return;
        }

        var text = 'Allocated: ' + formatRs(allocated);
        if (Math.abs(remaining) > 0.02) {
            text += ' · Remaining: ' + formatRs(remaining);
            $summary.addClass('text-danger').removeClass('text-muted');
        } else {
            text += ' · Balanced';
            $summary.removeClass('text-danger').addClass('text-muted');
        }
        $summary.text(text);
    }

    function feeRowLabel($row) {
        var $labelCell = $row.find('td').eq(1);
        return $.trim($labelCell.text().replace(/\s*\(custom\)\s*/i, ''));
    }

    function feeRowsWithAmounts() {
        return $('.allFeesTypes tr').filter(function () {
            return $(this).find('[name^="groups["]').length > 0 && $(this).find('.inputSubTotal').length > 0;
        });
    }

    function proportionalSplit(collect, feeTotal, sub) {
        if (!(collect > 0) || !(feeTotal > 0) || !(sub > 0)) return 0;
        return Math.round(collect * (sub / feeTotal) * 100) / 100;
    }

    function renderBifurcationPanel() {
        var collect = parseFloat($('#fiwCollectAmount').val()) || 0;
        var invoiceTotal = invoiceTotalFromRows();
        var $body = $('#fiwBifurcationBody');
        var $foot = $('#fiwBifurcationFoot');
        var $preview = $('#fiwBifurcationPreview');
        if (!$body.length) return;

        $body.empty();
        if (collect <= 0 || invoiceTotal <= 0) {
            $body.append('<tr class="fiw-bifurcation-empty"><td colspan="4" class="text-muted">Enter a collection amount to see the split.</td></tr>');
            $foot.hide();
            if ($preview.length) $preview.text('');
            updateAllocationSummary();
            return;
        }

        var pctOfInvoice = Math.round((collect / invoiceTotal) * 10000) / 100;
        if ($preview.length) {
            $preview.text('(' + pctOfInvoice + '% of invoice · auto-split by fee share)');
        }

        var $rows = feeRowsWithAmounts();
        var remaining = collect;
        var rowCount = $rows.length;
        var rowIndex = 0;

        $rows.each(function () {
            var $row = $(this);
            var sub = parseFloat($row.find('.inputSubTotal').val()) || 0;
            var sharePct = invoiceTotal > 0 ? Math.round((sub / invoiceTotal) * 10000) / 100 : 0;
            var paid = 0;
            if (sub > 0) {
                if (rowIndex === rowCount - 1) {
                    paid = Math.round(remaining * 100) / 100;
                } else {
                    paid = proportionalSplit(collect, invoiceTotal, sub);
                    remaining -= paid;
                }
            }
            var currentPaid = parseFloat($row.find('.paidAmount').val());
            if (allocationMode === 'manual' && !isNaN(currentPaid) && currentPaid >= 0) {
                paid = currentPaid;
            } else {
                $row.find('.paidAmount').val(paid > 0 ? paid.toFixed(2) : '');
            }

            var label = feeRowLabel($row);
            var $tr = $('<tr></tr>').attr('data-row-index', rowIndex);
            $tr.append($('<td></td>').text(label));
            $tr.append($('<td class="text-right"></td>').text(formatRs(sub)));
            $tr.append($('<td class="text-right"></td>').text(sharePct.toFixed(2) + '%'));

            if (allocationMode === 'manual' && canManageAllocation) {
                $tr.append(
                    $('<td class="text-right"></td>').append(
                        $('<input type="number" min="0" step="0.01" class="primary_input_field form-control fiw-bifurc-paid-input fiw-bifurc-paid">')
                            .val(paid > 0 ? paid.toFixed(2) : '')
                            .attr('data-row-index', rowIndex)
                    )
                );
            } else {
                $tr.append($('<td class="text-right"></td>').text(paid > 0 ? formatRs(paid) : '—'));
            }

            $body.append($tr);
            rowIndex++;
        });

        $('#fiwBifurcationTotal').text(formatRs(totalPaidFromRows()));
        $foot.show();
        updateAllocationSummary();
    }

    function setAllocationMode(mode) {
        allocationMode = mode === 'manual' && canManageAllocation ? 'manual' : 'auto';
        $('#paymentAllocationMode').val(allocationMode);
        if ($('#fiwCustomAllocation').length) {
            $('#fiwCustomAllocation').prop('checked', allocationMode === 'manual');
        }
        if (allocationMode === 'manual') {
            $('#fiwAllocationHint').text('Enter how much goes to each fee type. Total must match the collection amount.');
        } else {
            $('#fiwAllocationHint').text('By default, payment is split by each fee type\'s share of the invoice total (same percentage for every line).');
        }
        syncCollectNow();
    }

    function syncCollectNow() {
        if (!isQuickForm) return;
        var collect = parseFloat($('#fiwCollectAmount').val()) || 0;
        var total = invoiceTotalFromRows();
        if (collect <= 0) {
            $('#paymentStatus').val('not');
            $('.allFeesTypes .paidAmount').val('');
            renderBifurcationPanel();
            return true;
        }
        var method = getNiceSelectValue($('#fiwCollectMethod')) || $('#fiwCollectMethod').val();
        if (!method) {
            toastr.error('Select a payment method for collection', 'Required');
            return false;
        }
        if (method === 'Bank' && !getNiceSelectValue($('#fiwCollectBank'))) {
            toastr.error('Select a bank account', 'Required');
            return false;
        }
        $('#paymentStatus').val(collect >= total - 0.02 ? 'full' : 'partial');

        if (allocationMode !== 'manual') {
            var $feeRows = feeRowsWithAmounts();
            var remaining = collect;
            var feeTotal = total;
            var count = $feeRows.length;
            var i = 0;
            $feeRows.each(function () {
                var $row = $(this);
                var sub = parseFloat($row.find('.inputSubTotal').val()) || 0;
                var paid = 0;
                if (feeTotal > 0 && sub > 0) {
                    if (i === count - 1) {
                        paid = Math.round(remaining * 100) / 100;
                    } else {
                        paid = proportionalSplit(collect, feeTotal, sub);
                        remaining -= paid;
                    }
                }
                $row.find('.paidAmount').val(paid > 0 ? paid.toFixed(2) : '');
                i++;
            });
        }

        renderBifurcationPanel();
        return true;
    }

    function renderSearchResults(items) {
        searchResults = items || [];
        searchActiveIndex = -1;
        var $box = $('#fiwSearchResults');
        $box.empty();
        if (!searchResults.length) {
            $box.attr('hidden', true);
            $('#fiwStudentSearch').attr('aria-expanded', 'false');
            return;
        }
        searchResults.forEach(function (item, idx) {
            var sub = [item.class_name, item.section_name, item.admission_no ? 'Adm ' + item.admission_no : ''].filter(Boolean).join(' · ');
            $box.append(
                $('<button type="button" class="fiw-search-item" role="option">')
                    .attr('data-index', idx)
                    .html('<span class="fiw-search-item__name">' + escapeHtml(item.full_name) + '</span>' +
                        '<span class="fiw-search-item__meta">' + escapeHtml(sub) + '</span>')
            );
        });
        $box.removeAttr('hidden');
        $('#fiwStudentSearch').attr('aria-expanded', 'true');
    }

    function highlightSearchItem(idx) {
        var $items = $('#fiwSearchResults .fiw-search-item');
        $items.removeClass('is-active');
        if (idx >= 0 && idx < $items.length) {
            $items.eq(idx).addClass('is-active');
            searchActiveIndex = idx;
        }
    }

    function revealUnitRow() {
        var $row = $('#feeUnitRow');
        if ($row.length && $row.is(':hidden')) {
            $row.show();
            niceUpdate($('#feeUnitSelect'));
        }
        var $schedule = $('#fiwAdvancedSchedule');
        if ($schedule.length && !$schedule.attr('open')) {
            $schedule.attr('open', true);
        }
    }

    function selectSearchResult(item) {
        if (!item) return;
        $('#fiwSearchResults').attr('hidden', true).empty();
        $('#fiwStudentSearch').val('').attr('aria-expanded', 'false');
        showStudentPill(item);
        revealUnitRow();
        $('#fiwRepeatLast').toggle(!!item.last_invoice).data('recordId', item.record_id);
        $('#select_class').val(item.class_id);
        niceUpdate($('#select_class'));
        loadSectionsAndThen(function () {
            $('#select_section').val(item.section_id);
            niceUpdate($('#select_section'));
            loadStudentsAndThen(function () {
                $('#selectStudent').val(item.record_id);
                niceUpdate($('#selectStudent'));
                var unitId = item.default_unit_id || invoicePrefs.unit_id;
                if (unitId) {
                    $('#feeUnitSelect').val(unitId);
                    niceUpdate($('#feeUnitSelect'));
                } else {
                    autoSelectUnit();
                }
                bulkMode = false;
                loadFeeLines(false);
                checkDuplicateMonths();
                saveDraft();
            });
        });
    }

    function loadSectionsAndThen(done) {
        var classId = getNiceSelectValue($('#select_class'));
        var $sec = $('#select_section');
        if (!classId) { if (done) done(); return; }
        $.ajax({
            url: baseUrl + '/fees/ajax-get-all-section',
            data: { class_id: classId },
            dataType: 'json'
        }).done(function (data) {
            $sec.empty().append('<option value="">Select section</option>');
            $.each(data[0] || data || [], function (i, sec) {
                if (sec && sec.id) $sec.append($('<option>', { value: sec.id, text: sec.section_name }));
            });
            niceUpdate($sec);
            if (done) done();
        }).fail(function () { if (done) done(); });
    }

    function loadStudentsAndThen(done) {
        var classId = getNiceSelectValue($('#select_class'));
        var sectionId = getNiceSelectValue($('#select_section'));
        var $stu = $('#selectStudent');
        if (!classId || !sectionId) { if (done) done(); return; }
        $('#selectStudentLoader').show();
        $.get(baseUrl + '/fees/ajax-section-all-student', { class_id: classId, section_id: sectionId }, function (data) {
            $stu.empty().append('<option value="">Select student</option>');
            $stu.append($('<option>', { value: 'all_student', text: 'All Students' }));
            $.each(data[0] || [], function (i, rec) {
                if (rec.student_detail && rec.student_detail.full_name) {
                    var roll = rec.roll_no || '';
                    var sec = rec.section ? rec.section.section_name : '';
                    $stu.append($('<option>', {
                        value: rec.id,
                        text: rec.student_detail.full_name + ' (' + sec + ' - ' + roll + ')'
                    }));
                }
            });
            niceUpdate($stu);
            if (done) done();
        }).fail(function () { if (done) done(); }).always(function () {
            $('#selectStudentLoader').hide();
        });
    }

    function initStudentSearch() {
        if (!isQuickForm || isEditMode) return;
        var $input = $('#fiwStudentSearch');
        if (!$input.length) return;

        $input.on('input', function () {
            var q = $.trim($input.val());
            clearTimeout(searchDebounce);
            if (q.length < 2) {
                renderSearchResults([]);
                return;
            }
            searchDebounce = setTimeout(function () {
                $.get(baseUrl + '/fees/student-search', { q: q }, function (data) {
                    renderSearchResults(data.results || []);
                });
            }, 220);
        });

        $input.on('keydown', function (e) {
            var $items = $('#fiwSearchResults .fiw-search-item');
            if (e.key === 'ArrowDown') {
                e.preventDefault();
                highlightSearchItem(Math.min(searchActiveIndex + 1, $items.length - 1));
            } else if (e.key === 'ArrowUp') {
                e.preventDefault();
                highlightSearchItem(Math.max(searchActiveIndex - 1, 0));
            } else if (e.key === 'Enter') {
                if (searchActiveIndex >= 0 && searchResults[searchActiveIndex]) {
                    e.preventDefault();
                    selectSearchResult(searchResults[searchActiveIndex]);
                }
            } else if (e.key === 'Escape') {
                renderSearchResults([]);
            }
        });

        $(document).on('click', '#fiwSearchResults .fiw-search-item', function () {
            var idx = parseInt($(this).data('index'), 10);
            if (searchResults[idx]) selectSearchResult(searchResults[idx]);
        });

        $(document).on('click', function (e) {
            if (!$(e.target).closest('.fiw-search-wrap').length) {
                renderSearchResults([]);
            }
        });

        $(document).on('keydown', function (e) {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                $input.trigger('focus');
            }
        });
    }

    function applyRepeatLast(recordId) {
        $.get(baseUrl + '/fees/repeat-last-invoice/' + recordId, function (data) {
            if (data.schedule_from_index) {
                $('#scheduleFromIndex').val(data.schedule_from_index);
                niceUpdate($('#scheduleFromIndex'));
            }
            if (data.pay_months) {
                $('#payMonths').val(data.pay_months);
                niceUpdate($('#payMonths'));
            }
            if (data.fee_due_day) $('#feeDueDay').val(data.fee_due_day);
            if (data.default_unit_id) {
                $('#feeUnitSelect').val(data.default_unit_id);
                niceUpdate($('#feeUnitSelect'));
            }
            dueDateManual = false;
            loadFeeLines(true);
            toastr.success('Loaded settings from last invoice', 'Repeat');
        }).fail(function () {
            toastr.warning('No previous invoice found', 'Repeat');
        });
    }

    function applyDueDates(months) {
        if (!feeMonthSchedule.length) return;
        syncScheduleIndices();
        var slice = feeMonthSchedule.slice(feeScheduleStartIndex, feeScheduleStartIndex + months);
        if (!slice.length) return;
        var first = slice[0];
        var display = first.due_display || first.due_date || '';
        $('#due_date').val(first.due_date || display);
        $('#fiwDueDisplay').text(display || '—');
        $('.fee-next-due-display').text(display);
        updateQuickSummary();
    }

    function isPresetFeeRow($row) {
        return $row.find('[name*="[preset_line]"]').val() === '1'
            || $row.find('.fee-preset-amount').length > 0;
    }

    function applyPayMonths() {
        var months = getPayMonthsValue();
        if ($('#feeMonthsCount').length) $('#feeMonthsCount').val(months);
        $('.allFeesTypes tr').each(function () {
            var $row = $(this);
            if (!$row.find('[name^="groups["]').length) return;
            var $input = $row.find('.fee-preset-amount').first();
            if (!$input.length) $input = $row.find('.amount').first();
            if (!$input.length) return;
            var monthly = parseFloat($input.val()) || parseFloat($input.data('monthly')) || 0;
            var weaver = parseFloat($row.find('.weaver').val()) || 0;
            var isPreset = isPresetFeeRow($row);
            if (isPreset) {
                $input.data('monthly', monthly);
            }
            var periodTotal = isPreset
                ? Math.max(0, Math.round((monthly * months - weaver) * 100) / 100)
                : Math.max(0, Math.round((monthly - weaver) * 100) / 100);
            $row.find('.inputSubTotal').val(periodTotal);
            $row.find('.period-total, .subTotal').text(periodTotal.toFixed(2));
        });
        var monthly = monthlyTotalFromRows();
        $('#monthly_total_amount').val(monthly.toFixed(2));
        $('#feeMonthlyTotal').text(formatRs(monthly));
        $('#feePayingTotal').text(formatRs(invoiceTotalFromRows()));
        var $table = $('.fees_invoice_type_table');
        if ($table.length) {
            $table.find('thead th').eq(2).html('<strong>Monthly</strong>');
            $table.find('thead th').eq(4).html('<span class="text-muted">' + (months > 1 ? 'Total (' + months + ' mo)' : 'Total') + '</span>');
        }
        applyDueDates(months);
        refreshTotals();
        if ((parseFloat($('#fiwCollectAmount').val()) || 0) > 0) {
            syncCollectNow();
        }
        checkDuplicateMonths();
        saveDraft();
        if (bulkMode) loadBulkPreview();
    }

    function loadMonthScheduleFromDom(preserveFromIndex) {
        var fromHidden = parseInt($('#feeScheduleFromIndex').val(), 10);
        feeMonthSchedule = [];
        try {
            var data = $('#feeMonthScheduleData');
            if (data.length) feeMonthSchedule = JSON.parse(data.text() || '[]');
        } catch (e) {}
        if (feeMonthSchedule.length) {
            var fromIndex = preserveFromIndex || fromHidden || getScheduleFromValue();
            $('#scheduleFromIndex').val(fromIndex);
            niceUpdate($('#scheduleFromIndex'));
            syncScheduleIndices();
            syncPayMonthsOptions();
        } else {
            buildClientSchedule();
        }
    }

    function nextGroupIndex() {
        var max = -1;
        $('.allFeesTypes [name^="groups["]').each(function () {
            var m = $(this).attr('name').match(/groups\[(\d+)\]/);
            if (m) max = Math.max(max, parseInt(m[1], 10));
        });
        return max + 1;
    }

    function feeTypeAlreadyAdded(typeVal) {
        var found = false;
        $('.allFeesTypes .fees').each(function () {
            if ($(this).val() === typeVal) found = true;
        });
        return found;
    }

    function normalizeExtraFeeRow($rows) {
        var idx = nextGroupIndex();
        $rows.each(function () {
            var $row = $(this);
            $row.addClass('fee-extra-line');
            $row.find('[name^="types["], [name^="groups["]').each(function () {
                var $el = $(this);
                var name = $el.attr('name')
                    .replace(/types\[\d+\]/, 'groups[' + idx + ']')
                    .replace(/groups\[\d+\]/, 'groups[' + idx + ']');
                $el.attr('name', name);
            });
            if (!$row.find('[name*="[preset_line]"]').length) {
                var presetName = 'groups[' + idx + '][preset_line]';
                $row.find('[name*="[feesType]"]').first().before(
                    $('<input>', { type: 'hidden', name: presetName, value: '0' })
                );
            }
            if (!$row.find('.fee-next-due-display').length) {
                $row.find('.inputSubTotal').after('<td class="fee-next-due-display fiw-col-hide text-muted">One-time</td>');
            }
            $row.find('.paidAmount').val('');
            idx++;
        });
    }

    function escapeHtml(text) {
        return $('<div>').text(text || '').html();
    }

    function appendCustomFeeLine(label, amount) {
        label = $.trim(label);
        amount = parseFloat(amount);
        if (!label) {
            toastr.error('Enter a description for the custom line', 'Required');
            return;
        }
        if (!(amount > 0)) {
            toastr.error('Enter a valid amount greater than zero', 'Required');
            return;
        }
        var idx = nextGroupIndex();
        var sl = $('.allFeesTypes tr').filter(function () {
            return $(this).find('[name^="groups["]').length > 0;
        }).length + 1;
        var $row = $('<tr class="fee-extra-line"></tr>');
        $row.append('<td>' + sl + '</td>');
        $row.append('<td>' + escapeHtml(label) + ' <small class="text-muted">(custom)</small></td>');
        $row.append($('<input>', { type: 'hidden', name: 'groups[' + idx + '][feesType]', value: '0' }));
        $row.append($('<input>', { type: 'hidden', name: 'groups[' + idx + '][preset_line]', value: '0' }));
        $row.append($('<input>', { type: 'hidden', name: 'groups[' + idx + '][note]', value: label }));
        $row.append(
            '<td><div class="primary_input">' +
            '<input class="primary_input_field form-control amount" min="0" type="number" step="0.01" ' +
            'name="groups[' + idx + '][amount]" value="' + amount.toFixed(2) + '" title="One-time amount">' +
            '</div></td>'
        );
        $row.append(
            '<td><div class="primary_input">' +
            '<input class="primary_input_field form-control weaver" min="0" type="number" step="0.01" ' +
            'name="groups[' + idx + '][weaver]" value="0" title="Waiver / discount">' +
            '</div></td>'
        );
        $row.append('<td class="subTotal text-muted">' + amount.toFixed(2) + '</td>');
        $row.append($('<input>', {
            type: 'hidden',
            name: 'groups[' + idx + '][sub_total]',
            'class': 'inputSubTotal',
            value: amount.toFixed(2)
        }));
        $row.append('<td class="fee-next-due-display fiw-col-hide text-muted">One-time</td>');
        $row.append($('<input>', {
            type: 'hidden',
            'class': 'paidAmount',
            name: 'groups[' + idx + '][paid_amount]',
            value: ''
        }));
        $row.append(
            '<td><button class="primary-btn icon-only fix-gr-bg" type="button" data-tooltip="tooltip" ' +
            'title="Delete" id="deleteField"><span class="ti-trash"></span></button></td>'
        );
        $('.allFeesTypes').append($row);
        applyPayMonths();
        $('#customFeeLineLabel').val('');
        $('#customFeeLineAmount').val('');
    }

    function appendExtraFeeType(type) {
        if (!type) return;
        if (feeTypeAlreadyAdded(type) && type.slice(0, 3) !== 'grp') {
            toastr.warning('This fee type is already on the invoice', 'Warning');
            return;
        }
        $.post(baseUrl + '/fees/select-fees-type', {
            type: type,
            editData: '',
            _token: $('meta[name="csrf-token"]').attr('content') || $('input[name="_token"]').val()
        }, function (html) {
            var $rows = $(html).filter('tr');
            if (!$rows.length) $rows = $(html).find('tr');
            if (!$rows.length) return;
            normalizeExtraFeeRow($rows);
            $('.allFeesTypes').append($rows);
            $('.allFeesTypes .fee-preset-month-note').remove();
            applyPayMonths();
            $('#selectExtraFeesType').val('');
            niceUpdate($('#selectExtraFeesType'));
        }).fail(function () {
            toastr.error('Could not add fee type', 'Error');
        });
    }

    function autoSelectUnit() {
        var $u = $('#feeUnitSelect');
        var opts = $u.find('option').filter(function () { return $(this).val(); });
        if (opts.length === 1) {
            $u.val(opts.first().val());
            niceUpdate($u);
        }
    }

    function tryAutoLoad() {
        if (isEditMode) return;
        var classId = getNiceSelectValue($('#select_class'));
        var sectionId = getNiceSelectValue($('#select_section'));
        var studentId = getNiceSelectValue($('#selectStudent'));
        var unitId = getNiceSelectValue($('#feeUnitSelect'));
        if (!classId || !sectionId || !studentId || !unitId) {
            updateQuickSummary();
            return;
        }
        loadFeeLines(true);
    }

    function loadSections() {
        var classId = getNiceSelectValue($('#select_class'));
        var $sec = $('#select_section');
        if (!classId) {
            $sec.html('<option value="">Select class first</option>');
            niceUpdate($sec);
            return;
        }
        $sec.html('<option value="">Loading…</option>');
        niceUpdate($sec);
        $.ajax({
            url: baseUrl + '/fees/ajax-get-all-section',
            data: { class_id: classId },
            dataType: 'json'
        }).done(function (data) {
            $sec.empty().append('<option value="">Select section</option>');
            $.each(data[0] || data || [], function (i, sec) {
                if (sec && sec.id) $sec.append($('<option>', { value: sec.id, text: sec.section_name }));
            });
            niceUpdate($sec);
        }).fail(function () {
            $sec.html('<option value="">Could not load sections</option>');
            niceUpdate($sec);
            toastr.error('Failed to load sections', 'Error');
        });
        $('#selectStudent').html('<option value="">Select section first</option>');
        niceUpdate($('#selectStudent'));
    }

    function loadStudents() {
        var classId = getNiceSelectValue($('#select_class'));
        var sectionId = getNiceSelectValue($('#select_section'));
        var $stu = $('#selectStudent');
        if (!classId || !sectionId) {
            $stu.html('<option value="">Select section first</option>');
            niceUpdate($stu);
            return;
        }
        $('#selectStudentLoader').show();
        $.get(baseUrl + '/fees/ajax-section-all-student', { class_id: classId, section_id: sectionId }, function (data) {
            $stu.empty().append('<option value="">Select student</option>');
            $stu.append($('<option>', { value: 'all_student', text: 'All Students' }));
            $.each(data[0] || [], function (i, rec) {
                if (rec.student_detail && rec.student_detail.full_name) {
                    var roll = rec.roll_no || '';
                    var sec = rec.section ? rec.section.section_name : '';
                    $stu.append($('<option>', {
                        value: rec.id,
                        text: rec.student_detail.full_name + ' (' + sec + ' - ' + roll + ')'
                    }));
                }
            });
            niceUpdate($stu);
            autoSelectUnit();
        }).fail(function () {
            toastr.error('Failed to load students', 'Error');
        }).always(function () {
            $('#selectStudentLoader').hide();
        });
    }

    function resolveServiceLine() {
        var svc = ($('#feeUnitSelect option:selected').data('service') || '').toString().toLowerCase();
        return svc === 'hostel' ? 'hostel' : 'school';
    }

    function loadFeeLines(preserveMonths) {
        var classId = getNiceSelectValue($('#select_class'));
        var unitId = getNiceSelectValue($('#feeUnitSelect'));
        var recordId = getNiceSelectValue($('#selectStudent'));
        if (!classId || !unitId || !recordId) return;

        var savedMonths = preserveMonths ? getPayMonthsValue() : null;
        var savedFrom = preserveMonths ? getScheduleFromValue() : null;

        bulkMode = (recordId === 'all_student');
        var params = {
            unit_id: unitId,
            class_id: classId,
            service_line: resolveServiceLine()
        };
        Object.assign(params, scheduleQueryParams());
        if (!bulkMode) params.record_id = recordId;

        $.get(baseUrl + '/fees/unit-fee-preset-rows-for-unit', params, function (html) {
            if (!html || !html.trim()) {
                $('.allFeesTypes').empty();
                updateQuickSummary();
                toastr.warning('No fee rates for this unit/class.', 'Warning');
                return;
            }
            $('.allFeesTypes').html(html);
            $('#preset_unit_id').val(unitId);
            $('#preset_service_line').val(resolveServiceLine());
            if (savedFrom) {
                $('#scheduleFromIndex').val(savedFrom);
                niceUpdate($('#scheduleFromIndex'));
            }
            loadMonthScheduleFromDom(savedFrom || getScheduleFromValue());
            if (savedMonths) {
                $('#payMonths').val(savedMonths);
                niceUpdate($('#payMonths'));
            }
            dueDateManual = false;
            applyPayMonths();
            checkDuplicateMonths();
            saveDraft();
            if (bulkMode) loadBulkPreview();
            else { $('#bulk-fee-preview-wrap').hide(); $('#feeBulkCount').hide(); }
        }).fail(function (xhr) {
            var msg = 'Could not load fees';
            try { if (xhr.responseJSON && xhr.responseJSON.message) msg = xhr.responseJSON.message; } catch (e) {}
            toastr.error(msg, 'Error');
        });
    }

    function loadBulkPreview() {
        if (!bulkMode) return;
        var payMonths = getPayMonthsValue();
        $.get(baseUrl + '/fees/bulk-fee-preview', {
            class_id: getNiceSelectValue($('#select_class')),
            section_id: getNiceSelectValue($('#select_section')),
            unit_id: getNiceSelectValue($('#feeUnitSelect')),
            pay_months: payMonths
        }, function (data) {
            var $body = $('#bulkFeePreviewBody').empty();
            (data.students || []).forEach(function (s) {
                var invoiceCell = canEditInvoice
                    ? '<td><input type="text" class="primary_input_field form-control form-control-sm" name="manual_invoice_numbers[' + s.record_id + ']" placeholder="Auto"></td>'
                    : '';
                $body.append('<tr><td>' + s.name + '</td><td>' + s.section + '</td><td>' + (s.roll_no || '') +
                    '</td><td>' + formatRs(s.monthly) + '</td><td>' + payMonths + '</td><td>' + formatRs(s.paying) + '</td>' +
                    invoiceCell + '<td></td></tr>');
            });
            $('#bulk-fee-preview-wrap').toggle((data.count || 0) > 0);
        });
    }

    function onStudentChange() {
        var recordId = getNiceSelectValue($('#selectStudent'));
        if (!recordId) {
            updateQuickSummary();
            showStudentPill(null);
            $('#fiwRepeatLast').hide();
            return;
        }
        bulkMode = (recordId === 'all_student');
        $('#fiwRepeatLast').toggle(!bulkMode).data('recordId', recordId);
        revealUnitRow();
        tryAutoLoad();
        saveDraft();
    }

    function onUnitChange() {
        if (isEditMode) return;
        if (!getNiceSelectValue($('#select_class'))) return;
        updateQuickSummary();
        tryAutoLoad();
        saveDraft();
    }

    function onMonthsOrScheduleChange() {
        buildClientSchedule();
        applyPayMonths();
    }

    // One-click: bill the complete academic session (e.g. June → March).
    function applyFullAcademicYear() {
        var total = academicMonthTotal();
        var meta = getAcademicMeta();
        $('#scheduleFromIndex').val(1);
        niceUpdate($('#scheduleFromIndex'));
        syncPayMonthsOptions();
        $('#payMonths').val(total);
        niceUpdate($('#payMonths'));
        onMonthsOrScheduleChange();
        saveDraft();
        if (meta.months && meta.months.length) {
            var hint = 'Bills all ' + total + ' months — ' + meta.months[0].label + ' through ' + meta.months[meta.months.length - 1].label;
            if (meta.title) hint += ' (' + meta.title + ')';
            $('#fiwFullYearHint').text(hint + '.');
        }
        if (typeof toastr !== 'undefined') {
            toastr.success('Billing the full academic year (' + total + ' months)', 'Full year');
        }
    }

    niceUpdate($('#feeUnitSelect'));
    niceUpdate($('#select_section'));
    niceUpdate($('#payMonths'));
    niceUpdate($('#scheduleFromIndex'));
    if ($('#selectExtraFeesType').length) niceUpdate($('#selectExtraFeesType'));

    // Refresh niceSelect width once the "add lines" section is revealed.
    $('#fiwAddLines').on('toggle', function () {
        if (this.open) niceUpdate($('#selectExtraFeesType'));
    });

    $(document).on('change', '#select_class', loadSections);
    $(document).on('change', '#select_section', loadStudents);
    $(document).on('change', '#selectStudent', onStudentChange);
    $(document).on('change', '#feeUnitSelect', onUnitChange);
    $(document).on('change', '#payMonths', onMonthsOrScheduleChange);
    $(document).on('change', '#scheduleFromIndex', onMonthsOrScheduleChange);
    $(document).on('click', '#fiwFullYearBtn', function (e) {
        e.preventDefault();
        applyFullAcademicYear();
    });
    $(document).on('change', '#selectExtraFeesType', function () {
        appendExtraFeeType($(this).val());
    });
    $(document).on('click', '#fiwDueAdjustBtn', function () {
        var $wrap = $('#fiwDueDayWrap');
        $wrap.toggle();
        if ($wrap.is(':visible')) $('#fiwDueDayInput').trigger('focus');
    });

    $(document).on('input change', '#fiwDueDayInput', function () {
        var day = Math.min(28, Math.max(1, parseInt($(this).val(), 10) || 25));
        $('#feeDueDay').val(day);
        buildClientSchedule();
        applyPayMonths();
        saveDraft();
    });

    $(document).on('click', '#selectSectionDiv .nice-select .option:not(.disabled)', function () { setTimeout(loadStudents, 200); });
    $(document).on('click', '#selectStudentDiv .nice-select .option:not(.disabled)', function () { setTimeout(onStudentChange, 200); });
    $(document).on('click', '#feeUnitSelectDiv .nice-select .option:not(.disabled)', function () { setTimeout(onUnitChange, 200); });
    $(document).on('click', '#id-card-div .nice-select .option:not(.disabled)', function () { setTimeout(loadSections, 200); });
    $(document).on('click', '#payMonths + .nice-select .option:not(.disabled)', function () {
        setTimeout(onMonthsOrScheduleChange, 150);
    });
    $(document).on('click', '#scheduleFromIndex + .nice-select .option:not(.disabled)', function () {
        setTimeout(onMonthsOrScheduleChange, 150);
    });
    $(document).on('click', '#selectExtraFeesType + .nice-select .option:not(.disabled)', function () {
        setTimeout(function () { appendExtraFeeType(getNiceSelectValue($('#selectExtraFeesType'))); }, 150);
    });
    $(document).on('click', '#btnAddCustomFeeLine', function () {
        appendCustomFeeLine($('#customFeeLineLabel').val(), $('#customFeeLineAmount').val());
    });
    $(document).on('keydown', '#customFeeLineLabel, #customFeeLineAmount', function (e) {
        if (e.key === 'Enter') {
            e.preventDefault();
            appendCustomFeeLine($('#customFeeLineLabel').val(), $('#customFeeLineAmount').val());
        }
    });

    $(document).on('input', '.fee-preset-amount', function () {
        $(this).data('monthly', parseFloat($(this).val()) || 0);
        applyPayMonths();
    });
    $(document).on('input', '.allFeesTypes .weaver', function () {
        applyPayMonths();
    });
    $(document).on('input', '.allFeesTypes .amount:not(.fee-preset-amount)', function () {
        applyPayMonths();
    });

    $(document).on('click', '#deleteField', function () {
        setTimeout(function () {
            reindexGroupFields();
            applyPayMonths();
        }, 50);
    });

    function reindexGroupFields() {
        var i = 0;
        $('.allFeesTypes tr').each(function () {
            var $row = $(this);
            if (!$row.find('[name^="groups["]').length && !$row.find('[name^="types["]').length) return;
            $row.find('[name^="groups["], [name^="types["]').each(function () {
                var name = $(this).attr('name').replace(/(groups|types)\[\d+\]/, 'groups[' + i + ']');
                $(this).attr('name', name);
            });
            i++;
        });
    }

    function syncNativeSelects() {
        $('#fiwQuickForm select.primary_select').each(function () {
            var $sel = $(this);
            var v = getNiceSelectValue($sel);
            if (v !== '') $sel.val(v);
        });
    }

    function validateBeforeSubmit() {
        syncNativeSelects();
        applyPayMonths();
        if (!syncCollectNow()) return false;

        var collect = parseFloat($('#fiwCollectAmount').val()) || 0;
        if (collect > 0) {
            var allocated = totalPaidFromRows();
            if (Math.abs(collect - allocated) > 0.02) {
                toastr.error(
                    'Allocated amounts must equal the collection amount (' + formatRs(collect) + ').',
                    'Allocation mismatch'
                );
                $('#fiwCollectNow').attr('open', true);
                $('#fiwBifurcation').attr('open', true);
                return false;
            }
        }

        var classId = isEditMode
            ? ($('input[name="class"]').val() || $('#select_class').val())
            : getNiceSelectValue($('#select_class'));
        var sectionId = isEditMode
            ? ($('#select_section').val() || getNiceSelectValue($('#select_section')))
            : getNiceSelectValue($('#select_section'));
        var studentId = isEditMode
            ? ($('input[name="student"]').val() || $('#selectStudent').val())
            : getNiceSelectValue($('#selectStudent'));
        var unitId = getNiceSelectValue($('#feeUnitSelect')) || $('#preset_unit_id').val();
        var dueDate = $.trim($('#due_date').val());
        var feeRows = $('.allFeesTypes tr').filter(function () {
            return $(this).find('[name^="groups["]').length > 0;
        }).length;

        if (!classId) { toastr.error('Select class', 'Required'); return false; }
        if (!isEditMode && !sectionId) { toastr.error('Select section', 'Required'); return false; }
        if (!studentId) { toastr.error('Select student', 'Required'); return false; }
        if (!isEditMode && !unitId) { toastr.error('Select unit', 'Required'); return false; }
        if (feeRows < 1) { toastr.error('Add at least one fee line', 'Required'); return false; }
        if (!dueDate) { toastr.error('Due date is required', 'Required'); return false; }

        if (!isEditMode && unitId) {
            $('#preset_unit_id').val(unitId);
            $('#preset_service_line').val(resolveServiceLine());
        }

        return true;
    }

    $(document).on('submit', 'form.form-horizontal', function (e) {
        if (!isQuickForm) return;
        if (!validateBeforeSubmit()) {
            e.preventDefault();
            return false;
        }
        clearDraft();
    });

    $(document).on('click', '#fiwRepeatLast', function () {
        var recordId = $(this).data('recordId') || getNiceSelectValue($('#selectStudent'));
        if (recordId && recordId !== 'all_student') applyRepeatLast(recordId);
    });

    $(document).on('input change', '#fiwCollectAmount, #fiwCollectMethod, #fiwCollectBank', function () {
        syncCollectNow();
        saveDraft();
    });

    $(document).on('change', '#fiwCustomAllocation', function () {
        if (!canManageAllocation) return;
        setAllocationMode($(this).is(':checked') ? 'manual' : 'auto');
        saveDraft();
    });

    $(document).on('input', '.fiw-bifurc-paid', function () {
        if (allocationMode !== 'manual' || !canManageAllocation) return;
        var idx = parseInt($(this).data('rowIndex'), 10);
        var val = parseFloat($(this).val()) || 0;
        var $rows = feeRowsWithAmounts();
        if ($rows.eq(idx).length) {
            $rows.eq(idx).find('.paidAmount').val(val > 0 ? val.toFixed(2) : '');
        }
        $('#fiwBifurcationTotal').text(formatRs(totalPaidFromRows()));
        updateAllocationSummary();
        saveDraft();
    });

    $(document).on('toggle', '#fiwBifurcation', function () {
        if (this.open) renderBifurcationPanel();
    });

    $(document).on('change', '#fiwCollectMethod', function () {
        var method = getNiceSelectValue($('#fiwCollectMethod')) || $(this).val();
        $('#fiwCollectBankWrap').toggle(method === 'Bank');
        niceUpdate($('#fiwCollectBank'));
    });

    $(document).on('click', '#fiwCollectMethod + .nice-select .option:not(.disabled)', function () {
        setTimeout(function () {
            var method = getNiceSelectValue($('#fiwCollectMethod'));
            $('#fiwCollectBankWrap').toggle(method === 'Bank');
        }, 120);
    });

    $(document).on('click', '#fiwFillFullAmount', function () {
        var total = invoiceTotalFromRows();
        if (total > 0) {
            $('#fiwCollectAmount').val(total.toFixed(2));
            $('#fiwCollectNow').attr('open', true);
            syncCollectNow();
            saveDraft();
        }
    });

    if ($('#paymentAllocationMode').val() === 'manual' && canManageAllocation) {
        setAllocationMode('manual');
    }

    $(document).on('toggle', '#fiwCollectNow', function () {
        if (this.open) syncCollectNow();
    });

    niceUpdate($('#fiwCollectMethod'));
    niceUpdate($('#fiwCollectBank'));
    initStudentSearch();

    $(document).on('toggle', '#fiwManualPick', function () {
        if (this.open) {
            niceUpdate($('#select_class'));
            niceUpdate($('#select_section'));
            niceUpdate($('#selectStudent'));
        }
    });
    // Safari/older browsers: also refresh on summary click
    $(document).on('click', '#fiwManualPick > summary', function () {
        setTimeout(function () {
            niceUpdate($('#select_class'));
            niceUpdate($('#select_section'));
            niceUpdate($('#selectStudent'));
        }, 60);
    });

    buildClientSchedule();
    if (isEditMode) {
        loadMonthScheduleFromDom();
        applyPayMonths();
        setTimeout(function () { applyPayMonths(); }, 300);
    } else {
        if (invoicePrefs.fee_due_day) $('#feeDueDay').val(invoicePrefs.fee_due_day);
        applyPayMonths();
        setTimeout(function () { applyPayMonths(); }, 300);
        if ($('#select_class').val()) loadSections();
        restoreDraft();
    }
});
</script>
@endif
