import { createApp } from 'vue';
import { createPinia } from 'pinia';
import PrimeVue from 'primevue/config';
import Aura from '@primeuix/themes/aura';
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

import 'primeicons/primeicons.css';
import './style.css';

const app = createApp(App);
const pinia = createPinia();

app.use(pinia);
app.use(router);
app.use(ToastService);
app.use(PrimeVue, {
	theme: {
		preset: Aura,
		options: {
			darkModeSelector: '.app-dark'
		}
	}
});

app.component('PvButton', Button);
app.component('PvCard', Card);
app.component('PvAvatar', Avatar);
app.component('PvInputText', InputText);
app.component('PvPassword', Password);
app.component('PvBreadcrumb', Breadcrumb);
app.component('PvDivider', Divider);
app.component('PvTag', Tag);
app.component('PvToast', Toast);
app.component('PvSkeleton', Skeleton);

app.mount('#app');
