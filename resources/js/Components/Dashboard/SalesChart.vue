<script setup lang="ts">
import { BarElement, CategoryScale, Chart as ChartJS, LinearScale, Tooltip, type ChartData, type ChartOptions } from 'chart.js';
import { getActiveLanguage } from 'laravel-vue-i18n';
import { computed } from 'vue';
import { Bar } from 'vue-chartjs';
import { formatRupiah } from '@/lib/money';

ChartJS.register(BarElement, CategoryScale, LinearScale, Tooltip);

/**
 * Sales (without tax) per month for the last 12 months. The current month is highlighted.
 */
const props = defineProps<{ months: { month: string; sales: number }[]; label: string }>();

// The theme's primary colour, so the chart matches the rest of the app.
const primary = computed(() => {
    const value = typeof window === 'undefined' ? '' : getComputedStyle(document.documentElement).getPropertyValue('--primary').trim();

    return value ? `hsl(${value})` : '#2563eb';
});

const monthName = (key: string): string => {
    const [year, month] = key.split('-').map(Number);

    return new Intl.DateTimeFormat(getActiveLanguage() === 'en' ? 'en-GB' : 'id-ID', { month: 'short' }).format(new Date(year ?? 2026, (month ?? 1) - 1, 1));
};

const data = computed<ChartData<'bar'>>(() => ({
    labels: props.months.map((row) => monthName(row.month)),
    datasets: [
        {
            label: props.label,
            data: props.months.map((row) => row.sales),
            backgroundColor: props.months.map((_, index) => (index === props.months.length - 1 ? primary.value : `${primary.value.replace(')', ' / 0.35)')}`)),
            borderRadius: 4,
        },
    ],
}));

const options: ChartOptions<'bar'> = {
    responsive: true,
    maintainAspectRatio: false,
    plugins: { tooltip: { callbacks: { label: (context) => formatRupiah(Number(context.raw)) } } },
    scales: {
        x: { grid: { display: false } },
        y: { ticks: { callback: (value) => (Number(value) >= 1_000_000 ? `${Number(value) / 1_000_000} jt` : Number(value) >= 1000 ? `${Number(value) / 1000} rb` : String(value)) } },
    },
};
</script>

<template>
    <div class="h-56" role="img" :aria-label="label">
        <Bar :data="data" :options="options" />
    </div>
</template>
