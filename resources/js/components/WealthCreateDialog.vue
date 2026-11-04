<script setup>
import { reactive, ref, watch } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { useFinanceStore } from '../stores/finance';

const visible = defineModel({ type: Boolean, default: false });
const props = defineProps({ type: { type: String, required: true }, dashboard: { type: Object, required: true } });
const store = useFinanceStore();
const saving = ref(false);
const error = ref('');
const form = reactive({ name: '', creditor: '', original: null, rate: 0, quantity: 1, cost: null, price: null, symbol: '', investmentType: 'other' });
const investmentTypes = [{ label: 'Otra inversión', value: 'other' }, { label: 'Acción', value: 'stock' }, { label: 'Fondo', value: 'fund' }, { label: 'Cripto', value: 'crypto' }, { label: 'Inmueble', value: 'property' }];

watch(visible, (open) => { if (open) { Object.assign(form, { name: '', creditor: '', original: null, rate: 0, quantity: 1, cost: null, price: null, symbol: '', investmentType: 'other' }); error.value = ''; } });

async function submit() {
    saving.value = true; error.value = '';
    try {
        if (props.type === 'debt') await store.addDebt({ name: form.name, creditor: form.creditor || null, original_amount: Math.round((form.original ?? 0) * 100), outstanding_balance: Math.round((form.original ?? 0) * 100), annual_interest_rate: form.rate ?? 0 });
        else await store.addInvestment({ name: form.name, symbol: form.symbol || null, type: form.investmentType, quantity: form.quantity ?? 0, average_cost: Math.round((form.cost ?? 0) * 100), current_price: Math.round((form.price ?? 0) * 100), currency: props.dashboard.workspace.currency });
        visible.value = false;
    } catch (exception) { error.value = Object.values(exception.response?.data?.errors ?? {})[0]?.[0] ?? 'No hemos podido guardar los datos.'; }
    finally { saving.value = false; }
}
</script>

<template>
  <Dialog v-model:visible="visible" modal :header="type === 'debt' ? 'Añadir deuda' : 'Añadir inversión'" :style="{ width: 'min(92vw, 34rem)' }">
    <form class="grid gap-4 pt-2" @submit.prevent="submit">
      <div><label class="mb-2 block text-sm font-semibold">Nombre</label><InputText v-model="form.name" :placeholder="type === 'debt' ? 'Ej. Hipoteca' : 'Ej. Fondo indexado'" class="w-full" required /></div>
      <template v-if="type === 'debt'"><div><label class="mb-2 block text-sm font-semibold">Acreedor (opcional)</label><InputText v-model="form.creditor" class="w-full" /></div><div><label class="mb-2 block text-sm font-semibold">Importe pendiente inicial</label><InputNumber v-model="form.original" mode="currency" :currency="dashboard.workspace.currency" locale="es-ES" :min="0.01" class="w-full" required /></div><div><label class="mb-2 block text-sm font-semibold">Interés anual</label><InputNumber v-model="form.rate" suffix=" %" :min="0" :max="100" :min-fraction-digits="0" :max-fraction-digits="2" class="w-full" /></div></template>
      <template v-else><div class="grid grid-cols-2 gap-3"><div><label class="mb-2 block text-sm font-semibold">Tipo</label><Select v-model="form.investmentType" :options="investmentTypes" option-label="label" option-value="value" class="w-full" /></div><div><label class="mb-2 block text-sm font-semibold">Símbolo</label><InputText v-model="form.symbol" placeholder="Opcional" class="w-full" /></div></div><div class="grid grid-cols-2 gap-3"><div><label class="mb-2 block text-sm font-semibold">Cantidad</label><InputNumber v-model="form.quantity" :min="0" :min-fraction-digits="0" :max-fraction-digits="8" class="w-full" required /></div><div><label class="mb-2 block text-sm font-semibold">Precio medio</label><InputNumber v-model="form.cost" mode="currency" :currency="dashboard.workspace.currency" locale="es-ES" :min="0" class="w-full" required /></div></div><div><label class="mb-2 block text-sm font-semibold">Precio actual</label><InputNumber v-model="form.price" mode="currency" :currency="dashboard.workspace.currency" locale="es-ES" :min="0" class="w-full" required /></div></template>
      <p v-if="error" class="rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
      <div class="flex justify-end gap-2"><Button type="button" label="Cancelar" severity="secondary" text @click="visible = false" /><Button type="submit" label="Guardar" icon="pi pi-check" :loading="saving" /></div>
    </form>
  </Dialog>
</template>
