<script setup>
import { useNavigationMenu } from '@/app/composables/useNavigationMenu.js';
import AppMenuItem from '@/app/layout/AppMenuItem.vue';

const { model } = useNavigationMenu();
</script>

<template>
    <ul class="layout-menu">
        <template v-for="(item, i) in model" :key="i">
            <app-menu-item v-if="!item.separator" :item="item" :index="i"></app-menu-item>
            <li v-if="item.separator" class="menu-separator"></li>
        </template>
    </ul>
</template>
