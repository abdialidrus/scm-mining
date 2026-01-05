<script setup lang="ts">
import { PieChart } from 'echarts/charts';
import {
    LegendComponent,
    TitleComponent,
    TooltipComponent,
} from 'echarts/components';
import { use } from 'echarts/core';
import { CanvasRenderer } from 'echarts/renderers';
import { computed, onMounted, ref, watch } from 'vue';
import VChart from 'vue-echarts';

use([
    CanvasRenderer,
    PieChart,
    TitleComponent,
    TooltipComponent,
    LegendComponent,
]);

interface Props {
    title?: string;
    data: Array<{
        name: string;
        value: number;
    }>;
    height?: string;
    loading?: boolean;
    donut?: boolean;
    radius?: string | string[];
}

const props = withDefaults(defineProps<Props>(), {
    title: '',
    height: '400px',
    loading: false,
    donut: false,
    radius: '70%',
});

const chartRef = ref();

const option = computed(() => ({
    title: {
        text: props.title,
        left: 'center',
        textStyle: {
            fontSize: 16,
            fontWeight: 'bold',
        },
    },
    tooltip: {
        trigger: 'item',
        formatter: '{a} <br/>{b}: {c} ({d}%)',
    },
    legend: {
        orient: 'vertical',
        right: '10%',
        top: 'center',
    },
    series: [
        {
            name: props.title || 'Data',
            type: 'pie',
            radius: props.donut ? ['40%', '70%'] : props.radius,
            center: ['40%', '50%'],
            data: props.data,
            emphasis: {
                itemStyle: {
                    shadowBlur: 10,
                    shadowOffsetX: 0,
                    shadowColor: 'rgba(0, 0, 0, 0.5)',
                },
            },
            label: {
                show: true,
                formatter: '{b}: {d}%',
            },
        },
    ],
}));

watch(
    () => props.loading,
    (loading) => {
        if (
            chartRef.value &&
            typeof chartRef.value.showLoading === 'function'
        ) {
            if (loading) {
                chartRef.value.showLoading();
            } else if (typeof chartRef.value.hideLoading === 'function') {
                chartRef.value.hideLoading();
            }
        }
    },
);

onMounted(() => {
    if (
        props.loading &&
        chartRef.value &&
        typeof chartRef.value.showLoading === 'function'
    ) {
        chartRef.value.showLoading();
    }
});
</script>

<template>
    <VChart ref="chartRef" :option="option" :style="{ height }" autoresize />
</template>
