<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Button from 'primevue/button';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { useFinanceStore } from '../stores/finance';
import CategoryDialog from './CategoryDialog.vue';

const visible = defineModel({ type: Boolean, default: false });
const props = defineProps({ dashboard: { type: Object, required: true }, budget: { type: Object, default: null } });
const store = useFinanceStore();
const submitting = ref(false);
const categoryDialogVisible = ref(false);
const errors = ref({});
const form = reactive({ type: 'expense', name: '', category_id: null, month: new Date(), amount: null, rollover_policy: 'expires', notes: '' });
const typeOptions = [{ label: 'Gasto', value: 'expense' }, { label: 'Ingreso', value: 'income' }];
const rolloverOptions = [{ label: 'El sobrante caduca', value: 'expires' }, { label: 'Acumular al mes siguiente', value: 'carry' }];
const categories = computed(() => props.dashboard.workspace.categories?.filter((category) => category.kind === form.type) ?? []);

watch(visible, (isOpen) => {
    if (!isOpen) return;
    const source = props.budget;
    const [year, month] = props.dashboard.plan.month.split('-').map(Number);
    Object.assign(form, { type: source?.type ?? 'expense', name: source?.name ?? '', category_id: source?.category_id ?? null, month: new Date(year, month - 1, 1, 12), amount: source ? source.base_budget / 100 : null, rollover_policy: source?.rollover_policy ?? 'expires', notes: '' });
    errors.value = {};
});

watch(() => form.category_id, (id) => {
    const category = categories.value.find((item) => item.id === id);
    if (category && !props.budget) form.name = category.name;
});

async function submit() {
    submitting.value = true;
    errors.value = {};
    try {
        const category = categories.value.find((item) => item.id === form.category_id);
        await store.saveBudget({
            type: form.type, name: category?.name ?? form.name, category_id: form.category_id,
            month: `${form.month.getFullYear()}-${String(form.month.getMonth() + 1).padStart(2, '0')}-01`,
            amount: Math.round((form.amount ?? 0) * 100), rollover_policy: form.rollover_policy, notes: form.notes || null,
        });
        visible.value = false;
    } catch (error) {
        errors.value = error.response?.data?.errors ?? { general: ['No hemos podido guardar el presupuesto.'] };
    } finally {
        submitting.value = false;
    }
}

function categoryCreated(category) {
    form.type = category.kind;
    form.category_id = category.id;
    form.name = category.name;
}
</script>

<template>
  <Dialog v-model:visible="visible" modal :header="budget ? 'Editar presupuesto' : 'Nuevo presupuesto'" :style="{ width: 'min(92vw, 36rem)' }">
    <form class="grid gap-5 pt-2 md:grid-cols-2" @submit.prevent="submit">
      <div><label class="mb-2 block text-sm font-semibold">Tipo</label><Select v-model="form.type" :options="typeOptions" option-label="label" option-value="value" class="w-full" /></div>
      <div><label class="mb-2 block text-sm font-semibold">Mes</label><DatePicker v-model="form.month" view="month" date-format="mm/yy" class="w-full" required /></div>
      <div><div class="mb-2 flex items-center justify-between"><label class="text-sm font-semibold">Categoría</label><button type="button" class="text-xs font-semibold text-emerald-700 hover:underline" @click="categoryDialogVisible = true">+ Nueva categoría</button></div><Select v-model="form.category_id" :options="categories" option-label="name" option-value="id" placeholder="Selecciona una categoría" class="w-full" required /></div>
      <div><label class="mb-2 block text-sm font-semibold">Importe previsto</label><InputNumber v-model="form.amount" mode="currency" :currency="dashboard.workspace.currency" locale="es-ES" :min="0.01" class="w-full" required /></div>
      <div class="md:col-span-2"><label class="mb-2 block text-sm font-semibold">Al terminar el mes</label><Select v-model="form.rollover_policy" :options="rolloverOptions" option-label="label" option-value="value" class="w-full" /></div>
      <div class="md:col-span-2"><label class="mb-2 block text-sm font-semibold">Nota opcional</label><Textarea v-model="form.notes" rows="2" class="w-full" /></div>
      <div v-if="Object.keys(errors).length" class="rounded-xl bg-red-50 p-3 text-sm text-red-700 md:col-span-2"><p v-for="messages in errors" :key="messages[0]">{{ messages[0] }}</p></div>
      <div class="flex justify-end gap-3 md:col-span-2"><Button type="button" label="Cancelar" severity="secondary" text @click="visible = false" /><Button type="submit" label="Guardar presupuesto" icon="pi pi-check" :loading="submitting" /></div>
    </form>
  </Dialog>
  <CategoryDialog v-model="categoryDialogVisible" :initial-type="form.type" @created="categoryCreated" />
</template>
