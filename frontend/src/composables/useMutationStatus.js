import { readonly, ref } from 'vue';
import { createMutationGuard } from '@/service/mutationGuard.mjs';

const issue = ref(null);
const reported = new WeakSet();
export const reportMutationIssue = (error) => {
    if (reported.has(error)) return;
    reported.add(error);
    issue.value = { message: error.userMessage };
};
let storage;
try {
    storage = globalThis.sessionStorage;
} catch {
    /* optional */
}
export const runMutation = createMutationGuard({ storage });
export const useMutationStatus = () => ({ issue: readonly(issue) });
