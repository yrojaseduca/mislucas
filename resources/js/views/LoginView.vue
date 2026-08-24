<script setup>
import { reactive, ref } from 'vue';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import { useFinanceStore } from '../stores/finance';

const store = useFinanceStore();
const form = reactive({ email: 'juan@example.com', password: 'password', remember: false });
const submitting = ref(false);
const error = ref('');

async function submit() {
    submitting.value = true;
    error.value = '';
    try {
        await store.login(form);
    } catch (exception) {
        error.value = exception.response?.data?.errors?.email?.[0] ?? 'No hemos podido iniciar sesión.';
    } finally {
        submitting.value = false;
    }
}
</script>

<template>
  <main class="grid min-h-screen bg-[#f6f7f2] lg:grid-cols-2">
    <section class="hidden overflow-hidden bg-[#183f35] p-14 text-white lg:flex lg:flex-col lg:justify-between">
      <div class="flex items-center gap-3 text-xl font-bold"><span class="grid h-11 w-11 place-items-center rounded-xl bg-[#d9ff85] text-[#183f35]">M</span> MisLucas</div>
      <div class="max-w-xl"><p class="mb-5 text-sm font-semibold uppercase tracking-[.2em] text-[#d9ff85]">Tus números, sin fricción</p><h1 class="text-5xl font-bold leading-tight">Compartir gastos no debería complicar las cuentas.</h1><p class="mt-6 max-w-lg text-lg leading-relaxed text-emerald-100">Hogar y negocio, cada uno en su espacio. Sabed qué se pagó, quién lo adelantó y qué corresponde a cada persona.</p></div>
      <p class="text-sm text-emerald-200">Finanzas claras. Conversaciones más fáciles.</p>
    </section>
    <section class="flex items-center justify-center p-6">
      <form class="w-full max-w-md rounded-3xl bg-white p-8 shadow-sm md:p-10" @submit.prevent="submit">
        <div class="mb-8 lg:hidden"><span class="mr-3 inline-grid h-10 w-10 place-items-center rounded-xl bg-[#183f35] font-bold text-[#d9ff85]">M</span><b class="text-xl">MisLucas</b></div>
        <p class="text-sm font-semibold text-emerald-700">Bienvenido de nuevo</p><h2 class="mt-2 text-3xl font-bold tracking-tight">Entra en tu espacio</h2><p class="mt-2 text-slate-500">Usa tus datos para continuar.</p>
        <div class="mt-8"><label class="mb-2 block text-sm font-semibold" for="email">Correo electrónico</label><InputText id="email" v-model="form.email" type="email" autocomplete="email" class="w-full" required /></div>
        <div class="mt-5"><label class="mb-2 block text-sm font-semibold" for="password">Contraseña</label><Password input-id="password" v-model="form.password" :feedback="false" toggle-mask fluid autocomplete="current-password" required /></div>
        <div class="mt-5 flex items-center gap-2"><Checkbox v-model="form.remember" input-id="remember" binary /><label for="remember" class="text-sm text-slate-600">Mantener mi sesión</label></div>
        <p v-if="error" class="mt-5 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
        <Button type="submit" label="Entrar" icon="pi pi-arrow-right" icon-pos="right" :loading="submitting" class="mt-7 w-full" />
        <div class="mt-6 rounded-xl bg-[#f6f7f2] p-4 text-sm text-slate-600"><b class="block text-slate-800">Cuenta de demostración</b>juan@example.com · password</div>
      </form>
    </section>
  </main>
</template>
