<script setup>
import { useLayout } from '@/layout/composables/layout';
import { useInternetFeatures } from '@/composables/useInternetFeatures';
import { useAuthStore } from '@/stores/auth';
import { computed, onMounted, provide, ref, watch } from 'vue';
import AppFooter from './AppFooter.vue';
import AppSidebar from './AppSidebar.vue';
import AppTopbar from './AppTopbar.vue';
import AppRightRail from './AppRightRail.vue';

const { layoutConfig, layoutState, isSidebarActive, isHubNavigation } = useLayout();
const auth = useAuthStore();
const { syncFromServer } = useInternetFeatures();

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

const containerClass = computed(() => {
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
                layoutState.overlayMenuActive = false;
                layoutState.staticMenuMobileActive = false;
                layoutState.menuHoverActive = false;
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
    }
});
</script>

<template>
    <div :class="['layout-wrapper', containerClass]">
        <AppTopbar v-if="!isHubNavigation" />
        <div class="layout-content">
            <AppSidebar v-if="!isHubNavigation" />
            <div class="layout-main-container">
                <div class="layout-main">
                    <router-view v-slot="{ Component, route: viewRoute }">
                        <Transition
                            name="page-route"
                            mode="out-in"
                            @before-leave="onPageRouteBeforeLeave"
                            @before-enter="onPageRouteBeforeEnter"
                            @after-enter="onPageRouteAfterEnter"
                        >
                            <div v-if="Component" :key="viewRoute.fullPath" class="page-route-root">
                                <component :is="Component" />
                            </div>
                        </Transition>
                    </router-view>
                </div>
                <AppFooter />
            </div>
            <AppRightRail v-if="isHubNavigation" />
        </div>
    </div>

    <AppToast />
</template>
