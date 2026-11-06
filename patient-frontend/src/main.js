import { createApp } from 'vue';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
import { definePreset } from '@primeuix/themes';
const PatientTheme = definePreset(Aura, {
    semantic: {
        primary: {
            50: '{sky.50}',
            100: '{sky.100}',
            200: '{sky.200}',
            300: '{sky.300}',
            400: '{sky.400}',
            500: '#0ea5e9',
            600: '#0284c7',
            700: '{sky.700}',
            800: '{sky.800}',
            900: '{sky.900}',
            950: '{sky.950}',
        },
        colorScheme: {
            light: {
                primary: {
                    color: '{sky.700}',
                    contrastColor: '#ffffff',
                    hoverColor: '{sky.800}',
                    activeColor: '{sky.900}',
                },
            },
            dark: {
                primary: {
                    color: '{sky.300}',
                    contrastColor: '{sky.950}',
                    hoverColor: '{sky.200}',
                    activeColor: '{sky.100}',
                },
            },
        },
    },
});
import ToastService from 'primevue/toastservice';

import App from './App.vue';
import router from './router';
import cabinetConfig from './cabinetConfig';

// Cabinet builds copy the selected internal logo into public/.
const favicon = document.querySelector('link[rel="icon"]');
if (favicon && cabinetConfig.logo) {
    favicon.removeAttribute('type');
    const logo = new URL(`${import.meta.env.BASE_URL}${cabinetConfig.logo.replace(/^\/+/, '')}`, window.location.origin);
    logo.searchParams.set('cabinet', cabinetConfig.id);
    favicon.href = logo.href;
}

import Button from 'primevue/button';
import Card from 'primevue/card';
import Avatar from 'primevue/avatar';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import Breadcrumb from 'primevue/breadcrumb';
import Divider from 'primevue/divider';
import Tag from 'primevue/tag';
import Toast from 'primevue/toast';
import Skeleton from 'primevue/skeleton';
import Message from 'primevue/message';

import 'primeicons/primeicons.css';
import './style.css';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);
app.use(ToastService);
app.use(PrimeVue, {
    theme: {
        preset: PatientTheme,
        options: {
            darkModeSelector: '.app-dark',
        },
    },
});

app.component('Button', Button);
app.component('Card', Card);
app.component('Avatar', Avatar);
app.component('InputText', InputText);
app.component('Password', Password);
app.component('Breadcrumb', Breadcrumb);
app.component('Divider', Divider);
app.component('Tag', Tag);
app.component('Toast', Toast);
app.component('Skeleton', Skeleton);
app.component('Message', Message);

app.mount('#app');
