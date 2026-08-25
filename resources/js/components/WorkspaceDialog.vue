<script setup>
import { reactive, ref, watch } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';
import { useFinanceStore } from '../stores/finance';

const visible = defineModel({ type: Boolean, default: false });
const store = useFinanceStore();
const form = reactive({ name: '', type: 'household', currency: 'EUR' });
const types = [{ label: 'Hogar', value: 'household' }, { label: 'Negocio', value: 'business' }];
const loading = ref(false);
const error = ref('');

watch(visible, (value) => {
    if (value) { form.name = ''; form.type = 'household'; form.currency = 'EUR'; error.value = ''; }
});

async function submit() {
    loading.value = true;
    error.value = '';
    try { await store.createWorkspace(form); visible.value = false; }
    catch (exception) { error.value = exception.response?.data?.message ?? 'No se pudo crear el espacio.'; }
    finally { loading.value = false; }
}
</script>

<template>
  <Dialog v-model:visible="visible" modal header="Crear espacio" :style="{ width: 'min(32rem, 94vw)' }">
    <form @submit.prevent="submit">
      <label for="workspace-name" class="mb-2 block text-sm font-semibold">Nombre</label>
      <InputText id="workspace-name" v-model="form.name" class="w-full" placeholder="Por ejemplo: Mi casa" required autofocus />
      <label for="workspace-type" class="mb-2 mt-5 block text-sm font-semibold">Tipo de espacio</label>
      <Select id="workspace-type" v-model="form.type" :options="types" option-label="label" option-value="value" class="w-full" />
      <p class="mt-3 text-sm text-slate-500">Se creará con una cuenta principal y categorías básicas. Después podrás invitar participantes.</p>
      <p v-if="error" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
      <Button type="submit" label="Crear espacio" icon="pi pi-plus" :loading="loading" class="mt-6 w-full" />
    </form>
  </Dialog>
</template>
