<script setup>
import { computed, onMounted, ref } from 'vue';
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import { useFinanceStore } from './stores/finance';
import LoginView from './views/LoginView.vue';
import MovementDialog from './components/MovementDialog.vue';
import BudgetDialog from './components/BudgetDialog.vue';
import BaseBudgetPlanDialog from './components/BaseBudgetPlanDialog.vue';
import WealthPanel from './components/WealthPanel.vue';
import BankInbox from './components/BankInbox.vue';
import InvitationDialog from './components/InvitationDialog.vue';
import InvitationView from './views/InvitationView.vue';
import WorkspaceDialog from './components/WorkspaceDialog.vue';
const store = useFinanceStore();
const movementDialogVisible = ref(false);
const selectedMovement = ref(null);
const selectedBankTransaction = ref(null);
const budgetDialogVisible = ref(false);
const basePlanDialogVisible = ref(false);
const selectedBudget = ref(null);
const invitationDialogVisible = ref(false);
const workspaceDialogVisible = ref(false);
const invitationToken = window.location.pathname.match(/^\/invitacion\/([^/]+)$/)?.[1] ?? null;
onMounted(() => store.bootstrap());
const money = (cents) => new Intl.NumberFormat('es-ES', { style: 'currency', currency: store.current?.workspace.currency ?? 'EUR' }).format((cents ?? 0) / 100);
const title = computed(() => store.current?.workspace.type === 'business' ? 'Resultado del negocio' : 'Tu hogar, en equilibrio');
const incomes = computed(() => store.current?.transactions.filter((item) => item.type === 'income') ?? []);
const expenses = computed(() => store.current?.transactions.filter((item) => item.type === 'expense') ?? []);
const expectedResult = computed(() => (store.current?.plan.summary.expected_income ?? 0) - (store.current?.plan.summary.expected_expenses ?? 0));
const monthLabel = computed(() => new Intl.DateTimeFormat('es-ES', { month: 'long', year: 'numeric' }).format(new Date(`${store.current?.plan.month ?? store.planMonth}-02T12:00:00`)));
const canInvite = computed(() => store.user?.is_superadmin || store.current?.workspace.members.some((member) => member.user_id === store.user?.id && member.role === 'owner'));
function openBudget(budget = null) { selectedBudget.value = budget; budgetDialogVisible.value = true; }
function openMovement(movement = null) { selectedBankTransaction.value = null; selectedMovement.value = movement; movementDialogVisible.value = true; }
function openBankTransaction(transaction) { selectedMovement.value = null; selectedBankTransaction.value = transaction; movementDialogVisible.value = true; }
async function deleteMovement(movement) { if (window.confirm(`¿Eliminar "${movement.description}"? Esta acción no se puede deshacer.`)) await store.deleteMovement(movement.id); }
function shiftMonth(offset) { const [year, month] = store.planMonth.split('-').map(Number); const date = new Date(year, month - 1 + offset, 1, 12); store.changePlanMonth(`${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}`); }
</script>

