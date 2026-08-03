<template>
  <div class="p-4 space-y-4">
    <h2 class="text-2xl font-semibold">Paramètres — Fichiers & Workspace</h2>

    <div class="p-4 border rounded shadow-sm bg-white">
      <div class="flex items-center justify-between">
        <div class="text-lg font-medium">Dossier racine (workspace)</div>
        <div class="text-sm text-gray-500">Gérez l'accès au dossier racine</div>
      </div>

      <div class="mt-4 flex items-center gap-3">
        <div>
          <Tag v-if="rootStatus === 'ok'" size="large" class="text-lg" icon="pi pi-check" severity="success"
            value="Dossier racine sélectionné"></Tag>
          <Tag v-else-if="rootStatus === 'no'" size="large" class="text-lg" icon="pi pi-times" severity="danger"
            value="Aucun dossier racine"></Tag>
          <Tag v-else size="large" class="text-lg" icon="pi pi-info" severity="info" value="Vérification..."></Tag>
        </div>

        <div class="ml-auto flex gap-2">
          <Button @click="selectRoot" label="Sélectionner"
            class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-md shadow-sm hover:bg-indigo-700 focus:outline-none" />
          <Button v-if="rootStatus === 'ok'" @click="clearRoot" label="Révoquer"
            class="inline-flex items-center px-3 py-1.5 bg-gray-100 text-gray-800 text-sm rounded-md border hover:bg-gray-200" />
          <Button v-if="rootStatus !== 'checking'" @click="requestRootPermission" label="Demander la permission"
            class="inline-flex items-center px-3 py-1.5 bg-yellow-500 text-white text-sm rounded-md hover:bg-yellow-600" />
        </div>
      </div>

      <div class="mt-3 text-sm text-gray-600">
        Note: les options d'ouverture et de création de fichiers/dossiers sont désactivées dans cette vue.
      </div>
    </div>

    <ConfirmDialog />
    <div class="p-4 border rounded shadow-sm bg-white">
      <div class="flex items-center justify-between">
        <div class="text-lg font-medium">Recherche & création dossiers</div>
        <div class="flex items-center gap-2">
          <Button @click="searchMissing" :disabled="searching" :loading="searching"
            label="Rechercher dossiers manquants"
            class="inline-flex items-center px-3 py-1.5 bg-indigo-600 text-white text-sm rounded-md shadow-sm hover:bg-indigo-700" />
          <Button @click="createAll" :disabled="creatingAll || !missingRows.length" :loading="creatingAll"
            label="Créer tout"
            class="inline-flex items-center px-3 py-1.5 bg-green-600 text-white text-sm rounded-md shadow-sm hover:bg-green-700" />
        </div>
      </div>

      <div class="mt-4">
        <div v-if="searching" class="flex items-center gap-2 text-sm text-gray-600">
          <ProgressSpinner style="width:1rem;height:1rem" />
          Recherche en cours...
        </div>

        <div v-else>
          <div v-if="!missingRows.length" class="text-sm text-gray-500">Aucun dossier manquant trouvé. Lancez une
            recherche pour vérifier.</div>

          <div v-else class="mt-3 overflow-x-auto">
            <table class="w-full text-sm table-auto">
              <thead>
                <tr class="text-left text-xs text-gray-600">
                  <th class="px-2 py-1">Projet</th>
                  <th class="px-2 py-1">Parcelle / GeoSheet</th>
                  <th class="px-2 py-1">Projet manquant</th>
                  <th class="px-2 py-1">Parcelle manquante</th>
                  <th class="px-2 py-1">Actions</th>
                </tr>
              </thead>
              <tbody>
                <tr v-for="(r, idx) in missingRows" :key="idx" class="border-t">
                  <td class="px-2 py-2">{{ r.projectTitle }}</td>
                  <td class="px-2 py-2">{{ r.geoName || '—' }}</td>
                  <td class="px-2 py-2">{{ r.projectMissing ? 'Oui' : 'Non' }}</td>
                  <td class="px-2 py-2">{{ r.geoMissing ? 'Oui' : 'Non' }}</td>
                  <td class="px-2 py-2">
                    <Button @click="createForRow(r)" icon="pi pi-plus" label="Créer"
                      class="p-button-sm p-button-rounded" />
                  </td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </div>
    </div>
  </div>
