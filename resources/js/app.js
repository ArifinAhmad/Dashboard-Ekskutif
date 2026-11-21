const sidebar = document.querySelector('#sidebar');
const menuButton = document.querySelector('[data-menu-toggle]');
const desktopSidebar = window.matchMedia('(min-width: 1001px)');

const setMenuOpen = (open) => {
    sidebar?.classList.toggle('open', open);
    document.body.classList.toggle('menu-open', open);
    menuButton?.setAttribute('aria-expanded', String(open));
};

const syncSidebarButton = () => {
    const collapsed = document.documentElement.classList.contains('sidebar-collapsed');
    menuButton?.setAttribute('aria-expanded', String(desktopSidebar.matches ? !collapsed : sidebar?.classList.contains('open')));
    menuButton?.setAttribute('aria-label', desktopSidebar.matches && !collapsed ? 'Sembunyikan sidebar' : 'Tampilkan sidebar');
};

menuButton?.addEventListener('click', () => {
    if (!desktopSidebar.matches) {
        setMenuOpen(!sidebar?.classList.contains('open'));
        return;
    }
    const collapsed = document.documentElement.classList.toggle('sidebar-collapsed');
    localStorage.setItem('dashboard-sidebar', collapsed ? 'collapsed' : 'expanded');
    syncSidebarButton();
});
desktopSidebar.addEventListener('change', () => {
    if (desktopSidebar.matches) setMenuOpen(false);
    syncSidebarButton();
});
syncSidebarButton();
document.querySelectorAll('[data-menu-close]').forEach((button) => button.addEventListener('click', () => setMenuOpen(false)));
document.querySelector('[data-bottom-menu]')?.addEventListener('click', () => setMenuOpen(true));
sidebar?.querySelectorAll('a').forEach((link) => link.addEventListener('click', () => setMenuOpen(false)));
document.addEventListener('keydown', (event) => {
    if (event.key === 'Escape') setMenuOpen(false);
});

const toggleTheme = () => {
    const theme = document.documentElement.dataset.theme === 'dark' ? 'light' : 'dark';
    document.documentElement.dataset.theme = theme;
    localStorage.setItem('ee-theme', theme);
};
document.querySelector('[data-theme-toggle]')?.addEventListener('click', toggleTheme);
document.querySelector('[data-bottom-theme]')?.addEventListener('click', toggleTheme);
document.querySelector('[data-bottom-logout]')?.addEventListener('click', () => document.querySelector('[data-session-logout]')?.requestSubmit());

const sessionLifetime = Number(document.body.dataset.sessionLifetime || 0) * 60 * 1000;
if (sessionLifetime > 0) {
    let sessionTimer;
    const logoutForm = document.querySelector('[data-session-logout]');
    const resetSessionTimer = () => {
        window.clearTimeout(sessionTimer);
        sessionTimer = window.setTimeout(() => logoutForm?.requestSubmit() ?? window.location.assign(document.body.dataset.loginUrl || '/login'), sessionLifetime + 1000);
    };
    ['click', 'keydown', 'pointerdown', 'touchstart'].forEach((event) => document.addEventListener(event, resetSessionTimer, { passive: true }));
    resetSessionTimer();
}

document.querySelectorAll('[data-tabs]').forEach((tabs) => {
    const buttons = tabs.querySelectorAll('[data-tab-target]');
    const panels = tabs.querySelectorAll('[data-tab-panel]');

    buttons.forEach((button) => button.addEventListener('click', () => {
        buttons.forEach((item) => item.classList.toggle('active', item === button));
        panels.forEach((panel) => panel.classList.toggle('active', panel.dataset.tabPanel === button.dataset.tabTarget));
    }));
});

const dashboardRefresh = document.querySelector('[data-dashboard-refresh]');
if (dashboardRefresh) {
    const interval = Math.max(60, Number(dashboardRefresh.dataset.dashboardRefresh || 600)) * 1000;
    const countdown = document.querySelector('[data-refresh-countdown]');
    let deadline = Date.now() + interval;
    let ticker;

    const renderCountdown = () => {
        const remaining = Math.max(0, deadline - Date.now());
        const minutes = Math.floor(remaining / 60000);
        const seconds = Math.floor((remaining % 60000) / 1000);
        if (countdown) countdown.textContent = `${String(minutes).padStart(2, '0')}:${String(seconds).padStart(2, '0')}`;
        if (remaining === 0 && document.visibilityState === 'visible') window.location.reload();
    };
    const startTicker = () => {
        window.clearInterval(ticker);
        renderCountdown();
        ticker = window.setInterval(renderCountdown, 1000);
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            window.clearInterval(ticker);
            return;
        }
        if (Date.now() >= deadline) {
            window.location.reload();
            return;
        }
        startTicker();
    });
    startTicker();
}

