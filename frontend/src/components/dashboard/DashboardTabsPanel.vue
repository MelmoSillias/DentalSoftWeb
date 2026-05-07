<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import TabPanel from 'primevue/tabpanel'; 
import { TabList } from 'primevue';

const props = defineProps({
    role: { type: String, default: 'admin' },
    tabs: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false }
});

const activeIndex = ref("0");

const isMedecin = computed(() => props.role === 'medecin');
const isReception = computed(() => props.role === 'reception');

const appointments = computed(() => props.tabs?.appointments || []);
const pendingConsultations = computed(() => props.tabs?.pendingConsultations || []);
const unpaidInvoices = computed(() => props.tabs?.unpaidInvoices || props.tabs?.invoices || []);
const payments = computed(() => props.tabs?.payments || []);
const topActs = computed(() => props.tabs?.topActs || []);

const formatAmount = (value) => `${new Intl.NumberFormat('fr-FR').format(Number(value || 0))} Fcfa`;
</script>

<template>
    <div class="flex flex-col h-full rounded-2xl border border-surface-200 dark:border-surface-700 bg-surface-0 dark:bg-surface-900 shadow-xl overflow-hidden">
        <!-- Header -->
        <div class="px-5 py-4 border-b border-surface-200 dark:border-surface-700">
            <h3 class="text-lg font-semibold">En attente</h3>
            <p class="text-sm text-surface-500">Actions nécessitant votre attention</p>
        </div>

        <!-- Tabs -->
        <div class="flex-1 min-h-0">
            <Tabs :value="activeIndex" class="h-full flex flex-col" > 
                    <TabList>
                        <Tab value="0" >Rendez-vous</Tab>
                        <Tab value="1">Consultations</Tab>
                        <Tab value="2">Factures</Tab>
                        <Tab v-if="isReception" value="3">Paiements</Tab>
                        <Tab v-if="isMedecin" value="4">Actes</Tab>
                    </TabList>
                <!-- RDV -->
                <TabPanels>
                <TabPanel value="0">
                    <div class="list">
                        <template v-if="loading">
                            <div v-for="i in 3" :key="i" class="skeleton" />
                        </template>

                        <template v-else-if="appointments.length">
                            <div v-for="rdv in appointments" :key="rdv.id" class="item">
                                <div class="icon bg-blue-100 text-blue-600 dark:bg-blue-900 dark:text-blue-300"><i class="pi pi-clock" /></div>
                                <div class="content">
                                    <div class="title">{{ rdv.patient }}</div>
                                    <div class="meta">{{ rdv.date }} • {{ rdv.medecin || '—' }}</div>
                                    <div class="desc">{{ rdv.motif }}</div>
                                    <Button as="router-link" to="/agenda/rendez-vous" label="Voir" icon="pi pi-eye" text size="small" />
                                </div>
                            </div>
                        </template>

                        <div v-else class="empty">Aucun rendez-vous</div>
                    </div>
                </TabPanel>

                <!-- Consultations -->
                <TabPanel value="1">
                    <div class="list">
                        <template v-if="loading">
                            <div v-for="i in 3" :key="i" class="skeleton" />
                        </template>

                        <template v-else-if="pendingConsultations.length">
                            <div v-for="c in pendingConsultations" :key="c.id" class="item">
                                <div class="icon bg-amber-100 text-amber-600 dark:bg-amber-900 dark:text-amber-300"><i class="pi pi-heartbeat" /></div>
                                <div class="content">
                                    <div class="title">{{ c.patient }}</div>
                                    <div class="meta">{{ c.date }} • {{ c.waitingTime || '--' }}</div>
                                    <Button as="router-link" to="/consultations/cards" label="Ouvrir" icon="pi pi-play" text size="small" />
                                </div>
                            </div>
                        </template>

                        <div v-else class="empty">Aucune consultation</div>
                    </div>
                </TabPanel>

                <!-- Factures -->
                <TabPanel value="2">
                    <div class="list">
                        <template v-if="loading">
                            <div v-for="i in 3" :key="i" class="skeleton" />
                        </template>

                        <template v-else-if="unpaidInvoices.length">
                            <div v-for="inv in unpaidInvoices" :key="inv.id" class="item">
                                <div class="icon bg-red-100 text-red-600"><i class="pi pi-wallet" /></div>
                                <div class="content">
                                    <div class="title">{{ inv.patient }}</div>
                                    <div class="meta">{{ inv.date }}</div>
                                    <div class="amount">{{ formatAmount(inv.amount) }}</div>
                                    <Button as="router-link" to="/caisse" label="Caisse" icon="pi pi-arrow-right" text size="small" />
                                </div>
                            </div>
                        </template>

                        <div v-else class="empty">Aucune facture</div>
                    </div>
                </TabPanel>

                <!-- Paiements -->
                <TabPanel v-if="isReception" value="3">
                    <div class="list">
                        <template v-if="loading">
                            <div v-for="i in 2" :key="i" class="skeleton" />
                        </template>

                        <template v-else-if="payments.length">
                            <div v-for="p in payments" :key="p.id" class="item">
                                <div class="icon bg-emerald-100 text-emerald-600"><i class="pi pi-money-bill" /></div>
                                <div class="content">
                                    <div class="title">{{ p.patient }}</div>
                                    <div class="amount">{{ formatAmount(p.amount) }}</div>
                                </div>
                            </div>
                        </template>

                        <div v-else class="empty">Aucun paiement</div>
                    </div>
                </TabPanel>

                <!-- Actes -->
                <TabPanel v-if="isMedecin" value="4">
                    <div class="list">
                        <template v-if="loading">
                            <div v-for="i in 2" :key="i" class="skeleton" />
                        </template>

                        <template v-else-if="topActs.length">
                            <div v-for="act in topActs" :key="act.label" class="item simple">
                                <div class="icon bg-purple-100 text-purple-600"><i class="pi pi-chart-pie" /></div>
                                <div class="content">
                                    <div class="title">{{ act.label }}</div>
                                    <div class="meta">{{ act.total }} actes</div>
                                </div>
                            </div>
                        </template>

                        <div v-else class="empty">Aucun acte</div>
                    </div>
                </TabPanel>
                </TabPanels>
            </Tabs>
        </div>

        <!-- Footer -->
        <div class="p-4 border-t border-surface-200 dark:border-surface-700">
            <Button
            class="w-full"
            label="Voir tout"
            icon="pi pi-external-link"
            outlined
            as="router-link"
            :to="[
                '/agenda/rendez-vous',
                '/consultations/cards',
                '/caisse',
                isReception ? '/caisse' : isMedecin ? '/consultations/table' : '/agenda/rendez-vous',
                isMedecin ? '/consultations/table' : ''
            ][activeIndex]"
            />
        </div>
    </div>