</template>

<script setup>
import { logAppError } from '@/utils/appLogger';

import { apiPrefix } from '@/config';
import * as fsService from '@/services/olders/fsService';
import { sanitizeName } from '@/services/olders/fsService';
import http from '@/service/http';
import Button from 'primevue/button';
import ConfirmDialog from 'primevue/confirmdialog';
import ProgressSpinner from 'primevue/progressspinner';
import Tag from 'primevue/tag';
import { useConfirm } from 'primevue/useconfirm';
import { useToast } from 'primevue/usetoast';
import { onMounted, ref } from 'vue';

const axios = http;

const rootStatus = ref('checking'); // 'checking' | 'ok' | 'no'

const toast = useToast();
const confirm = useConfirm();

async function checkRoot() {
  try {
    const h = await fsService.getRootDirectoryHandle();
    rootStatus.value = h ? 'ok' : 'no';
  } catch (err) {
    logAppError('Erreur checkRoot', err);
    rootStatus.value = 'no';
  }
}

async function selectRoot() {
  try {
    await fsService.requestRootDirectory();
    await checkRoot();
    toast.add({ severity: 'success', summary: 'Dossier racine', detail: 'Dossier racine configuré.', life: 3000 });
  } catch (err) {
    logAppError('FilesOptions', err);
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Sélection du dossier racine annulée ou échouée.', life: 3000 });
  }
}

async function clearRoot() {
  try {
    confirm.require({
      message: "Voulez-vous révoquer l'accès au dossier racine ?",
      header: 'Confirmation',
      icon: 'pi pi-exclamation-triangle',
      acceptLabel: 'Confirmer',
      rejectLabel: 'Annuler',
      accept: async () => {
        try {
          await fsService.clearRootHandle();
          await checkRoot();
          toast.add({ severity: 'success', summary: 'Révoqué', detail: 'Accès racine révoqué (local).', life: 3000 });
        } catch (e) {
          logAppError('FilesOptions', e);
          toast.add({ severity: 'error', summary: 'Erreur', detail: 'Erreur lors de la révocation.', life: 3000 });
        }
      },
      reject: () => {
        toast.add({ severity: 'info', summary: 'Annulé', detail: 'Opération annulée', life: 2000 });
      }
    });
  } catch (err) {
    logAppError('FilesOptions', err);
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Erreur lors de la révocation.', life: 3000 });
  }
}

async function requestRootPermission() {
  try {
    await fsService.requestPermissionForRoot();
    await checkRoot();
    toast.add({ severity: 'info', summary: 'Permission', detail: 'Permission demandée — vérifiez la boîte de dialogue du navigateur.', life: 3000 });
  } catch (err) {
    logAppError('requestRootPermission error', err);
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Aucune handle racine trouvée ou permission refusée.', life: 3000 });
  }
}

onMounted(() => {
  checkRoot();
});

// --- New: search / create missing folders ---
const searching = ref(false);
const creatingAll = ref(false);
const missingRows = ref([]); // { projectId, projectTitle, projectMissing, geoId, geoName, geoMissing }

function geoFolderName(gs) {
  // prefer parcelNumber, then title, then id-based name
  const raw = (gs && (gs.parcelNumber || gs.title)) || `geo-${gs?.id || Date.now()}`;
  return sanitizeName(raw);
}

