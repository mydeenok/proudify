import './bootstrap';

import Alpine from 'alpinejs';
import { initProudifyUi } from './ui';
import { initCertificateLivePreview } from './certificate-preview';

window.Alpine = Alpine;

Alpine.start();

document.addEventListener('DOMContentLoaded', () => {
    initProudifyUi();
    initCertificateLivePreview();
});
