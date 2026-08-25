<script setup>
import { ref, watch } from 'vue';
import Button from 'primevue/button';
import Dialog from 'primevue/dialog';
import InputText from 'primevue/inputtext';
import { useFinanceStore } from '../stores/finance';

const visible = defineModel({ type: Boolean, default: false });
const store = useFinanceStore();
const email = ref('');
const invitation = ref(null);
const loading = ref(false);
const copied = ref(false);
const error = ref('');

watch(visible, (value) => {
    if (value) { email.value = ''; invitation.value = null; copied.value = false; error.value = ''; }
});

async function createInvitation() {
    loading.value = true;
    error.value = '';
    try { invitation.value = await store.createInvitation(email.value); }
    catch (exception) { error.value = exception.response?.data?.message ?? 'No se pudo crear la invitación.'; }
    finally { loading.value = false; }
}

async function copyLink() {
    await navigator.clipboard.writeText(invitation.value.url);
    copied.value = true;
}
</script>

<template>
  <Dialog v-model:visible="visible" modal header="Invitar a una persona" :style="{ width: 'min(34rem, 94vw)' }">
    <form v-if="!invitation" @submit.prevent="createInvitation">
      <p class="mb-5 text-sm text-slate-500">El enlace solo servirá para este correo, caducará en siete días y podrá utilizarse una vez.</p>
      <label for="invite-email" class="mb-2 block text-sm font-semibold">Correo electrónico</label>
      <InputText id="invite-email" v-model="email" type="email" class="w-full" autocomplete="email" required />
      <p v-if="error" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
      <Button type="submit" label="Crear enlace" icon="pi pi-link" :loading="loading" class="mt-6 w-full" />
    </form>
    <div v-else>
      <p class="mb-2 text-sm font-semibold text-emerald-700">Invitación creada para {{ invitation.email }}</p>
      <div class="break-all rounded-xl bg-slate-100 p-4 text-sm">{{ invitation.url }}</div>
      <Button :label="copied ? 'Enlace copiado' : 'Copiar enlace'" :icon="copied ? 'pi pi-check' : 'pi pi-copy'" class="mt-5 w-full" @click="copyLink" />
    </div>
  </Dialog>
</template>
