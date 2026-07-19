(() => {
    const root = document.documentElement;
    const savedTheme = localStorage.getItem('radiushub-theme');
    if (savedTheme) root.dataset.theme = savedTheme;

    const closeModal = modal => {
        if (!modal) return;
        modal.classList.remove('open');
        document.body.style.overflow = '';
    };
    const openModal = modal => {
        if (!modal) return;
        modal.classList.add('open');
        document.body.style.overflow = 'hidden';
        setTimeout(() => modal.querySelector('input:not([type=hidden]),select,textarea')?.focus(), 30);
    };

    document.addEventListener('click', event => {
        const open = event.target.closest('[data-modal-open]');
        if (open) { event.preventDefault(); openModal(document.getElementById(open.dataset.modalOpen)); return; }
        const close = event.target.closest('[data-modal-close]');
        if (close) { event.preventDefault(); closeModal(close.closest('.modal-backdrop')); return; }
        if (event.target.classList.contains('modal-backdrop')) closeModal(event.target);

        const theme = event.target.closest('[data-theme-toggle]');
        if (theme) {
            const next = root.dataset.theme === 'dark' ? 'light' : 'dark';
            root.dataset.theme = next; localStorage.setItem('radiushub-theme', next);
        }

        const menu = event.target.closest('[data-menu-toggle]');
        if (menu) {
            document.querySelector('.sidebar')?.classList.toggle('open');
            document.querySelector('.mobile-overlay')?.classList.toggle('open');
        }
        if (event.target.classList.contains('mobile-overlay')) {
            document.querySelector('.sidebar')?.classList.remove('open');
            event.target.classList.remove('open');
        }

        const confirm = event.target.closest('[data-confirm]');
        if (confirm && !window.confirm(confirm.dataset.confirm || 'Confirma esta operação?')) event.preventDefault();
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') closeModal(document.querySelector('.modal-backdrop.open'));
    });

    document.querySelectorAll('[data-autosubmit]').forEach(el => el.addEventListener('change', () => el.form?.submit()));
    document.querySelectorAll('[data-alert-close]').forEach(el => el.addEventListener('click', () => el.closest('.alert')?.remove()));
    setTimeout(() => document.querySelectorAll('.alert[data-auto-dismiss]').forEach(el => el.remove()), 7000);
})();
