import './bootstrap';
import 'primeicons/primeicons.css';
import { createApp } from 'vue';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
import App from './App.vue';
createApp(App).use(createPinia()).use(PrimeVue, { theme: { preset: Aura } }).mount('#app');
