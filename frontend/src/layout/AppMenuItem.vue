<script setup>
import { useLayout } from '@/layout/composables/layout';
import { onBeforeMount, ref, watch } from 'vue';
import { useRoute } from 'vue-router';

const route = useRoute();

const { layoutState, setActiveMenuItem, toggleMenu } = useLayout();

const props = defineProps({
    item: {
        type: Object,
        default: () => ({})
    },
    index: {
        type: Number,
        default: 0
    },
    root: {
        type: Boolean,
        default: true
    },
    parentItemKey: {
        type: String,
        default: null
    }
});

const isActiveMenu = ref(false);
const itemKey = ref(null);

onBeforeMount(() => {
    itemKey.value = props.parentItemKey ? props.parentItemKey + '-' + props.index : String(props.index);

    const activeItem = layoutState.activeMenuItem;

    isActiveMenu.value = activeItem === itemKey.value || (activeItem ? activeItem.startsWith(itemKey.value + '-') : false);
});

watch(
    () => layoutState.activeMenuItem,
    (newVal) => {
        isActiveMenu.value = newVal === itemKey.value || (newVal ? newVal.startsWith(itemKey.value + '-') : false);
    }
);

function itemClick(event, item) {
    if (item.disabled) {
        event.preventDefault();
        return;
    }

    if ((item.to || item.url) && (layoutState.staticMenuMobileActive || layoutState.overlayMenuActive)) {
        toggleMenu();
    }

    if (item.command) {
        item.command({ originalEvent: event, item: item });
    }

    const foundItemKey = item.items ? (isActiveMenu.value ? props.parentItemKey : itemKey.value) : itemKey.value;

    setActiveMenuItem(foundItemKey);
}

function checkActiveRoute(item) {
    if (!item.to) return false;
    if (typeof item.to === 'object') {
        if (item.to.path && route.path !== item.to.path) return false;
        if (item.to.params) {
            for (const key in item.to.params) {
                if (route.params[key] != item.to.params[key]) return false;
            }
        }
        return true;
    }
    return route.path.startsWith(item.to);
}
</script>

<template>
    <li class="layout-root-menuitem" :class="{ 'active-menuitem': isActiveMenu }">
        <div v-if="item.visible !== false" class="layout-menuitem-root-text">
            <span class="layout-menuitem-root-label">{{ item.label }}</span>
        </div>

        <ul v-if="item.items?.length" class="layout-submenu">
            <li v-for="(child, i) in item.items" :key="child.label + '-' + i" :class="{ 'active-menuitem': checkActiveRoute(child) }">
                <template v-if="child.visible !== false">
                    <a
                        v-if="(!child.to || child.items) && child.visible !== false"
                        :href="child.url"
                        :class="child.class"
                        :target="child.target"
                        tabindex="0"
                        @click="itemClick($event, child)"
                    >
                        <i :class="child.icon" class="layout-menuitem-icon"></i>
                        <span class="layout-menuitem-text">{{ child.label }}</span>
                        <i v-if="child.items" class="pi pi-fw pi-angle-down layout-submenu-toggler"></i>
                    </a>
                    <router-link
                        v-if="child.to && !child.items && child.visible !== false"
                        :class="[child.class, { 'active-route': checkActiveRoute(child) }]"
                        tabindex="0"
                        :to="typeof child.to === 'object' ? child.to : { path: child.to }"
                        @click="itemClick($event, child)"
                    >
                        <i :class="child.icon" class="layout-menuitem-icon"></i>
                        <span class="layout-menuitem-text">{{ child.label }}</span>
                        <i v-if="child.items" class="pi pi-fw pi-angle-down layout-submenu-toggler"></i>
                    </router-link>
                </template>
            </li>
        </ul>
    </li>
</template>
