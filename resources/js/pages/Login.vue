<script setup>
import { ref } from 'vue';
import { useRouter } from 'vue-router';
import { login } from '../lib/auth';
import Logo from '../components/Logo.vue';

const router = useRouter();
const email = ref('');
const password = ref('');
const loading = ref(false);
const error = ref('');

async function onSubmit() {
    error.value = '';
    loading.value = true;
    try {
        await login(email.value, password.value);
        router.push({ name: 'dashboard' });
    } catch (e) {
        error.value = e?.response?.status === 422 || e?.response?.status === 401
            ? 'Неверный логин или пароль'
            : 'Не удалось войти, попробуйте ещё раз';
    } finally {
        loading.value = false;
    }
}
</script>

<template>
    <div class="mx-auto mt-16 max-w-sm">
        <div class="mb-8 flex justify-center">
            <Logo />
        </div>
        <div class="rounded-2xl border border-[var(--border)] bg-[var(--surface-raised)] p-7 shadow-[0_1px_20px_oklch(0.74_0.15_65_/_0.06)]">
            <h1 class="mb-6 text-lg font-semibold">Вход</h1>
            <form class="space-y-4" @submit.prevent="onSubmit">
                <div>
                    <label class="mb-1.5 block text-sm text-[var(--text-muted)]">Email</label>
                    <input v-model="email" type="email" required autocomplete="username"
                        class="w-full rounded-lg border border-[var(--border-strong)] bg-[var(--surface)] px-3 py-2 text-sm outline-none transition-colors focus:border-[var(--accent)]" />
                </div>
                <div>
                    <label class="mb-1.5 block text-sm text-[var(--text-muted)]">Пароль</label>
                    <input v-model="password" type="password" required autocomplete="current-password"
                        class="w-full rounded-lg border border-[var(--border-strong)] bg-[var(--surface)] px-3 py-2 text-sm outline-none transition-colors focus:border-[var(--accent)]" />
                </div>
                <p v-if="error" class="text-sm text-[var(--danger)]">{{ error }}</p>
                <button type="submit" :disabled="loading"
                    class="w-full rounded-lg bg-[var(--ink)] py-2.5 text-sm font-medium text-white transition-opacity disabled:opacity-50">
                    {{ loading ? 'Входим…' : 'Войти' }}
                </button>
            </form>
        </div>
    </div>
</template>
