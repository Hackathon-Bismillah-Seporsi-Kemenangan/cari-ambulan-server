/**
 * CariAmbulan Client-side Tab & Filter Utilities
 * Lightweight, zero-dependency, hash-synced tabs for Command Hub dashboards.
 */

import './echo';

function initTabs() {
    const panels = Array.from(document.querySelectorAll('[data-tab-content]'));
    if (!panels.length) return;

    const buttons = Array.from(document.querySelectorAll('[data-tab-target]'));
    const container = document.querySelector('[data-tabs-container]');
    const defaultTab = container?.dataset.defaultTab || buttons[0]?.dataset.tabTarget || panels[0]?.dataset.tabContent;
    const validTabs = panels.map(p => p.dataset.tabContent);

    function activateTab(tabId, updateHash = true) {
        if (!validTabs.includes(tabId)) {
            tabId = defaultTab || validTabs[0];
        }

        panels.forEach(panel => {
            panel.classList.toggle('hidden', panel.dataset.tabContent !== tabId);
        });

        buttons.forEach(btn => {
            const isActive = btn.dataset.tabTarget === tabId;
            btn.setAttribute('aria-selected', isActive ? 'true' : 'false');

            if (btn.classList.contains('sidebar-tab-link')) {
                btn.classList.toggle('bg-primary', isActive);
                btn.classList.toggle('text-white', isActive);
                btn.classList.toggle('font-bold', isActive);
                btn.classList.toggle('shadow-xs', isActive);
                btn.classList.toggle('text-slate-300', !isActive);
            } else {
                btn.classList.toggle('bg-card', isActive);
                btn.classList.toggle('text-ink', isActive);
                btn.classList.toggle('shadow-xs', isActive);
                btn.classList.toggle('border-line/80', isActive);
                btn.classList.toggle('text-ink/60', !isActive);
                btn.classList.toggle('border-transparent', !isActive);
            }
        });

        if (updateHash && window.location.hash.replace(/^#/, '') !== tabId) {
            try {
                history.replaceState(null, '', `#${tabId}`);
            } catch {
                window.location.hash = tabId;
            }
        }
    }

    buttons.forEach(btn => {
        btn.addEventListener('click', (e) => {
            e.preventDefault();
            activateTab(btn.dataset.tabTarget, true);
        });
    });

    const initialHash = window.location.hash.replace(/^#/, '');
    if (initialHash && validTabs.includes(initialHash)) {
        activateTab(initialHash, false);
    } else if (defaultTab) {
        activateTab(defaultTab, false);
    }

    window.addEventListener('hashchange', () => {
        const hash = window.location.hash.replace(/^#/, '');
        if (hash && validTabs.includes(hash)) {
            activateTab(hash, false);
        }
    });
}

function initQuickFilters() {
    const filterInputs = document.querySelectorAll('[data-quick-filter]');
    filterInputs.forEach(input => {
        const selector = input.dataset.quickFilter;
        const target = document.querySelector(selector);
        if (!target) return;

        input.addEventListener('input', () => {
            const query = input.value.toLowerCase().trim();
            const rows = target.querySelectorAll('tbody tr:not([data-filter-empty])');
            let matchCount = 0;

            rows.forEach(row => {
                const text = row.textContent.toLowerCase();
                const matched = !query || text.includes(query);
                row.style.display = matched ? '' : 'none';
                if (matched) matchCount++;
            });

            const emptyRow = target.querySelector('[data-filter-empty]');
            if (emptyRow) {
                emptyRow.style.display = (matchCount === 0 && query) ? '' : 'none';
            }
        });
    });
}

/**
 * WebSocket Realtime Queue & Status Listener
 * Listens for EmergencyRequestStatusUpdated via Laravel Reverb / Echo.
 */
function initRealtimeHospitalQueue() {
    const table = document.querySelector('#requests-table[data-hospital-id]');
    if (!table || !window.Echo) return;

    const hospitalId = table.dataset.hospitalId;

    function getStatusBadgeHtml(status) {
        const s = String(status).toLowerCase();
        let bgClass = 'bg-surface text-muted';
        let dotClass = 'bg-muted';

        if (s.includes('search') || s.includes('pending')) {
            bgClass = 'bg-warning-50 text-warning';
            dotClass = 'bg-warning';
        } else if (s.includes('complete')) {
            bgClass = 'bg-success-50 text-success';
            dotClass = 'bg-success';
        } else if (s.includes('cancel')) {
            bgClass = 'bg-danger-50 text-danger';
            dotClass = 'bg-danger';
        } else if (s.includes('assign') || s.includes('accept') || s.includes('route') || s.includes('pickup') || s.includes('board')) {
            bgClass = 'bg-primary-50 text-primary';
            dotClass = 'bg-primary';
        }

        return `<span class="inline-flex items-center gap-1.5 rounded-full px-3 py-1 text-xs font-semibold capitalize ${bgClass}">
            <span class="size-1.5 rounded-full ${dotClass}"></span>
            ${status}
        </span>`;
    }

    function showLiveToast(requestCode, status) {
        let toastContainer = document.getElementById('live-toast-container');
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.id = 'live-toast-container';
            toastContainer.className = 'fixed bottom-4 right-4 z-50 flex flex-col gap-2 max-w-sm pointer-events-none';
            document.body.appendChild(toastContainer);
        }

        const toast = document.createElement('div');
        toast.className = 'pointer-events-auto flex items-center gap-3 rounded-2xl border border-line bg-card p-3.5 shadow-lg text-xs text-ink transition transform duration-300 translate-y-2 opacity-0';
        toast.innerHTML = `
            <span class="flex size-7 shrink-0 items-center justify-center rounded-xl bg-primary text-white">
                <svg class="size-4 animate-spin" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8z"></path></svg>
            </span>
            <div class="min-w-0 flex-1">
                <p class="font-bold text-ink">Status Terupdate (${requestCode})</p>
                <p class="text-[11px] text-ink/70">Status terbaru: <span class="font-semibold text-primary capitalize">${status}</span></p>
            </div>
        `;
        toastContainer.appendChild(toast);

        requestAnimationFrame(() => {
            toast.classList.remove('translate-y-2', 'opacity-0');
        });

        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-2');
            setTimeout(() => toast.remove(), 300);
        }, 4000);
    }

    try {
        const hospitalChannel = window.Echo.channel(`hospital.${hospitalId}`);
        const globalChannel = window.Echo.channel('emergency-requests');

        const handleUpdate = (data) => {
            // Check if this event belongs to this hospital
            if (data.hospital_id && String(data.hospital_id) !== String(hospitalId)) {
                return;
            }

            // Find matching row by ID or request_code
            const row = table.querySelector(`tr[data-request-id="${data.id}"]`) ||
                        table.querySelector(`tr[data-request-code="${data.request_code}"]`);

            if (row) {
                // Update status badge
                const statusCell = row.querySelector('[data-field="status"]');
                if (statusCell) {
                    statusCell.innerHTML = getStatusBadgeHtml(data.status);
                }

                // Sync status select dropdown if present
                const statusSelect = row.querySelector('select[name="status"]');
                if (statusSelect && data.status) {
                    statusSelect.value = data.status;
                }

                // Update driver/ambulance unit cell
                const driverCell = row.querySelector('[data-field="driver-unit"]');
                if (driverCell && (data.driver_name || data.ambulance_plate)) {
                    driverCell.innerHTML = `
                        <p class="font-semibold text-ink">${data.driver_name || '-'}</p>
                        <p class="font-mono text-[11px] text-muted">${data.ambulance_plate || '-'}</p>
                    `;
                }

                // Flash highlight on the updated row
                row.classList.add('bg-primary-50/90');
                setTimeout(() => {
                    row.classList.remove('bg-primary-50/90');
                }, 1800);

                showLiveToast(data.request_code, data.status);
            } else {
                // Prepend new incoming referral row
                const tbody = table.querySelector('tbody');
                const emptyRow = tbody?.querySelector('tr[data-empty-row]');
                if (emptyRow) emptyRow.remove();

                const newRow = document.createElement('tr');
                newRow.className = 'hover:bg-surface/50 transition duration-500 bg-primary-50/90';
                newRow.dataset.requestId = data.id;
                newRow.dataset.requestCode = data.request_code;
                newRow.innerHTML = `
                    <td class="px-4 py-3.5 font-mono text-xs font-bold text-ink">${data.request_code}</td>
                    <td class="px-4 py-3.5">
                        <p class="font-bold text-ink text-xs sm:text-sm">${data.patient_name || '-'}</p>
                        ${data.patient_phone ? `<a href="tel:${data.patient_phone}" class="text-[11px] text-primary hover:underline font-mono">${data.patient_phone}</a>` : ''}
                    </td>
                    <td class="px-4 py-3.5 text-xs">
                        <div class="font-bold text-ink flex items-center gap-1.5">
                            <span>${data.emergency_type || '-'}</span>
                            ${data.severity_level === 'critical' ? '<span class="size-2 rounded-full bg-danger animate-pulse" title="Kritis"></span>' : ''}
                        </div>
                        <p class="text-[11px] text-ink/70">${data.igd_preparation || 'IGD sudah menerima data pasien.'}</p>
                    </td>
                    <td class="hidden px-4 py-3.5 text-xs sm:table-cell" data-field="driver-unit">
                        ${data.driver_name ? `<p class="font-semibold text-ink">${data.driver_name}</p><p class="font-mono text-[11px] text-muted">${data.ambulance_plate || '-'}</p>` : '<span class="text-muted italic">Mencari driver...</span>'}
                    </td>
                    <td class="px-4 py-3.5" data-field="status">
                        ${getStatusBadgeHtml(data.status)}
                    </td>
                    <td class="hidden px-4 py-3.5 text-xs text-muted md:table-cell">
                        ${data.updated_at || 'Baru saja'}
                    </td>
                    <td class="px-4 py-3.5 text-right">
                        <form method="POST" action="/dashboard/hospital/requests/${data.id}/status" class="inline-flex items-center gap-1.5 justify-end">
                            <input type="hidden" name="_token" value="${document.querySelector('meta[name="csrf-token"]')?.getAttribute('content') || ''}">
                            <input type="hidden" name="_method" value="PATCH">
                            <select name="status" class="rounded-lg border border-slate-700 bg-slate-950 px-2 py-1 text-xs text-white">
                                <option value="searching_driver" ${data.status === 'searching_driver' ? 'selected' : ''}>Mencari Driver</option>
                                <option value="driver_assigned" ${data.status === 'driver_assigned' ? 'selected' : ''}>Driver Ditugaskan</option>
                                <option value="en_route_to_pickup" ${data.status === 'en_route_to_pickup' ? 'selected' : ''}>Menuju Pasien</option>
                                <option value="arrive_at_pickup" ${data.status === 'arrive_at_pickup' ? 'selected' : ''}>Tiba di Lokasi</option>
                                <option value="en_route_to_hospital" ${data.status === 'en_route_to_hospital' ? 'selected' : ''}>Menuju RS/IGD</option>
                                <option value="completed" ${data.status === 'completed' ? 'selected' : ''}>Selesai di IGD</option>
                                <option value="cancelled" ${data.status === 'cancelled' ? 'selected' : ''}>Batalkan</option>
                            </select>
                            <button type="submit" class="rounded-lg bg-sky-600 hover:bg-sky-500 text-white px-2.5 py-1 text-xs font-semibold">Ubah</button>
                        </form>
                    </td>
                `;

                if (tbody.firstChild) {
                    tbody.insertBefore(newRow, tbody.firstChild);
                } else {
                    tbody.appendChild(newRow);
                }

                setTimeout(() => {
                    newRow.classList.remove('bg-primary-50/90');
                }, 2000);

                showLiveToast(data.request_code, data.status);
            }
        };

        hospitalChannel.listen('.EmergencyRequestStatusUpdated', handleUpdate);
        hospitalChannel.listen('EmergencyRequestStatusUpdated', handleUpdate);
        globalChannel.listen('.EmergencyRequestStatusUpdated', handleUpdate);
        globalChannel.listen('EmergencyRequestStatusUpdated', handleUpdate);
    } catch (err) {
        console.warn('[Realtime] Failed to subscribe to hospital queue channels:', err);
    }
}

// Auto-run on DOM ready
if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', () => {
        initTabs();
        initQuickFilters();
        initRealtimeHospitalQueue();
    });
} else {
    initTabs();
    initQuickFilters();
    initRealtimeHospitalQueue();
}
