// resources/js/app.js

import Alpine from 'alpinejs';
import { loginForm } from './components/login-form.js';
import { deploymentTable } from './components/deployment-table.js';
import { initTokenRefreshWatcher } from './services/auth-service.js';

window.Alpine = Alpine;

// Register Alpine components
Alpine.data('loginForm', loginForm);
Alpine.data('deploymentTable', deploymentTable);

Alpine.start();

// Start background token rotation watcher (every 30-45 mins)
initTokenRefreshWatcher();

