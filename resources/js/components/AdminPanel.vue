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
const spaceSearch = ref('');
const spaceTypeFilter = ref('all');
const spaceStatusFilter = ref('active');
const userSearch = ref('');
const userStatusFilter = ref('all');
const userRoleFilter = ref('all');
const categorySearch = ref('');
const categoryKindFilter = ref('all');
const newCategory = reactive({ name: '', kind: 'expense', icon: 'pi-tag', color: '' });
const types = [{ label: 'Hogar', value: 'household' }, { label: 'Negocio', value: 'business' }];
const kinds = [{ label: 'Gasto', value: 'expense' }, { label: 'Ingreso', value: 'income' }];
const selectedWorkspace = computed(() => workspaces.value.find((item) => item.id === selectedWorkspaceId.value));
const filteredWorkspaces = computed(() => workspaces.value.filter((space) => {
    const search = spaceSearch.value.trim().toLowerCase();
    return (!search || space.name.toLowerCase().includes(search))
        && (spaceTypeFilter.value === 'all' || space.type === spaceTypeFilter.value)
        && (spaceStatusFilter.value === 'all' || (spaceStatusFilter.value === 'archived' ? space.archived : !space.archived));
}));
const filteredUsers = computed(() => users.value.filter((user) => {
    const search = userSearch.value.trim().toLowerCase();
    return (!search || user.name.toLowerCase().includes(search) || user.email.toLowerCase().includes(search))
        && (userStatusFilter.value === 'all' || (userStatusFilter.value === 'active' ? user.is_active : !user.is_active))
        && (userRoleFilter.value === 'all' || (userRoleFilter.value === 'superadmin' ? user.is_superadmin : !user.is_superadmin));
}));
const filteredCategories = computed(() => (selectedWorkspace.value?.categories ?? []).filter((category) => {
    const search = categorySearch.value.trim().toLowerCase();
    return (!search || category.name.toLowerCase().includes(search)) && (categoryKindFilter.value === 'all' || category.kind === categoryKindFilter.value);
}));

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
const removeUser = (user) => { if (window.confirm(`¿Eliminar al usuario "${user.name}"?\n\nMovimientos creados por este usuario: ${user.transactions_count ?? 0}\n\nLos movimientos se conservarán y se reasignarán al superadmin indicando su autor original.`)) run(() => axios.delete(`/api/admin/users/${user.id}`), 'Usuario eliminado y movimientos reasignados.'); };
const removeWorkspace = (space) => { if (window.confirm(`¿Eliminar el espacio "${space.name}"?\n\nParticipantes: ${space.members_count}\nMovimientos: ${space.transactions_count}\n\nEsta acción no se puede deshacer.`)) run(() => axios.delete(`/api/admin/workspaces/${space.id}`), 'Espacio eliminado.'); };
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
    <header class="mb-5 sm:mb-7"><p class="text-xs font-semibold uppercase tracking-wider text-emerald-700 sm:text-sm">Superadministración</p><h1 class="mt-1 text-2xl font-bold sm:text-3xl">Gestionar MisLucas</h1></header>
    <nav class="mb-5 grid grid-cols-3 gap-2 sm:mb-6 sm:flex sm:flex-wrap"><Button label="Espacios" icon="pi pi-home" class="w-full sm:w-auto" :severity="tab === 'spaces' ? undefined : 'secondary'" @click="tab = 'spaces'" /><Button label="Usuarios" icon="pi pi-users" class="w-full sm:w-auto" :severity="tab === 'users' ? undefined : 'secondary'" @click="tab = 'users'" /><Button label="Categorías" icon="pi pi-tags" class="w-full sm:w-auto" :severity="tab === 'categories' ? undefined : 'secondary'" @click="tab = 'categories'" /></nav>
    <p v-if="error" class="mb-5 rounded-xl bg-red-50 p-4 text-sm text-red-700">{{ error }}</p><p v-if="message" class="mb-5 rounded-xl bg-emerald-50 p-4 text-sm text-emerald-700">{{ message }}</p>
    <p v-if="loading" class="py-16 text-center text-slate-500">Cargando administración…</p>

    <div v-else-if="tab === 'spaces'" class="grid gap-4">
      <div class="grid gap-2 rounded-2xl bg-white p-3 shadow-sm sm:grid-cols-[2fr_1fr_1fr]"><InputText v-model="spaceSearch" placeholder="Buscar espacio" class="w-full" /><Select v-model="spaceTypeFilter" :options="[{ label: 'Todos los tipos', value: 'all' }, ...types]" option-label="label" option-value="value" class="w-full" /><Select v-model="spaceStatusFilter" :options="[{ label: 'Activos', value: 'active' }, { label: 'Archivados', value: 'archived' }, { label: 'Todos', value: 'all' }]" option-label="label" option-value="value" class="w-full" /></div>
      <div class="overflow-hidden rounded-2xl bg-white shadow-sm"><article v-for="space in filteredWorkspaces" :key="space.id" class="border-b border-slate-100 p-4 last:border-0 sm:p-5"><details class="group"><summary class="flex cursor-pointer list-none items-start justify-between gap-3 [&::-webkit-details-marker]:hidden"><div class="flex min-w-0 items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl bg-[#E7F0ED] text-[#123B4A]"><i class="pi pi-home" /></span><div class="min-w-0"><p class="truncate font-semibold text-[#172D38]">{{ space.name }}</p><p class="text-xs text-slate-500">{{ space.type === 'business' ? 'Negocio' : 'Hogar' }} · {{ space.currency }} · {{ space.members_count }} participantes</p></div></div><span class="flex shrink-0 items-center gap-2"><span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="space.archived ? 'bg-slate-100 text-slate-500' : 'bg-emerald-50 text-emerald-700'">{{ space.archived ? 'Archivado' : 'Activo' }}</span><i class="pi pi-chevron-down text-xs text-slate-400 transition-transform group-open:rotate-180" /></span></summary>
        <div class="mt-4 grid items-end gap-4 md:grid-cols-[2fr_1fr_8rem_auto_auto]">
          <div><label class="mb-2 block text-xs font-semibold uppercase text-slate-500">Nombre</label><InputText v-model="space.name" class="w-full" /></div>
          <div><label class="mb-2 block text-xs font-semibold uppercase text-slate-500">Tipo</label><Select v-model="space.type" :options="types" option-label="label" option-value="value" class="w-full" /></div>
          <div><label class="mb-2 block text-xs font-semibold uppercase text-slate-500">Moneda</label><InputText v-model="space.currency" maxlength="3" class="w-full" /></div>
          <label class="flex h-10 items-center gap-2"><Checkbox v-model="space.archived" binary /> Archivado</label>
          <div class="grid grid-cols-2 gap-2 md:flex"><Button label="Guardar" icon="pi pi-save" class="w-full md:w-auto" @click="saveWorkspace(space)" /><Button icon="pi pi-trash" label="Borrar" severity="danger" outlined class="w-full md:w-auto" @click="removeWorkspace(space)" /></div>
        </div><p class="mt-3 text-xs text-slate-500">{{ space.members_count }} participantes · {{ space.transactions_count }} movimientos</p></details></article><p v-if="!filteredWorkspaces.length" class="p-8 text-center text-sm text-slate-500">No hay espacios que coincidan con los filtros.</p></div>
    </div>

    <div v-else-if="tab === 'users'" class="grid gap-4">
      <div class="grid gap-2 rounded-2xl bg-white p-3 shadow-sm sm:grid-cols-[2fr_1fr_1fr]"><InputText v-model="userSearch" placeholder="Buscar por nombre o correo" class="w-full" /><Select v-model="userStatusFilter" :options="[{ label: 'Todos los estados', value: 'all' }, { label: 'Activos', value: 'active' }, { label: 'Inactivos', value: 'inactive' }]" option-label="label" option-value="value" class="w-full" /><Select v-model="userRoleFilter" :options="[{ label: 'Todos los roles', value: 'all' }, { label: 'Superadmins', value: 'superadmin' }, { label: 'Usuarios', value: 'user' }]" option-label="label" option-value="value" class="w-full" /></div>
      <div class="overflow-hidden rounded-2xl bg-white shadow-sm"><article v-for="user in filteredUsers" :key="user.id" class="border-b border-slate-100 p-4 last:border-0 sm:p-5"><details class="group"><summary class="flex cursor-pointer list-none items-center justify-between gap-3 [&::-webkit-details-marker]:hidden"><div class="flex min-w-0 items-center gap-3"><span class="grid h-10 w-10 shrink-0 place-items-center rounded-full bg-[#123B4A] font-bold text-[#A9E5D1]">{{ user.name.slice(0, 1).toUpperCase() }}</span><div class="min-w-0"><p class="truncate font-semibold text-[#172D38]">{{ user.name }}</p><p class="truncate text-xs text-slate-500">{{ user.email }}</p></div></div><span class="flex shrink-0 items-center gap-2"><span class="rounded-full px-2.5 py-1 text-[11px] font-semibold" :class="user.is_active ? 'bg-emerald-50 text-emerald-700' : 'bg-slate-100 text-slate-500'">{{ user.is_active ? 'Activo' : 'Inactivo' }}</span><i class="pi pi-chevron-down text-xs text-slate-400 transition-transform group-open:rotate-180" /></span></summary>
        <div class="mt-4 grid items-end gap-4 md:grid-cols-[2fr_auto_auto_auto]">
          <div><label class="mb-2 block text-xs font-semibold uppercase text-slate-500">Nombre</label><InputText v-model="user.name" class="w-full" /></div>
          <div class="grid grid-cols-1 gap-2 md:contents"><label class="flex min-h-10 items-center gap-2 text-sm"><Checkbox v-model="user.is_active" binary /> Activo</label><label class="flex min-h-10 items-center gap-2 text-sm"><Checkbox v-model="user.is_superadmin" binary /> Superadmin</label></div>
          <div class="grid grid-cols-2 gap-2 md:flex"><Button label="Guardar" icon="pi pi-save" class="w-full md:w-auto" @click="saveUser(user)" /><Button icon="pi pi-trash" label="Borrar" severity="danger" outlined class="w-full md:w-auto" @click="removeUser(user)" /></div>
        </div><p class="mt-3 text-xs text-slate-500">Espacios: {{ user.memberships.map((item) => item.workspace?.name).filter(Boolean).join(', ') || 'ninguno' }}</p></details></article><p v-if="!filteredUsers.length" class="p-8 text-center text-sm text-slate-500">No hay usuarios que coincidan con los filtros.</p></div>
    </div>

    <div v-else>
      <Select v-model="selectedWorkspaceId" :options="workspaces" option-label="name" option-value="id" placeholder="Selecciona un espacio" class="mb-4 w-full md:mb-5 md:w-80" />
      <template v-if="selectedWorkspace">
        <div class="mb-4 grid gap-2 rounded-2xl bg-white p-3 shadow-sm sm:grid-cols-[2fr_1fr]"><InputText v-model="categorySearch" placeholder="Buscar categoría" class="w-full" /><Select v-model="categoryKindFilter" :options="[{ label: 'Todos los tipos', value: 'all' }, ...kinds]" option-label="label" option-value="value" class="w-full" /></div>
        <form class="mb-5 grid items-end gap-3 rounded-2xl bg-emerald-50 p-4 sm:p-5 md:grid-cols-[2fr_1fr_1fr_auto]" @submit.prevent="addCategory">
          <div><label class="mb-2 block text-xs font-semibold uppercase text-emerald-800">Nueva categoría</label><InputText v-model="newCategory.name" class="w-full" required /></div>
          <Select v-model="newCategory.kind" :options="kinds" option-label="label" option-value="value" class="w-full" />
          <InputText v-model="newCategory.icon" placeholder="pi-tag" class="w-full" />
          <Button type="submit" label="Añadir categoría" icon="pi pi-plus" class="w-full md:w-auto" />
        </form>
        <div class="overflow-hidden rounded-2xl bg-white shadow-sm"><article v-for="category in filteredCategories" :key="category.id" class="border-b border-slate-100 p-4 last:border-0"><details class="group"><summary class="flex cursor-pointer list-none items-center gap-3 [&::-webkit-details-marker]:hidden"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#E7F0ED] text-[#2A7F87]"><i :class="['pi', category.icon || 'pi-tag']" /></span><span class="min-w-0 flex-1 truncate font-semibold text-[#172D38]">{{ category.name }}</span><span class="rounded-full bg-slate-100 px-2 py-1 text-[10px] font-semibold text-slate-500">{{ category.kind === 'income' ? 'Ingreso' : 'Gasto' }}</span><i class="pi pi-chevron-down text-xs text-slate-400 transition-transform group-open:rotate-180" /></summary><div class="mt-4 grid items-center gap-3 md:grid-cols-[2fr_1fr_1fr_auto_auto]"><div class="min-w-0"><label class="mb-1 block text-xs font-semibold uppercase text-slate-500 md:hidden">Nombre</label><InputText v-model="category.name" class="w-full" /></div><div class="grid grid-cols-2 gap-3 md:contents"><div><label class="mb-1 block text-xs font-semibold uppercase text-slate-500 md:hidden">Tipo</label><Select v-model="category.kind" :options="kinds" option-label="label" option-value="value" class="w-full" /></div><div><label class="mb-1 block text-xs font-semibold uppercase text-slate-500 md:hidden">Icono</label><InputText v-model="category.icon" class="w-full" /></div></div><div class="col-span-2 grid grid-cols-2 gap-2 md:col-span-1 md:flex"><Button icon="pi pi-save" label="Guardar" severity="secondary" class="w-full md:w-auto" @click="saveCategory(category)" /><Button icon="pi pi-trash" label="Borrar" severity="danger" outlined class="w-full md:w-auto" @click="removeCategory(category)" /></div></div></details></article><p v-if="!filteredCategories.length" class="p-8 text-center text-sm text-slate-500">No hay categorías que coincidan con los filtros.</p></div>
      </template>
    </div>
  </section>
</template>
