<script setup>
import { ref, computed, watch } from 'vue'
import { formatDistanceToNow, parseISO } from 'date-fns'
import fr from 'date-fns/locale/fr'

// PrimeVue
import Button from 'primevue/button'
import SelectButton from 'primevue/selectbutton'
import ConfirmPopup from 'primevue/confirmpopup'
import Badge from 'primevue/badge'
import Avatar from 'primevue/avatar'
import Paginator from 'primevue/paginator'
import { useConfirm } from 'primevue/useconfirm'

const props = defineProps({
  notifications: { type: Array, default: () => [] },
  unreadCount: { type: Number, default: 0 },
  loading: { type: Boolean, default: false },
  filter: { type: String, default: 'all' },
  notificationsEnabled: { type: Boolean, default: true }
})

const emit = defineEmits([
  'filter-change',
  'mark-read',
  'mark-all',
  'notifications-enabled-change'
])

const confirm = useConfirm()

// Options de filtre
const filterOptions = [
  { label: 'Toutes', value: 'all' },
  { label: 'Lues', value: 'read' },
  { label: 'Non lues', value: 'unread' }
]

// Filtrage des notifications
const filteredItems = computed(() => {
  if (!props.notifications.length) return []

  if (props.filter === 'read') {
    return props.notifications.filter(n => n.status !== 'non_vu')
  }
  if (props.filter === 'unread') {
    return props.notifications.filter(n => n.status === 'non_vu')
  }
  return props.notifications
})

// Pagination
const currentPage = ref(1)
const rowsPerPage = ref(5)

const paginatedItems = computed(() => {
  const start = (currentPage.value - 1) * rowsPerPage.value
  return filteredItems.value.slice(start, start + rowsPerPage.value)
})

const onPageChange = (event) => {
  currentPage.value = Math.floor(event.first / event.rows) + 1
  rowsPerPage.value = event.rows
}

// Réinitialiser la pagination quand le filtre ou l'état change
watch([() => props.filter, () => props.notificationsEnabled], () => {
  currentPage.value = 1
})

// Fonctions utilitaires
const formatRelativeDate = (dateString) => {
  if (!dateString) return 'Date inconnue'
  try {
    const date = parseISO(dateString)
    return formatDistanceToNow(date, { addSuffix: true, locale: fr })
  } catch {
    return dateString
  }
}

const getNotificationIcon = (notif) => {
  if (notif.type === 'success') return 'pi pi-check-circle'
  if (notif.type === 'warning') return 'pi pi-exclamation-triangle'
  if (notif.type === 'error') return 'pi pi-times-circle'
  return 'pi pi-bell'
}

const shortenLink = (link) => {
  if (!link) return ''
  const maxLength = 40
  return link.length > maxLength ? link.substring(0, maxLength) + '…' : link
}

// Marquer tout comme lu
const markAll = (event) => {
  if (!props.notifications.length) return

  confirm.require({
    target: event.currentTarget,
    message: 'Marquer toutes les notifications comme lues ?',
    icon: 'pi pi-exclamation-triangle',
    acceptLabel: 'Confirmer',
    rejectLabel: 'Annuler',
    accept: () => emit('mark-all')
  })
}
</script>

