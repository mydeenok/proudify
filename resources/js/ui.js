/**
 * Proudify UI interactions — ported from Stitch design mockups.
 */

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
    initFormSubmitLoadingState();
}
