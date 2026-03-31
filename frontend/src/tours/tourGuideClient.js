import { TourGuideClient } from '@sjmc11/tourguidejs/src/Tour';

const baseOptions = {
    autoScroll: true,
    autoScrollSmooth: true,
    autoScrollOffset: 32,
    backdropAnimate: true,
    backdropClass: 'orodent-tour-backdrop',
    backdropColor: 'rgba(15, 23, 42, 0.72)',
    closeButton: true,
    completeOnFinish: false,
    debug: false,
    dialogClass: 'orodent-tour-dialog',
    dialogMaxWidth: 420,
    dialogZ: 2600,
    exitOnClickOutside: true,
    exitOnEscape: true,
    finishLabel: 'Terminer',
    hidePrev: false,
    keyboardControls: true,
    nextLabel: 'Suivant',
    prevLabel: 'Precedent',
    rememberStep: false,
    showButtons: true,
    showStepDots: true,
    showStepProgress: true,
    stepDotsPlacement: 'footer',
    targetPadding: 18
};

let tourGuide = null;

export function getTourGuideClient() {
    if (typeof window === 'undefined') {
        return null;
    }

    if (!tourGuide) {
        tourGuide = new TourGuideClient(baseOptions);
    }

    return tourGuide;
}

export async function startTourGuide({ group, steps, options = {}, onAfterExit, onFinish }) {
    const tg = getTourGuideClient();

    if (!tg) {
        return null;
    }

    if (tg.isVisible) {
        await tg.exit().catch(() => undefined);
    }

    tg.onFinish(async () => {
        if (typeof onFinish === 'function') {
            await onFinish();
        }
        return true;
    });

    tg.onAfterExit(() => {
        if (typeof onAfterExit === 'function') {
            onAfterExit();
        }
    });

    await tg.setOptions({
        ...baseOptions,
        ...options,
        steps
    });

    return tg.start(group);
}
