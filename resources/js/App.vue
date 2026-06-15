<script setup>
import { computed } from 'vue';
import { RouterView, RouterLink, useRouter } from 'vue-router';
import { auth, logout } from './lib/auth';
import Logo from './components/Logo.vue';

const router = useRouter();

const initial = computed(() => (auth.user?.email || '?').trim().charAt(0).toUpperCase());

async function onLogout() {
    await logout();
    router.push({ name: 'login' });
}
</script>

<template>
    <div class="flex min-h-full flex-col">
        <header v-if="auth.user" class="sticky top-0 z-10 border-b border-[var(--border)] bg-[oklch(0.985_0.004_70_/_0.8)] backdrop-blur">
            <div class="mx-auto flex max-w-3xl items-center justify-between px-5 py-3">
                <RouterLink to="/" aria-label="Arcturus, на главную">
                    <Logo />
                </RouterLink>
                <div class="flex items-center gap-3">
                    <div class="hidden items-center gap-2 sm:flex">
                        <span class="grid h-7 w-7 place-items-center rounded-full bg-[var(--ink)] text-xs font-semibold text-white">{{ initial }}</span>
                        <span class="text-sm text-[var(--text-muted)]">{{ auth.user.email }}</span>
                    </div>
                    <button
                        class="rounded-lg px-2.5 py-1 text-sm text-[var(--text-muted)] transition-colors hover:bg-[oklch(0.92_0.006_70_/_0.6)] hover:text-[var(--text)]"
                        @click="onLogout">Выйти</button>
                </div>
            </div>
        </header>

        <main class="mx-auto w-full max-w-3xl flex-1 px-5 py-10">
            <RouterView />
        </main>

        <footer v-if="auth.user" class="mt-12 border-t border-[var(--border)]">
            <div class="mx-auto flex max-w-3xl flex-col gap-3 px-5 py-7 text-sm sm:flex-row sm:items-center sm:justify-between">
                <div class="flex items-center gap-2 text-[var(--text-muted)]">
                    <svg viewBox="0 0 24 24" class="h-4 w-4 pulse-star star-glow" aria-hidden="true">
                        <path d="M12 3c.4 3.6 2.4 5.6 6 6-3.6.4-5.6 2.4-6 6-.4-3.6-2.4-5.6-6-6 3.6-.4 5.6-2.4 6-6Z" fill="var(--star)" />
                    </svg>
                    <span><span class="font-semibold text-[var(--ink)]">Arcturus</span> · парсер отзывов Яндекс.Карт</span>
                </div>
                <div class="flex items-center gap-4 text-[var(--text-muted)]">
                    <span>Сделал
                        <a href="https://selim.services" target="_blank" rel="noopener"
                           class="font-medium text-[var(--accent-ink)] underline-offset-2 hover:underline">Селим Атабаллыев</a>
                    </span>
                    <a href="https://github.com/selimdev00/arcturus" target="_blank" rel="noopener"
                       class="hover:text-[var(--text)]">GitHub</a>
                </div>
            </div>
        </footer>
    </div>
</template>
