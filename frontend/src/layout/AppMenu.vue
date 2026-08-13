<script setup>
import { useNavigationMenu } from '@/composables/useNavigationMenu';
import AppMenuItem from './AppMenuItem.vue';

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

<style lang="scss" scoped>
/* Ajoutez ici vos styles personnalisés si nécessaire */
</style>
