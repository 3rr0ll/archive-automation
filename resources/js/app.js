// resources/js/app.js

import Alpine from 'alpinejs';
import { loginForm } from './components/login-form.js';
import { deploymentTable } from './components/deployment-table.js';
import { userNav } from './components/user-nav.js';
import { dashboardOverview } from './components/dashboard-overview.js';
import { queueData } from './components/queue.js';
import { domainListData } from './components/domain-list.js';

window.Alpine = Alpine;

Alpine.data('loginForm', loginForm);
Alpine.data('deploymentTable', deploymentTable);
Alpine.data('userNav', userNav);
Alpine.data('queueData', queueData);
Alpine.data('domainListData', domainListData);

Alpine.store('domainView', {
    mode: 'grid',
    set(val) { this.mode = val; }
});
// Register tsui.side-bar store for TallStackUI sidebar collapse support
if (!Alpine.store('tsui.side-bar')) {
    Alpine.store('tsui.side-bar', {
        open: true,
        mobile: false,
        collapsible: true,
        get collapsed() {
            return this.collapsible && !this.open && !this.mobile;
        },
        init() {
            if (typeof window === 'undefined') return;
            const media = window.matchMedia('(max-width: 768px)');
            this.mobile = media.matches;
            media.addEventListener('change', (e) => {
                this.mobile = e.matches;
                if (!e.matches) {
                    window.dispatchEvent(
                        new CustomEvent('tallstackui-menu-mobile', { detail: { status: false } })
                    );
                }
            });
            const stored = localStorage.getItem('side-bar');
            this.open = stored !== null ? JSON.parse(stored) : true;
        },
        toggle(value = null) {
            this.open = value ?? !this.open;
            localStorage.setItem('side-bar', JSON.stringify(this.open));
        },
    });
}

Alpine.start();


