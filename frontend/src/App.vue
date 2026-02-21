<script setup>
import { watch } from 'vue'
import { useRoute } from 'vue-router'

const route = useRoute()

watch(
  () => route.meta.fixedWidth,
  (fixed) => {
    const viewport = document.getElementById('viewport-meta')
    if (!viewport) return

    if (fixed) {
      viewport.setAttribute(
        'content',
        'width=800, initial-scale=0.4'
      )
    } else {
      viewport.setAttribute(
        'content',
        'width=device-width, initial-scale=1'
      )
    }
  },
  { immediate: true }
)

</script>

<template>
    <router-view />
</template>

<style scoped></style>
