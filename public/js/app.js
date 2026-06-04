/* tt-apps — global JS */

document.addEventListener('DOMContentLoaded', () => {
    // Disable submit buttons on form submit to prevent double-submission
    document.querySelectorAll('form').forEach((form) => {
        form.addEventListener('submit', () => {
            form.querySelectorAll('button[type="submit"], input[type="submit"]').forEach((btn) => {
                btn.disabled = true;
                if (btn.dataset.loadingText) btn.textContent = btn.dataset.loadingText;
            });
        });
    });

    // Expandable nav groups
    document.querySelectorAll('[data-nav-toggle]').forEach((btn) => {
        btn.addEventListener('click', () => {
            const group = btn.closest('[data-nav-group]');
            const open  = group.classList.toggle('is-open');
            btn.setAttribute('aria-expanded', open ? 'true' : 'false');
        });
    });

    // Auto-dismiss flash banners after 5 seconds
    document.querySelectorAll('.banner[role="status"]').forEach((el) => {
        setTimeout(() => {
            el.style.transition = 'opacity 0.3s ease';
            el.style.opacity    = '0';
            setTimeout(() => el.remove(), 300);
        }, 5000);
    });
});
