/* tt-apps — global JS */

document.addEventListener('DOMContentLoaded', () => {
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
