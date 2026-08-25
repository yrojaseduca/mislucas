<script setup>
import { computed, onMounted, reactive, ref } from 'vue';
import axios from 'axios';
import Button from 'primevue/button';
import Checkbox from 'primevue/checkbox';
import InputText from 'primevue/inputtext';
import Select from 'primevue/select';

const emit = defineEmits(['updated']);
const loading = ref(true);
const error = ref('');
const message = ref('');
const tab = ref('spaces');
const users = ref([]);
const workspaces = ref([]);
const selectedWorkspaceId = ref(null);
const newCategory = reactive({ name: '', kind: 'expense', icon: 'pi-tag', color: '' });
const types = [{ label: 'Hogar', value: 'household' }, { label: 'Negocio', value: 'business' }];
const kinds = [{ label: 'Gasto', value: 'expense' }, { label: 'Ingreso', value: 'income' }];
const selectedWorkspace = computed(() => workspaces.value.find((item) => item.id === selectedWorkspaceId.value));

async function load() {
    loading.value = true;
    try {
        const data = (await axios.get('/api/admin')).data;
        users.value = data.users;
        workspaces.value = data.workspaces.map((space) => ({ ...space, archived: Boolean(space.archived_at) }));
        selectedWorkspaceId.value ??= workspaces.value[0]?.id ?? null;
    } catch (exception) { error.value = exception.response?.data?.message ?? 'No se pudo cargar la administración.'; }
    finally { loading.value = false; }
}

async function run(action, success) {
    error.value = ''; message.value = '';
    try { await action(); message.value = success; await load(); emit('updated'); }
    catch (exception) { const errors = exception.response?.data?.errors; error.value = errors ? Object.values(errors)[0][0] : (exception.response?.data?.message ?? 'No se pudo guardar.'); }
}
const saveUser = (user) => run(() => axios.put(`/api/admin/users/${user.id}`, user), 'Usuario actualizado.');
const saveWorkspace = (space) => run(() => axios.put(`/api/admin/workspaces/${space.id}`, space), 'Espacio actualizado.');
const saveCategory = (category) => run(() => axios.put(`/api/admin/workspaces/${selectedWorkspaceId.value}/categories/${category.id}`, category), 'Categoría actualizada.');
const removeCategory = (category) => {
    if (window.confirm(`¿Eliminar la categoría "${category.name}"?`)) run(() => axios.delete(`/api/admin/workspaces/${selectedWorkspaceId.value}/categories/${category.id}`), 'Categoría eliminada.');
};
async function addCategory() {
    await run(() => axios.post(`/api/admin/workspaces/${selectedWorkspaceId.value}/categories`, newCategory), 'Categoría creada.');
    newCategory.name = ''; newCategory.kind = 'expense'; newCategory.icon = 'pi-tag'; newCategory.color = '';
}
onMounted(load);
</script>

