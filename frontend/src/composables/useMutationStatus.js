import { readonly, ref } from 'vue';
import { createMutationGuard } from '@/service/mutationGuard.mjs';

const pending = ref(0);
const issue = ref(null);
const reported = new WeakSet();
export const reportMutationIssue = (error) => {
    if (reported.has(error)) return;
    reported.add(error);
    issue.value = { message: error.userMessage };
};
let requests = 0;
let preparing = 0;
const update = () => {
    pending.value = requests + preparing;
};
let storage;
try {
    storage = globalThis.sessionStorage;
} catch {
    /* optional */
}
export const runMutation = createMutationGuard({
    storage,
    onPending: (count) => {
        requests = count;
        update();
    }
});
export const beginMutationPreparation = () => {
    preparing++;
    update();
    return () => {
        preparing--;
        update();
    };
};
export const useMutationStatus = () => ({ pending: readonly(pending), issue: readonly(issue) });
