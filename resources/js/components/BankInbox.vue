<script setup>
import { ref } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import Select from 'primevue/select';
import Tag from 'primevue/tag';
import { useFinanceStore } from '../stores/finance';

const props = defineProps({ dashboard: { type: Object, required: true }, money: { type: Function, required: true } });
const emit = defineEmits(['accept']);
const store = useFinanceStore();
const loading = ref(false);
const error = ref('');
const connectVisible = ref(false);
const institutions = ref([]);
const selectedInstitution = ref(null);

async function openConnect() {
    error.value = '';
    try {
        loading.value = true;
        institutions.value = await store.bankInstitutions();
        connectVisible.value = true;
    } catch (exception) {
        error.value = exception.response?.data?.errors?.connection?.[0] ?? 'No se pudo cargar la lista de bancos.';
    } finally {
        loading.value = false;
    }
}

async function connect() {
    if (!selectedInstitution.value) return;
    loading.value = true;
    try { window.location.assign(await store.connectBank(selectedInstitution.value)); }
    catch (exception) { error.value = exception.response?.data?.message ?? 'No se pudo iniciar la conexión bancaria.'; loading.value = false; }
}

async function sync(connection) {
    loading.value = true;
    error.value = '';
    try { await store.syncBank(connection.id); }
    catch (exception) { error.value = exception.response?.data?.message ?? 'No se pudo sincronizar el banco.'; }
    finally { loading.value = false; }
}

async function dismiss(item) {
    if (window.confirm(`¿Descartar "${item.description}" de la bandeja?`)) await store.dismissBankTransaction(item.id);
}
</script>

<template>
  <section class="mb-8 rounded-2xl bg-white p-6 shadow-sm">
    <div class="mb-5 flex flex-wrap items-center justify-between gap-3">
      <div><div class="flex items-center gap-2"><h2 class="text-xl font-bold">Bandeja bancaria</h2><Tag :value="String(dashboard.bank_inbox.length)" severity="info" /></div><p class="mt-1 text-sm text-slate-500">Revisa cada operación antes de añadirla a tus finanzas.</p></div>
      <Button label="Vincular banco" icon="pi pi-link" :disabled="!dashboard.banking_configured" :loading="loading" @click="openConnect" />
    </div>
    <p v-if="!dashboard.banking_configured" class="mb-4 rounded-xl bg-amber-50 p-3 text-sm text-amber-800">Configura tu aplicación personal de Enable Banking para habilitar la conexión. MisLucas nunca solicita ni almacena tus claves bancarias.</p>
    <p v-if="error" class="mb-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
    <div v-if="dashboard.workspace.bank_connections?.length" class="mb-5 flex flex-wrap gap-2">
      <Button v-for="connection in dashboard.workspace.bank_connections" :key="connection.id" :label="`Sincronizar ${connection.provider_name || 'banco'}`" icon="pi pi-refresh" size="small" severity="secondary" :loading="loading" @click="sync(connection)" />
    </div>
    <div v-for="item in dashboard.bank_inbox" :key="item.id" class="flex flex-wrap items-center justify-between gap-4 border-t border-slate-100 py-4">
      <div><b>{{ item.description }}</b><p class="text-sm text-slate-500">{{ item.bank_account.display_name }} · {{ item.occurred_at.slice(0, 10) }}</p></div>
      <div class="flex items-center gap-2"><b :class="item.type === 'income' ? 'text-emerald-700' : 'text-slate-800'">{{ item.type === 'income' ? '+' : '-' }} {{ money(item.amount) }}</b><Button label="Revisar" icon="pi pi-check" size="small" @click="emit('accept', item)"/><Button icon="pi pi-trash" severity="danger" text rounded aria-label="Descartar" @click="dismiss(item)"/></div>
    </div>
    <p v-if="!dashboard.bank_inbox.length" class="py-6 text-center text-sm text-slate-400">No hay operaciones pendientes de revisar.</p>
    <Dialog v-model:visible="connectVisible" modal header="Selecciona tu banco" :style="{ width: 'min(92vw, 34rem)' }"><p class="mb-4 text-sm text-slate-500">Después continuarás en el entorno seguro de tu banco para autorizar el acceso.</p><Select v-model="selectedInstitution" :options="institutions" option-label="name" placeholder="Buscar banco" filter class="w-full"/><div class="mt-5 flex justify-end gap-2"><Button label="Cancelar" severity="secondary" text @click="connectVisible=false"/><Button label="Continuar" icon="pi pi-external-link" :disabled="!selectedInstitution" :loading="loading" @click="connect"/></div></Dialog>
  </section>
</template>
