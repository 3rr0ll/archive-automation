// resources/js/app.js

import Alpine from 'alpinejs';
import { loginForm } from './components/login-form.js';
import { deploymentTable } from './components/deployment-table.js';
import { userNav } from './components/user-nav.js';
import { dashboardOverview } from './components/dashboard-overview.js';

window.Alpine = Alpine;

// Register Alpine components
Alpine.data('loginForm', loginForm);
Alpine.data('deploymentTable', deploymentTable);
Alpine.data('userNav', userNav);
Alpine.data('dashboardOverview', dashboardOverview);

Alpine.start();


