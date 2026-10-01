import Alpine from 'alpinejs';
import { deploymentTable } from './components/deployment-table.js';

window.Alpine = Alpine;

// Register Alpine components
Alpine.data('deploymentTable', deploymentTable);

Alpine.start();
