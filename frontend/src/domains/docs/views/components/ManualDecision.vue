<script setup>
defineProps({
    question: { type: String, required: true },
    /** Branches: `{ when, then }`. */
    options: { type: Array, required: true }
});
</script>

<template>
    <div class="m-decision">
        <p class="m-decision__label"><i class="pi pi-directions" aria-hidden="true"></i>Décision</p>
        <p class="m-decision__question">{{ question }}</p>
        <ul class="m-decision__branches">
            <li v-for="option in options" :key="option.when">
                <span class="m-decision__when">{{ option.when }}</span>
                <span class="m-decision__then">{{ option.then }}</span>
            </li>
        </ul>
    </div>
</template>
