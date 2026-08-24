import { defineStore } from 'pinia';
import axios from 'axios';
export const useFinanceStore = defineStore('finance', {
    state: () => ({ user: null, workspaces: [], current: null, loading: true, planMonth: new Date().toLocaleDateString('en-CA').slice(0, 7) }),
    actions: {
        async bootstrap() {
            this.loading = true;
            try {
                this.applySession((await axios.get('/api/user')).data);
                await this.loadWorkspaces();
            } catch (error) {
                if (error.response?.status !== 401) throw error;
                this.user = null;
            } finally {
                this.loading = false;
            }
        },
        async login(credentials) {
            this.applySession((await axios.post('/api/login', credentials)).data);
            await this.loadWorkspaces();
        },
        applySession(session) {
            this.user = session.user;
            axios.defaults.headers.common['X-CSRF-TOKEN'] = session.csrf_token;
        },
        async logout() {
            const session = (await axios.post('/api/logout')).data;
            axios.defaults.headers.common['X-CSRF-TOKEN'] = session.csrf_token;
            this.user = null;
            this.workspaces = [];
            this.current = null;
        },
        async loadWorkspaces() {
            this.workspaces = (await axios.get('/api/workspaces')).data;
            this.current = null;
            if (this.workspaces[0]) await this.select(this.workspaces[0].id);
        },
        async select(id) {
            this.current = (await axios.get(`/api/workspaces/${id}`, { params: { month: this.planMonth } })).data;
        },
        async addMovement(movement) {
            await axios.post(`/api/workspaces/${this.current.workspace.id}/transactions`, movement);
            await this.select(this.current.workspace.id);
        },
        async updateMovement(id, movement) {
            await axios.put(`/api/workspaces/${this.current.workspace.id}/transactions/${id}`, movement);
            await this.select(this.current.workspace.id);
        },
        async deleteMovement(id) {
            await axios.delete(`/api/workspaces/${this.current.workspace.id}/transactions/${id}`);
            await this.select(this.current.workspace.id);
        },
        async changePlanMonth(month) {
            this.planMonth = month;
            await this.select(this.current.workspace.id);
        },
        async saveBudget(budget) {
            await axios.post(`/api/workspaces/${this.current.workspace.id}/budgets`, budget);
            this.planMonth = budget.month.slice(0, 7);
            await this.select(this.current.workspace.id);
        },
        async addCategory(category) {
            const created = (await axios.post(`/api/workspaces/${this.current.workspace.id}/categories`, category)).data;
            await this.select(this.current.workspace.id);
            return created;
        },
        async saveMonthlyBudgetRules(rules) {
            await axios.put(`/api/workspaces/${this.current.workspace.id}/monthly-budget-rules`, { rules });
            await this.select(this.current.workspace.id);
        },
        async addDebt(data){await axios.post(`/api/workspaces/${this.current.workspace.id}/debts`,data);await this.select(this.current.workspace.id);},
        async addInvestment(data){await axios.post(`/api/workspaces/${this.current.workspace.id}/investments`,data);await this.select(this.current.workspace.id);},
        async payDebt(id,data){await axios.post(`/api/workspaces/${this.current.workspace.id}/debts/${id}/payments`,data);await this.select(this.current.workspace.id);},
        async increaseDebt(id,data){await axios.post(`/api/workspaces/${this.current.workspace.id}/debts/${id}/increases`,data);await this.select(this.current.workspace.id);},
        async deleteDebtIncrease(debtId,increaseId){await axios.delete(`/api/workspaces/${this.current.workspace.id}/debts/${debtId}/increases/${increaseId}`);await this.select(this.current.workspace.id);},
    },
});
