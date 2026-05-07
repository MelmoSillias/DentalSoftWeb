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
    // If item.to is an object, use vue-router's route matching
    if (typeof item.to === 'object') {
        // Compare path and params
        if (item.to.path && route.path !== item.to.path) return false;
        if (item.to.params) {
            for (const key in item.to.params) {
                if (route.params[key] != item.to.params[key]) return false;
            }
        }
        return true;
    }
    // If item.to is a string, check if route.path starts with it (for params)
    return route.path.startsWith(item.to);
}
</script>

<template>
    <!-- <li :class="{ 'layout-root-menuitem': root, 'active-menuitem': isActiveMenu }">
        <div v-if="root && item.visible !== false" class="layout-menuitem-root-text">{{ item.label }}</div>
        <a v-if="(!item.to || item.items) && item.visible !== false" :href="item.url" @click="itemClick($event, item, index)" :class="item.class" :target="item.target" tabindex="0">
            <i :class="item.icon" class="layout-menuitem-icon"></i>
            <span class="layout-menuitem-text">{{ item.label }}</span>
            <i class="pi pi-fw pi-angle-down layout-submenu-toggler" v-if="item.items"></i>
        </a>
        <router-link
            v-if="item.to && !item.items && item.visible !== false"
            @click="itemClick($event, item, index)"
            :class="[item.class, { 'active-route': checkActiveRoute(item) }]"
            tabindex="0"
            :to="typeof item.to === 'object' ? item.to : { path: item.to }"
        >
            <i :class="item.icon" class="layout-menuitem-icon"></i>
            <span class="layout-menuitem-text">{{ item.label }}</span>
            <i class="pi pi-fw pi-angle-down layout-submenu-toggler" v-if="item.items"></i>
        </router-link>
        <Transition v-if="item.items && item.visible !== false" name="layout-submenu">
            <ul v-show="root ? true : isActiveMenu" class="layout-submenu">
                <app-menu-item v-for="(child, i) in item.items" :key="child" :index="i" :item="child" :parentItemKey="itemKey" :root="false"></app-menu-item>
            </ul>
        </Transition>
    </li> -->

    <li class="nav-link-root">
        <Divider align="right" class="mb-0 text-xs">{{ props.item.label }}</Divider>
        <ul class="border-l-[1px] border-gray-300 dark:border-gray-700 ml-2">
            <li v-for="(child, i) in props.item.items" :key="child">
                <template v-if="child.visible !== false">
                    <a v-if="(!child.to || child.items) && child.visible !== false" :href="child.url" @click="itemClick($event, child, i)" :class="child.class" :target="child.target" tabindex="0">
                        <i :class="child.icon" class="layout-menuitem-icon"></i>
                        <span class="layout-menuitem-text">{{ child.label }}</span>
                        <i class="pi pi-fw pi-angle-down layout-submenu-toggler" v-if="child.items"></i>
                    </a>
                    <router-link 
                        v-if="child.to && !child.items && child.visible !== false"
                        @click="itemClick($event, child, i)"
                        :class="[child.class, { 'active-route': checkActiveRoute(child) }]"
                        tabindex="0"
                        :to="typeof child.to === 'object' ? child.to : { path: child.to }"
                    >
                        <i :class="child.icon" class="layout-menuitem-icon"></i>
                        <span class="layout-menuitem-text">{{ child.label }}</span>
                        <i class="pi pi-fw pi-angle-down layout-submenu-toggler" v-if="child.items"></i>
                    </router-link>
                    
                </template>
            </li>
        </ul>
        
        
    </li>

</template>

<style lang="scss" scoped>
:deep(.p-divider-content) {
    color : var(--primary-color);
    font-weight: bold;
}

:deep(.p-divider) {
    margin-bottom: 0;
}
</style>
<!-- 
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

const isActiveMenu = computed(() => {
    return layoutState.activeMenuItem === itemKey.value;
});
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
    // If item.to is an object, use vue-router's route matching
    if (typeof item.to === 'object') {
        // Compare path and params
        if (item.to.path && route.path !== item.to.path) return false;
        if (item.to.params) {
            for (const key in item.to.params) {
                if (route.params[key] != item.to.params[key]) return false;
            }
        }
        return true;
    }
    // If item.to is a string, check if route.path starts with it (for params)
    return route.path.startsWith(item.to);
}

function isSubMenuActive(item) {
    if (item.items) {
        return item.items.some((child) => checkActiveRoute(child) || isSubMenuActive(child));
    }
    return false;
}

