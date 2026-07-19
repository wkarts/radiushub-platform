(() => {
    const root = document.documentElement;
    const sidebar = document.querySelector('.sidebar');
    const overlay = document.querySelector('[data-mobile-overlay]');
    const isMobile = () => window.matchMedia('(max-width: 900px)').matches;

    const closeModal = modal => { if (!modal) return; modal.classList.remove('open'); document.body.style.overflow = ''; };
    const openModal = modal => { if (!modal) return; modal.classList.add('open'); document.body.style.overflow = 'hidden'; setTimeout(() => modal.querySelector('input:not([type=hidden]),select,textarea')?.focus(), 30); };
    const closeMobileMenu = () => { sidebar?.classList.remove('open'); overlay?.classList.remove('open'); document.body.style.overflow = ''; };

    try {
        document.querySelectorAll('[data-nav-group]').forEach(group => {
            const key = `radiushub-nav-${group.dataset.navGroup}`;
            if (localStorage.getItem(key) === '1') group.classList.add('open');
        });
    } catch (e) {}

    document.addEventListener('click', event => {
        const open = event.target.closest('[data-modal-open]');
        if (open) { event.preventDefault(); openModal(document.getElementById(open.dataset.modalOpen)); return; }
        const close = event.target.closest('[data-modal-close]');
        if (close) { event.preventDefault(); closeModal(close.closest('.modal-backdrop')); return; }
        if (event.target.classList.contains('modal-backdrop')) closeModal(event.target);

        const theme = event.target.closest('[data-theme-toggle]');
        if (theme) { const next = root.dataset.theme === 'dark' ? 'light' : 'dark'; root.dataset.theme = next; localStorage.setItem('radiushub-theme', next); }

        if (event.target.closest('[data-menu-toggle]')) {
            sidebar?.classList.toggle('open'); overlay?.classList.toggle('open');
            document.body.style.overflow = sidebar?.classList.contains('open') ? 'hidden' : '';
        }
        if (event.target.closest('[data-mobile-overlay]')) closeMobileMenu();

        const collapse = event.target.closest('[data-sidebar-collapse]');
        if (collapse && !isMobile()) {
            root.classList.toggle('sidebar-collapsed');
            localStorage.setItem('radiushub-sidebar-collapsed', root.classList.contains('sidebar-collapsed') ? '1' : '0');
        }

        const groupToggle = event.target.closest('[data-nav-group-toggle]');
        if (groupToggle) {
            const group = groupToggle.closest('[data-nav-group]');
            if (root.classList.contains('sidebar-collapsed') && !isMobile()) group.classList.toggle('flyout-open');
            else group.classList.toggle('open');
            try { localStorage.setItem(`radiushub-nav-${group.dataset.navGroup}`, group.classList.contains('open') ? '1' : '0'); } catch (e) {}
        }

        if (isMobile() && event.target.closest('.sidebar a.nav-link')) closeMobileMenu();

        const confirm = event.target.closest('[data-confirm]');
        if (confirm && !window.confirm(confirm.dataset.confirm || 'Confirma esta operação?')) event.preventDefault();

        const copy = event.target.closest('[data-copy-target]');
        if (copy) {
            const target = document.querySelector(copy.dataset.copyTarget);
            navigator.clipboard?.writeText(target?.value || target?.textContent || '').then(() => { const old=copy.textContent; copy.textContent='Copiado'; setTimeout(()=>copy.textContent=old,1300); });
        }
    });

    document.addEventListener('keydown', event => { if (event.key === 'Escape') { closeModal(document.querySelector('.modal-backdrop.open')); closeMobileMenu(); } });
    document.querySelectorAll('[data-autosubmit]').forEach(el => el.addEventListener('change', () => el.form?.submit()));
    document.querySelectorAll('[data-alert-close]').forEach(el => el.addEventListener('click', () => el.closest('.alert')?.remove()));
    setTimeout(() => document.querySelectorAll('.alert[data-auto-dismiss]').forEach(el => el.remove()), 7000);
})();
