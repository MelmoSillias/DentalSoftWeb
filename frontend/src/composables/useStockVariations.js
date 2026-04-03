import { useAuthStore } from "@/stores/auth";
import { apiPrefix } from "@/config"; 
import { fetchStockVariationsTourMock, isAdminTourMockEnabled } from '@/services/adminTourMock';
import { ref } from "vue";
import http from '@/service/http';

const variations = ref([])
const useAuth = useAuthStore()
const loading = ref(false)
const error = ref(null)

export function useStockVariations(){
    
    async function fetchStockVariations(consumableId = null, start = "", end = "") {
        loading.value = true
        error.value = null

        const params = new URLSearchParams();
     
        if (consumableId) params.append("consumableId", consumableId);
         
        if (start instanceof Date) {
            params.append("start", start.toISOString().split('T')[0]);
        }
        if (end instanceof Date) {
            params.append("end", end.toISOString().split('T')[0]);
        }

        try {
            if (isAdminTourMockEnabled()) {
                variations.value = fetchStockVariationsTourMock(consumableId, start, end);
                return;
            }

            const response = await http.get(`${apiPrefix}/stocks?${params}`, {
                headers: {
                    Authorization: `Bearer ${useAuth.token}`
                }
            });
            const data = response.data;
            variations.value = data;
            
        } catch (err) {
            error.value = err.message
        } finally {
            loading.value = false
        }

    }

    return {
        variations,
        loading,
        error,
        fetchStockVariations
    }
}

