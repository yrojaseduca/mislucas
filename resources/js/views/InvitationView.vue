<script setup>
import { onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import Button from 'primevue/button';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import { useFinanceStore } from '../stores/finance';

const props = defineProps({ token: { type: String, required: true } });
const store = useFinanceStore();
const invitation = ref(null);
const loading = ref(true);
const submitting = ref(false);
const error = ref('');
const form = reactive({ name: '', password: '', password_confirmation: '' });

onMounted(async () => {
    try { invitation.value = (await axios.get(`/api/invitations/${props.token}`)).data; }
    catch { error.value = 'Este enlace no existe, ha caducado o ya fue utilizado.'; }
    finally { loading.value = false; }
});

async function accept() {
    submitting.value = true;
    error.value = '';
    try {
        store.applySession((await axios.post(`/api/invitations/${props.token}/accept`, store.user ? {} : form)).data);
        await store.loadWorkspaces();
        window.history.replaceState({}, '', '/');
        window.location.reload();
    } catch (exception) {
        const errors = exception.response?.data?.errors;
        error.value = errors ? Object.values(errors)[0][0] : (exception.response?.data?.message ?? 'No se pudo aceptar la invitación.');
    } finally { submitting.value = false; }
}
</script>

<template>
  <main class="grid min-h-screen place-items-center bg-[#183f35] p-5">
    <section class="w-full max-w-lg rounded-3xl bg-white p-8 shadow-xl md:p-10">
      <div class="mb-7 flex items-center gap-3"><span class="grid h-11 w-11 place-items-center rounded-xl bg-[#d9ff85] font-bold text-[#183f35]">M</span><b class="text-xl">MisLucas</b></div>
      <p v-if="loading" class="text-slate-500">Comprobando invitación…</p>
      <template v-else-if="invitation">
        <p class="text-sm font-semibold text-emerald-700">Te han invitado</p>
        <h1 class="mt-2 text-3xl font-bold">Únete a {{ invitation.workspace.name }}</h1>
        <p class="mt-3 text-slate-500">La invitación corresponde a <b>{{ invitation.email }}</b>.</p>
        <form class="mt-7" @submit.prevent="accept">
          <template v-if="!store.user">
            <label for="invite-name" class="mb-2 block text-sm font-semibold">Tu nombre</label>
            <InputText id="invite-name" v-model="form.name" class="w-full" required />
            <label for="invite-password" class="mb-2 mt-5 block text-sm font-semibold">Crea una contraseña</label>
            <Password input-id="invite-password" v-model="form.password" toggle-mask fluid required />
            <label for="invite-password-confirmation" class="mb-2 mt-5 block text-sm font-semibold">Repite la contraseña</label>
            <Password input-id="invite-password-confirmation" v-model="form.password_confirmation" :feedback="false" toggle-mask fluid required />
          </template>
          <p v-else class="rounded-xl bg-emerald-50 p-4 text-sm text-emerald-800">Aceptarás como {{ store.user.name }} ({{ store.user.email }}).</p>
          <p v-if="error" class="mt-4 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
          <Button type="submit" label="Aceptar y entrar" icon="pi pi-arrow-right" icon-pos="right" :loading="submitting" class="mt-6 w-full" />
        </form>
      </template>
      <p v-else class="rounded-xl bg-red-50 p-4 text-red-700">{{ error }}</p>
    </section>
  </main>
</template>
