<script setup>
import { ref, onMounted, onUnmounted, computed } from 'vue';
import { RouterLink } from 'vue-router';
import api from '../lib/api';
import Stars from '../components/Stars.vue';

const props = defineProps({ id: { type: [String, Number], required: true } });
const base = `/organizations/${props.id}`;

const org = ref(null);
const reviews = ref([]);
const page = ref(1);
const lastPage = ref(1);
const total = ref(0);
const loading = ref(false);
const error = ref('');
const reparsing = ref(false);
let poll = null;

const parsing = computed(() => org.value?.parseStatus === 'pending');

function stopPoll() {
    if (poll) { clearInterval(poll); poll = null; }
}

// While the background parse runs, refresh the header and reload page 1 as
// reviews land.
function startPoll() {
    stopPoll();
    poll = setInterval(async () => {
        try {
            const { data } = await api.get(base);
            org.value = data.data;
            await loadPage(page.value);
            if (org.value?.parseStatus !== 'pending') stopPoll();
        } catch { stopPoll(); }
    }, 3000);
}

async function loadOrg() {
    try {
        const { data } = await api.get(base);
        org.value = data.data;
        if (org.value?.parseStatus === 'pending') startPoll();
    } catch { org.value = null; }
}

async function loadPage(p) {
    error.value = '';
    loading.value = true;
    try {
        const { data } = await api.get(`${base}/reviews`, { params: { page: p } });
        reviews.value = data.data;
        page.value = data.meta.current_page;
        lastPage.value = data.meta.last_page;
        total.value = data.meta.total;
    } catch (e) {
        error.value = 'Не удалось загрузить отзывы';
    } finally {
        loading.value = false;
    }
}

async function reparse() {
    reparsing.value = true;
    try {
        await api.post(`${base}/reparse`);
        await loadOrg();
        startPoll();
    } finally {
        reparsing.value = false;
    }
}

function fmtDate(iso) {
    if (!iso) return '';
    try { return new Date(iso).toLocaleDateString('ru-RU', { day: 'numeric', month: 'long', year: 'numeric' }); }
    catch { return iso; }
}

onMounted(async () => { await loadOrg(); await loadPage(1); });
onUnmounted(stopPoll);
</script>

<template>
    <div>
        <RouterLink to="/" class="mb-5 inline-flex items-center gap-1 text-sm text-[var(--text-muted)] transition-colors hover:text-[var(--text)]">← к дашборду</RouterLink>

        <div v-if="org" class="mb-6 flex items-center justify-between gap-4 rounded-xl border border-[var(--border)] bg-[var(--surface-raised)] p-5">
            <div class="min-w-0">
                <div class="flex items-center gap-2">
                    <Stars :value="Math.round(org.averageRating || 0)" />
                    <h1 class="truncate text-lg font-semibold">{{ org.name || 'Организация' }}</h1>
                </div>
                <div class="mt-1.5 flex flex-wrap gap-4 text-sm text-[var(--text-muted)]">
                    <span class="tnum">{{ (org.ratingsCount ?? 0).toLocaleString('ru-RU') }} оценок</span>
                    <span class="tnum">{{ (org.reviewsCount ?? 0).toLocaleString('ru-RU') }} отзывов</span>
                    <span class="tnum">в базе {{ org.reviewsStored ?? 0 }}</span>
                </div>
            </div>
            <div class="shrink-0 text-right">
                <div class="text-3xl font-bold tnum">{{ org.averageRating ?? '—' }}</div>
                <button class="mt-1 text-xs text-[var(--text-muted)] underline underline-offset-2 transition-opacity hover:text-[var(--accent-ink)] disabled:opacity-50"
                    :disabled="reparsing" @click="reparse">
                    {{ reparsing ? 'обновляем…' : 'обновить' }}
                </button>
            </div>
        </div>

        <p v-if="parsing" class="mb-4 flex items-center gap-2 rounded-lg border border-[oklch(0.74_0.15_65_/_0.35)] bg-[oklch(0.74_0.15_65_/_0.08)] px-4 py-3 text-sm text-[var(--accent-ink)]">
            <svg viewBox="0 0 24 24" class="h-4 w-4 pulse-star" aria-hidden="true"><path d="M12 3c.4 3.6 2.4 5.6 6 6-3.6.4-5.6 2.4-6 6-.4-3.6-2.4-5.6-6-6 3.6-.4 5.6-2.4 6-6Z" fill="var(--accent)" /></svg>
            Идёт сбор отзывов… список обновляется автоматически.
        </p>
        <p v-else-if="org && org.parseStatus && org.parseStatus !== 'ok'" class="mb-4 rounded-lg border border-[oklch(0.56_0.18_25_/_0.3)] bg-[oklch(0.56_0.18_25_/_0.07)] px-4 py-3 text-sm text-[var(--danger)]">
            {{ org.parseStatusLabel || org.parseStatus }}
        </p>

        <div v-if="loading" class="py-12 text-center text-[var(--text-muted)]">Загрузка…</div>
        <p v-else-if="error" class="py-12 text-center text-[var(--danger)]">{{ error }}</p>
        <p v-else-if="!reviews.length" class="py-12 text-center text-[var(--text-muted)]">Отзывов пока нет</p>

        <ul v-else class="space-y-3">
            <li v-for="(r, i) in reviews" :key="r.id" class="rise rounded-xl border border-[var(--border)] bg-[var(--surface-raised)] p-4" :style="{ animationDelay: Math.min(i, 8) * 30 + 'ms' }">
                <div class="flex items-center justify-between gap-3">
                    <span class="truncate font-medium">{{ r.author }}</span>
                    <Stars :value="r.rating || 0" />
                </div>
                <div class="mt-0.5 text-xs text-[var(--text-muted)]">{{ fmtDate(r.date) }}</div>
                <p v-if="r.text" class="mt-2 max-w-[70ch] whitespace-pre-line text-[0.95rem] leading-relaxed text-[var(--text)]">{{ r.text }}</p>
            </li>
        </ul>

        <div v-if="lastPage > 1" class="mt-7 flex items-center justify-center gap-3 text-sm">
            <button class="rounded-lg border border-[var(--border-strong)] px-3.5 py-1.5 transition-colors hover:bg-[oklch(0.92_0.006_70_/_0.5)] disabled:opacity-40"
                :disabled="page <= 1 || loading" @click="loadPage(page - 1)">← Назад</button>
            <span class="tnum text-[var(--text-muted)]">{{ page }} / {{ lastPage }}</span>
            <button class="rounded-lg border border-[var(--border-strong)] px-3.5 py-1.5 transition-colors hover:bg-[oklch(0.92_0.006_70_/_0.5)] disabled:opacity-40"
                :disabled="page >= lastPage || loading" @click="loadPage(page + 1)">Вперёд →</button>
        </div>
    </div>
</template>
