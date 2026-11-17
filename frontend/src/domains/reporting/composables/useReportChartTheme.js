import { onBeforeUnmount, onMounted, shallowRef } from 'vue';

export function useReportChartTheme() {
    const chartTheme = shallowRef({});
    let observer;

    function updateColors() {
        const style = getComputedStyle(document.documentElement);
        const colors = Object.fromEntries(
            [
                '--text-color',
                '--text-color-secondary',
                '--surface-border',
                '--p-primary-500',
                '--p-emerald-500',
                '--p-orange-500',
                '--p-indigo-500',
                '--p-blue-500',
                '--p-violet-500',
                '--p-cyan-500',
                '--p-red-500',
                '--p-teal-500',
                '--p-amber-500',
                '--p-pink-500',
                '--p-purple-500',
                '--p-surface-400',
                '--p-rose-500',
                '--p-sky-500'
            ].map((token) => [token, style.getPropertyValue(token).trim()])
        );
        if (JSON.stringify(colors) !== JSON.stringify(chartTheme.value)) chartTheme.value = colors;
    }

    onMounted(() => {
        updateColors();
        // PrimeVue changes palette styles as well as the root dark-mode class.
        observer = new MutationObserver(updateColors);
        observer.observe(document.documentElement, { attributes: true, attributeFilter: ['class', 'style'] });
        observer.observe(document.head, { childList: true, subtree: true, characterData: true });
    });
    onBeforeUnmount(() => observer?.disconnect());

    return {
        chartStyle: {
            getPropertyValue: (token) => chartTheme.value[token] || ''
        }
    };
}
