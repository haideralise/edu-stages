import * as echarts from 'echarts';

const container = document.getElementById('chart2-container');
if (!container) throw new Error('#chart2-container not found');

let userId = container.dataset.userId;
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.content;

let chart = echarts.init(container);
let currentType = 'bmi';

// Student selector (admin only)
const selector = document.getElementById('student-selector');
if (selector) {
    selector.addEventListener('change', function () {
        userId = this.value;
        loadChart(currentType);
    });
}

// Type switcher buttons
document.querySelectorAll('[data-chart-type]').forEach(btn => {
    btn.addEventListener('click', () => {
        currentType = btn.dataset.chartType;

        document.querySelectorAll('[data-chart-type]').forEach(b =>
            b.classList.remove('bg-blue-500', 'text-white')
        );
        document.querySelectorAll('[data-chart-type]').forEach(b =>
            b.classList.add('bg-gray-200', 'text-gray-700')
        );
        btn.classList.remove('bg-gray-200', 'text-gray-700');
        btn.classList.add('bg-blue-500', 'text-white');

        loadChart(currentType);
    });
});

async function loadChart(type) {
    chart.showLoading();

    try {
        const isResult = type === 'result';
        const url = isResult
            ? `/api/chart2/result/${userId}`
            : `/api/chart2/bmi/${userId}?type=${type}`;

        const res = await fetch(url, {
            headers: {
                'Accept': 'application/json',
                'X-CSRF-TOKEN': csrfToken,
            },
            credentials: 'same-origin',
        });

        if (!res.ok) throw new Error(`HTTP ${res.status}`);

        const json = await res.json();
        const { datasets, labels, series } = json.data;

        const option = isResult
            ? buildResultOption(datasets, labels)
            : buildBmiOption(labels, series);

        chart.hideLoading();
        chart.setOption(option, true);
    } catch (err) {
        chart.hideLoading();
        console.error('Chart2 load error:', err);
    }
}

function buildBmiOption(labels, series) {
    return {
        tooltip: { trigger: 'axis' },
        legend: { data: buildLegend(series) },
        xAxis: {
            type: 'value',
            name: labels.x,
            nameLocation: 'center',
            nameGap: 30,
        },
        yAxis: {
            type: 'value',
            name: labels.y,
            nameLocation: 'center',
            nameGap: 45,
        },
        series: buildBmiSeries(series),
    };
}

function buildResultOption(datasets, labels) {
    const dates = datasets.map(d => d[0]);
    const scores = datasets.map(d => d[1]);

    return {
        tooltip: { trigger: 'axis' },
        xAxis: {
            type: 'category',
            data: dates,
            name: labels.x,
            nameLocation: 'center',
            nameGap: 30,
            axisLabel: { rotate: 30 },
        },
        yAxis: {
            type: 'value',
            name: labels.y,
            nameLocation: 'center',
            nameGap: 45,
        },
        series: [{
            name: 'Score',
            type: 'line',
            data: scores,
            symbolSize: 8,
            itemStyle: { color: '#3b82f6' },
            lineStyle: { width: 2 },
        }],
    };
}

function buildLegend(series) {
    return Object.keys(series).map(key => {
        if (key === 'student') return 'Student';
        return key.toUpperCase();
    });
}

function buildBmiSeries(series) {
    const colors = {
        student: '#3b82f6',
        p5: '#94a3b8',
        p85: '#f59e0b',
        p95: '#ef4444',
    };

    return Object.entries(series).map(([key, data]) => ({
        name: key === 'student' ? 'Student' : key.toUpperCase(),
        type: key === 'student' ? 'scatter' : 'line',
        data: data,
        symbolSize: key === 'student' ? 8 : 0,
        lineStyle: key === 'student'
            ? undefined
            : { type: 'dashed', width: 1 },
        itemStyle: { color: colors[key] || '#6b7280' },
        smooth: key !== 'student',
    }));
}

// Resize handler
window.addEventListener('resize', () => chart.resize());

// Initial load
loadChart(currentType);
