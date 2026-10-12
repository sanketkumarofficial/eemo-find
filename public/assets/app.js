const toggle = document.getElementById('sidebar-toggle');
const backdrop = document.getElementById('sidebar-backdrop');
const mobile = () => window.matchMedia('(max-width: 900px)').matches;
function closeSidebar() {
    const wasOpen = document.body.classList.contains('sidebar-open');
    document.body.classList.remove('sidebar-open');
    if (backdrop) backdrop.hidden = true;
    toggle?.setAttribute('aria-expanded', mobile() ? 'false' : String(!document.body.classList.contains('sidebar-collapsed')));
    if (wasOpen) toggle?.focus();
}
toggle?.addEventListener('click', () => {
    if (mobile()) {
        const open = document.body.classList.toggle('sidebar-open');
        backdrop.hidden = !open;
        toggle.setAttribute('aria-expanded', String(open));
        if (open) document.querySelector('#sidebar a')?.focus();
    } else {
        const collapsed = document.body.classList.toggle('sidebar-collapsed');
        toggle.setAttribute('aria-expanded', String(!collapsed));
    }
});
backdrop?.addEventListener('click', closeSidebar);
document.addEventListener('keydown', event => {
    if (event.key === 'Escape') closeSidebar();
    if (event.key === 'Tab' && mobile() && document.body.classList.contains('sidebar-open')) {
        const items = [...document.querySelectorAll('#sidebar a, #sidebar button')].filter(el => el.getClientRects().length);
        const first = items[0], last = items.at(-1);
        if (event.shiftKey && document.activeElement === first) { event.preventDefault(); last?.focus(); }
        else if (!event.shiftKey && document.activeElement === last) { event.preventDefault(); first?.focus(); }
    }
});
window.addEventListener('resize', closeSidebar);
closeSidebar();
const securityToggle = document.getElementById('security-toggle');
securityToggle?.addEventListener('click', () => {
    const open = securityToggle.getAttribute('aria-expanded') === 'true';
    securityToggle.setAttribute('aria-expanded', String(!open));
    document.getElementById('security-submenu').hidden = open;
});
const dialog = document.getElementById('confirm-dialog');
let pendingForm;
document.querySelectorAll('form[data-confirm]').forEach(form => {
    form.addEventListener('submit', event => {
        if (!dialog?.showModal) {
            if (!window.confirm(form.dataset.confirm)) event.preventDefault();
            return;
        }
        event.preventDefault();
        pendingForm = form;
        document.getElementById('confirm-message').textContent = form.dataset.confirm;
        dialog.showModal();
        document.getElementById('confirm-cancel').focus();
    });
});
document.getElementById('confirm-cancel')?.addEventListener('click', () => dialog.close());
document.getElementById('confirm-accept')?.addEventListener('click', () => { dialog.close(); pendingForm?.submit(); });
const clock = document.querySelector('[data-clock]');
if (clock) {
    const start = Date.parse(clock.dateTime);
    const loaded = Date.now();
    const updateClock = () => {
        clock.textContent = new Intl.DateTimeFormat('en-GB', { weekday: 'short', day: '2-digit', month: 'short', year: 'numeric', hour: '2-digit', minute: '2-digit', timeZone: clock.dataset.timezone }).format(new Date(start + Date.now() - loaded)) + ' ' + clock.dataset.timezone;
    };
    updateClock();
    setInterval(updateClock, 30000);
}
