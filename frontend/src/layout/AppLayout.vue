<script setup>
import { useLayout } from '@/layout/composables/layout';
import { useInternetFeatures } from '@/composables/useInternetFeatures';
import { useAuthStore } from '@/stores/auth';
import { useMercureClient } from '@/composables/realtime/useMercureClient';
import { useNotificationPresentation } from '@/composables/useNotificationPresentation';
import { computed, onBeforeUnmount, onMounted, provide, ref, watch } from 'vue';
import AppFooter from './AppFooter.vue';
import AppSidebar from './AppSidebar.vue';
import AppTopbar from './AppTopbar.vue';
import AppRightRail from './AppRightRail.vue';

const {
    layoutConfig,
    layoutState,
    isSidebarActive,
    isHubNavigation,
    showLayoutMask,
    closeMenu
} = useLayout();
const auth = useAuthStore();
const { syncFromServer } = useInternetFeatures();
const mercureClient = useMercureClient();
useNotificationPresentation();

const outsideClickListener = ref(null);
const pageRouteReady = ref(true);

provide('pageRouteReady', pageRouteReady);

function onPageRouteBeforeLeave() {
    pageRouteReady.value = false;
}

function onPageRouteBeforeEnter() {
    pageRouteReady.value = false;
}

function onPageRouteAfterEnter() {
    pageRouteReady.value = true;
    window.dispatchEvent(new Event('resize'));
}

watch(isSidebarActive, (newVal) => {
    if (newVal && !isHubNavigation.value) {
        bindOutsideClickListener();
    } else {
        unbindOutsideClickListener();
    }
});

const shellClass = computed(() => {
    if (isHubNavigation.value) {
        return {
            'layout-hub': true
        };
    }

    return {
        'layout-overlay': layoutConfig.menuMode === 'overlay',
        'layout-static': layoutConfig.menuMode === 'static',
        'layout-static-inactive': layoutState.staticMenuDesktopInactive && layoutConfig.menuMode === 'static',
        'layout-overlay-active': layoutState.overlayMenuActive,
        'layout-mobile-active': layoutState.staticMenuMobileActive
    };
});

function bindOutsideClickListener() {
    if (!outsideClickListener.value) {
        outsideClickListener.value = (event) => {
            if (isOutsideClicked(event)) {
                closeMenu();
            }
        };
        document.addEventListener('click', outsideClickListener.value);
    }
}

function unbindOutsideClickListener() {
    if (outsideClickListener.value) {
        document.removeEventListener('click', outsideClickListener.value);
        outsideClickListener.value = null;
    }
}

function isOutsideClicked(event) {
    const sidebarEl = document.querySelector('.layout-sidebar');
    const topbarEl = document.querySelector('.layout-menu-button');

    return !(
        (sidebarEl && (sidebarEl.isSameNode(event.target) || sidebarEl.contains(event.target))) ||
        (topbarEl && (topbarEl.isSameNode(event.target) || topbarEl.contains(event.target)))
    );
}

onMounted(async () => {
    if (auth.token) {
        await syncFromServer(auth.token);
        await mercureClient.start();
    }
});

watch(
    () => [auth.token, auth.user?.notificationsEnabled],
    async ([token]) => {
        if (token) {
            await mercureClient.start();
            return;
        }

        mercureClient.disconnect();
    }
);

onBeforeUnmount(() => {
    unbindOutsideClickListener();
});
</script>

<template>
    <div class="layout-shell layout-wrapper" :class="shellClass">
        <AppTopbar v-if="!isHubNavigation" />

        <div class="layout-body">
            <AppSidebar v-if="!isHubNavigation" />

            <div class="layout-main-column">
                <main class="layout-main">
                    <router-view v-slot="{ Component, route: viewRoute }">
                        <Transition
                            name="page-route"
                            mode="out-in"
                            @before-leave="onPageRouteBeforeLeave"
                            @before-enter="onPageRouteBeforeEnter"
                            @after-enter="onPageRouteAfterEnter"
                        >
                            <div v-if="Component" :key="viewRoute.name || viewRoute.path" class="page-route-root">
                                <component :is="Component" />
                            </div>
                        </Transition>
                    </router-view>
                </main>
                <AppFooter v-if="!isHubNavigation" />
            </div>

            <AppRightRail v-if="isHubNavigation" />
        </div>

        <div
            v-if="showLayoutMask && !isHubNavigation"
            class="layout-mask"
            @click="closeMenu"
        />

        <AppToast />
    </div>
</template>
