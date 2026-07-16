/**
 * Proudify UI interactions — ported from Stitch design mockups.
 */

/** Landing page + global: scroll-reveal for .fade-up elements */
export function initFadeUp() {
    const observer = new IntersectionObserver((entries) => {
        entries.forEach((entry) => {
            if (entry.isIntersecting) {
                entry.target.classList.add('visible');
            }
        });
    }, { threshold: 0.1 });

    document.querySelectorAll('.fade-up').forEach((el) => observer.observe(el));
}

/** pricing_proudify: FAQ accordion */
export function initFaqAccordion() {
    document.querySelectorAll('[data-faq-toggle]').forEach((button) => {
        button.addEventListener('click', () => {
            const content = button.nextElementSibling;
            const icon = button.querySelector('.material-symbols-outlined');
            const isHidden = content.classList.contains('hidden');

            content.classList.toggle('hidden', !isHidden);
            icon?.classList.toggle('rotate-180', isHidden);
        });
    });
}

/** pricing_proudify: monthly / yearly billing toggle */
export function initBillingToggle() {
    const toggle = document.getElementById('billing-toggle');
    const thumb = document.getElementById('billing-toggle-thumb');
    if (!toggle || !thumb) return;

    let isYearly = toggle.dataset.yearly === 'true';

    const sync = () => {
        toggle.classList.toggle('bg-primary-container', isYearly);
        toggle.classList.toggle('bg-surface-variant', !isYearly);
        thumb.classList.toggle('translate-x-5', isYearly);
        thumb.classList.toggle('translate-x-0', !isYearly);
        toggle.setAttribute('aria-checked', isYearly ? 'true' : 'false');

        document.querySelectorAll('[data-price-monthly]').forEach((el) => {
            el.classList.toggle('hidden', isYearly);
        });
        document.querySelectorAll('[data-price-yearly]').forEach((el) => {
            el.classList.toggle('hidden', !isYearly);
        });
        document.querySelectorAll('[data-billing-period]').forEach((el) => {
            el.textContent = isYearly ? 'yearly' : 'monthly';
        });
    };

    toggle.addEventListener('click', () => {
        isYearly = !isYearly;
        sync();
    });

    sync();
}

/** my_certificates_proudify: list / grid view toggle */
export function initViewModeToggle() {
    const root = document.getElementById('certificates-view');
    if (!root) return;

    const tableView = document.getElementById('certificates-table-view');
    const gridView = document.getElementById('certificates-grid-view');
    const btnList = document.getElementById('view-list');
    const btnGrid = document.getElementById('view-grid');
    if (!tableView || !gridView || !btnList || !btnGrid) return;

    const setMode = (mode) => {
        const isGrid = mode === 'grid';
        tableView.classList.toggle('hidden', isGrid);
        gridView.classList.toggle('hidden', !isGrid);
        btnList.classList.toggle('bg-surface', !isGrid);
        btnList.classList.toggle('shadow-sm', !isGrid);
        btnList.classList.toggle('text-primary', !isGrid);
        btnGrid.classList.toggle('bg-surface', isGrid);
        btnGrid.classList.toggle('shadow-sm', isGrid);
        btnGrid.classList.toggle('text-primary', isGrid);
        localStorage.setItem('certificates-view-mode', mode);
    };

    btnList.addEventListener('click', () => setMode('list'));
    btnGrid.addEventListener('click', () => setMode('grid'));
    setMode(localStorage.getItem('certificates-view-mode') || 'list');
}

/** certificate_verification_proudify: copy credential ID / link */
export function initCopyButtons() {
    document.querySelectorAll('[data-copy]').forEach((btn) => {
        btn.addEventListener('click', async () => {
            const text = btn.dataset.copy;
            if (!text) return;

            try {
                await navigator.clipboard.writeText(text);
                const original = btn.innerHTML;
                btn.innerHTML = '<span class="material-symbols-outlined text-[16px]">check</span>';
                setTimeout(() => { btn.innerHTML = original; }, 2000);
            } catch {
                /* clipboard unavailable */
            }
        });
    });
}