<template>
  <div class="bg-white dark:bg-surface-800/80 rounded-2xl shadow-lg border border-surface-200/50 dark:border-surface-700/50 overflow-hidden transition-all">
    <!-- En-tête -->
    <div class="p-5 border-b border-surface-200/50 dark:border-surface-700/50 flex flex-col sm:flex-row sm:items-center justify-between gap-4">
      <div class="flex items-center gap-3">
        <i class="pi pi-bell text-primary-500 text-xl"></i>
        <div>
          <p class="text-xs uppercase tracking-wider text-surface-500">Centre d’alertes</p>
          <div class="flex items-center gap-2">
            <h3 class="text-lg font-semibold text-surface-900 dark:text-surface-100">Notifications</h3>
            <Badge 
              :value="unreadCount" 
              severity="warn" 
              :class="unreadCount ? 'bg-amber-500' : 'hidden'" 
            />
          </div>
        </div>
      </div>

      <div class="flex items-center gap-3">
        <span class="text-xs text-surface-500">
          {{ unreadCount }} non lue{{ unreadCount > 1 ? 's' : '' }}
        </span>
        <Button 
          label="Tout lire" 
          icon="pi pi-check" 
          size="small" 
          outlined 
          :disabled="!notifications.length" 
          @click="markAll" 
        />
      </div>
    </div>

    <!-- Corps -->
    <div class="p-5 space-y-5">
      <!-- Activation + Filtres -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-3">
        <div class="flex items-center gap-2 text-xs text-surface-600">
          <i class="pi pi-toggle-on text-primary-500"></i>
          <span>Réception des notifications</span>
        </div>
        <SelectButton
          :modelValue="notificationsEnabled"
          :options="[{ label: 'Activées', value: true }, { label: 'Désactivées', value: false }]"
          optionLabel="label"
          optionValue="value"
          :allowEmpty="false"
          @update:modelValue="emit('notifications-enabled-change', $event)"
        />
      </div>

      <SelectButton
        :options="filterOptions"
        optionLabel="label"
        optionValue="value"
        :modelValue="filter"
        class="w-full sm:w-auto"
        :disabled="!notificationsEnabled"
        @update:modelValue="emit('filter-change', $event)"
      />

      <!-- États -->
      <div v-if="!notificationsEnabled" class="text-sm text-surface-500 bg-surface-100 dark:bg-surface-800 p-4 rounded-xl text-center">
        <i class="pi pi-ban text-surface-400 mr-2"></i>
        Notifications désactivées pour ce compte.
      </div>

      <div v-else-if="loading">
        <div v-for="i in 3" :key="i" class="flex items-start gap-4 p-4 rounded-xl border border-surface-100 dark:border-surface-700 animate-pulse">
          <div class="w-10 h-10 rounded-full bg-surface-200 dark:bg-surface-700"></div>
          <div class="flex-1 space-y-2">
            <div class="h-4 bg-surface-200 dark:bg-surface-700 rounded w-3/4"></div>
            <div class="h-3 bg-surface-100 dark:bg-surface-800 rounded w-1/2"></div>
          </div>
        </div>
      </div>

      <!-- Liste des notifications -->
      <div v-else>
        <TransitionGroup name="notif-list" tag="div" class="space-y-3">
          <div
            v-for="notif in paginatedItems"
            :key="notif.id"
            class="group relative flex items-start gap-4 p-4 rounded-xl border-l-4 transition-all duration-200 hover:shadow-md hover:scale-[1.01]"
            :class="[
              notif.status === 'non_vu'
                ? 'border-l-amber-500 bg-amber-50/30 dark:bg-amber-950/10'
                : 'border-l-transparent bg-surface-50/30 dark:bg-surface-800/40'
            ]"
          >
            <!-- Icône -->
            <div class="flex-shrink-0 mt-0.5">
              <Avatar
                :icon="getNotificationIcon(notif)"
                class="w-10 h-10"
                :class="notif.status === 'non_vu' ? 'bg-amber-100 text-amber-700' : 'bg-surface-200 text-surface-500'"
                shape="circle"
              />
            </div>

            <!-- Contenu -->
            <div class="flex-1 min-w-0">
              <p class="text-sm font-medium text-surface-900 dark:text-surface-100 break-words">
                {{ notif.message }}
              </p>
              <div class="flex flex-wrap items-center gap-x-3 gap-y-1 mt-1">
                <span class="text-xs text-surface-400 flex items-center gap-1">
                  <i class="pi pi-clock text-[10px]"></i>
                  {{ formatRelativeDate(notif.createdAt) }}
                </span>
                <span v-if="notif.link" class="text-xs text-primary-600 truncate max-w-[200px]">
                  <i class="pi pi-link mr-1"></i>
                  <a :href="notif.link" target="_blank" rel="noopener noreferrer" class="hover:underline">
                    {{ shortenLink(notif.link) }}
                  </a>
                </span>
              </div>
            </div>

            <!-- Statut & Action -->
            <div class="flex flex-col items-end gap-2">
              <span
                class="text-xs px-2 py-0.5 rounded-full"
                :class="notif.status === 'non_vu'
                  ? 'bg-amber-100 text-amber-700'
                  : 'bg-surface-100 text-surface-500 dark:bg-surface-700 dark:text-surface-300'"
              >
                {{ notif.status === 'non_vu' ? 'Non lue' : 'Lue' }}
              </span>

              <Button
                v-if="notif.status === 'non_vu'"
                icon="pi pi-check-circle"
                size="small"
                text
                rounded
                severity="success"
                @click="emit('mark-read', [notif.id])"
                tooltip="Marquer comme lue"
                tooltipOptions="{ position: 'left' }"
              />
            </div>
          </div>
        </TransitionGroup>

        <!-- Pagination -->
        <div v-if="filteredItems.length" class="flex flex-col sm:flex-row items-center justify-between gap-4 mt-6 pt-2 border-t border-surface-200 dark:border-surface-700">
          <div class="flex items-center gap-2 text-xs text-surface-500">
            <i class="pi pi-sliders-h"></i>
            <SelectButton
              v-model="rowsPerPage"
              :options="[5, 10, 20]"
              size="small"
            />
            <span>par page</span>
          </div>

          <Paginator
            :rows="rowsPerPage"
            :totalRecords="filteredItems.length"
            :first="(currentPage - 1) * rowsPerPage"
            @page="onPageChange"
            template="FirstPageLink PrevPageLink PageLinks NextPageLink LastPageLink"
            class="bg-transparent border-0 p-0"
          />
        </div>

        <!-- Aucun résultat -->
        <div v-else class="flex flex-col items-center justify-center py-12 text-surface-400">
          <i class="pi pi-inbox text-5xl mb-3 opacity-40"></i>
          <p class="text-sm">Aucune notification à afficher</p>
          <p class="text-xs mt-1">Les nouvelles alertes apparaîtront ici</p>
        </div>
      </div>
    </div>

    <ConfirmPopup />
  </div>
</template>

<style scoped>
.notif-list-enter-active,
.notif-list-leave-active {
  transition: all 0.3s ease;
}
.notif-list-enter-from {
  opacity: 0;
  transform: translateX(20px);
}
.notif-list-leave-to {
  opacity: 0;
  transform: translateX(-20px);
}
</style>