function toggleSubMenu(item) {
    if (!item.items) return;

    const key = itemKey.value;

    setActiveMenuItem(
        layoutState.activeMenuItem === key ? null : key
    );
}

const mainlinkclass = "!text-[0.8rem] pi pi-chevron-right absolute left-[1rem] transition-all duration-300 rounded-full hover:bg-gray-200 p-1 hover:border hover:border-gray-300 bg-surface-50 dark:bg-gray-700"
const mainlinkactiveclass = "!text-[0.8rem] pi pi-chevron-right absolute left-[1rem] transition-all duration-300 rounded-full bg-primary text-white p-1"

const subMenuClass = "border-l-[1px] border-gray-300 dark:border-gray-700 ml-2 hidden transition-all duration-300"
const subMenuActiveClass = "border-l-[1px] border-gray-300 dark:border-gray-700 ml-2 block transition-all duration-300"


</script>

<template>
 
    <li class="nav-link-root"> 
        <Divider align="right" class="mb-0 text-xs relative">
            <Button
                icon=" pi-chevron-right"
                :class="isActiveMenu ? mainlinkactiveclass : mainlinkclass"
                @click="toggleSubMenu(props.item)"
            />
            {{ props.item.label }}
        </Divider>
        <ul :class="isActiveMenu ? subMenuActiveClass : subMenuClass">
            <li v-for="(child, i) in props.item.items" :key="child">
                <template v-if="child.visible !== false">
                    <a v-if="(!child.to || child.items) && child.visible !== false" :href="child.url" @click="itemClick($event, child, i)" :class="child.class" :target="child.target" tabindex="0">
                        <i :class="child.icon" class="layout-menuitem-icon"></i>
                        <span class="layout-menuitem-text">{{ child.label }}</span>
                        <i class="pi pi-fw pi-angle-down layout-submenu-toggler" v-if="child.items"></i>
                    </a>
                    <router-link 
                        v-if="child.to && !child.items && child.visible !== false"
                        @click="itemClick($event, child, i)"
                        :class="[child.class, { 'active-route': checkActiveRoute(child) }]"
                        tabindex="0"
                        :to="typeof child.to === 'object' ? child.to : { path: child.to }"
                    >
                        <i :class="child.icon" class="layout-menuitem-icon"></i>
                        <span class="layout-menuitem-text">{{ child.label }}</span>
                        <i class="pi pi-fw pi-angle-down layout-submenu-toggler" v-if="child.items"></i>
                    </router-link>
                    
                </template>
            </li>
        </ul>
        
        
    </li>

</template>

<style lang="scss" scoped>
:deep(.p-divider-content) {
    color : var(--primary-color);
    font-weight: bold;
}

:deep(.p-divider) {
    margin-bottom: 0;
}
</style>

   <!-- <li :class="{ 'layout-root-menuitem': root, 'active-menuitem': isActiveMenu }">
        <div v-if="root && item.visible !== false" class="layout-menuitem-root-text">{{ item.label }}</div>
        <a v-if="(!item.to || item.items) && item.visible !== false" :href="item.url" @click="itemClick($event, item, index)" :class="item.class" :target="item.target" tabindex="0">
            <i :class="item.icon" class="layout-menuitem-icon"></i>
            <span class="layout-menuitem-text">{{ item.label }}</span>
            <i class="pi pi-fw pi-angle-down layout-submenu-toggler" v-if="item.items"></i>
        </a>
        <router-link
            v-if="item.to && !item.items && item.visible !== false"
            @click="itemClick($event, item, index)"
            :class="[item.class, { 'active-route': checkActiveRoute(item) }]"
            tabindex="0"
            :to="typeof item.to === 'object' ? item.to : { path: item.to }"
        >
            <i :class="item.icon" class="layout-menuitem-icon"></i>
            <span class="layout-menuitem-text">{{ item.label }}</span>
            <i class="pi pi-fw pi-angle-down layout-submenu-toggler" v-if="item.items"></i>
        </router-link>
        <Transition v-if="item.items && item.visible !== false" name="layout-submenu">
            <ul v-show="root ? true : isActiveMenu" class="layout-submenu">
                <app-menu-item v-for="(child, i) in item.items" :key="child" :index="i" :item="child" :parentItemKey="itemKey" :root="false"></app-menu-item>
            </ul>
        </Transition>
    </li> --> -->