const autoScrollButton = document.querySelector('[data-auto-scroll-toggle]');
if (autoScrollButton) {
    const status = autoScrollButton.querySelector('[data-auto-scroll-status]');
    const reducedMotion = window.matchMedia('(prefers-reduced-motion: reduce)');
    let enabled = localStorage.getItem('dashboard-auto-scroll') === 'enabled' && !reducedMotion.matches;
    let paused = false;
    let direction = 1;
    let frame;
    let pauseTimer;
    let previousTime = 0;
    let edgePauseUntil = 0;
    const speed = Math.max(20, Math.min(200, Number(autoScrollButton.dataset.scrollSpeed || 86)));

    const syncAutoScroll = () => {
        autoScrollButton.classList.toggle('active', enabled);
        autoScrollButton.setAttribute('aria-pressed', String(enabled));
        autoScrollButton.title = enabled ? 'Nonaktifkan auto scroll berulang' : 'Aktifkan auto scroll berulang';
        if (status) status.textContent = enabled ? (paused ? 'Dijeda' : 'Aktif') : 'Nonaktif';
    };
    const animate = (time) => {
        if (!enabled) return;
        if (!previousTime) previousTime = time;
        const elapsed = Math.min(50, time - previousTime);
        previousTime = time;
        if (!paused && document.visibilityState === 'visible' && time >= edgePauseUntil) {
            const maximum = Math.max(0, document.documentElement.scrollHeight - window.innerHeight);
            window.scrollBy(0, direction * speed * elapsed / 1000);
            if (direction > 0 && window.scrollY >= maximum - 2) {
                direction = -1;
                edgePauseUntil = time + 1800;
            } else if (direction < 0 && window.scrollY <= 2) {
                direction = 1;
                edgePauseUntil = time + 1800;
            }
        }
        frame = window.requestAnimationFrame(animate);
    };
    const startAutoScroll = () => {
        window.cancelAnimationFrame(frame);
        previousTime = 0;
        if (enabled) frame = window.requestAnimationFrame(animate);
        syncAutoScroll();
    };

    autoScrollButton.addEventListener('click', () => {
        enabled = !enabled;
        localStorage.setItem('dashboard-auto-scroll', enabled ? 'enabled' : 'disabled');
        startAutoScroll();
    });
    const pauseForInteraction = () => {
        if (!enabled) return;
        paused = true;
        syncAutoScroll();
        window.clearTimeout(pauseTimer);
        pauseTimer = window.setTimeout(() => {
            paused = false;
            previousTime = 0;
            syncAutoScroll();
        }, 4000);
    };
    ['wheel', 'pointerdown', 'touchstart', 'keydown'].forEach((event) => document.addEventListener(event, pauseForInteraction, { passive: true }));
    document.addEventListener('visibilitychange', () => { previousTime = 0; });
    reducedMotion.addEventListener('change', () => {
        if (reducedMotion.matches && enabled) {
            enabled = false;
            localStorage.setItem('dashboard-auto-scroll', 'disabled');
            startAutoScroll();
        }
    });
    startAutoScroll();
}

document.querySelectorAll('[data-modal-open]').forEach((button) => {
    button.addEventListener('click', () => {
        const modal = document.getElementById(button.dataset.modalOpen);
        modal?.showModal();
        modal?.querySelector('input:not([type="hidden"]), button')?.focus();
    });
});

document.querySelectorAll('.app-modal').forEach((modal) => {
    modal.querySelectorAll('[data-modal-close]').forEach((button) => button.addEventListener('click', () => modal.close()));
    modal.addEventListener('click', (event) => {
        const bounds = modal.getBoundingClientRect();
        if (event.clientX < bounds.left || event.clientX > bounds.right || event.clientY < bounds.top || event.clientY > bounds.bottom) modal.close();
    });
});

document.querySelectorAll('[data-load-more-table]').forEach((container) => {
    const button = container.querySelector('[data-load-more-url]');
    const tbody = container.querySelector('tbody');
    const shown = container.querySelector('[data-shown-count]');
    if (!button || !tbody) return;

    let controller;
    button.addEventListener('click', async (event) => {
        event.preventDefault();
        if (button.disabled) return;
        controller?.abort();
        controller = new AbortController();
        const label = button.querySelector('span');
        const original = label?.textContent;
        button.setAttribute('aria-disabled', 'true');
        button.classList.add('loading');
        if (label) label.textContent = 'Memuat data...';

        const params = new URLSearchParams({
            from: button.dataset.from,
            to: button.dataset.to,
            section: button.dataset.section,
            page: button.dataset.page,
            direction: button.dataset.direction || 'asc',
        });
        if (button.dataset.sort) params.set('sort', button.dataset.sort);

        try {
            const response = await fetch(`${button.dataset.loadMoreUrl}?${params}`, {
                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                signal: controller.signal,
            });
            if (!response.ok) throw new Error('Gagal memuat data berikutnya.');
            const payload = await response.json();
            tbody.insertAdjacentHTML('beforeend', payload.html);
            button.dataset.page = String(payload.page + 1);
            if (shown) shown.textContent = payload.shown.toLocaleString('id-ID');
            if (payload.page >= payload.last_page) {
                button.remove();
                return;
            }
            if (label) label.textContent = original;
            button.removeAttribute('aria-disabled');
            button.classList.remove('loading');
        } catch (error) {
            if (error.name === 'AbortError') return;
            if (label) label.textContent = 'Coba lagi';
            button.title = error.message;
            button.removeAttribute('aria-disabled');
            button.classList.remove('loading');
        }
    });
});

