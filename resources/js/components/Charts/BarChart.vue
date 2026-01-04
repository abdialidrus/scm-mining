<script setup lang="ts">
import { BarChart } from 'echarts/charts';
import {
    GridComponent,
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
    BarChart,
    TitleComponent,
    TooltipComponent,
    LegendComponent,
    GridComponent,
]);

interface Props {
    title?: string;
    xData: string[];
    series: Array<{
        name: string;
        data: number[];
        color?: string;
        stack?: string;
    }>;
    height?: string;
    loading?: boolean;
    horizontal?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    title: '',
    height: '400px',
    loading: false,
    horizontal: false,
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
        trigger: 'axis',
        axisPointer: {
            type: 'shadow',
        },
    },
    legend: {
        data: props.series.map((s) => s.name),
        top: props.title ? '30px' : '10px',
    },
    grid: {
        left: '3%',
        right: '4%',
        bottom: '3%',
        containLabel: true,
    },
    xAxis: props.horizontal
        ? { type: 'value' }
        : {
              type: 'category',
              data: props.xData,
          },
    yAxis: props.horizontal
        ? {
              type: 'category',
              data: props.xData,
          }
        : { type: 'value' },
    series: props.series.map((s) => ({
        name: s.name,
        type: 'bar',
        data: s.data,
        stack: s.stack,
        itemStyle: {
            color: s.color || undefined,
        },
    })),
}));

watch(
    () => props.loading,
    (loading) => {
        if (chartRef.value) {
            if (loading) {
                chartRef.value.showLoading();
            } else {
                chartRef.value.hideLoading();
            }
        }
    },
);

onMounted(() => {
    if (props.loading && chartRef.value) {
        chartRef.value.showLoading();
    }
});
</script>

<template>
    <VChart ref="chartRef" :option="option" :style="{ height }" autoresize />
</template>
