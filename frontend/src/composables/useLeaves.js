import { ref } from 'vue';
import { createLeave, deleteLeave, fetchLeaves, updateLeave } from '@/services/rhManagementService';

const leaves = ref([]);
const loading = ref(false);
const error = ref(null);

const getToken = () => localStorage.getItem('token') || localStorage.getItem('authToken');

export function useLeaves() {
    const fetchData = async (filters = {}) => {
        loading.value = true;
        error.value = null;
        try {
            leaves.value = await fetchLeaves(filters, getToken());
        } catch (err) {
            error.value = err?.response?.data?.message || err?.message || 'Erreur lors du chargement des conges.';
            leaves.value = [];
        } finally {
            loading.value = false;
        }
    };

    const add = async (payload) => {
        error.value = null;
        try {
            return await createLeave(payload, getToken());
        } catch (err) {
            error.value = err?.response?.data?.message || err?.message || 'Erreur lors de la creation du conge.';
            throw err;
        }
    };

    const edit = async (id, payload) => {
        error.value = null;
        try {
            return await updateLeave(id, payload, getToken());
        } catch (err) {
            error.value = err?.response?.data?.message || err?.message || 'Erreur lors de la mise a jour du conge.';
            throw err;
        }
    };

    const remove = async (id) => {
        error.value = null;
        try {
            return await deleteLeave(id, getToken());
        } catch (err) {
            error.value = err?.response?.data?.message || err?.message || 'Erreur lors de la suppression du conge.';
            throw err;
        }
    };

    return {
        leaves,
        loading,
        error,
        fetchData,
        add,
        edit,
        remove
    };
}
