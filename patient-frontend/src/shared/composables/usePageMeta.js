import { computed } from 'vue';
import { useRoute } from 'vue-router';

export function usePageMeta() {
    const route = useRoute();

    const title = computed(() => route.meta?.title || 'Espace patient');

    const breadcrumbItems = computed(() => {
        const labels = route.meta?.breadcrumb || ['Espace patient'];
        return labels.map((label, index) => ({
            label,
            to: index === labels.length - 1 ? undefined : '/'
        }));
    });

    return {
        title,
        breadcrumbItems
    };
}
