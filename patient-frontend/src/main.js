import { createApp } from 'vue';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
import { definePreset } from '@primeuix/themes';
const PatientTheme = definePreset(Aura, {
    semantic: {
        primary: {
            50: '{violet.50}',
            100: '{violet.100}',
            200: '{violet.200}',
            300: '{violet.300}',
            400: '{violet.400}',
            500: '#7047f5',
            600: '#6035e5',
            700: '{violet.700}',
            800: '{violet.800}',
            900: '{violet.900}',
            950: '{violet.950}',
        },
    },
});
import ToastService from 'primevue/toastservice';

import App from './App.vue';
import router from './router';

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
