import './bootstrap';

// Alpine (and its intersect/collapse/persist plugins) come from Livewire's
// own bundle (vendor/livewire/livewire/dist/livewire.esm.js) rather than a
// separate `alpinejs` import, so there is exactly one Alpine instance on the
// page — Livewire ships its own copy internally, and importing a second one
// would double-initialize Alpine. Registering @alpinejs/persist separately
// here was tried and removed: Livewire.start() registers the identical
// official plugin internally, and Object.defineProperty can't redefine the
// same magic property twice, so the second (Livewire's own) registration
// threw synchronously and silently broke every Alpine component on the page.
// The @livewireScriptConfig Blade directive in <head> (see
// components/head.blade.php) sets window.livewireScriptConfig before this
// module loads, which suppresses Livewire's own auto-start so
// registerCertificatePreview can run first.
import { Livewire, Alpine } from '../../vendor/livewire/livewire/dist/livewire.esm';
import { initProudifyUi } from './ui';
import { registerCertificatePreview } from './certificate-preview';

registerCertificatePreview(Alpine);

// ES modules are deferred and often run *after* DOMContentLoaded has
// already fired. Listening only for that event silently skips Livewire.start()
// on production Vite builds. Match document readiness either way.
const start = () => {
    Livewire.start();
    initProudifyUi();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', start);
} else {
    start();
}
