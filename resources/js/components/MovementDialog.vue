<script setup>
import { computed, reactive, ref, watch } from 'vue';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import DatePicker from 'primevue/datepicker';
import Dialog from 'primevue/dialog';
import InputNumber from 'primevue/inputnumber';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import Textarea from 'primevue/textarea';
import { useFinanceStore } from '../stores/finance';

const visible = defineModel({ type: Boolean, default: false });
const props = defineProps({ dashboard: { type: Object, required: true }, movement: { type: Object, default: null }, bankTransaction: { type: Object, default: null } });
const emit = defineEmits(['saved']);
const store = useFinanceStore();
const submitting = ref(false);
const errors = ref({});
const today = () => new Date(new Date().setHours(12, 0, 0, 0));
const form = reactive({ type: 'expense', amount: null, occurred_at: today(), description: '', category_id: null, account_id: null, paid_by_member_id: null, split_mode: 'equal', assigned_member_id: null, notes: '', recurring: false, frequency: 'monthly', ends_on: null, debt_id: null, interest_amount: 0 });
const members = computed(() => props.dashboard.workspace.members ?? []);
const debts = computed(() => props.dashboard.workspace.debts?.filter((debt) => debt.is_active) ?? []);
const expenseCategories = computed(() => props.dashboard.workspace.categories?.filter((category) => category.kind === form.type) ?? []);
const splitOptions = [{ label: 'A partes iguales', value: 'equal' }, { label: 'Solo una persona', value: 'single' }];
const typeOptions = [{ label: 'Gasto', value: 'expense' }, { label: 'Ingreso', value: 'income' }];
const frequencyOptions = [{ label: 'Cada semana', value: 'weekly' }, { label: 'Cada mes', value: 'monthly' }, { label: 'Cada año', value: 'yearly' }];

watch(visible, (isOpen) => {
    if (!isOpen) return;
    const source = props.movement ?? props.bankTransaction;
    Object.assign(form, source ? {
        type: source.type, amount: source.amount / 100, occurred_at: new Date(`${source.occurred_at.slice(0, 10)}T12:00:00`), description: source.description,
        category_id: source.category_id ?? null, account_id: source.account_id ?? props.dashboard.workspace.accounts?.[0]?.id ?? null, paid_by_member_id: source.paid_by_member_id ?? members.value[0]?.id ?? null,
        split_mode: source.splits?.length === 1 ? 'single' : 'equal', assigned_member_id: source.splits?.[0]?.member_id ?? members.value[0]?.id,
        notes: source.notes ?? '', recurring: false, frequency: 'monthly', ends_on: null, debt_id: source.debt_payment?.debt_id ?? null, interest_amount: (source.debt_payment?.interest_amount ?? 0) / 100,
    } : { type: 'expense', amount: null, occurred_at: today(), description: '', category_id: null, account_id: props.dashboard.workspace.accounts?.[0]?.id ?? null, paid_by_member_id: members.value[0]?.id ?? null, split_mode: 'equal', assigned_member_id: members.value[0]?.id ?? null, notes: '', recurring: false, frequency: 'monthly', ends_on: null, debt_id: null, interest_amount: 0 });
    errors.value = {};
});
watch(() => form.debt_id, (id) => {
    if (!id || props.movement?.debt_payment) return;
    const debt = debts.value.find((item) => item.id === id);
    form.interest_amount = Math.min(form.amount ?? Infinity, debt ? debt.outstanding_balance / 100 * Number(debt.annual_interest_rate) / 1200 : 0);
});

function buildSplits(amount) {
    if (form.type === 'income') return [];
    if (form.split_mode === 'single') return [{ member_id: form.assigned_member_id, amount, percentage: 100 }];
    const base = Math.floor(amount / members.value.length);
    let remainder = amount - (base * members.value.length);
    return members.value.map((member) => ({ member_id: member.id, amount: base + (remainder-- > 0 ? 1 : 0), percentage: Number((100 / members.value.length).toFixed(4)) }));
}

