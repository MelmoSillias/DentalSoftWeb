<template>
    <div class="vitrine-filter-bar"><slot /></div>
</template>

<style scoped>
.vitrine-filter-bar {
    display: flex;
    flex-wrap: wrap;
    align-items: end;
    gap: 0.75rem;
    padding-bottom: 1rem;
}
.vitrine-filter-bar :deep(.vitrine-filter) {
    display: flex;
    flex-direction: column;
    gap: 0.375rem;
    flex: 1 1 11rem;
    min-width: 0;
}
.vitrine-filter-bar :deep(.vitrine-filter--search) {
    flex: 2 1 16rem;
}
.vitrine-filter-bar :deep(label) {
    color: var(--text-color-secondary);
    font-weight: 500;
}
.vitrine-filter-bar :deep(.p-iconfield),
.vitrine-filter-bar :deep(.p-inputtext),
.vitrine-filter-bar :deep(.p-select) {
    width: 100%;
}
@media (max-width: 639px) {
    .vitrine-filter-bar :deep(.vitrine-filter) {
        flex-basis: 100%;
    }
}
</style>
