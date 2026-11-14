<script setup>
defineProps({
    label: { type: String, required: true },
    number: { type: String, default: '' },
    caption: { type: String, default: '' },
    /** Numbered callouts drawn on the placeholder: `{ x, y, text }`, x and y in percent of the frame. */
    markers: { type: Array, default: () => [] },
    compact: { type: Boolean, default: false }
});
</script>

<template>
    <figure class="m-figure" :class="{ 'm-figure--compact': compact }">
        <div class="m-figure__frame">
            <i class="pi pi-image m-figure__icon" aria-hidden="true"></i>
            <span class="m-figure__placeholder">[Capture écran – {{ label }}]</span>
            <span v-for="(marker, index) in markers" :key="index" class="m-figure__marker" :style="{ left: `${marker.x}%`, top: `${marker.y}%` }" aria-hidden="true">{{ index + 1 }}</span>
        </div>
        <figcaption>
            <span v-if="number" class="m-figure__number">Figure {{ number }}.</span>
            {{ caption }}
        </figcaption>
        <ol v-if="markers.length" class="m-figure__legend">
            <li v-for="(marker, index) in markers" :key="index">
                <span class="m-figure__legend-n">{{ index + 1 }}</span>
                <span>{{ marker.text }}</span>
            </li>
        </ol>
    </figure>
</template>