<template>
  <div v-if="store.loading" class="grid min-h-screen place-items-center bg-[#183f35] text-white"><div class="text-center"><span class="mx-auto mb-4 grid h-14 w-14 place-items-center rounded-2xl bg-[#d9ff85] text-xl font-bold text-[#183f35]">M</span><p>Preparando MisLucas…</p></div></div>
  <InvitationView v-else-if="invitationToken" :token="invitationToken" />
  <LoginView v-else-if="!store.user" />
  <div v-else class="min-h-screen lg:flex">
    <aside class="bg-[#183f35] p-6 text-white lg:min-h-screen lg:w-72">
      <div class="mb-10 flex items-center gap-3 text-xl font-bold"><span class="grid h-10 w-10 place-items-center rounded-xl bg-[#d9ff85] text-[#183f35]">M</span> MisLucas</div>
      <p class="mb-3 text-xs font-semibold uppercase tracking-[.18em] text-emerald-200">Mis espacios</p>
      <button v-for="space in store.workspaces" :key="space.id" @click="store.select(space.id)" class="mb-2 flex w-full items-center gap-3 rounded-xl p-3 text-left transition" :class="store.current?.workspace.id === space.id ? 'bg-white/15' : 'hover:bg-white/10'">
        <i :class="space.type === 'business' ? 'pi pi-briefcase' : 'pi pi-home'" />
        <span><b class="block">{{ space.name }}</b><small class="text-emerald-100">{{ space.members.length }} participantes</small></span>
      </button>
      <Button v-if="store.user?.is_superadmin" label="Crear espacio" icon="pi pi-plus" severity="secondary" text class="mt-2 w-full !justify-start !text-white" @click="workspaceDialogVisible = true" />
      <div class="mt-10 border-t border-white/15 pt-5"><p class="mb-3 text-sm text-emerald-100">{{ store.user.name }}</p><Button label="Cerrar sesión" icon="pi pi-sign-out" severity="secondary" text class="w-full !justify-start !text-white" @click="store.logout" /></div>
    </aside>
    <main class="mx-auto w-full max-w-6xl p-5 md:p-10">
      <div v-if="store.current">
        <header class="mb-8 flex flex-wrap items-start justify-between gap-3"><div><p class="text-sm capitalize text-slate-500">{{ store.current.workspace.name }} · {{ monthLabel }}</p><h1 class="text-3xl font-bold tracking-tight">{{ title }}</h1></div><div class="flex gap-2"><Button v-if="canInvite" label="Invitar" icon="pi pi-user-plus" severity="secondary" @click="invitationDialogVisible = true" /><Button label="Nuevo movimiento" icon="pi pi-plus" @click="openMovement()" /></div></header>
        <section class="mb-8 grid gap-4 md:grid-cols-3">
          <article class="rounded-2xl bg-white p-6 shadow-sm"><p class="text-sm text-slate-500">Ingresos</p><strong class="mt-2 block text-2xl text-emerald-700">{{ money(store.current.summary.income) }}</strong></article>
          <article class="rounded-2xl bg-white p-6 shadow-sm"><p class="text-sm text-slate-500">Gastos</p><strong class="mt-2 block text-2xl">{{ money(store.current.summary.expenses) }}</strong></article>
          <article class="rounded-2xl bg-[#d9ff85] p-6 shadow-sm"><p class="text-sm text-[#315347]">Resultado</p><strong class="mt-2 block text-2xl">{{ money(store.current.summary.result) }}</strong></article>
        </section>
        <BankInbox :dashboard="store.current" :money="money" @accept="openBankTransaction" />
        <section class="mb-8 rounded-2xl bg-white p-6 shadow-sm">
          <div class="mb-6 flex flex-wrap items-center justify-between gap-4"><div><p class="text-sm font-semibold uppercase tracking-wider text-emerald-700">Plan mensual</p><h2 class="mt-1 text-2xl font-bold capitalize">{{ monthLabel }}</h2></div><div class="flex flex-wrap items-center gap-2"><Button icon="pi pi-chevron-left" severity="secondary" text rounded aria-label="Mes anterior" @click="shiftMonth(-1)" /><Button icon="pi pi-chevron-right" severity="secondary" text rounded aria-label="Mes siguiente" @click="shiftMonth(1)" /><Button label="Plan base" icon="pi pi-sync" severity="secondary" @click="basePlanDialogVisible = true" /><Button label="Excepción este mes" icon="pi pi-calendar-plus" @click="openBudget()" /></div></div>
          <div class="mb-6 grid gap-3 md:grid-cols-3"><div class="rounded-xl bg-emerald-50 p-4"><p class="text-sm text-emerald-700">Ingresos esperados</p><b class="mt-1 block text-xl">{{ money(store.current.plan.summary.expected_income) }}</b></div><div class="rounded-xl bg-amber-50 p-4"><p class="text-sm text-amber-700">Gastos previstos</p><b class="mt-1 block text-xl">{{ money(store.current.plan.summary.expected_expenses) }}</b></div><div class="rounded-xl bg-[#183f35] p-4 text-white"><p class="text-sm text-emerald-100">Ahorro esperado</p><b class="mt-1 block text-xl">{{ money(expectedResult) }}</b></div></div>
          <div class="overflow-x-auto"><table class="w-full min-w-[720px] text-left text-sm"><thead class="border-b border-slate-200 text-xs uppercase tracking-wider text-slate-500"><tr><th class="pb-3">Categoría</th><th class="pb-3 text-right">Presupuesto</th><th class="pb-3 text-right">Comprometido</th><th class="pb-3 text-right">Real</th><th class="pb-3 text-right">Disponible</th><th></th></tr></thead><tbody><tr v-for="row in store.current.plan.rows" :key="row.key" class="border-b border-slate-100 last:border-0"><td class="py-4"><b>{{ row.name }}</b><p class="text-xs text-slate-500">{{ row.type === 'income' ? 'Ingreso' : 'Gasto' }} · {{ row.is_override ? 'Excepción de este mes' : 'Plan base' }}<span v-if="row.carry"> · Incluye {{ money(row.carry) }} acumulados</span></p></td><td class="py-4 text-right">{{ money(row.budget) }}</td><td class="py-4 text-right">{{ money(row.committed) }}</td><td class="py-4 text-right">{{ money(row.actual) }}</td><td class="py-4 text-right font-semibold" :class="row.remaining < 0 ? 'text-red-600' : 'text-emerald-700'">{{ money(row.remaining) }}</td><td class="py-4 pl-3 text-right"><Button icon="pi pi-pencil" severity="secondary" text rounded aria-label="Editar solo este mes" @click="openBudget(row)" /></td></tr><tr v-if="!store.current.plan.rows.length"><td colspan="6" class="py-10 text-center text-slate-400">Configura tu plan base mensual para empezar.</td></tr></tbody></table></div>
          <p class="mt-4 text-xs text-slate-500">La previsión usa el mayor valor entre el presupuesto y lo ya gastado más los pagos recurrentes pendientes, evitando contar dos veces el mismo gasto.</p>
        </section>
        <section class="mb-8 grid gap-6 lg:grid-cols-[1.6fr_1fr]">
          <section class="grid gap-4 md:grid-cols-2">
            <article class="rounded-2xl bg-white p-6 shadow-sm"><div class="mb-5 flex items-center justify-between"><h2 class="text-lg font-bold">Ingresos</h2><span class="rounded-full bg-emerald-50 px-3 py-1 text-xs font-semibold text-emerald-700">{{ incomes.length }}</span></div><div v-for="item in incomes" :key="item.id" class="flex items-center justify-between gap-3 border-b border-slate-100 py-4 last:border-0"><div class="min-w-0"><b class="block truncate">{{ item.description }}</b><p class="truncate text-sm text-slate-500">{{ item.category?.name ?? item.account?.name ?? 'Sin categoría' }}</p></div><div class="flex shrink-0 items-center gap-1"><span class="mr-1 font-semibold text-emerald-700">+ {{ money(item.amount) }}</span><Button icon="pi pi-pencil" severity="secondary" text rounded size="small" aria-label="Editar ingreso" @click="openMovement(item)" /><Button icon="pi pi-trash" severity="danger" text rounded size="small" aria-label="Eliminar ingreso" @click="deleteMovement(item)" /></div></div><p v-if="!incomes.length" class="py-8 text-center text-sm text-slate-400">Todavía no hay ingresos.</p></article>
            <article class="rounded-2xl bg-white p-6 shadow-sm"><div class="mb-5 flex items-center justify-between"><h2 class="text-lg font-bold">Gastos</h2><span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-semibold text-amber-700">{{ expenses.length }}</span></div><div v-for="item in expenses" :key="item.id" class="flex items-center justify-between gap-3 border-b border-slate-100 py-4 last:border-0"><div class="min-w-0"><b class="block truncate">{{ item.description }}</b><p class="truncate text-sm text-slate-500">{{ item.category?.name ?? 'Sin categoría' }} · {{ item.payer?.display_name ?? item.account?.name }}</p></div><div class="flex shrink-0 items-center gap-1"><span class="mr-1 font-semibold text-slate-800">- {{ money(item.amount) }}</span><Button icon="pi pi-pencil" severity="secondary" text rounded size="small" aria-label="Editar gasto" @click="openMovement(item)" /><Button icon="pi pi-trash" severity="danger" text rounded size="small" aria-label="Eliminar gasto" @click="deleteMovement(item)" /></div></div><p v-if="!expenses.length" class="py-8 text-center text-sm text-slate-400">Todavía no hay gastos.</p></article>
          </section>
          <article class="rounded-2xl bg-white p-6 shadow-sm"><h2 class="mb-2 text-lg font-bold">Saldos</h2><p class="mb-5 text-sm text-slate-500">Pagado frente a lo que correspondía</p><div v-for="row in store.current.balances" :key="row.member.id" class="mb-4 rounded-xl bg-slate-50 p-4"><div class="flex justify-between"><b>{{ row.member.display_name }}</b><Tag :severity="row.balance >= 0 ? 'success' : 'warn'" :value="money(row.balance)" /></div><p class="mt-2 text-xs text-slate-500">Pagó {{ money(row.paid) }} · Parte {{ money(row.share) }}</p></div></article>
        </section>
        <WealthPanel :dashboard="store.current" :money="money" />
      </div>
      <section v-else class="mx-auto mt-24 max-w-lg rounded-3xl bg-white p-10 text-center shadow-sm"><span class="mx-auto grid h-16 w-16 place-items-center rounded-2xl bg-emerald-50 text-2xl text-emerald-700"><i class="pi pi-home" /></span><h1 class="mt-5 text-2xl font-bold">Crea tu primer espacio</h1><p class="mt-3 text-slate-500">Organiza un hogar o un negocio y después invita a las personas que participarán.</p><Button v-if="store.user?.is_superadmin" label="Crear espacio" icon="pi pi-plus" class="mt-7" @click="workspaceDialogVisible = true" /></section>
    </main>
    <MovementDialog v-if="store.current" v-model="movementDialogVisible" :dashboard="store.current" :movement="selectedMovement" :bank-transaction="selectedBankTransaction" />
    <BudgetDialog v-if="store.current" v-model="budgetDialogVisible" :dashboard="store.current" :budget="selectedBudget" />
    <BaseBudgetPlanDialog v-if="store.current" v-model="basePlanDialogVisible" :dashboard="store.current" />
    <InvitationDialog v-if="store.current" v-model="invitationDialogVisible" />
    <WorkspaceDialog v-model="workspaceDialogVisible" />
  </div>
</template>
