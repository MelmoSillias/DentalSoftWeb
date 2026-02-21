<script setup>
import { computed, ref } from 'vue';
import Button from 'primevue/button';
import TabPanel from 'primevue/tabpanel';
import TabView from 'primevue/tabview';

const props = defineProps({
    role: { type: String, default: 'admin' },
    tabs: { type: Object, default: () => ({}) },
    loading: { type: Boolean, default: false }
});

const activeTab = ref(0);

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
    <div class="bg-surface-0 dark:bg-surface-800/80 rounded-2xl shadow-xl overflow-hidden border border-surface-200/50 dark:border-surface-700/50 backdrop-blur-sm h-full">
        <div class="px-4 sm:px-5 md:px-6 py-3 sm:py-4 border-b border-surface-200/50 dark:border-surface-700/50 bg-gradient-to-r from-surface-50 to-surface-0 dark:from-surface-900/50 dark:to-surface-800">
            <div class="space-y-1">
                <h3 class="text-base sm:text-lg font-semibold text-surface-900 dark:text-surface-100">En attente</h3>
                <p class="text-xs sm:text-sm text-surface-600 dark:text-surface-400">Actions necessitant votre attention</p>
            </div>
        </div>

        <Tabs v-model="activeTab" class="p-0 h-80">
            <TabList>
                <Tab value="0">Rendez-vous</Tab>
                <Tab value="1">Consultations</Tab>
                <Tab value="2">Factures</Tab>
                <Tab v-if="isReception" value="3">Paiements</Tab>
                <Tab v-if="isMedecin" value="4">Actes</Tab>
            </TabList>
            <TabPanels>
                <TabPanel value="0">
                    <div class="p-3 sm:p-4 space-y-4 max-h-[360px] sm:max-h-[400px] overflow-y-auto">
                        <div v-if="loading" class="space-y-3 animate-pulse">
                            <div class="h-16 rounded-xl bg-surface-200/80 dark:bg-surface-700/70"></div>
                            <div class="h-16 rounded-xl bg-surface-200/80 dark:bg-surface-700/70"></div>
                            <div class="h-16 rounded-xl bg-surface-200/80 dark:bg-surface-700/70"></div>
                        </div>
                        <div
                            v-else
                            v-for="rdv in appointments"
                            :key="rdv.id"
                            class="p-3 sm:p-4 rounded-xl border border-surface-200/50 dark:border-surface-700/50 hover:border-primary-200 dark:hover:border-primary-700 transition-colors"
                        >
                            <div class="flex items-start gap-3">
                                <div class="p-2 rounded-lg bg-blue-100/50 dark:bg-blue-900/30">
                                    <i class="pi pi-clock text-blue-600 dark:text-blue-400"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <h5 class="font-semibold text-surface-900 dark:text-surface-100 truncate text-sm sm:text-base">{{ rdv.patient }}</h5>
                                            <p class="text-xs sm:text-sm text-surface-500 dark:text-surface-400 mt-1">{{ rdv.date }}</p>
                                            <p v-if="rdv.medecin" class="text-[11px] sm:text-xs text-surface-500">{{ rdv.medecin }}</p>
                                        </div>
                                    </div>
                                    <p class="text-xs sm:text-sm text-surface-600 dark:text-surface-400 mt-2">{{ rdv.motif }}</p>
                                    <div class="flex items-center gap-2 mt-3">
                                        <Button as="router-link" to="/agenda/rendez-vous" icon="pi pi-eye" severity="info" size="small" text label="Voir" class="text-xs" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="!loading && !appointments.length" class="text-sm text-surface-500">Aucun rendez-vous.</div>
                    </div>
                </TabPanel>

                <TabPanel value="1">
                    <div class="p-3 sm:p-4 space-y-4 max-h-[360px] sm:max-h-[400px] overflow-y-auto">
                        <div v-if="loading" class="space-y-3 animate-pulse">
                            <div class="h-16 rounded-xl bg-surface-200/80 dark:bg-surface-700/70"></div>
                            <div class="h-16 rounded-xl bg-surface-200/80 dark:bg-surface-700/70"></div>
                            <div class="h-16 rounded-xl bg-surface-200/80 dark:bg-surface-700/70"></div>
                        </div>
                        <div
                            v-else
                            v-for="consult in pendingConsultations"
                            :key="consult.id"
                            class="p-3 sm:p-4 rounded-xl border border-surface-200/50 dark:border-surface-700/50 hover:border-amber-200 dark:hover:border-amber-700 transition-colors"
                        >
                            <div class="flex items-start gap-3">
                                <div class="p-2 rounded-lg bg-amber-100/50 dark:bg-amber-900/30">
                                    <i class="pi pi-heartbeat text-amber-600 dark:text-amber-400"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <h5 class="font-semibold text-surface-900 dark:text-surface-100 truncate text-sm sm:text-base">{{ consult.patient }}</h5>
                                            <p class="text-xs sm:text-sm text-surface-500 dark:text-surface-400 mt-1">{{ consult.date }}</p>
                                            <p v-if="consult.medecin" class="text-[11px] sm:text-xs text-surface-500">{{ consult.medecin }}</p>
                                        </div>
                                        <div class="flex items-center gap-1 shrink-0">
                                            <i class="pi pi-stopwatch text-surface-400"></i>
                                            <span class="text-[11px] sm:text-xs text-surface-500">{{ consult.waitingTime || '--' }}</span>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 mt-3">
                                        <Button as="router-link" to="/consultations/cards" icon="pi pi-play" severity="info" size="small" text label="Ouvrir" class="text-xs" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="!loading && !pendingConsultations.length" class="text-sm text-surface-500">Aucune consultation.</div>
                    </div>
                </TabPanel>

                <TabPanel value="2">
                    <div class="p-3 sm:p-4 space-y-4 max-h-[360px] sm:max-h-[400px] overflow-y-auto">
                        <div v-if="loading" class="space-y-3 animate-pulse">
                            <div class="h-16 rounded-xl bg-surface-200/80 dark:bg-surface-700/70"></div>
                            <div class="h-16 rounded-xl bg-surface-200/80 dark:bg-surface-700/70"></div>
                            <div class="h-16 rounded-xl bg-surface-200/80 dark:bg-surface-700/70"></div>
                        </div>
                        <div
                            v-else
                            v-for="invoice in unpaidInvoices"
                            :key="invoice.id"
                            class="p-3 sm:p-4 rounded-xl border border-surface-200/50 dark:border-surface-700/50 hover:border-red-200 dark:hover:border-red-700 transition-colors"
                        >
                            <div class="flex items-start gap-3">
                                <div class="p-2 rounded-lg bg-red-100/50 dark:bg-red-900/30">
                                    <i class="pi pi-euro text-red-600 dark:text-red-400"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <h5 class="font-semibold text-surface-900 dark:text-surface-100 truncate text-sm sm:text-base">{{ invoice.patient }}</h5>
                                            <p class="text-xs sm:text-sm text-surface-500 dark:text-surface-400 mt-1">{{ invoice.date }}</p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <p class="font-bold text-surface-900 dark:text-surface-100 text-sm sm:text-base">{{ formatAmount(invoice.amount) }}</p>
                                        </div>
                                    </div>
                                    <div class="flex items-center gap-2 mt-3">
                                        <Button as="router-link" to="/caisse" icon="pi pi-wallet" severity="warning" size="small" text label="Caisse" class="text-xs" />
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="!loading && !unpaidInvoices.length" class="text-sm text-surface-500">Aucune facture.</div>
                    </div>
                </TabPanel>

                <TabPanel v-if="isReception" value="3">
                    <div class="p-3 sm:p-4 space-y-4 max-h-[360px] sm:max-h-[400px] overflow-y-auto">
                        <div v-if="loading" class="space-y-3 animate-pulse">
                            <div class="h-16 rounded-xl bg-surface-200/80 dark:bg-surface-700/70"></div>
                            <div class="h-16 rounded-xl bg-surface-200/80 dark:bg-surface-700/70"></div>
                        </div>
                        <div
                            v-else
                            v-for="payment in payments"
                            :key="payment.id"
                            class="p-3 sm:p-4 rounded-xl border border-surface-200/50 dark:border-surface-700/50 hover:border-emerald-200 dark:hover:border-emerald-700 transition-colors"
                        >
                            <div class="flex items-start gap-3">
                                <div class="p-2 rounded-lg bg-emerald-100/50 dark:bg-emerald-900/30">
                                    <i class="pi pi-money-bill text-emerald-600 dark:text-emerald-400"></i>
                                </div>
                                <div class="flex-1 min-w-0">
                                    <div class="flex items-start justify-between gap-2">
                                        <div>
                                            <h5 class="font-semibold text-surface-900 dark:text-surface-100 truncate text-sm sm:text-base">{{ payment.patient }}</h5>
                                            <p class="text-xs sm:text-sm text-surface-500 dark:text-surface-400 mt-1">{{ payment.date }}</p>
                                        </div>
                                        <div class="text-right shrink-0">
                                            <p class="font-bold text-surface-900 dark:text-surface-100 text-sm sm:text-base">{{ formatAmount(payment.amount) }}</p>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                        <div v-if="!loading && !payments.length" class="text-sm text-surface-500">Aucun paiement.</div>
                    </div>
                </TabPanel>

                <TabPanel v-if="isMedecin" value="4">
                    <div class="p-3 sm:p-4 space-y-3 max-h-[360px] sm:max-h-[400px] overflow-y-auto">
                        <div v-if="loading" class="space-y-3 animate-pulse">
                            <div class="h-16 rounded-xl bg-surface-200/80 dark:bg-surface-700/70"></div>
                            <div class="h-16 rounded-xl bg-surface-200/80 dark:bg-surface-700/70"></div>
                        </div>
                        <div
                            v-else
                            v-for="act in topActs"
                            :key="act.label"
                            class="flex items-center justify-between p-3 sm:p-4 rounded-xl border border-surface-200/50 dark:border-surface-700/50"
                        >
                            <div class="flex items-center gap-3">
                                <div class="p-2 rounded-lg bg-purple-100/50 dark:bg-purple-900/30">
                                    <i class="pi pi-chart-pie text-purple-600 dark:text-purple-400"></i>
                                </div>
                                <div>
                                    <p class="font-medium text-surface-900 dark:text-surface-100 text-sm sm:text-base">{{ act.label }}</p>
                                    <p class="text-xs sm:text-sm text-surface-500 dark:text-surface-400">{{ act.total }} actes</p>
                                </div>
                            </div>
                        </div>
                        <div v-if="!loading && !topActs.length" class="text-sm text-surface-500">Aucun acte.</div>
                    </div>
                </TabPanel>
            </TabPanels>
        </Tabs>

        <div class="px-4 sm:px-5 py-3 sm:py-4 border-t border-surface-200/50 dark:border-surface-700/50 bg-surface-50/50 dark:bg-surface-800/50">
            <Button
                label="Voir tout"
                icon="pi pi-external-link"
                severity="secondary"
                outlined
                class="w-full justify-center rounded-xl"
                as="router-link"
                :to="isMedecin ? '/consultations/table' : '/agenda/rendez-vous'"
            />
        </div>
    </div>
</template>