<template>
  <section>
    <header class="mb-7"><p class="text-sm font-semibold uppercase tracking-wider text-emerald-700">Superadministración</p><h1 class="mt-1 text-3xl font-bold">Gestionar MisLucas</h1></header>
    <nav class="mb-6 flex flex-wrap gap-2"><Button label="Espacios" icon="pi pi-home" :severity="tab === 'spaces' ? undefined : 'secondary'" @click="tab = 'spaces'" /><Button label="Usuarios" icon="pi pi-users" :severity="tab === 'users' ? undefined : 'secondary'" @click="tab = 'users'" /><Button label="Categorías" icon="pi pi-tags" :severity="tab === 'categories' ? undefined : 'secondary'" @click="tab = 'categories'" /></nav>
    <p v-if="error" class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ error }}</p><p v-if="message" class="mb-5 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-700">{{ message }}</p>
    <p v-if="loading" class="py-16 text-center text-slate-500">Cargando administración…</p>

    <div v-else-if="tab === 'spaces'" class="grid gap-4">
      <article v-for="space in workspaces" :key="space.id" class="rounded-2xl bg-white p-5 shadow-sm">
        <div class="grid items-end gap-4 md:grid-cols-[2fr_1fr_8rem_auto_auto]">
          <div><label class="mb-2 block text-xs font-semibold uppercase text-slate-500">Nombre</label><InputText v-model="space.name" class="w-full" /></div>
          <div><label class="mb-2 block text-xs font-semibold uppercase text-slate-500">Tipo</label><Select v-model="space.type" :options="types" option-label="label" option-value="value" class="w-full" /></div>
          <div><label class="mb-2 block text-xs font-semibold uppercase text-slate-500">Moneda</label><InputText v-model="space.currency" maxlength="3" class="w-full" /></div>
          <label class="flex h-10 items-center gap-2"><Checkbox v-model="space.archived" binary /> Archivado</label>
          <Button label="Guardar" icon="pi pi-save" @click="saveWorkspace(space)" />
        </div><p class="mt-3 text-xs text-slate-500">{{ space.members_count }} participantes · {{ space.transactions_count }} movimientos</p>
      </article>
    </div>

    <div v-else-if="tab === 'users'" class="grid gap-4">
      <article v-for="user in users" :key="user.id" class="rounded-2xl bg-white p-5 shadow-sm">
        <div class="grid items-end gap-4 md:grid-cols-[2fr_2fr_auto_auto_auto]">
          <div><label class="mb-2 block text-xs font-semibold uppercase text-slate-500">Nombre</label><InputText v-model="user.name" class="w-full" /></div>
          <div><label class="mb-2 block text-xs font-semibold uppercase text-slate-500">Correo</label><p class="h-10 py-2 text-sm">{{ user.email }}</p></div>
          <label class="flex h-10 items-center gap-2"><Checkbox v-model="user.is_active" binary /> Activo</label>
          <label class="flex h-10 items-center gap-2"><Checkbox v-model="user.is_superadmin" binary /> Superadmin</label>
          <Button label="Guardar" icon="pi pi-save" @click="saveUser(user)" />
        </div><p class="mt-3 text-xs text-slate-500">Espacios: {{ user.memberships.map((item) => item.workspace?.name).filter(Boolean).join(', ') || 'ninguno' }}</p>
      </article>
    </div>

    <div v-else>
      <Select v-model="selectedWorkspaceId" :options="workspaces" option-label="name" option-value="id" placeholder="Selecciona un espacio" class="mb-5 w-full md:w-80" />
      <template v-if="selectedWorkspace">
        <form class="mb-5 grid items-end gap-3 rounded-2xl bg-emerald-50 p-5 md:grid-cols-[2fr_1fr_1fr_auto]" @submit.prevent="addCategory">
          <div><label class="mb-2 block text-xs font-semibold uppercase text-emerald-800">Nueva categoría</label><InputText v-model="newCategory.name" class="w-full" required /></div>
          <Select v-model="newCategory.kind" :options="kinds" option-label="label" option-value="value" class="w-full" />
          <InputText v-model="newCategory.icon" placeholder="pi-tag" class="w-full" />
          <Button type="submit" label="Añadir" icon="pi pi-plus" />
        </form>
        <div class="grid gap-3"><article v-for="category in selectedWorkspace.categories" :key="category.id" class="grid items-center gap-3 rounded-2xl bg-white p-4 shadow-sm md:grid-cols-[2fr_1fr_1fr_auto_auto]"><InputText v-model="category.name" /><Select v-model="category.kind" :options="kinds" option-label="label" option-value="value" /><InputText v-model="category.icon" /><Button icon="pi pi-save" label="Guardar" severity="secondary" @click="saveCategory(category)" /><Button icon="pi pi-trash" severity="danger" text aria-label="Eliminar" @click="removeCategory(category)" /></article></div>
      </template>
    </div>
  </section>
</template>
