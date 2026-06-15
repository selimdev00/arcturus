<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useRouter } from 'vue-router';
import api from '../lib/api';

const router = useRouter();
const orgs = ref([]);
const loading = ref(true);
let poll = null;

function stopPoll() { if (poll) { clearInterval(poll); poll = null; } }

async function load() {
    try {
        const { data } = await api.get('/organizations');
        orgs.value = data.data;
        // keep refreshing while any org is still parsing
        if (orgs.value.some((o) => o.parseStatus === 'pending')) {
            if (!poll) poll = setInterval(load, 3000);
        } else {
            stopPoll();
        }
    } catch { orgs.value = []; }
    finally { loading.value = false; }
}

function open(o) {
    router.push({ name: 'reviews', params: { id: o.id } });
}

function fmtDate(iso) {
    if (!iso) return '';
    try { return new Date(iso).toLocaleString('ru-RU', { day: 'numeric', month: 'short', hour: '2-digit', minute: '2-digit' }); }
    catch { return ''; }
}

onMounted(load);
onUnmounted(stopPoll);
</script>

<template>
    <div>
        <div class="mb-6 flex items-center justify-between">
            <h1 class="text-xl font-semibold">Организации</h1>
            <RouterLink to="/" class="text-sm font-medium text-slate-900 underline">+ Добавить</RouterLink>
        </div>

        <p v-if="loading" class="py-12 text-center text-slate-400">Загрузка…</p>
        <p v-else-if="!orgs.length" class="py-12 text-center text-slate-400">
            Пока ничего не добавлено. <RouterLink to="/" class="underline">Добавить организацию →</RouterLink>
        </p>

        <ul v-else class="space-y-3">
            <li v-for="o in orgs" :key="o.id">
                <button
                    class="flex w-full items-center justify-between rounded-xl border border-slate-200 bg-white p-5 text-left transition hover:border-slate-300 hover:shadow-sm"
                    @click="open(o)">
                    <div>
                        <h2 class="font-medium">{{ o.name || 'Организация' }}</h2>
                        <div class="mt-1 flex flex-wrap gap-x-4 gap-y-1 text-sm text-slate-500">
                            <span>{{ o.ratingsCount ?? '—' }} оценок</span>
                            <span>{{ o.reviewsCount ?? '—' }} отзывов</span>
                            <span>в базе: {{ o.reviewsStored ?? 0 }}</span>
                            <span v-if="o.parsedAt" class="text-slate-400">обновлено {{ fmtDate(o.parsedAt) }}</span>
                        </div>
                        <p v-if="o.parseStatus === 'pending'" class="mt-1 text-xs text-amber-600">идёт загрузка отзывов…</p>
                        <p v-else-if="o.parseStatus !== 'ok'" class="mt-1 text-xs text-red-600">{{ o.parseStatusLabel }}</p>
                    </div>
                    <div class="pl-4 text-right">
                        <div class="text-2xl font-semibold">{{ o.averageRating ?? '—' }}</div>
                        <div class="text-xs text-slate-400">открыть →</div>
                    </div>
                </button>
            </li>
        </ul>
    </div>
</template>
