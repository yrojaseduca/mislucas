<script setup>
import { reactive, ref, watch } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { useFinanceStore } from '../stores/finance';

const visible = defineModel({ type: Boolean, default: false });
const props = defineProps({ initialType: { type: String, default: 'expense' } });
const emit = defineEmits(['created']);
const store = useFinanceStore();
const submitting = ref(false);
const errors = ref({});
const form = reactive({ name: '', kind: 'expense', color: '#84a940' });
const typeOptions = [{ label: 'Gasto', value: 'expense' }, { label: 'Ingreso', value: 'income' }];

watch(visible, (isOpen) => {
    if (isOpen) Object.assign(form, { name: '', kind: props.initialType, color: '#84a940' });
    errors.value = {};
});

async function submit() {
    submitting.value = true;
    errors.value = {};
    try {
        const category = await store.addCategory({ ...form, icon: null });
        emit('created', category);
        visible.value = false;
    } catch (error) {
        errors.value = error.response?.data?.errors ?? { general: ['No hemos podido crear la categoría.'] };
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
  <Dialog v-model:visible="visible" modal header="Nueva categoría" :style="{ width: 'min(92vw, 30rem)' }">
    <form class="grid gap-5 pt-2" @submit.prevent="submit">
      <div><label class="mb-2 block text-sm font-semibold">Nombre</label><InputText v-model="form.name" placeholder="Ej. Ropa" maxlength="100" class="w-full" required /></div>
      <div><label class="mb-2 block text-sm font-semibold">Tipo</label><Select v-model="form.kind" :options="typeOptions" option-label="label" option-value="value" class="w-full" /></div>
      <div><label class="mb-2 block text-sm font-semibold">Color</label><div class="flex items-center gap-3"><input v-model="form.color" type="color" class="h-11 w-16 cursor-pointer rounded-lg border border-slate-200 bg-white p-1"><span class="text-sm text-slate-500">{{ form.color }}</span></div></div>
      <div v-if="Object.keys(errors).length" class="rounded-xl bg-red-50 p-3 text-sm text-red-700"><p v-for="messages in errors" :key="messages[0]">{{ messages[0] }}</p></div>
      <div class="flex justify-end gap-3"><Button type="button" label="Cancelar" severity="secondary" text @click="visible = false" /><Button type="submit" label="Crear categoría" icon="pi pi-plus" :loading="submitting" /></div>
    </form>
  </Dialog>
</template>
