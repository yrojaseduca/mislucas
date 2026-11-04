import './bootstrap';
import 'primeicons/primeicons.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
import App from './App.vue';

window.__mislucasInstallPrompt = null;
window.addEventListener('beforeinstallprompt', (event) => {
    event.preventDefault();
    window.__mislucasInstallPrompt = event;
    window.dispatchEvent(new Event('mislucas-install-available'));
});
window.addEventListener('appinstalled', () => { window.__mislucasInstallPrompt = null; });

createApp(App).use(createPinia()).use(PrimeVue, {
    theme: { preset: Aura, options: { darkModeSelector: false } },
}).mount('#app');

if ('serviceWorker' in navigator) {
    window.addEventListener('load', () => navigator.serviceWorker.register('/sw.js').catch(() => {}));
}
