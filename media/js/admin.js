/**
 * @package    Cybersalt.Component.Removesampledata
 * @copyright  Copyright (C) 2026 Cybersalt. All rights reserved.
 * @license    GNU General Public License version 2 or later
 */

(function () {
    'use strict';

    function init() {
        const form = document.getElementById('rsd-remove-form');
        if (!form) {
            return;
        }

        const openBtnBottom = document.getElementById('rsd-open-confirm');
        const openBtnTop    = document.getElementById('rsd-open-confirm-top');
        const masterToggle  = document.getElementById('rsd-master-toggle');
        const filterType    = document.getElementById('rsd-filter-type');
        const filterTitle   = document.getElementById('rsd-filter-title');
        const filterStatus  = document.getElementById('rsd-filter-status');
        const filterClear   = document.getElementById('rsd-filter-clear');
        const rowCountEl    = document.getElementById('rsd-row-count');
        const modalEl       = document.getElementById('rsd-confirm');
        const finalBtn      = document.getElementById('rsd-final-submit');
        const confirmBox    = document.getElementById('rsd-confirm-checkbox');
        const editedHdr     = document.getElementById('rsd-edited-warning');
        const lockout       = document.getElementById('rsd-lockout');
        const countTop      = document.getElementById('rsd-ticked-count-top');
        const countBottom   = document.getElementById('rsd-ticked-count-bottom');

        if (!modalEl || !finalBtn || !confirmBox || !lockout) {
            return;
        }

        // Fetch the current session's CSRF token from our component's own
        // token endpoint and swap it into the form via Joomla.replaceTokens.
        // We use our own endpoint because Joomla 6 doesn't ship the legacy
        // plg_system_token plugin that provided this on Joomla 4. The
        // endpoint is gated by our PermissionHelper::requireManage() so the
        // admin session cookie is required.
        function refreshToken() {
            const url = 'index.php?option=com_removesampledata&task=token.fetch';
            return fetch(url, {
                credentials: 'same-origin',
                cache: 'no-store',
                headers: {
                    'Accept': 'text/plain',
                    'X-Requested-With': 'XMLHttpRequest'
                }
            })
                .then(function (r) { return r.ok ? r.text() : ''; })
                .then(function (text) {
                    // Forgiving extraction — find any 32-char alphanumeric run
                    // anywhere in the response, so trailing newlines, BOMs or
                    // other accidental wrapping don't break the regex check
                    // inside Joomla.replaceTokens.
                    const match = (text || '').match(/[a-zA-Z0-9]{32}/);
                    if (match && window.Joomla && typeof Joomla.replaceTokens === 'function') {
                        Joomla.replaceTokens(match[0]);
                    }
                })
                .catch(function () { /* best-effort — the server-side fallback in RemoveController will validate via same-origin Referer */ });
        }

        let _modal = null;
        function getModal() {
            if (!_modal) {
                if (typeof bootstrap === 'undefined' || !bootstrap.Modal) {
                    return null;
                }
                _modal = bootstrap.Modal.getOrCreateInstance(modalEl);
            }
            return _modal;
        }

        const allRows  = Array.from(form.querySelectorAll('tr.rsd-row'));
        const allBoxes = allRows.map(function (r) { return r.querySelector('.rsd-remove'); }).filter(Boolean);
        const showingTemplate = (rowCountEl && rowCountEl.textContent) || '';

        function rowIsVisible(row) {
            return row.style.display !== 'none';
        }

        function visibleRows() {
            return allRows.filter(rowIsVisible);
        }

        function visibleBoxes() {
            return visibleRows().map(function (r) { return r.querySelector('.rsd-remove'); }).filter(Boolean);
        }

        function tickedCount() {
            return allBoxes.filter(function (b) { return b.checked; }).length;
        }

        function refreshCounts() {
            const n = tickedCount();
            if (countTop)    countTop.textContent    = n;
            if (countBottom) countBottom.textContent = n;
            refreshMaster();
        }

        function refreshMaster() {
            if (!masterToggle) return;
            const vis = visibleBoxes();
            const total = vis.length;
            const checked = vis.filter(function (b) { return b.checked; }).length;
            masterToggle.checked       = total > 0 && checked === total;
            masterToggle.indeterminate = checked > 0 && checked < total;
        }

        function updateRowCountIndicator(visible) {
            if (!rowCountEl) return;
            // Re-render the "Showing X of N rows" line. We rebuild from the
            // first occurrence of two numbers in the template the server gave us.
            const total = allRows.length;
            rowCountEl.textContent = (showingTemplate
                .replace(/(\d+)([^\d]+)(\d+)/, visible + '$2' + total));
        }

        function applyFilter() {
            const tFilt  = filterType   ? filterType.value   : '';
            const titleF = filterTitle  ? filterTitle.value  : '';
            const sFilt  = filterStatus ? filterStatus.value : '';

            let visible = 0;
            allRows.forEach(function (row) {
                const t   = row.dataset.type   || '';
                const s   = row.dataset.status || '';
                const ttl = row.dataset.title  || '';
                let show = true;
                if (tFilt  && t   !== tFilt)  show = false;
                if (sFilt  && s   !== sFilt)  show = false;
                if (titleF && ttl !== titleF) show = false;
                row.style.display = show ? '' : 'none';
                if (show) visible++;
            });
            updateRowCountIndicator(visible);
            refreshMaster();
        }

        function clearFilters() {
            if (filterType)   filterType.value   = '';
            if (filterTitle)  filterTitle.value  = '';
            if (filterStatus) filterStatus.value = '';
            applyFilter();
        }

        // Master checkbox — ticks/unticks every visible row.
        if (masterToggle) {
            masterToggle.addEventListener('change', function () {
                visibleBoxes().forEach(function (b) { b.checked = masterToggle.checked; });
                masterToggle.indeterminate = false;
                refreshCounts();
            });
        }

        // Per-row click + shift-click range select (within visible rows).
        let lastClicked = null;
        allBoxes.forEach(function (b) {
            b.addEventListener('click', function (e) {
                const vis = visibleBoxes();
                if (e.shiftKey && lastClicked && lastClicked !== b && vis.indexOf(lastClicked) !== -1) {
                    const i1 = vis.indexOf(lastClicked);
                    const i2 = vis.indexOf(b);
                    if (i1 !== -1 && i2 !== -1) {
                        const start = Math.min(i1, i2);
                        const end   = Math.max(i1, i2);
                        const state = b.checked;
                        for (let i = start; i <= end; i++) {
                            vis[i].checked = state;
                        }
                        window.getSelection && window.getSelection().removeAllRanges();
                    }
                }
                lastClicked = b;
                refreshCounts();
            });
        });

        // Filter inputs.
        if (filterType)   filterType.addEventListener('change',   applyFilter);
        if (filterTitle)  filterTitle.addEventListener('change',  applyFilter);
        if (filterStatus) filterStatus.addEventListener('change', applyFilter);
        if (filterClear)  filterClear.addEventListener('click',   clearFilters);

        refreshCounts();
        applyFilter();

        function openConfirm() {
            const ticked = tickedCount();
            if (ticked === 0) {
                alert(jText('COM_REMOVESAMPLEDATA_JS_NOTHING_TO_DELETE',
                    'Nothing ticked for removal.'));
                return;
            }
            let editedToDelete = 0;
            form.querySelectorAll('tr.rsd-row-edited').forEach(function (row) {
                const cb = row.querySelector('.rsd-remove');
                if (cb && cb.checked) {
                    editedToDelete++;
                }
            });
            if (editedToDelete > 0 && editedHdr) {
                editedHdr.classList.remove('d-none');
                const template = jText('COM_REMOVESAMPLEDATA_EDITED_WARNING_N',
                    'Heads up: %d of the items you are about to remove look like they have been edited since the sample data was installed.');
                editedHdr.textContent = template.replace('%d', editedToDelete);
            } else if (editedHdr) {
                editedHdr.classList.add('d-none');
            }
            confirmBox.checked = false;
            finalBtn.disabled = true;

            const m = getModal();
            if (m) {
                m.show();
            } else {
                if (window.confirm(jText('COM_REMOVESAMPLEDATA_CONFIRM_BODY', 'Remove these items?'))) {
                    confirmBox.checked = true;
                    form.submit();
                }
            }
        }

        if (openBtnBottom) openBtnBottom.addEventListener('click', openConfirm);
        if (openBtnTop)    openBtnTop.addEventListener('click', openConfirm);

        confirmBox.addEventListener('change', function () {
            finalBtn.disabled = !confirmBox.checked;
        });

        form.addEventListener('submit', function (e) {
            if (!confirmBox.checked) {
                e.preventDefault();
                return;
            }
            // Always block the synchronous submit, refresh the token first,
            // THEN submit programmatically. This guarantees the token going
            // up is fresh, regardless of how long the user spent on the page.
            e.preventDefault();
            const m = getModal();
            if (m) m.hide();
            lockout.classList.remove('d-none');
            // Disable only the buttons to prevent double-submit. DO NOT
            // disable form inputs — `disabled` inputs are stripped from
            // the POST body by the browser, which would drop confirm_remove
            // and every remove[<group>][] checkbox we're trying to submit.
            // The full-screen lockout overlay prevents user interaction
            // with anything else, so disabling the inputs themselves is
            // both unnecessary and actively harmful here.
            form.querySelectorAll('button').forEach(function (el) { el.disabled = true; });

            refreshToken().then(function () {
                // form.submit() bypasses our submit handler so we don't
                // recurse into this branch.
                form.submit();
            });
        });
    }

    function jText(key, fallback) {
        if (window.Joomla && Joomla.Text && Joomla.Text._) {
            const v = Joomla.Text._(key);
            if (v && v !== key) {
                return v;
            }
        }
        return fallback;
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
