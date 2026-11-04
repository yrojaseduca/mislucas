<script setup>
import Button from 'primevue/button';
import Tag from 'primevue/tag';
import Select from 'primevue/select';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

const props = defineProps({ dashboard: { type: Object, required: true }, workspaces: { type: Array, default: () => [] }, user: { type: Object, required: true }, canInvite: { type: Boolean, default: false }, money: { type: Function, required: true }, monthLabel: { type: String, required: true } });
const emit = defineEmits(['movement', 'budget', 'plan', 'group', 'bank', 'month', 'workspace', 'create-workspace', 'invite', 'import-bank', 'connect-bank', 'add-debt', 'add-investment', 'admin', 'logout']);
const showAllMovements = ref(false);
const movementFilter = ref('all');
const accountMenuOpen = ref(false);
const accountMenuRef = ref(null);
const mockMode = new URLSearchParams(window.location.search).get('mock');
const wealthMock = mockMode === 'wealth' || mockMode === 'dashboard';
const dashboardMock = mockMode === 'dashboard';
const mockDebts = Array.from({ length: 8 }, (_, index) => ({ id: `mock-debt-${index}`, name: ['Hipoteca', 'Coche', 'Préstamo familiar', 'Tarjeta', 'Reforma', 'Estudios', 'Viaje', 'Otro préstamo'][index], outstanding_balance: (index + 1) * 125000, is_active: true }));
const mockInvestments = Array.from({ length: 8 }, (_, index) => ({ id: `mock-investment-${index}`, name: ['Indexado global', 'S&P 500', 'Bitcoin', 'Bonos', 'Acciones', 'Fondo europeo', 'Inmueble', 'Plan ahorro'][index], quantity: index + 1, current_price: (index + 2) * 8500, average_cost: (index + 1) * 7000 }));
const mockTransactions = Array.from({ length: 12 }, (_, index) => ({ id: `mock-transaction-${index}`, type: index % 4 === 0 ? 'income' : 'expense', amount: (index + 1) * 1250, description: ['Mercadona', 'Restaurante', 'Internet', 'Transporte', 'Salario', 'Farmacia', 'Gasolina', 'Alquiler', 'Luz', 'Supermercado', 'Seguro', 'Ocio'][index], occurred_at: `2026-11-${String(28 - index).padStart(2, '0')}`, category: { name: index % 4 === 0 ? 'Ingresos' : 'Gastos' }, payer: { display_name: index % 2 === 0 ? 'Juan' : 'María' } }));
const mockBalances = Array.from({ length: 8 }, (_, index) => ({ member: { id: `mock-member-${index}`, display_name: ['Juan', 'María', 'Pedro', 'Lucía', 'Carlos', 'Ana', 'Pablo', 'Sofía'][index] }, balance: (index % 2 === 0 ? 1 : -1) * (index + 1) * 2500, paid: (index + 1) * 5000, share: (index + 1) * 2500 }));
const mockBudgetRows = Array.from({ length: 8 }, (_, index) => ({ key: `mock-budget-${index}`, name: ['Alimentación', 'Vivienda', 'Transporte', 'Ocio', 'Salud', 'Educación', 'Ropa', 'Otros'][index], actual: (index + 1) * 3000, budget: (index + 2) * 5000 }));
const debts = computed(() => wealthMock ? mockDebts : (props.dashboard.workspace.debts ?? []));
const investments = computed(() => wealthMock ? mockInvestments : (props.dashboard.workspace.investment_positions ?? []));
const transactions = computed(() => dashboardMock ? mockTransactions : props.dashboard.transactions);
const filteredTransactions = computed(() => movementFilter.value === 'all' ? transactions.value : transactions.value.filter((item) => item.type === movementFilter.value));
const balances = computed(() => dashboardMock ? mockBalances : props.dashboard.balances);
const budgetRows = computed(() => dashboardMock ? mockBudgetRows : (props.dashboard.plan.rows ?? []));
const recent = computed(() => showAllMovements.value ? filteredTransactions.value : filteredTransactions.value.slice(0, 5));
const topBudget = () => budgetRows.value.slice(0, 3);
const balanceLabel = (balance) => balance >= 0 ? 'a favor' : 'debe';
function goHome() { window.scrollTo({ top: 0, behavior: 'smooth' }); }
function goMovements() { window.document.querySelector('.mobile-home section:nth-of-type(3)')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
function goGroup() { window.document.getElementById('mobile-group-summary')?.scrollIntoView({ behavior: 'smooth', block: 'start' }); }
function closeAccountMenu(event) {
    if (accountMenuOpen.value && !accountMenuRef.value?.contains(event.target)) accountMenuOpen.value = false;
}
function closeAccountMenuWithEscape(event) {
    if (event.key === 'Escape') accountMenuOpen.value = false;
}
onMounted(() => {
    window.document.addEventListener('pointerdown', closeAccountMenu);
    window.document.addEventListener('keydown', closeAccountMenuWithEscape);
});
onBeforeUnmount(() => {
    window.document.removeEventListener('pointerdown', closeAccountMenu);
    window.document.removeEventListener('keydown', closeAccountMenuWithEscape);
});
</script>

<template>
  <div class="mobile-home space-y-4">
    <header class="flex items-center gap-2 px-1"><span class="grid h-9 w-9 shrink-0 place-items-center rounded-xl bg-[#123B4A] font-bold text-[#A9E5D1]">M</span><b class="text-lg text-[#172D38]">MisLucas</b></header>
    <section class="mobile-summary rounded-[20px] bg-[#123B4A] p-5 text-white shadow-sm">
      <div class="mb-5 flex items-start justify-between gap-3">
        <div class="min-w-0 flex-1"><Select :model-value="dashboard.workspace.id" :options="workspaces" option-label="name" option-value="id" class="mobile-workspace-select mobile-workspace-select-dark mobile-period-select w-full" @update:model-value="emit('workspace', $event)" /><p class="text-xs text-[#A9E5D1]">Resultado del mes</p><strong class="mt-1 block text-3xl tracking-tight">{{ money(dashboard.summary.result) }}</strong></div>
        <div class="mobile-period-control flex min-w-0 flex-1 items-center justify-between gap-1"><button class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-[#A9E5D1]" aria-label="Mes anterior" @click="emit('month', -1)"><i class="pi pi-chevron-left text-xs" /></button><button class="min-w-0 flex-1 truncate text-center text-[11px] text-[#C7DBDB]" aria-label="Mes actual">{{ monthLabel }}</button><button class="grid h-8 w-8 shrink-0 place-items-center rounded-lg text-[#A9E5D1]" aria-label="Mes siguiente" @click="emit('month', 1)"><i class="pi pi-chevron-right text-xs" /></button></div>
      </div>
      <div class="grid grid-cols-2 gap-3 border-t border-white/15 pt-4 text-sm"><div><span class="text-[#C7DBDB]">Ingresos</span><b class="mt-1 block text-[#A9E5D1]">{{ money(dashboard.summary.income) }}</b></div><div><span class="text-[#C7DBDB]">Gastos</span><b class="mt-1 block text-white">{{ money(dashboard.summary.expenses) }}</b></div></div>
    </section>

    <section class="rounded-[20px] bg-white p-4 shadow-sm">
      <div class="mb-3 flex items-center justify-between"><h2 class="font-bold text-[#172D38]">Añadir movimiento</h2><i class="pi pi-bolt text-[#2A7F87]" /></div>
      <div class="grid grid-cols-2 gap-2">
        <button class="mobile-quick-action mobile-quick-action-primary" @click="emit('movement', null, 'expense')"><i class="pi pi-plus" /><span>Gasto</span></button>
        <button class="mobile-quick-action mobile-quick-action-income" @click="emit('movement', null, 'income')"><i class="pi pi-arrow-down" /><span>Ingreso</span></button>
      </div>
    </section>

    <section class="rounded-[20px] bg-white p-4 shadow-sm">
      <div class="mb-3 flex items-center justify-between gap-2"><h2 class="font-bold text-[#172D38]">{{ showAllMovements ? 'Movimientos del mes' : 'Últimos movimientos' }}</h2><button class="min-h-11 shrink-0 text-xs font-bold text-[#2A7F87]" @click="showAllMovements = !showAllMovements">{{ showAllMovements ? 'Ver menos' : 'Ver todos' }} <i class="pi" :class="showAllMovements ? 'pi-angle-up' : 'pi-angle-down'" /></button></div>
      <div class="mb-3 grid grid-cols-3 gap-1 rounded-xl bg-[#F5F8F7] p-1" role="group" aria-label="Filtrar movimientos">
        <button v-for="filter in [{ value: 'all', label: 'Todos' }, { value: 'expense', label: 'Gastos' }, { value: 'income', label: 'Ingresos' }]" :key="filter.value" class="min-h-10 rounded-lg px-2 text-xs font-bold transition-colors" :class="movementFilter === filter.value ? 'bg-white text-[#123B4A] shadow-sm' : 'text-[#64748B]'" :aria-pressed="movementFilter === filter.value" @click="movementFilter = filter.value; showAllMovements = false">{{ filter.label }}</button>
      </div>
      <div v-if="recent.length" class="divide-y divide-slate-100">
        <button v-for="item in recent" :key="item.id" class="flex min-h-[4.25rem] w-full items-center gap-3 py-3 text-left" @click="emit('movement', item)">
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl" :class="item.type === 'income' ? 'bg-emerald-50 text-[#16805D]' : 'bg-[#FFE7ED] text-[#F1073B]'"><i :class="item.type === 'income' ? 'pi pi-arrow-down-left' : 'pi pi-shopping-cart'" /></span>
          <span class="min-w-0 flex-1"><b class="block truncate text-sm text-[#172D38]">{{ item.description }}</b><small class="block truncate text-xs text-[#64748B]">{{ item.category?.name ?? 'Sin categoría' }} · {{ item.occurred_at.slice(0, 10) }}<span v-if="item.payer"> · {{ item.payer.display_name }}</span></small></span>
          <span class="shrink-0 text-right"><b class="block text-sm" :class="item.type === 'income' ? 'text-[#16805D]' : 'text-[#D94F45]'">{{ item.type === 'income' ? '+' : '-' }}{{ money(item.amount) }}</b><i class="pi pi-angle-right text-xs text-slate-300" /></span>
        </button>
      </div>
      <p v-else class="py-8 text-center text-sm text-[#64748B]">Todavía no hay movimientos este mes.</p>
    </section>

    <div id="mobile-group-summary" class="grid scroll-mt-4 grid-cols-1 gap-3">
      <section class="mobile-data-card rounded-[20px] bg-white p-4 shadow-sm"><div class="mb-3 flex items-center justify-between"><h2 class="text-sm font-bold text-[#172D38]">Saldos</h2><i class="pi pi-users text-[#2A7F87]" /></div><div v-for="row in balances" :key="row.member.id" class="mb-2 last:mb-0"><div class="flex items-center justify-between gap-2"><span class="truncate text-xs text-[#172D38]">{{ row.member.display_name }}</span><b class="text-xs" :class="row.balance >= 0 ? 'text-[#16805D]' : 'text-[#D94F45]'">{{ money(Math.abs(row.balance)) }}</b></div><small class="text-[10px] text-[#64748B]">{{ balanceLabel(row.balance) }}</small></div><button class="mt-3 text-xs font-bold text-[#2A7F87]" @click="emit('group')">Ver grupo</button></section>
      <section class="rounded-[20px] bg-white p-4 shadow-sm"><div class="mb-3 flex items-center justify-between"><h2 class="text-sm font-bold text-[#172D38]">Presupuesto</h2><i class="pi pi-chart-pie text-[#2A7F87]" /></div><div v-if="topBudget().length"><div class="mb-2 grid min-w-0 grid-cols-[minmax(0,.72fr)_repeat(4,minmax(0,1fr))] gap-1 text-[8px] font-semibold uppercase text-[#64748B]"><span class="min-w-0 truncate">Categoría</span><span class="text-right">Ppto.</span><span class="text-right">Comp.</span><span class="text-right">Real</span><span class="text-right">Disp.</span></div><div v-for="row in topBudget()" :key="row.key" class="mb-3 grid min-w-0 grid-cols-[minmax(0,.72fr)_repeat(4,minmax(0,1fr))] items-center gap-1 border-t border-slate-100 pt-2 last:mb-0"><span class="min-w-0 truncate text-[9px] font-semibold text-[#172D38]">{{ row.name }}</span><span class="min-w-0 break-words text-right text-[8px] leading-tight">{{ money(row.budget) }}</span><span class="min-w-0 break-words text-right text-[8px] leading-tight">{{ money(row.committed ?? 0) }}</span><span class="min-w-0 break-words text-right text-[8px] leading-tight">{{ money(row.actual) }}</span><span class="min-w-0 break-words text-right text-[8px] font-semibold leading-tight" :class="(row.remaining ?? row.budget - row.actual) < 0 ? 'text-[#D94F45]' : 'text-[#16805D]'">{{ money(row.remaining ?? row.budget - row.actual) }}</span></div></div><p v-else class="py-3 text-xs leading-relaxed text-[#64748B]">Configura tu plan base mensual para empezar.</p><div class="mt-3 flex flex-wrap gap-3 text-xs font-bold"><button class="text-[#2A7F87]" @click="emit('plan')">Ver plan completo</button><button class="text-[#C58B28]" @click="emit('budget')">Excepción este mes</button></div></section>
    </div>

    <div class="grid grid-cols-2 gap-3">
      <section class="mobile-wealth-card rounded-[20px] bg-white p-4 shadow-sm"><div class="mb-3 flex items-center justify-between"><h2 class="text-sm font-bold text-[#172D38]">Deudas</h2><i class="pi pi-credit-card text-[#C58B28]" /></div><div v-if="debts.length" class="mobile-wealth-list"><div v-for="debt in debts" :key="debt.id" class="mb-3 last:mb-0"><div class="flex items-center justify-between gap-2"><span class="truncate text-xs font-semibold">{{ debt.name }}</span><b class="text-xs text-[#D94F45]">{{ money(debt.outstanding_balance) }}</b></div><small class="text-[10px] text-[#64748B]">Pendiente</small></div></div><p v-else class="text-xs text-[#64748B]">No hay deudas.</p><button class="mt-3 min-h-11 text-xs font-bold text-[#C58B28]" @click="emit('add-debt')"><i class="pi pi-plus mr-1" />Añadir deuda</button></section>
      <section class="mobile-wealth-card rounded-[20px] bg-white p-4 shadow-sm"><div class="mb-3 flex items-center justify-between"><h2 class="text-sm font-bold text-[#172D38]">Inversiones</h2><i class="pi pi-chart-line text-[#2A7F87]" /></div><div v-if="investments.length" class="mobile-wealth-list"><div v-for="item in investments" :key="item.id" class="mb-3 last:mb-0"><div class="flex items-center justify-between gap-2"><span class="truncate text-xs font-semibold">{{ item.name }}</span><b class="text-xs text-[#16805D]">{{ money(Math.round(Number(item.quantity) * item.current_price)) }}</b></div><small class="text-[10px] text-[#64748B]">{{ item.quantity }} unidades</small></div></div><p v-else class="text-xs text-[#64748B]">No hay inversiones.</p><button class="mt-3 min-h-11 text-xs font-bold text-[#2A7F87]" @click="emit('add-investment')"><i class="pi pi-plus mr-1" />Añadir inversión</button></section>
    </div>

    <nav class="mobile-bottom-nav fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-2 pt-2 shadow-[0_-4px_18px_rgba(18,59,74,.08)] backdrop-blur" aria-label="Navegación principal">
      <div class="mx-auto grid max-w-lg grid-cols-5 items-end gap-1">
        <button class="flex min-h-12 flex-col items-center justify-center gap-1 rounded-xl text-[10px] font-semibold text-[#2A7F87]" @click="goHome"><i class="pi pi-home text-base" />Inicio</button>
        <button class="flex min-h-12 flex-col items-center justify-center gap-1 rounded-xl text-[10px] font-semibold text-[#64748B]" @click="goMovements"><i class="pi pi-list text-base" />Movimientos</button>
        <button class="-mt-5 flex min-h-14 flex-col items-center justify-center gap-1 rounded-2xl bg-[#2A7F87] text-[10px] font-bold text-white shadow-lg shadow-[#2A7F87]/25" @click="emit('movement', null, 'expense')"><i class="pi pi-plus text-lg" />Añadir</button>
        <button class="flex min-h-12 flex-col items-center justify-center gap-1 rounded-xl text-[10px] font-semibold text-[#64748B]" @click="emit('plan')"><i class="pi pi-chart-pie text-base" />Plan</button>
        <div ref="accountMenuRef" class="relative"><button class="flex min-h-12 w-full flex-col items-center justify-center gap-1 rounded-xl text-[10px] font-semibold text-[#64748B]" @click="accountMenuOpen = !accountMenuOpen"><i class="pi pi-user text-base" />Cuenta</button><div v-if="accountMenuOpen" class="absolute bottom-14 right-0 z-50 w-56 rounded-2xl border border-slate-100 bg-white p-2 text-left shadow-xl"><p class="truncate px-3 py-2 text-xs text-[#64748B]">{{ user.name }}</p><button v-if="user.is_superadmin" class="flex min-h-11 w-full items-center gap-3 rounded-xl px-3 text-sm font-semibold text-[#172D38] hover:bg-slate-50" @click="emit('admin'); accountMenuOpen = false"><i class="pi pi-cog text-[#2A7F87]" />Administración</button><button v-if="canInvite" class="flex min-h-11 w-full items-center gap-3 rounded-xl px-3 text-sm font-semibold text-[#172D38] hover:bg-slate-50" @click="emit('invite'); accountMenuOpen = false"><i class="pi pi-user-plus text-[#2A7F87]" />Invitar</button><button class="flex min-h-11 w-full items-center gap-3 rounded-xl px-3 text-sm font-semibold text-[#172D38] hover:bg-slate-50" @click="emit('create-workspace'); accountMenuOpen = false"><i class="pi pi-plus text-[#2A7F87]" />Crear workspace</button><button class="mobile-menu-import flex min-h-11 w-full items-center gap-3 rounded-xl px-3 text-sm font-semibold hover:bg-slate-50" @click="emit('import-bank'); accountMenuOpen = false"><i class="pi pi-upload" />Importar archivo</button><button class="mobile-menu-bank flex min-h-11 w-full items-center gap-3 rounded-xl px-3 text-sm font-semibold hover:bg-slate-50" @click="emit('connect-bank'); accountMenuOpen = false"><i class="pi pi-link" />Vincular banco</button><button class="flex min-h-11 w-full items-center gap-3 rounded-xl px-3 text-sm font-semibold text-[#D94F45] hover:bg-rose-50" @click="emit('logout'); accountMenuOpen = false"><i class="pi pi-sign-out" />Cerrar sesión</button></div></div>
      </div>
    </nav>
  </div>
</template>