</template>

<style scoped>
.list{max-height:25rem;overflow-y:auto;padding:1rem;display:flex;flex-direction:column;gap:0.75rem}
.list {
  display: flex;
  flex-direction: column;
  gap: 0.75rem; 
}

.item {
  background: white;
  border-radius: 1rem;
  box-shadow: 0 1px 3px rgba(0, 0, 0, 0.05), 0 1px 2px rgba(0, 0, 0, 0.03);
  transition: all 0.2s ease;
  display: flex;
  align-items: flex-start;
  gap: 1rem;
  padding: 1rem;
 
  &:hover {
    box-shadow: 0 8px 20px rgba(0, 0, 0, 0.08), 0 2px 4px rgba(0, 0, 0, 0.02);
    transform: translateY(-2px);
    background: #fefefe;
  }
 
  &.simple {
    align-items: center;
  }
}
 
.icon {
  flex-shrink: 0;
  width: 2.75rem;
  height: 2.75rem;
  display: flex;
  align-items: center;
  justify-content: center;
  border-radius: 9999px;
  font-size: 1.25rem;
  transition: transform 0.1s ease;

  .item:hover & {
    transform: scale(1.02);
  }
}
 
.content {
  flex: 1;
  display: flex;
  flex-direction: column;
  gap: 0.25rem;

  .title {
    font-weight: 600;
    font-size: 1rem;
    line-height: 1.4;
    color: #1e293b;
  }

  .meta {
    font-size: 0.75rem;
    color: #64748b;
    display: flex;
    align-items: center;
    gap: 0.5rem;
  }

  .desc {
    font-size: 0.8125rem;
    color: #475569;
    margin-top: 0.125rem;
  }

  .amount {
    font-weight: 600;
    font-size: 0.9375rem;
    color: #0f172a;
    margin-top: 0.125rem;
  }
}

.app-dark .item {
  background: #1e293b;
  color: #f1f5f9;

    &:hover {
        background: #2c3e50;
    }

    .title {
        color: #f1f5f9;
    }

    .meta {
        color: #94a3b8;
    }

    .desc {
        color: #cbd5e1;
    }

    .amount {
        color: #f1f5f9;
    }
}
 
.p-button-text {
  margin-top: 0.25rem;
  align-self: flex-start;
  padding: 0.25rem 0.5rem;

  &:hover {
    background: rgba(59, 130, 246, 0.08);
  }
}
.title{font-weight:600;font-size:0.95rem}
.meta{font-size:0.75rem;color:var(--text-color-secondary)}
.desc{font-size:0.8rem;margin-top:0.25rem}
.amount{font-weight:700;margin-top:0.25rem}
.empty{margin:auto;color:var(--text-color-secondary);font-size:0.9rem}
.skeleton{height:64px;border-radius:12px;background:var(--surface-200);opacity:.6}
</style>
