const root = document.documentElement;

function applyTheme(theme) {
    root.dataset.theme = theme;
    try {
        localStorage.setItem('bkh-theme', theme);
    } catch (error) {
        /* storage may be unavailable */
    }
}

window.bkhToggleTheme = function () {
    applyTheme(root.dataset.theme === 'dark' ? 'light' : 'dark');
};

function initThemeToggle() {
    const button = document.getElementById('theme-toggle');
    if (button) {
        button.addEventListener('click', window.bkhToggleTheme);
    }
}

function initNavigation() {
    const sidebar = document.getElementById('app-sidebar');
    const toggle = document.getElementById('nav-toggle');
    const scrim = document.getElementById('nav-scrim');

    const close = () => {
        sidebar?.classList.remove('nav-open');
        scrim?.classList.remove('show');
    };

    toggle?.addEventListener('click', () => {
        sidebar?.classList.toggle('nav-open');
        scrim?.classList.toggle('show');
    });

    scrim?.addEventListener('click', close);

    window.addEventListener('keydown', (event) => {
        if (event.key === 'Escape') {
            close();
        }
    });
}

function initClock() {
    const target = document.getElementById('live-clock');
    if (!target) {
        return;
    }

    const label = target.querySelector('span');
    const timezone = document.body.dataset.timezone || undefined;
    let formatter;

    try {
        formatter = new Intl.DateTimeFormat('en-GB', {
            timeZone: timezone,
            weekday: 'short',
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        });
    } catch (error) {
        formatter = new Intl.DateTimeFormat('en-GB', {
            weekday: 'short',
            day: '2-digit',
            month: 'short',
            hour: '2-digit',
            minute: '2-digit',
            second: '2-digit',
            hour12: false,
        });
    }

    const tick = () => {
        if (label) {
            label.textContent = formatter.format(new Date()).replace(',', ' ·');
        }
    };

    tick();
    window.setInterval(tick, 1000);
}

function initFlash() {
    document.querySelectorAll('[data-flash]').forEach((item) => {
        window.setTimeout(() => {
            item.style.transition = 'opacity .4s ease, transform .4s ease';
            item.style.opacity = '0';
            item.style.transform = 'translateY(-4px)';
            window.setTimeout(() => item.remove(), 450);
        }, 6000);
    });
}

function initConfirmations() {
    document.querySelectorAll('form[data-confirm]').forEach((form) => {
        form.addEventListener('submit', (event) => {
            if (!window.confirm(form.dataset.confirm)) {
                event.preventDefault();
            }
        });
    });

    document.querySelectorAll('[data-confirm-click]').forEach((element) => {
        element.addEventListener('click', (event) => {
            if (!window.confirm(element.dataset.confirmClick)) {
                event.preventDefault();
            }
        });
    });
}

function initDialogs() {
    document.querySelectorAll('dialog[data-dialog]').forEach((dialog) => {
        dialog.addEventListener('click', (event) => {
            if (event.target === dialog) {
                dialog.close();
            }
        });
    });

    document.querySelectorAll('[data-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });
}

function initDropdowns() {
    document.querySelectorAll('details.user-menu').forEach((menu) => {
        document.addEventListener('click', (event) => {
            if (menu.open && !menu.contains(event.target)) {
                menu.open = false;
            }
        });
    });
}

function formatMoney(minor, symbol) {
    const value = Number(minor || 0) / 100;
    const formatted = value.toLocaleString(undefined, {
        minimumFractionDigits: 2,
        maximumFractionDigits: 2,
    });
    return symbol ? symbol + ' ' + formatted : formatted;
}

window.bkh = {
    formatMoney,
    print: () => window.print(),
    closeDialog: (id) => document.getElementById(id)?.close(),
};

document.addEventListener('DOMContentLoaded', () => {
    initThemeToggle();
    initNavigation();
    initClock();
    initFlash();
    initConfirmations();
    initDialogs();
    initDropdowns();
});