async function submit() {
    submitting.value = true;
    errors.value = {};
    const amount = Math.round((form.amount ?? 0) * 100);
    try {
        const payload = {
            type: form.type,
            amount,
            occurred_at: form.occurred_at.toLocaleDateString('en-CA'),
            description: form.description,
            category_id: form.category_id,
            account_id: form.account_id,
            paid_by_member_id: form.type === 'expense' ? form.paid_by_member_id : null,
            notes: form.notes || null,
            splits: buildSplits(amount),
            recurrence: !props.movement && form.recurring ? { frequency: form.frequency, ends_on: form.ends_on?.toLocaleDateString('en-CA') ?? null } : null,
            debt_payment: form.type === 'expense' && form.debt_id ? { debt_id: form.debt_id, interest_amount: Math.round((form.interest_amount ?? 0) * 100) } : null,
        };
        if (props.movement) await store.updateMovement(props.movement.id, payload);
        else if (props.bankTransaction) await store.acceptBankTransaction(props.bankTransaction.id, payload);
        else await store.addMovement(payload);
        visible.value = false;
        emit('saved');
    } catch (error) {
        errors.value = error.response?.data?.errors ?? { general: ['No hemos podido guardar el movimiento.'] };
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
  <Dialog v-model:visible="visible" modal :header="movement ? 'Editar movimiento' : bankTransaction ? 'Revisar operación bancaria' : 'Nuevo movimiento'" :style="{ width: 'min(92vw, 42rem)' }">
    <form class="grid gap-5 pt-2 md:grid-cols-2" @submit.prevent="submit">
      <div><label class="mb-2 block text-sm font-semibold">Tipo</label><Select v-model="form.type" :options="typeOptions" option-label="label" option-value="value" class="w-full" /></div>
      <div><label class="mb-2 block text-sm font-semibold">Importe</label><InputNumber v-model="form.amount" mode="currency" :currency="dashboard.workspace.currency" locale="es-ES" :min="0.01" class="w-full" required /></div>
      <div class="md:col-span-2"><label class="mb-2 block text-sm font-semibold">Concepto</label><InputText v-model="form.description" placeholder="Ej. Compra semanal" maxlength="255" class="w-full" required /></div>
      <div><label class="mb-2 block text-sm font-semibold">Fecha</label><DatePicker v-model="form.occurred_at" date-format="dd/mm/yy" show-icon class="w-full" required /></div>
      <div><label class="mb-2 block text-sm font-semibold">Categoría</label><Select v-model="form.category_id" :options="expenseCategories" option-label="name" option-value="id" placeholder="Sin categoría" show-clear class="w-full" /></div>
      <div><label class="mb-2 block text-sm font-semibold">Cuenta</label><Select v-model="form.account_id" :options="dashboard.workspace.accounts" option-label="name" option-value="id" placeholder="Selecciona una cuenta" class="w-full" /></div>
      <div v-if="form.type === 'expense'"><label class="mb-2 block text-sm font-semibold">Pagado por</label><Select v-model="form.paid_by_member_id" :options="members" option-label="display_name" option-value="id" class="w-full" required /></div>
      <div v-if="form.type === 'expense'"><label class="mb-2 block text-sm font-semibold">Vincular a deuda</label><Select v-model="form.debt_id" :options="debts" option-label="name" option-value="id" placeholder="No reduce ninguna deuda" show-clear class="w-full" /></div>
      <div v-if="form.type === 'expense' && form.debt_id"><label class="mb-2 block text-sm font-semibold">Intereses incluidos</label><InputNumber v-model="form.interest_amount" mode="currency" :currency="dashboard.workspace.currency" locale="es-ES" :min="0" :max="form.amount ?? 0" class="w-full" /><p class="mt-1 text-xs text-slate-500">El resto de la cuota reducirá el capital pendiente.</p></div>
      <template v-if="form.type === 'expense'">
        <div><label class="mb-2 block text-sm font-semibold">Reparto</label><Select v-model="form.split_mode" :options="splitOptions" option-label="label" option-value="value" class="w-full" /></div>
        <div v-if="form.split_mode === 'single'"><label class="mb-2 block text-sm font-semibold">Asignado a</label><Select v-model="form.assigned_member_id" :options="members" option-label="display_name" option-value="id" class="w-full" required /></div>
      </template>
      <div class="md:col-span-2"><label class="mb-2 block text-sm font-semibold">Nota opcional</label><Textarea v-model="form.notes" rows="2" class="w-full" /></div>
      <div v-if="!movement && !bankTransaction && !form.debt_id" class="flex items-center gap-3 rounded-xl bg-[#f6f7f2] p-4 md:col-span-2"><Checkbox v-model="form.recurring" input-id="recurring" binary /><label for="recurring"><b class="block">Movimiento recurrente</b><span class="text-sm text-slate-500">Se generará automáticamente en cada vencimiento.</span></label></div>
      <p v-else-if="movement && movement.recurring_transaction_id" class="rounded-xl bg-blue-50 p-3 text-sm text-blue-700 md:col-span-2">Estás editando solo esta aparición. La regla recurrente futura no cambiará.</p>
      <template v-if="!movement && !bankTransaction && form.recurring">
        <div><label class="mb-2 block text-sm font-semibold">Frecuencia</label><Select v-model="form.frequency" :options="frequencyOptions" option-label="label" option-value="value" class="w-full" /></div>
        <div><label class="mb-2 block text-sm font-semibold">Finaliza (opcional)</label><DatePicker v-model="form.ends_on" date-format="dd/mm/yy" show-icon show-button-bar class="w-full" /></div>
      </template>
      <div v-if="Object.keys(errors).length" class="rounded-xl bg-red-50 p-3 text-sm text-red-700 md:col-span-2"><p v-for="messages in errors" :key="messages[0]">{{ messages[0] }}</p></div>
      <div class="flex justify-end gap-3 md:col-span-2"><Button type="button" label="Cancelar" severity="secondary" text @click="visible = false" /><Button type="submit" :label="movement ? 'Guardar cambios' : 'Guardar movimiento'" icon="pi pi-check" :loading="submitting" /></div>
    </form>
  </Dialog>
</template>