const normalizeRegion = (value) => String(value ?? '')
    .toUpperCase()
    .normalize('NFD')
    .replace(/[\u0300-\u036f]/g, '')
    .replace(/\b(KABUPATEN|KAB\.?|ADMINISTRASI)\b/g, '')
    .replace(/[^A-Z0-9]/g, '');

const ntbRegions = new Set([
    'BIMA', 'KOTABIMA', 'DOMPU', 'KOTAMATARAM', 'LOMBOKBARAT',
    'LOMBOKTENGAH', 'LOMBOKTIMUR', 'LOMBOKUTARA', 'SUMBAWA', 'SUMBAWABARAT',
]);

const loadPatientMap = async (element) => {
    element.innerHTML = '<div class="map-loading">Memuat modul peta...</div>';
    let L;
    let ntbDistrictsUrl;
    try {
        [{ default: L }, { default: ntbDistrictsUrl }] = await Promise.all([
            import('leaflet'),
            import('./maps/ntb-adm2.geojson?url'),
            import('leaflet/dist/leaflet.css'),
        ]);
    } catch {
        element.innerHTML = '<div class="map-loading error">Modul peta tidak dapat dimuat.</div>';
        return;
    }
    const areas = JSON.parse(element.dataset.areas || '[]');
    const patients = new Map();
    areas.forEach((area) => {
        let key = normalizeRegion(area.name);
        if (key === 'MATARAM') key = 'KOTAMATARAM';
        patients.set(key, (patients.get(key) ?? 0) + (Number(area.total) || 0));
    });
    element.innerHTML = '<div class="map-loading">Memuat batas wilayah...</div>';
    let geojson;
    try {
        geojson = await fetch(ntbDistrictsUrl).then((response) => {
            if (!response.ok) throw new Error('GeoJSON unavailable');
            return response.json();
        });
    } catch {
        element.innerHTML = '<div class="map-loading error">Peta wilayah tidak dapat dimuat.</div>';
        return;
    }
    element.innerHTML = '';
    geojson.features = geojson.features.filter((feature) => ntbRegions.has(normalizeRegion(feature.properties?.shapeName)));
    const maximum = Math.max(1, ...geojson.features.map((feature) => patients.get(normalizeRegion(feature.properties?.shapeName)) ?? 0));
    const palette = ['#dfeee5', '#c8e7d2', '#99d4af', '#52b47c', '#148454', '#f2a044', '#df7322'];
    const map = L.map(element, { attributionControl: false, minZoom: 7, maxZoom: 12, zoomControl: true });
    const totalFor = (feature) => patients.get(normalizeRegion(feature.properties?.shapeName)) ?? 0;
    const colorFor = (total) => {
        if (!total) return palette[0];
        const index = Math.min(palette.length - 1, Math.max(1, Math.ceil(total / maximum * (palette.length - 1))));
        return palette[index];
    };

    const layer = L.geoJSON(geojson, {
        style: (feature) => ({ color: '#fff', weight: 0.75, fillColor: colorFor(totalFor(feature)), fillOpacity: 0.9 }),
        onEachFeature: (feature, region) => {
            const name = feature.properties?.shapeName || 'Wilayah tidak diketahui';
            const total = totalFor(feature);
            region.bindTooltip(`<strong>${name}</strong><br>${total.toLocaleString('id-ID')} pasien`, { sticky: true, direction: 'top' });
            region.on({
                mouseover: () => region.setStyle({ weight: 2.2, color: '#d96a12', fillOpacity: 1 }),
                mouseout: () => layer.resetStyle(region),
                click: () => map.fitBounds(region.getBounds(), { padding: [24, 24], maxZoom: 9 }),
            });
        },
    }).addTo(map);

    map.fitBounds(layer.getBounds(), { padding: [12, 12] });
    L.control.attribution({ position: 'bottomleft', prefix: false }).addAttribution('Batas wilayah: geoBoundaries / BPS, WFP, OCHA').addTo(map);

    const legend = L.control({ position: 'bottomright' });
    legend.onAdd = () => {
        const node = L.DomUtil.create('div', 'map-legend');
        node.innerHTML = `<strong>Jumlah pasien</strong>${palette.slice(1).map((color, index) => `<span><i style="background:${color}"></i>${index === 0 ? 'Rendah' : index === palette.length - 2 ? 'Tinggi' : ''}</span>`).join('')}`;
        return node;
    };
    legend.addTo(map);
};

document.querySelectorAll('[data-patient-map]').forEach((element) => {
    if (!('IntersectionObserver' in window)) {
        loadPatientMap(element);
        return;
    }

    const observer = new IntersectionObserver((entries) => {
        if (!entries.some((entry) => entry.isIntersecting)) return;
        observer.disconnect();
        loadPatientMap(element);
    }, { rootMargin: '500px 0px' });
    observer.observe(element);
});
