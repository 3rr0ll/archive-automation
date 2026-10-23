// resources/js/app.js

import Alpine from 'alpinejs';
import { loginForm } from './components/login-form.js';
import { deploymentTable } from './components/deployment-table.js';
import { userNav } from './components/user-nav.js';
import { dashboardOverview } from './components/dashboard-overview.js';
import { queueData } from './components/queue.js';
import { domainListData } from './components/domain-list.js';
import { environmentVariablesPage } from './components/environment-variables.js';
import { serversPage } from './components/servers.js';
import { usersPage } from './components/users.js';
import { deploymentLogsPage } from './components/deployment-logs.js';
import { deploymentFilesPage } from './components/deployment-files.js';
import { googleSearchConsolePage } from './components/google-search-console.js';

window.Alpine = Alpine;

Alpine.data('loginForm', loginForm);
Alpine.data('deploymentTable', deploymentTable);
Alpine.data('userNav', userNav);
Alpine.data('dashboardOverview', dashboardOverview);
Alpine.data('queueData', queueData);
Alpine.data('domainListData', domainListData);
Alpine.data('environmentVariablesPage', environmentVariablesPage);
Alpine.data('serversPage', serversPage);
Alpine.data('usersPage', usersPage);
Alpine.data('deploymentLogsPage', deploymentLogsPage);
Alpine.data('deploymentFilesPage', deploymentFilesPage);
Alpine.data('googleSearchConsolePage', googleSearchConsolePage);

Alpine.store('theme', {
    mode: localStorage.getItem('theme') || localStorage.getItem('ide_theme') || (window.matchMedia('(prefers-color-scheme: dark)').matches ? 'dark' : 'light'),
    init() {
        this.apply();
    },
    toggle() {
        this.mode = this.mode === 'dark' ? 'light' : 'dark';
        try {
            localStorage.setItem('theme', this.mode);
            localStorage.setItem('ide_theme', this.mode);
        } catch (e) {}
        this.apply();
        window.dispatchEvent(new CustomEvent('app-theme-changed', { detail: { mode: this.mode } }));
    },
    apply() {
        if (this.mode === 'dark') {
            document.documentElement.classList.add('dark');
        } else {
            document.documentElement.classList.remove('dark');
        }
    }
});

Alpine.store('sidebar', {
    collapsed: localStorage.getItem('sidebar_collapsed') === 'true',
    toggle() {
        this.collapsed = !this.collapsed;
        try {
            localStorage.setItem('sidebar_collapsed', this.collapsed);
        } catch (e) {}
        window.dispatchEvent(new CustomEvent('sidebar-collapsed-changed', { detail: { collapsed: this.collapsed } }));
    }
});

Alpine.store('domainView', {
    mode: localStorage.getItem('domain_view_mode') || 'list',
    set(val) { 
        this.mode = val; 
        try { localStorage.setItem('domain_view_mode', val); } catch (e) {}
    }
});

Alpine.start();

