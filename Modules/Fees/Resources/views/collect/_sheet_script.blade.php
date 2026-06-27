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
    const customAllocWrap = root.querySelector('#faCustomAllocationWrap');

    let canManageAllocation = false;
    let allocationMode = 'auto';
    const manualLinePaid = {};

    try { canManageAllocation = JSON.parse(root.querySelector('#collectCanManageAllocation')?.textContent || 'false'); } catch (e) {}

    function monthCard(invoiceId) {
        return root.querySelector('.fa-collect-month[data-invoice-id="' + invoiceId + '"]');
    }

    function splitPanel(invoiceId) {
        const card = monthCard(invoiceId);
        return card ? card.querySelector('.fa-month-split') : null;
    }

    function inputFor(id) {
        return root.querySelector('.amount-input[data-id="' + id + '"]');
    }

    function amountFor(id) {
        const input = inputFor(id);
        if (!input || input.disabled) return 0;
        const v = parseFloat(input.value || 0);
        return isNaN(v) ? 0 : v;
    }

    function invoiceBalance(invoiceId) {
        const card = monthCard(invoiceId);
        return card ? parseFloat(card.dataset.balance || 0) || 0 : 0;
    }

    function formatINR(n) {
        return '₹' + n.toLocaleString('en-IN', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function lineKey(invoiceId, feesType) {
        return String(invoiceId) + ':' + String(feesType);
    }

    function lineRows(invoiceId) {
        const panel = splitPanel(invoiceId);
        if (!panel) return [];
        return Array.prototype.slice.call(panel.querySelectorAll('.fa-split-line[data-fees-type]'));
    }

    function computeMonthSplit(invoiceId, collectAmount) {
        const invoiceBal = invoiceBalance(invoiceId);
        const rows = lineRows(invoiceId);
        const result = {};
        if (!(collectAmount > 0) || !(invoiceBal > 0) || rows.length === 0) {
            return result;
        }

        let remaining = collectAmount;
        const lastIdx = rows.length - 1;

        rows.forEach(function (row, idx) {
            const feesType = row.dataset.feesType;
            const due = parseFloat(row.dataset.due || 0) || 0;
            let paid = 0;

            if (due > 0) {
                if (idx === lastIdx) {
                    paid = Math.round(remaining * 100) / 100;
                } else {
                    paid = Math.round(collectAmount * (due / invoiceBal) * 100) / 100;
                    remaining = Math.round((remaining - paid) * 100) / 100;
                }
                paid = Math.min(Math.max(0, paid), due);
            }

            result[feesType] = paid;
        });

        return result;
    }

    function setBadge(panel, collectAmount, invoiceBal, manual) {
        const badge = panel.querySelector('[data-split-badge]');
        if (!badge) return;

        badge.classList.remove('is-custom', 'is-full');
        if (manual) {
            badge.textContent = 'Custom';
            badge.classList.add('is-custom');
        } else if (invoiceBal > 0 && Math.abs(collectAmount - invoiceBal) < 0.02) {
            badge.textContent = 'Full';
            badge.classList.add('is-full');
        } else {
            badge.textContent = 'Auto';
        }
    }

    function setSplitHint(panel, manual) {
        const hint = panel.querySelector('[data-split-hint]');
        if (!hint) return;
        hint.textContent = manual
            ? 'Enter exact amounts per fee type. Total must match the paying amount.'
            : 'Split follows each fee type\'s share of the balance.';
    }

    function syncMonthSplit(invoiceId) {
        const panel = splitPanel(invoiceId);
        const card = monthCard(invoiceId);
        const check = root.querySelector('.month-check[data-id="' + invoiceId + '"]');
        if (!panel || !check) return;

        const collectAmount = amountFor(invoiceId);
        const invoiceBal = invoiceBalance(invoiceId);
        const rows = lineRows(invoiceId);
        const fieldsWrap = panel.querySelector('[data-line-fields]');
        const metaEl = panel.querySelector('[data-split-meta]');
        const progressEl = panel.querySelector('[data-split-progress]');
        const progressTrack = panel.querySelector('.fa-month-split__progress-track');
        const totalElMonth = panel.querySelector('[data-split-total]');
        const manual = allocationMode === 'manual' && canManageAllocation;

        if (!check.checked || !(collectAmount > 0) || rows.length === 0) {
            panel.hidden = true;
            if (fieldsWrap) fieldsWrap.innerHTML = '';
            if (card) card.classList.remove('is-mismatch');
            return;
        }

        panel.hidden = false;
        setBadge(panel, collectAmount, invoiceBal, manual);
        setSplitHint(panel, manual);

        const autoSplit = computeMonthSplit(invoiceId, collectAmount);
        let allocated = 0;
        const pctOfBalance = invoiceBal > 0 ? Math.min(100, Math.max(0, (collectAmount / invoiceBal) * 100)) : 0;

        if (progressEl) progressEl.style.width = pctOfBalance.toFixed(1) + '%';
        if (progressTrack) progressTrack.setAttribute('aria-valuenow', String(Math.round(pctOfBalance)));

        rows.forEach(function (row) {
            const feesType = row.dataset.feesType;
            const due = parseFloat(row.dataset.due || 0) || 0;
            const key = lineKey(invoiceId, feesType);
            let paid = autoSplit[feesType] || 0;

            if (manual) {
                if (manualLinePaid[key] === undefined) {
                    manualLinePaid[key] = paid;
                }
                paid = Math.min(Math.max(0, manualLinePaid[key] || 0), due);
                manualLinePaid[key] = paid;
            } else {
                manualLinePaid[key] = paid;
            }

            allocated += paid;

            const textEl = row.querySelector('[data-paid-text]');
            const inputEl = row.querySelector('.fa-month-line-paid');
            const barEl = row.querySelector('[data-line-bar]');
            const linePct = due > 0 ? Math.min(100, Math.max(0, (paid / due) * 100)) : 0;

            if (barEl) barEl.style.width = linePct.toFixed(1) + '%';
            row.classList.toggle('is-active', manual && paid > 0);

            if (manual && inputEl) {
                inputEl.hidden = false;
                inputEl.disabled = false;
                inputEl.value = paid > 0 ? paid.toFixed(2) : '';
                if (textEl) textEl.hidden = true;
            } else {
                if (inputEl) {
                    inputEl.hidden = true;
                    inputEl.disabled = true;
                }
                if (textEl) {
                    textEl.hidden = false;
                    textEl.textContent = paid > 0 ? formatINR(paid) : '—';
                }
            }
        });

        allocated = Math.round(allocated * 100) / 100;
        const mismatch = Math.abs(collectAmount - allocated) > 0.05;

        if (metaEl) {
            metaEl.textContent = formatINR(collectAmount) + ' of ' + formatINR(invoiceBal) + ' (' + pctOfBalance.toFixed(0) + '%)';
        }
        if (totalElMonth) {
            totalElMonth.textContent = formatINR(allocated);
            totalElMonth.classList.toggle('is-mismatch', manual && mismatch);
        }
        if (card) {
            card.classList.toggle('is-mismatch', manual && mismatch);
        }

        if (fieldsWrap) {
            fieldsWrap.innerHTML = '';
            rows.forEach(function (row) {
                const feesType = row.dataset.feesType;
                const paid = manualLinePaid[lineKey(invoiceId, feesType)] || 0;
                const input = document.createElement('input');
                input.type = 'hidden';
                input.name = 'line_paid[' + invoiceId + '][' + feesType + ']';
                input.value = paid > 0 ? paid.toFixed(2) : '0';
                fieldsWrap.appendChild(input);
            });
        }
    }

    function syncAllMonthSplits() {
        checks.forEach(function (c) { syncMonthSplit(c.dataset.id); });
    }

    function setAllocationMode(mode) {
        allocationMode = (mode === 'manual' && canManageAllocation) ? 'manual' : 'auto';
        if (allocationModeEl) allocationModeEl.value = allocationMode;
        if (customAllocEl) customAllocEl.checked = allocationMode === 'manual';
        if (form) form.classList.toggle('is-manual-allocation', allocationMode === 'manual');
        if (customAllocWrap) customAllocWrap.classList.toggle('is-on', allocationMode === 'manual');
        syncAllMonthSplits();
    }

    function recalc() {
        syncAllMonthSplits();
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
        const card = c.closest('.fa-collect-month');
        const input = inputFor(c.dataset.id);
        if (card) card.classList.toggle('is-selected', c.checked);
        if (input) {
            input.disabled = !c.checked;
            if (c.checked && (!input.value || parseFloat(input.value) <= 0)) {
                input.value = c.dataset.amount;
            }
        }
        if (!c.checked) {
            lineRows(c.dataset.id).forEach(function (row) {
                delete manualLinePaid[lineKey(c.dataset.id, row.dataset.feesType)];
            });
            if (card) card.classList.remove('is-mismatch');
        }
        syncMonthSplit(c.dataset.id);
    }

    function validateAllocations() {
        let ok = true;
        let badInvoiceId = null;
        checks.forEach(function (c) {
            if (!c.checked) return;
            const invoiceId = c.dataset.id;
            const collectAmount = amountFor(invoiceId);
            if (!(collectAmount > 0)) return;

            let allocated = 0;
            lineRows(invoiceId).forEach(function (row) {
                allocated += manualLinePaid[lineKey(invoiceId, row.dataset.feesType)] || 0;
            });
            allocated = Math.round(allocated * 100) / 100;

            if (Math.abs(collectAmount - allocated) > 0.05) {
                ok = false;
                badInvoiceId = invoiceId;
            }
        });
        return { ok: ok, badInvoiceId: badInvoiceId };
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
            const invoiceId = i.dataset.id;
            lineRows(invoiceId).forEach(function (row) {
                delete manualLinePaid[lineKey(invoiceId, row.dataset.feesType)];
            });
            recalc();
        });
    });

    root.querySelectorAll('.fa-pay-fill').forEach(function (btn) {
        btn.addEventListener('click', function (e) {
            e.preventDefault();
            const invoiceId = btn.dataset.fillInvoice;
            const input = inputFor(invoiceId);
            const check = root.querySelector('.month-check[data-id="' + invoiceId + '"]');
            if (!input || !check) return;
            if (!check.checked) {
                check.checked = true;
                syncRow(check);
            }
            input.value = parseFloat(check.dataset.amount || input.max || 0).toFixed(2);
            lineRows(invoiceId).forEach(function (row) {
                delete manualLinePaid[lineKey(invoiceId, row.dataset.feesType)];
            });
            recalc();
        });
    });

    root.querySelectorAll('.fa-month-line-paid').forEach(function (input) {
        input.addEventListener('input', function () {
            const invoiceId = input.dataset.invoiceId;
            const feesType = input.dataset.feesType;
            const due = parseFloat(input.closest('.fa-split-line')?.dataset.due || 0) || 0;
            let val = parseFloat(input.value || 0) || 0;
            if (val > due) {
                val = due;
                input.value = due.toFixed(2);
            }
            manualLinePaid[lineKey(invoiceId, feesType)] = val;
            syncMonthSplit(invoiceId);
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
        syncAllMonthSplits();

        let any = false;
        checks.forEach(function (c) {
            if (c.checked && amountFor(c.dataset.id) > 0) any = true;
        });
        if (!any) {
            e.preventDefault();
            if (typeof toastr !== 'undefined') toastr.warning('Select at least one month with an amount greater than zero.');
            else alert('Select at least one month with an amount greater than zero.');
            return;
        }

        const validation = validateAllocations();
        if (!validation.ok) {
            e.preventDefault();
            const msg = 'Fee split must match the paying amount for each selected month.';
            if (typeof toastr !== 'undefined') toastr.error(msg, 'Allocation mismatch');
            else alert(msg);
            if (validation.badInvoiceId) {
                const card = monthCard(validation.badInvoiceId);
                if (card) card.scrollIntoView({ behavior: 'smooth', block: 'center' });
            }
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
