/**
 * CariAmbulan Client-side Tab & Filter Utilities
 * Lightweight, zero-dependency, hash-synced tabs for Command Hub dashboards.
 */

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
        } else if (s.includes('en_route') || s.includes('arrive') || s.includes('assigned')) {
            bgClass = 'bg-primary-50 text-primary';
            dotClass = 'bg-primary';
        } else if (s.includes('complete')) {
            bgClass = 'bg-success-50 text-success';
            dotClass = 'bg-success';
        } else if (s.includes('cancel')) {
            bgClass = 'bg-danger-50 text-danger';
            dotClass = 'bg-danger';
        }

        const labels = {
            searching_driver: 'Mencari Driver',
            driver_assigned: 'Driver Ditugaskan',
            en_route_to_pickup: 'Menuju Pasien',
            arrive_at_pickup: 'Tiba di Lokasi',
            en_route_to_hospital: 'Menuju RS/IGD',
            completed: 'Selesai di IGD',
            cancelled: 'Dibatalkan',
        };

        const label = labels[s] || status;

        return `<span class="inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-semibold ${bgClass}">
            <span class="size-1.5 rounded-full ${dotClass}"></span>
            ${label}
        </span>`;
    }

    try {
        const hospitalChannel = window.Echo.channel(`hospital.${hospitalId}`);
        const globalChannel = window.Echo.channel('emergencies');

        const handleUpdate = (event) => {
            const data = event.emergencyRequest || event;
            if (!data || !data.id) return;

            const row = table.querySelector(`tr[data-request-id="${data.id}"]`);
            if (row) {
                const statusCell = row.querySelector('[data-field="status"]');
                if (statusCell) {
                    statusCell.innerHTML = getStatusBadgeHtml(data.status);
                }

                const driverCell = row.querySelector('[data-field="driver-unit"]');
                if (driverCell && data.driver_name) {
                    driverCell.innerHTML = `<p class="font-semibold text-ink">${data.driver_name}</p><p class="font-mono text-[11px] text-muted">${data.ambulance_plate || '-'}</p>`;
                }

                row.classList.add('bg-primary-50/60');
                setTimeout(() => {
                    row.classList.remove('bg-primary-50/60');
                }, 2000);
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