async function searchMissing() {
  searching.value = true;
  missingRows.value = [];
  try {
    const root = await fsService.getRootDirectoryHandle();
    if (!root) {
      toast.add({ severity: 'error', summary: 'Erreur', detail: 'Dossier racine non configuré.', life: 3000 });
      searching.value = false;
      return;
    }

    const token = localStorage.getItem('token');
    const res = await axios.get(`${apiPrefix}/projects`, { headers: { Authorization: `Bearer ${token}` } });
    const projects = res.data || [];

    for (const proj of projects) {
      const rawProjName = proj.title || `project-${proj.id}`;
      const projName = sanitizeName(rawProjName);
      let projHandle = null;
      try {
        projHandle = await root.getDirectoryHandle(projName, { create: false });
      } catch (e) {
        projHandle = null;
      }

      const projectMissing = !projHandle;

      // get geosheets list from project object if present, else fetch
      const geos = Array.isArray(proj.geoSheets) ? proj.geoSheets : (await axios.get(`${apiPrefix}/projects/${proj.id}`, { headers: { Authorization: `Bearer ${token}` } })).data.geoSheets || [];

      if (projectMissing) {
        // mark all geos as missing under this project
        for (const gs of geos) {
          missingRows.value.push({ projectId: proj.id, projectTitle: projName, projectMissing: true, geoId: gs.id, geoName: geoFolderName(gs), geoMissing: true });
        }
        if (geos.length === 0) {
          // still push a row to allow creating the project folder alone
          missingRows.value.push({ projectId: proj.id, projectTitle: projName, projectMissing: true, geoId: null, geoName: null, geoMissing: false });
        }
      } else {
        // check each geosheet folder existence
        for (const gs of geos) {
          const gName = geoFolderName(gs);
          let gHandle = null;
          try {
            gHandle = await projHandle.getDirectoryHandle(gName, { create: false });
          } catch (e) {
            gHandle = null;
          }
          if (!gHandle) {
            missingRows.value.push({ projectId: proj.id, projectTitle: projName, projectMissing: false, geoId: gs.id, geoName: gName, geoMissing: true });
          }
        }
      }
    }

    toast.add({ severity: 'info', summary: 'Recherche terminée', detail: `${missingRows.value.length} élément(s) manquant(s)`, life: 3000 });
  } catch (err) {
    logAppError('searchMissing error', err);
    toast.add({ severity: 'error', summary: 'Erreur', detail: 'Recherche impossible', life: 3000 });
  } finally {
    searching.value = false;
  }
}

async function createForRow(row) {
  try {
    // create project if missing
    if (row.projectMissing) {
      await fsService.createProjectFolder(row.projectTitle);
      toast.add({ severity: 'success', summary: 'Créé', detail: `Dossier projet ${row.projectTitle} créé`, life: 2500 });
      row.projectMissing = false;
    }
    // create geo folder if requested
    if (row.geoId && row.geoMissing) {
      await fsService.createGeoFolder(row.projectTitle, row.geoName);
      toast.add({ severity: 'success', summary: 'Créé', detail: `Dossier parcelle ${row.geoName} créé`, life: 2500 });
      row.geoMissing = false;
    }
    // remove row if both satisfied
    if (!row.projectMissing && (!row.geoId || !row.geoMissing)) {
      const idx = missingRows.value.indexOf(row);
      if (idx !== -1) missingRows.value.splice(idx, 1);
    }
  } catch (err) {
    logAppError('createForRow error', err);
    toast.add({ severity: 'error', summary: 'Erreur', detail: `Création impossible: ${err.message || err}`, life: 3000 });
  }
}

async function createAll() {
  if (!missingRows.value.length) return toast.add({ severity: 'info', summary: 'Rien à faire', detail: 'Aucun dossier manquant', life: 2000 });
  creatingAll.value = true;
  // make a shallow copy to iterate since we'll modify missingRows
  const rows = [...missingRows.value];
  for (const r of rows) {
    // await sequentially to avoid too many file operations at once
    await createForRow(r);
  }
  creatingAll.value = false;
  toast.add({ severity: 'success', summary: 'Terminé', detail: 'Toutes les créations demandées sont effectuées', life: 3000 });
}
</script>

<style scoped>
textarea {
  font-family: monospace;
}
</style>
