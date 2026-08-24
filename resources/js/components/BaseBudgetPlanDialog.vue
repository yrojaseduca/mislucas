<script setup>
import { reactive, ref, watch } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import { useFinanceStore } from '../stores/finance';
import CategoryDialog from './CategoryDialog.vue';

const visible = defineModel({ type: Boolean, default: false });
const props = defineProps({ dashboard: { type: Object, required: true } });
const store = useFinanceStore();
const rows = reactive([]);
const submitting = ref(false);
const error = ref('');
const categoryDialogVisible = ref(false);
const rolloverOptions = [{ label: 'Caduca', value: 'expires' }, { label: 'Se acumula', value: 'carry' }];

function loadRows() {
    rows.splice(0, rows.length, ...props.dashboard.workspace.categories.map((category) => {
        const rule = props.dashboard.plan.base_rules.find((item) => item.category_id === category.id);
        return { category_id: category.id, name: category.name, kind: category.kind, amount: rule ? rule.default_amount / 100 : null, rollover_policy: rule?.rollover_policy ?? 'expires' };
    }));
}

watch(visible, (isOpen) => { if (isOpen) loadRows(); error.value = ''; });

function categoryCreated(category) {
    if (!rows.some((row) => row.category_id === category.id)) rows.push({ category_id: category.id, name: category.name, kind: category.kind, amount: null, rollover_policy: 'expires' });
}

async function submit() {
    submitting.value = true;
    error.value = '';
    try {
        await store.saveMonthlyBudgetRules(rows.filter((row) => row.amount > 0).map((row) => ({ category_id: row.category_id, default_amount: Math.round(row.amount * 100), rollover_policy: row.rollover_policy })));
        visible.value = false;
    } catch (exception) {
        error.value = exception.response?.data?.message ?? 'No hemos podido guardar el plan base.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
  <Dialog v-model:visible="visible" modal header="Plan base mensual" :style="{ width: 'min(94vw, 48rem)' }">
    <form class="pt-2" @submit.prevent="submit">
      <div class="mb-5 flex items-start justify-between gap-4"><p class="max-w-xl text-sm leading-relaxed text-slate-500">Define lo que normalmente esperas ingresar o gastar cada mes. Puedes cambiar después un mes concreto sin alterar este plan.</p><Button type="button" label="Nueva categoría" icon="pi pi-plus" severity="secondary" size="small" @click="categoryDialogVisible = true" /></div>
      <div class="max-h-[55vh] space-y-3 overflow-y-auto pr-1"><div v-for="row in rows" :key="row.category_id" class="grid items-center gap-3 rounded-xl border border-slate-100 p-4 md:grid-cols-[1fr_12rem_10rem]"><div><b>{{ row.name }}</b><p class="text-xs" :class="row.kind === 'income' ? 'text-emerald-700' : 'text-slate-500'">{{ row.kind === 'income' ? 'Ingreso' : 'Gasto' }}</p></div><InputNumber v-model="row.amount" mode="currency" :currency="dashboard.workspace.currency" locale="es-ES" :min="0" placeholder="Sin presupuesto" fluid /><Select v-model="row.rollover_policy" :options="rolloverOptions" option-label="label" option-value="value" :disabled="!row.amount" fluid /></div><p v-if="!rows.length" class="py-10 text-center text-slate-400">Crea una categoría para empezar.</p></div>
      <p v-if="error" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
      <div class="mt-6 flex justify-end gap-3"><Button type="button" label="Cancelar" severity="secondary" text @click="visible = false" /><Button type="submit" label="Guardar plan base" icon="pi pi-check" :loading="submitting" /></div>
    </form>
  </Dialog>
  <CategoryDialog v-model="categoryDialogVisible" @created="categoryCreated" />
</template>
