import { onBeforeUnmount, onMounted, ref } from 'vue';
import { useToast } from 'primevue/usetoast';
import { GUIDED_TOUR_START_EVENT } from '@/app/tours/index.js';
import { getTaskForRoute, buildTourStepsForRoute } from '@/app/tours/registry/index.js';
import { isTaskAllowedForRole } from '@/app/tours/shared/taskUtils.js';
import { startTourGuide } from '@/app/tours/tourGuideClient.js';
import { deactivateTourScrollControls } from '@/app/tours/shared/tourScrollControl.js';
import { useAuthStore } from '@/domains/auth/stores/auth.js';
import { logAppError } from '@/shared/lib/appLogger.js';

export function useGuidedTour({
    routeName,
    isLoading = () => false,
    hasOpenDialogs = () => false,
    prepareDemo = async () => undefined,
    cleanupDemo = async () => undefined,
    getStepContext = () => ({}),
    loadingMessage = 'Attendez la fin du chargement avant de lancer le tour.',
    dialogsMessage = 'Fermez d abord les fenetres ouvertes avant de lancer le tour.',
    errorMessage = 'Impossible de lancer le tour guide sur cette page.'
}) {
    const toast = useToast();
    const auth = useAuthStore();
    const isGuidedTourStarting = ref(false);

    const handleGuidedTourRequest = async (event) => {
        const { routeName: eventRouteName, taskId = 'overview', variantId = null } = event?.detail ?? {};

        if (eventRouteName !== routeName || isGuidedTourStarting.value) {
            return;
        }

        const task = getTaskForRoute(routeName, taskId);
        if (!task) {
            return;
        }

        if (!isTaskAllowedForRole(task, auth.roles || auth.user?.roles || [])) {
            toast.add({
                severity: 'warn',
                summary: 'Aide guidee',
                detail: 'Cette action n est pas disponible pour votre role.',
                life: 3000
            });
            return;
        }

        if (isLoading()) {
            toast.add({
                severity: 'warn',
                summary: 'Aide guidee',
                detail: loadingMessage,
                life: 3000
            });
            return;
        }

        if (hasOpenDialogs()) {
            toast.add({
                severity: 'warn',
                summary: 'Aide guidee',
                detail: dialogsMessage,
                life: 3000
            });
            return;
        }

        isGuidedTourStarting.value = true;

        try {
            await cleanupDemo();
            await prepareDemo({ taskId, variantId, task });

            const steps = buildTourStepsForRoute(routeName, taskId, variantId, getStepContext({ taskId, variantId, task }));
            if (!steps.length) {
                throw new Error(`Aucune etape definie pour ${routeName}:${taskId}`);
            }

            const tourGroup = steps.find((step) => step?.group)?.group || routeName;

            await startTourGuide({
                group: tourGroup,
                steps,
                onAfterExit: cleanupDemo,
                onFinish: cleanupDemo
            });
        } catch (error) {
            logAppError(`Erreur lancement guided tour ${routeName}`, error);
            await cleanupDemo();
            deactivateTourScrollControls();
            toast.add({
                severity: 'error',
                summary: 'Aide guidee',
                detail: errorMessage,
                life: 3000
            });
        } finally {
            isGuidedTourStarting.value = false;
        }
    };

    onMounted(() => {
        window.addEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
    });

    onBeforeUnmount(() => {
        window.removeEventListener(GUIDED_TOUR_START_EVENT, handleGuidedTourRequest);
    });

    return {
        isGuidedTourStarting,
        handleGuidedTourRequest
    };
}
