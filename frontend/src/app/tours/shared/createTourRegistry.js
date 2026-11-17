export function createTourRegistry(routeName, tasks, buildStepsByTask) {
    return {
        routeName,
        tasks,
        buildSteps(taskId, variantId, ctx) {
            const builder = buildStepsByTask[taskId] || buildStepsByTask.overview;
            return builder ? builder(ctx, variantId) : [];
        }
    };
}
