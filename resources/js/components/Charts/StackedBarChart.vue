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
import { computed } from 'vue';
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
    xData: string[];
    series: {
        name: string;
        data: number[];
        color?: string;
    }[];
    loading?: boolean;
    height?: string;
    horizontal?: boolean;
    showLegend?: boolean;
}

const props = withDefaults(defineProps<Props>(), {
    loading: false,
    height: '400px',
    horizontal: false,
    showLegend: true,
});

const option = computed(() => ({
    tooltip: {
        trigger: 'axis',
        axisPointer: {
            type: 'shadow',
        },
        formatter: (params: any) => {
            let result = `<strong>${params[0].axisValue}</strong><br/>`;
            params.forEach((item: any) => {
                result += `${item.marker} ${item.seriesName}: ${item.value.toLocaleString()}<br/>`;
            });
            return result;
        },
    },
    legend: props.showLegend
        ? {
              data: props.series.map((s) => s.name),
              bottom: 0,
          }
        : undefined,
    grid: {
        left: '3%',
        right: '4%',
        bottom: props.showLegend ? '15%' : '3%',
        top: '3%',
        containLabel: true,
    },
    xAxis: {
        type: props.horizontal ? 'value' : 'category',
        data: props.horizontal ? undefined : props.xData,
        axisLabel: {
            rotate: props.horizontal ? 0 : 0,
            interval: 0,
            fontSize: 11,
        },
    },
    yAxis: {
        type: props.horizontal ? 'category' : 'value',
        data: props.horizontal ? props.xData : undefined,
    },
    series: props.series.map((s) => ({
        name: s.name,
        type: 'bar',
        stack: 'total',
        data: s.data,
        itemStyle: {
            color: s.color,
        },
        emphasis: {
            focus: 'series',
        },
    })),
}));
</script>

<template>
    <div :style="{ height }">
        <VChart
            v-if="!loading"
            :option="option"
            :autoresize="true"
            style="width: 100%; height: 100%"
        />
        <div
            v-else
            class="flex h-full items-center justify-center text-muted-foreground"
        >
            <div class="animate-pulse">Loading chart...</div>
        </div>
    </div>
</template>
