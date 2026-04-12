<script setup>
import { ref, onMounted } from 'vue';
import http from '@/service/http';
import { apiPrefix } from '@/config';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import { useToast } from 'primevue/usetoast';

const axios = http;

const toast = useToast();
const token = localStorage.getItem('token');

const startDate = ref('');
const endDate = ref('');
const projectsReport = ref([]);
const summary = ref({ projects: 0, geoSheets: 0, points: 0 });

const loadReport = async () => {
    try {
        // Projet + geoSheets racines avec métriques
        const res = await axios.get(`${apiPrefix}/projects/report`, { headers: { Authorization: `Bearer ${token}` } });
        projectsReport.value = res.data;
        summary.value.projects = projectsReport.value.length;
        summary.value.geoSheets = projectsReport.value.reduce((s, p) => s + p.geoSheetsCount, 0);
        summary.value.points = projectsReport.value.reduce((s, p) => s + p.geoSheets.reduce((t, g) => t + g.pointsCount, 0), 0);
    } catch (e) {
        console.error(e);
        toast.add({ severity: 'error', summary: 'Erreur', detail: 'Chargement rapport projets impossible', life: 3000 });
    }
};

onMounted(() => loadReport());
</script>

<template>
    <div class="p-4">
        <h2 class="text-xl font-bold mb-4">Rapports Activité Projets & Parcelles</h2>
        <div class="card p-4 mb-4">
            <div class="grid grid-cols-2 gap-4">
                <div>
                    <label>Début</label>
                    <InputText v-model="startDate" placeholder="YYYY-MM-DD" />
                </div>
                <div>
                    <label>Fin</label>
                    <InputText v-model="endDate" placeholder="YYYY-MM-DD" />
                </div>
            </div>
            <div class="mt-4">
                <Button label="Générer" @click="loadReport" />
            </div>
        </div>

        <div class="card p-4">
            <h3>Résumé</h3>
            <p>Projets: {{ summary.projects }}</p>
            <p>GeoSheets racines: {{ summary.geoSheets }}</p>
            <p>Points (toutes versions racines): {{ summary.points }}</p>

            <h4 class="mt-4">Détails par projet</h4>
            <div v-for="p in projectsReport" :key="p.projectId" class="mb-6 border rounded p-3">
                <h5 class="font-semibold mb-2">Projet #{{ p.projectId }} — {{ p.projectTitle }} ({{ p.locality || '—' }})</h5>
                <p class="text-xs text-gray-500">Créé le: {{ p.createdAt }} • Statut: {{ p.status }} • Parcelles: {{ p.geoSheetsCount }}</p>
                <table class="w-full text-sm mt-2 table-auto">
                    <thead>
                        <tr>
                            <th>Parcelle</th>
                            <th>Titre</th>
                            <th>Créé le</th>
                            <th>Points</th>
                            <th>Versions</th>
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="g in p.geoSheets" :key="g.id">
                            <td>{{ g.parcelNumber || '—' }}</td>
                            <td>{{ g.title }}</td>
                            <td>{{ g.createdAt }}</td>
                            <td>{{ g.pointsCount }}</td>
                            <td>{{ g.versionsCount }}</td>
                        </tr>
                    </tbody>
                </table>
            </div>
        </div>
    </div>
</template>

<style scoped>
.card {
    background: white;
    border-radius: 6px;
    padding: 1rem;
}
</style>
