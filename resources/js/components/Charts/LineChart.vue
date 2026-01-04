<script setup lang="ts">
import { LineChart } from 'echarts/charts';
import {
    DataZoomComponent,
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
    LineChart,
    TitleComponent,
    TooltipComponent,
    LegendComponent,
    GridComponent,
    DataZoomComponent,
]);

interface Props {
    title?: string;
    xData: string[];
    series: Array<{
        name: string;
        data: number[];
        color?: string;
        smooth?: boolean;
    }>;
    height?: string;
    loading?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    title: '',
    height: '400px',
    loading: false,
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
            type: 'cross',
            label: {
                backgroundColor: '#6a7985',
            },
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
    xAxis: {
        type: 'category',
        boundaryGap: false,
        data: props.xData,
    },
    yAxis: {
        type: 'value',
    },
    series: props.series.map((s, index) => ({
        name: s.name,
        type: 'line',
        smooth: s.smooth ?? true,
        data: s.data,
        itemStyle: {
            color: s.color || undefined,
        },
        areaStyle: {
            opacity: 0.1,
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
