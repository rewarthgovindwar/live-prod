<script>
window.initFeeCollectForm = function (root) {
    root = root || document;
    const form = root.querySelector('#collect_form');
    const totalEl = root.querySelector('#collect_total');
    if (!form || !totalEl) return;

    const checks = Array.prototype.slice.call(root.querySelectorAll('.month-check'));
    const countEl = root.querySelector('#collect_count');
    const selectAllBtn = root.querySelector('#collect_select_all');
    const method = root.querySelector('#payment_method');
    const bankWrap = root.querySelector('#bank_wrap');
    const allocationModeEl = root.querySelector('#collectAllocationMode');
    const customAllocEl = root.querySelector('#faCustomAllocation');
    const bifurcationBody = root.querySelector('#faBifurcationBody');
    const bifurcationFoot = root.querySelector('#faBifurcationFoot');
    const bifurcationTotal = root.querySelector('#faBifurcationTotal');
    const bifurcationPreview = root.querySelector('#faBifurcationPreview');
    const bifurcationHidden = root.querySelector('#faBifurcationHiddenFields');
    const bifurcationHint = root.querySelector('#faBifurcationHint');

    let bifurcationData = [];
    let canManageAllocation = false;
    let allocationMode = 'auto';
    let manualLinePaid = {};

    try { bifurcationData = JSON.parse(root.querySelector('#collectBifurcationData')?.textContent || '[]'); } catch (e) {}
    try { canManageAllocation = JSON.parse(root.querySelector('#collectCanManageAllocation')?.textContent || 'false'); } catch (e) {}

    function inputFor(id) {
        return root.querySelector('.amount-input[data-id="' + id + '"]');
    }

    function amountFor(id) {
        const input = inputFor(id);
        if (!input || input.disabled) return 0;
        const v = parseFloat(input.value || 0);
        return isNaN(v) ? 0 : v;
    }

    function formatINR(n) {
        return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function invoiceMeta(invoiceId) {
        return bifurcationData.find(function (row) { return String(row.invoice_id) === String(invoiceId); }) || null;
    }

    function lineKey(invoiceId, feesType) {
        return String(invoiceId) + ':' + String(feesType);
    }

    function proportionalLinePaid(due, invoiceBalance, collectAmount) {
        if (!(due > 0) || !(invoiceBalance > 0) || !(collectAmount > 0)) return 0;
        return Math.round(collectAmount * (due / invoiceBalance) * 100) / 100;
    }

    function buildAutoAllocations() {
        const rows = [];
        const aggregated = {};

        checks.forEach(function (c) {
            if (!c.checked) return;
            const meta = invoiceMeta(c.dataset.id);
            if (!meta) return;
            const collectAmount = amountFor(c.dataset.id);
            if (!(collectAmount > 0)) return;

            let remaining = collectAmount;
            const lines = meta.lines || [];
            lines.forEach(function (line, idx) {
                let paid = 0;
                if (idx === lines.length - 1) {
                    paid = Math.round(remaining * 100) / 100;
                } else {
                    paid = proportionalLinePaid(line.due, meta.balance, collectAmount);
                    remaining -= paid;
                }
                paid = Math.min(paid, line.due);
                rows.push({
                    invoice_id: meta.invoice_id,
                    month_label: meta.month_label,
                    fees_type: line.fees_type,
                    label: line.label,
                    due: line.due,
                    paid: paid,
                    share: meta.balance > 0 ? (line.due / meta.balance) * 100 : 0
                });
                const key = lineKey(meta.invoice_id, line.fees_type);
                aggregated[key] = (aggregated[key] || 0) + paid;
            });
        });

        return { rows: rows, aggregated: aggregated };
    }

    function renderBifurcation() {
        if (!bifurcationBody) return;

        const totalCollect = checks.reduce(function (sum, c) {
            return c.checked ? sum + amountFor(c.dataset.id) : sum;
        }, 0);

        bifurcationBody.innerHTML = '';
        manualLinePaid = {};

        if (!(totalCollect > 0)) {
            bifurcationBody.innerHTML = '<tr><td colspan="4" class="text-muted">Select months and enter amounts to see the split.</td></tr>';
            if (bifurcationFoot) bifurcationFoot.style.display = 'none';
            if (bifurcationPreview) bifurcationPreview.textContent = '';
            if (bifurcationHidden) bifurcationHidden.innerHTML = '';
            return;
        }

        const auto = buildAutoAllocations();
        const displayRows = [];
        let allocatedTotal = 0;

        auto.rows.forEach(function (row) {
            const key = lineKey(row.invoice_id, row.fees_type);
            let paid = row.paid;
            if (allocationMode === 'manual' && canManageAllocation && manualLinePaid[key] !== undefined) {
                paid = manualLinePaid[key];
            } else if (allocationMode === 'manual' && canManageAllocation) {
                manualLinePaid[key] = row.paid;
                paid = row.paid;
            }
            allocatedTotal += paid;
            displayRows.push(Object.assign({}, row, { paid: paid }));
        });

        const grouped = {};
        displayRows.forEach(function (row) {
            const gkey = row.label;
            if (!grouped[gkey]) {
                grouped[gkey] = { label: row.label, due: 0, paid: 0, shareWeight: 0 };
            }
            grouped[gkey].due += row.due;
            grouped[gkey].paid += row.paid;
            grouped[gkey].shareWeight += row.due;
        });

        const totalDue = Object.values(grouped).reduce(function (s, g) { return s + g.due; }, 0);
        Object.keys(grouped).forEach(function (label) {
            const g = grouped[label];
            const sharePct = totalDue > 0 ? (g.due / totalDue) * 100 : 0;
            const tr = document.createElement('tr');
            const tdLabel = document.createElement('td');
            tdLabel.textContent = label;
            tr.appendChild(tdLabel);

            const tdDue = document.createElement('td');
            tdDue.className = 'text-right';
            tdDue.textContent = formatINR(g.due);
            tr.appendChild(tdDue);

            const tdShare = document.createElement('td');
            tdShare.className = 'text-right';
            tdShare.textContent = sharePct.toFixed(2) + '%';
            tr.appendChild(tdShare);

            const tdPaid = document.createElement('td');
            tdPaid.className = 'text-right';
            if (allocationMode === 'manual' && canManageAllocation) {
                const input = document.createElement('input');
                input.type = 'number';
                input.min = '0';
                input.step = '0.01';
                input.className = 'primary_input_field form-control fa-bifurc-paid-input';
                input.value = g.paid > 0 ? g.paid.toFixed(2) : '';
                input.dataset.label = label;
                input.addEventListener('input', function () {
                    redistributeManualByLabel(label, parseFloat(input.value || 0) || 0, auto.rows);
                    renderBifurcation();
                    recalc(false);
                });
                tdPaid.appendChild(input);
            } else {
                tdPaid.textContent = g.paid > 0 ? formatINR(g.paid) : '—';
            }
            tr.appendChild(tdPaid);
            bifurcationBody.appendChild(tr);
        });

        if (bifurcationFoot) bifurcationFoot.style.display = '';
        if (bifurcationTotal) bifurcationTotal.textContent = formatINR(allocatedTotal);
        if (bifurcationPreview) {
            bifurcationPreview.textContent = '(' + formatINR(totalCollect) + ' total · auto-split by fee share)';
        }

        if (bifurcationHidden) {
            bifurcationHidden.innerHTML = '';
            displayRows.forEach(function (row) {
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'line_paid[' + row.invoice_id + '][' + row.fees_type + ']';
                input.value = row.paid > 0 ? row.paid.toFixed(2) : '0';
                bifurcationHidden.appendChild(input);
            });
        }
    }

    function redistributeManualByLabel(label, targetTotal, autoRows) {
        const matching = autoRows.filter(function (r) { return r.label === label; });
        if (!matching.length) return;
        let remaining = targetTotal;
        matching.forEach(function (row, idx) {
            const key = lineKey(row.invoice_id, row.fees_type);
            let paid = 0;
            if (idx === matching.length - 1) {
                paid = Math.round(remaining * 100) / 100;
            } else if (targetTotal > 0) {
                const weight = row.due / matching.reduce(function (s, r) { return s + r.due; }, 0);
                paid = Math.round(targetTotal * weight * 100) / 100;
                remaining -= paid;
            }
            manualLinePaid[key] = Math.min(paid, row.due);
        });
    }

    function setAllocationMode(mode) {
        allocationMode = (mode === 'manual' && canManageAllocation) ? 'manual' : 'auto';
        if (allocationModeEl) allocationModeEl.value = allocationMode;
        if (customAllocEl) customAllocEl.checked = allocationMode === 'manual';
        if (bifurcationHint) {
            bifurcationHint.textContent = allocationMode === 'manual'
                ? 'Enter how much goes to each fee type. Total must match the collection amount.'
                : 'Split follows each fee type\'s share of the invoice balance (same percentage on every line).';
        }
        renderBifurcation();
    }

    function recalc(renderSplit) {
        if (renderSplit !== false) renderBifurcation();
        let sum = 0, selected = 0;
        checks.forEach(function (c) {
            if (c.checked) { selected += 1; sum += amountFor(c.dataset.id); }
        });
        totalEl.textContent = formatINR(sum);
        if (countEl) countEl.textContent = selected + (selected === 1 ? ' month selected' : ' months selected');
        if (selectAllBtn) {
            const allOn = checks.length && selected === checks.length;
            selectAllBtn.textContent = allOn ? 'Clear all' : 'Select all';
            selectAllBtn.dataset.mode = allOn ? 'clear' : 'all';
        }
    }

    function syncRow(c) {
        const row = c.closest('.fa-collect-month');
        const input = inputFor(c.dataset.id);
        if (row) row.classList.toggle('is-selected', c.checked);
        if (input) {
            input.disabled = !c.checked;
            if (c.checked && (!input.value || parseFloat(input.value) <= 0)) {
                input.value = c.dataset.amount;
            }
        }
    }

    checks.forEach(function (c) {
        c.addEventListener('change', function () {
            syncRow(c);
            recalc();
        });
    });

    root.querySelectorAll('.amount-input').forEach(function (i) {
        i.addEventListener('input', function () {
            const max = parseFloat(i.max || 0);
            if (max && parseFloat(i.value || 0) > max) i.value = max;
            recalc();
        });
    });

    if (customAllocEl) {
        customAllocEl.addEventListener('change', function () {
            setAllocationMode(customAllocEl.checked ? 'manual' : 'auto');
        });
    }

    if (selectAllBtn) {
        selectAllBtn.addEventListener('click', function () {
            const turnOn = selectAllBtn.dataset.mode !== 'clear';
            checks.forEach(function (c) {
                if (c.checked !== turnOn) {
                    c.checked = turnOn;
                    syncRow(c);
                }
            });
            recalc();
        });
    }

    const $ = window.jQuery;
    const canNice = !!($ && $.fn && typeof $.fn.niceSelect === 'function');
    function isDesktop() {
        return window.matchMedia && window.matchMedia('(min-width: 768px)').matches;
    }
    function ensureNice(el) {
        if (!el || !canNice || !isDesktop()) return;
        const $el = $(el);
        if (!$el.next('.nice-select').length) $el.niceSelect();
    }

    function toggleBank() {
        const show = !!(method && method.value === 'Bank');
        if (bankWrap) bankWrap.classList.toggle('d-none', !show);
        if (show) {
            const bankSel = root.querySelector('select[name="bank"]');
            ensureNice(bankSel);
            if (canNice && isDesktop() && bankSel && $(bankSel).next('.nice-select').length) {
                $(bankSel).niceSelect('update');
            }
        }
    }

    ensureNice(method);
    if (method) {
        method.addEventListener('change', toggleBank);
        if ($) $(method).on('change', toggleBank);
        toggleBank();
    }

    checks.forEach(syncRow);
    recalc();

    if (form.dataset.ajaxBound === '1') return;
    form.dataset.ajaxBound = '1';

    form.addEventListener('submit', function (e) {
        renderBifurcation();
        let any = false;
        let totalCollect = 0;
        let totalAllocated = 0;
        checks.forEach(function (c) {
            if (c.checked) {
                const amt = amountFor(c.dataset.id);
                if (amt > 0) any = true;
                totalCollect += amt;
            }
        });
        if (bifurcationHidden) {
            bifurcationHidden.querySelectorAll('input[type="hidden"]').forEach(function (inp) {
                totalAllocated += parseFloat(inp.value || 0) || 0;
            });
        }
        if (!any) {
            e.preventDefault();
            if (typeof toastr !== 'undefined') toastr.warning('Select at least one month with an amount greater than zero.');
            else alert('Select at least one month with an amount greater than zero.');
            return;
        }
        if (Math.abs(totalCollect - totalAllocated) > 0.05) {
            e.preventDefault();
            const msg = 'Allocated amounts must equal the collection amount (' + formatINR(totalCollect) + ').';
            if (typeof toastr !== 'undefined') toastr.error(msg, 'Allocation mismatch');
            else alert(msg);
            const bif = root.querySelector('#faBifurcation');
            if (bif) bif.open = true;
            return;
        }

        if (!form.closest('#faCollectModal')) return;

        e.preventDefault();
        const btn = root.querySelector('#collect_submit_btn');
        if (btn) { btn.disabled = true; btn.innerHTML = '<span class="fa-spinner"></span> Processing…'; }

        const fd = new FormData(form);
        fetch(form.action, {
            method: 'POST',
            body: fd,
            headers: { 'X-Requested-With': 'XMLHttpRequest', 'Accept': 'application/json' },
            credentials: 'same-origin'
        }).then(r => r.json().then(j => ({ ok: r.ok, j })))
          .then(({ ok, j }) => {
            if (!ok) throw new Error(j.message || 'Could not collect');
            if (typeof toastr !== 'undefined') toastr.success(j.message, 'Payment recorded');
            $('#faCollectModal').modal('hide');
            if (j.receipt_url && window.faFeeModal) {
                window.faFeeModal.showReceipt(j.receipt_url, 'Receipt');
            } else {
                setTimeout(() => window.location.reload(), 600);
            }
          })
          .catch(err => {
            if (typeof toastr !== 'undefined') toastr.error(err.message, 'Error');
            else alert(err.message);
            if (btn) { btn.disabled = false; btn.innerHTML = '<i class="ti-check"></i> Collect &amp; receipt'; }
          });
    });
};
document.addEventListener('DOMContentLoaded', function () {
    if (document.getElementById('collect_form') && !document.getElementById('faCollectModal')) {
        window.initFeeCollectForm(document);
    }
});
</script>
