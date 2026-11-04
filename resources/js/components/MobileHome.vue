<script setup>
import Button from 'primevue/button';
import Tag from 'primevue/tag';

const props = defineProps({ dashboard: { type: Object, required: true }, money: { type: Function, required: true }, monthLabel: { type: String, required: true } });
const emit = defineEmits(['movement', 'budget', 'plan', 'group', 'bank']);
const recent = () => props.dashboard.transactions.slice(0, 5);
const topBudget = () => props.dashboard.plan.rows?.slice(0, 3) ?? [];
const balanceLabel = (balance) => balance >= 0 ? 'a favor' : 'debe';
</script>

<template>
  <div class="mobile-home space-y-4">
    <section class="mobile-summary rounded-[20px] bg-[#123B4A] p-5 text-white shadow-sm">
      <div class="mb-5 flex items-start justify-between gap-3">
        <div><p class="text-xs text-[#A9E5D1]">Resultado del mes</p><strong class="mt-1 block text-3xl tracking-tight">{{ money(dashboard.summary.result) }}</strong></div>
        <div class="rounded-xl bg-white/10 px-3 py-2 text-right"><p class="text-[11px] text-[#C7DBDB]">{{ monthLabel }}</p><i class="pi pi-calendar text-[#A9E5D1]" /></div>
      </div>
      <div class="grid grid-cols-2 gap-3 border-t border-white/15 pt-4 text-sm"><div><span class="text-[#C7DBDB]">Ingresos</span><b class="mt-1 block text-[#A9E5D1]">{{ money(dashboard.summary.income) }}</b></div><div><span class="text-[#C7DBDB]">Gastos</span><b class="mt-1 block text-white">{{ money(dashboard.summary.expenses) }}</b></div></div>
    </section>

    <section class="rounded-[20px] bg-white p-4 shadow-sm">
      <div class="mb-3 flex items-center justify-between"><h2 class="font-bold text-[#172D38]">Añadir movimiento</h2><i class="pi pi-bolt text-[#2A7F87]" /></div>
      <div class="grid grid-cols-3 gap-2">
        <button class="mobile-quick-action mobile-quick-action-primary" @click="emit('movement', null, 'expense')"><i class="pi pi-plus" /><span>Gasto</span></button>
        <button class="mobile-quick-action mobile-quick-action-income" @click="emit('movement', null, 'income')"><i class="pi pi-arrow-down" /><span>Ingreso</span></button>
        <button class="mobile-quick-action mobile-quick-action-transfer" disabled title="Las transferencias no están disponibles todavía"><i class="pi pi-arrow-right-arrow-left" /><span>Transferencia</span></button>
      </div>
    </section>

    <section class="rounded-[20px] bg-white p-4 shadow-sm">
      <div class="mb-2 flex items-center justify-between"><h2 class="font-bold text-[#172D38]">Últimos movimientos</h2><button class="text-xs font-bold text-[#2A7F87]" @click="emit('movement')">Ver todos <i class="pi pi-angle-right" /></button></div>
      <div v-if="recent().length" class="divide-y divide-slate-100">
        <button v-for="item in recent()" :key="item.id" class="flex min-h-[4.25rem] w-full items-center gap-3 py-3 text-left" @click="emit('movement', item)">
          <span class="grid h-10 w-10 shrink-0 place-items-center rounded-xl" :class="item.type === 'income' ? 'bg-emerald-50 text-[#16805D]' : 'bg-rose-50 text-[#D94F45]'"><i :class="item.type === 'income' ? 'pi pi-arrow-down-left' : 'pi pi-shopping-cart'" /></span>
          <span class="min-w-0 flex-1"><b class="block truncate text-sm text-[#172D38]">{{ item.description }}</b><small class="block truncate text-xs text-[#64748B]">{{ item.category?.name ?? 'Sin categoría' }} · {{ item.occurred_at.slice(0, 10) }}<span v-if="item.payer"> · {{ item.payer.display_name }}</span></small></span>
          <span class="shrink-0 text-right"><b class="block text-sm" :class="item.type === 'income' ? 'text-[#16805D]' : 'text-[#D94F45]'">{{ item.type === 'income' ? '+' : '-' }}{{ money(item.amount) }}</b><i class="pi pi-angle-right text-xs text-slate-300" /></span>
        </button>
      </div>
      <p v-else class="py-8 text-center text-sm text-[#64748B]">Todavía no hay movimientos este mes.</p>
    </section>

    <div class="grid grid-cols-2 gap-3">
      <section class="rounded-[20px] bg-white p-4 shadow-sm"><div class="mb-3 flex items-center justify-between"><h2 class="text-sm font-bold text-[#172D38]">Saldos</h2><i class="pi pi-users text-[#2A7F87]" /></div><div v-for="row in dashboard.balances.slice(0, 3)" :key="row.member.id" class="mb-2 last:mb-0"><div class="flex items-center justify-between gap-2"><span class="truncate text-xs text-[#172D38]">{{ row.member.display_name }}</span><b class="text-xs" :class="row.balance >= 0 ? 'text-[#16805D]' : 'text-[#D94F45]'">{{ money(Math.abs(row.balance)) }}</b></div><small class="text-[10px] text-[#64748B]">{{ balanceLabel(row.balance) }}</small></div><button class="mt-3 text-xs font-bold text-[#2A7F87]" @click="emit('group')">Ver grupo</button></section>
      <section class="rounded-[20px] bg-white p-4 shadow-sm"><div class="mb-3 flex items-center justify-between"><h2 class="text-sm font-bold text-[#172D38]">Presupuesto</h2><i class="pi pi-chart-pie text-[#2A7F87]" /></div><div v-if="topBudget().length"><div v-for="row in topBudget()" :key="row.key" class="mb-3 last:mb-0"><div class="mb-1 flex justify-between gap-1 text-[10px]"><span class="truncate">{{ row.name }}</span><b>{{ money(row.actual) }}</b></div><div class="h-1.5 overflow-hidden rounded-full bg-[#E7F0ED]"><div class="h-full rounded-full bg-[#2A7F87]" :style="{ width: `${Math.min(100, row.budget ? row.actual / row.budget * 100 : 0)}%` }" /></div></div></div><p v-else class="text-xs text-[#64748B]">Configura tu plan mensual.</p><button class="mt-3 text-xs font-bold text-[#2A7F87]" @click="emit('plan')">Ver plan</button></section>
    </div>

    <nav class="mobile-bottom-nav fixed inset-x-0 bottom-0 z-40 border-t border-slate-200 bg-white/95 px-2 pt-2 shadow-[0_-4px_18px_rgba(18,59,74,.08)] backdrop-blur" aria-label="Navegación principal">
      <div class="mx-auto grid max-w-lg grid-cols-5 items-end gap-1">
        <button class="flex min-h-12 flex-col items-center justify-center gap-1 rounded-xl text-[10px] font-semibold text-[#2A7F87]" @click="window.scrollTo({ top: 0, behavior: 'smooth' })"><i class="pi pi-home text-base" />Inicio</button>
        <button class="flex min-h-12 flex-col items-center justify-center gap-1 rounded-xl text-[10px] font-semibold text-[#64748B]" @click="document.querySelector('.mobile-home section:nth-of-type(3)')?.scrollIntoView({ behavior: 'smooth', block: 'start' })"><i class="pi pi-list text-base" />Movimientos</button>
        <button class="-mt-5 flex min-h-14 flex-col items-center justify-center gap-1 rounded-2xl bg-[#2A7F87] text-[10px] font-bold text-white shadow-lg shadow-[#2A7F87]/25" @click="emit('movement', null, 'expense')"><i class="pi pi-plus text-lg" />Añadir</button>
        <button class="flex min-h-12 flex-col items-center justify-center gap-1 rounded-xl text-[10px] font-semibold text-[#64748B]" @click="emit('plan')"><i class="pi pi-chart-pie text-base" />Plan</button>
        <button class="flex min-h-12 flex-col items-center justify-center gap-1 rounded-xl text-[10px] font-semibold text-[#64748B]" @click="emit('group')"><i class="pi pi-users text-base" />Grupo</button>
      </div>
    </nav>
  </div>
</template>
