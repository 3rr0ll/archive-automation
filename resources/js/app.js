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

Alpine.store('domainView', {
    mode: localStorage.getItem('domain_view_mode') || 'list',
    set(val) { 
        this.mode = val; 
        try { localStorage.setItem('domain_view_mode', val); } catch (e) {}
    }
});

Alpine.start();