/** account_settings_proudify: tab panels */
export function initSettingsTabs() {
    const root = document.getElementById('settings-tabs');
    if (!root) return;

    const buttons = root.querySelectorAll('[data-tab]');
    const panels = document.querySelectorAll('[data-tab-panel]');

    buttons.forEach((btn) => {
        btn.addEventListener('click', () => {
            const tab = btn.dataset.tab;
            buttons.forEach((b) => {
                b.classList.toggle('nav-tab-active', b.dataset.tab === tab);
                b.classList.toggle('nav-tab', b.dataset.tab !== tab);
            });
            panels.forEach((panel) => {
                panel.classList.toggle('hidden', panel.dataset.tabPanel !== tab);
            });
        });
    });
}

/** bulk_upload / file inputs: show selected filename */
export function initFileInputLabels() {
    document.querySelectorAll('[data-file-label]').forEach((input) => {
        input.addEventListener('change', () => {
            const label = document.querySelector(input.dataset.fileLabel);
            if (label && input.files?.[0]) {
                label.textContent = input.files[0].name;
            }
        });
    });
}

/** bulk_upload_proudify: drag-and-drop file zones */
export function initFileDropZones() {
    document.querySelectorAll('[data-drop-zone]').forEach((zone) => {
        const input = zone.querySelector('input[type="file"]');
        const nameEl = zone.querySelector('[data-drop-filename]');
        if (!input) return;

        const setFile = (file) => {
            if (!file) return;
            const dt = new DataTransfer();
            dt.items.add(file);
            input.files = dt.files;
            input.dispatchEvent(new Event('change', { bubbles: true }));
            if (nameEl) {
                nameEl.textContent = file.name;
            }
        };

        const highlight = (on) => {
            zone.classList.toggle('border-primary-container', on);
            zone.classList.toggle('bg-surface-container-low', on);
            zone.classList.toggle('border-outline-variant', !on);
        };

        zone.addEventListener('dragover', (e) => {
            e.preventDefault();
            highlight(true);
        });
        zone.addEventListener('dragleave', (e) => {
            if (!zone.contains(e.relatedTarget)) {
                highlight(false);
            }
        });
        zone.addEventListener('drop', (e) => {
            e.preventDefault();
            highlight(false);
            const file = e.dataTransfer?.files?.[0];
            if (file) setFile(file);
        });
        input.addEventListener('change', () => {
            if (nameEl && input.files?.[0]) {
                nameEl.textContent = input.files[0].name;
            }
        });
    });
}

/**
 * Global: disable the button that triggered a form submission and spin its
 * icon (or add one), so every plain POST/PATCH/DELETE form gets feedback
 * without each page wiring up its own JS. Pages that already manage their
 * own submitting state (e.g. via Alpine) opt out with data-no-loading-state.
 */
export function initFormSubmitLoadingState() {
    document.addEventListener('submit', (event) => {
        const form = event.target;
        if (!(form instanceof HTMLFormElement)) return;
        if (event.defaultPrevented || form.hasAttribute('data-no-loading-state')) return;

        const button = event.submitter;
        if (!button || button.disabled) return;

        button.disabled = true;

        const icon = button.querySelector('.material-symbols-outlined');
        if (icon) {
            icon.textContent = 'progress_activity';
            icon.classList.add('animate-spin');
        } else {
            const spinner = document.createElement('span');
            spinner.className = 'material-symbols-outlined text-[16px] animate-spin';
            spinner.setAttribute('aria-hidden', 'true');
            spinner.textContent = 'progress_activity';
            button.prepend(spinner);
        }
    });
}

export function initProudifyUi() {
    initFadeUp();
    initFaqAccordion();
    initBillingToggle();
    initViewModeToggle();
    initCopyButtons();
    initSettingsTabs();
    initFileInputLabels();
    initFileDropZones();
    initFormSubmitLoadingState();
}
