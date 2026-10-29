<script setup>
import { reactive, ref } from 'vue';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Password from 'primevue/password';
import { useFinanceStore } from '../stores/finance';

const store = useFinanceStore();
const form = reactive({ name: '', email: '', password: '', password_confirmation: '', remember: false });
const submitting = ref(false);
const error = ref('');
const notice = ref('');
const mode = ref(new URLSearchParams(window.location.search).has('reset') ? 'reset' : 'login');

async function submit() {
    submitting.value = true;
    error.value = '';
    notice.value = '';
    try {
        if (mode.value === 'register') await store.register(form);
        else if (mode.value === 'forgot') { await store.forgotPassword(form.email); notice.value = 'Si existe una cuenta con ese correo, recibirás un enlace para restablecer la contraseña.'; }
        else if (mode.value === 'reset') { await store.resetPassword({ ...form, token: new URLSearchParams(window.location.search).get('token') }); mode.value = 'login'; notice.value = 'Contraseña actualizada. Ya puedes iniciar sesión.'; }
        else await store.login(form);
    } catch (exception) {
        error.value = Object.values(exception.response?.data?.errors ?? {})[0]?.[0] ?? 'No hemos podido completar la operación.';
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
        <p class="text-sm font-semibold text-emerald-700">{{ mode === 'register' ? 'Empieza con MisLucas' : mode === 'forgot' ? 'Recupera el acceso' : mode === 'reset' ? 'Nueva contraseña' : 'Bienvenido de nuevo' }}</p><h2 class="mt-2 text-3xl font-bold tracking-tight">{{ mode === 'register' ? 'Crea tu cuenta' : mode === 'forgot' ? 'Restablece tu contraseña' : mode === 'reset' ? 'Elige una contraseña nueva' : 'Entra en tu espacio' }}</h2><p class="mt-2 text-slate-500">{{ mode === 'register' ? 'Después podrás aceptar invitaciones a tus espacios.' : 'Usa tus datos para continuar.' }}</p>
        <div v-if="mode === 'register'" class="mt-8"><label class="mb-2 block text-sm font-semibold" for="name">Nombre</label><InputText id="name" v-model="form.name" autocomplete="name" class="w-full" required /></div>
        <div class="mt-8"><label class="mb-2 block text-sm font-semibold" for="email">Correo electrónico</label><InputText id="email" v-model="form.email" type="email" autocomplete="email" class="w-full" required /></div>
        <div v-if="mode !== 'forgot'" class="mt-5"><label class="mb-2 block text-sm font-semibold" for="password">Contraseña</label><Password input-id="password" v-model="form.password" :feedback="mode === 'register'" toggle-mask fluid :autocomplete="mode === 'reset' ? 'new-password' : 'current-password'" required /></div>
        <div v-if="mode === 'register' || mode === 'reset'" class="mt-5"><label class="mb-2 block text-sm font-semibold" for="password_confirmation">Repite la contraseña</label><Password input-id="password_confirmation" v-model="form.password_confirmation" :feedback="false" toggle-mask fluid autocomplete="new-password" required /></div>
        <div v-if="mode === 'login'" class="mt-5 flex items-center gap-2"><Checkbox v-model="form.remember" input-id="remember" binary /><label for="remember" class="text-sm text-slate-600">Mantener mi sesión</label></div>
        <p v-if="notice" class="mt-5 rounded-xl bg-emerald-50 p-3 text-sm text-emerald-700">{{ notice }}</p>
        <p v-if="error" class="mt-5 rounded-xl bg-red-50 p-3 text-sm text-red-700">{{ error }}</p>
        <Button type="submit" :label="mode === 'register' ? 'Crear cuenta' : mode === 'forgot' ? 'Enviar enlace' : mode === 'reset' ? 'Guardar contraseña' : 'Entrar'" icon="pi pi-arrow-right" icon-pos="right" :loading="submitting" class="mt-7 w-full" />
        <div class="mt-6 flex flex-wrap justify-center gap-x-4 gap-y-2 text-sm text-emerald-700"><button v-if="mode === 'login'" type="button" @click="mode = 'register'">Crear cuenta</button><button v-if="mode === 'login'" type="button" @click="mode = 'forgot'">He olvidado mi contraseña</button><button v-if="mode !== 'login'" type="button" @click="mode = 'login'">Volver a iniciar sesión</button></div>
      </form>
    </section>
  </main>
</template>
