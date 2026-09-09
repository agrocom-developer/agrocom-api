/**
 * organisms/dashboard-charts.js — instancia ApexCharts sobre cada
 * `[data-ag-chart]` (molecules/apex-chart.blade.php). Cargado vía import()
 * dinámico desde app.js, solo cuando la página tiene al menos un gráfico
 * (ver la convención en ese archivo).
 *
 * Colores SIEMPRE resueltos desde tokens (shared/color-tokens.js) — nunca
 * un hex acá (CLAUDE.md invariante 11). ApexCharts no reacciona solo a que
 * cambie `data-bs-theme`, así que cada instancia se reconstruye entera al
 * recibir `agrocom:theme-changed` (evento que ya dispara
 * molecules/theme-toggle.js).
 */

import ApexCharts from 'apexcharts';
import { leerColorToken, leerColoresTokens, leerTokenCrudo } from '../shared/color-tokens.js';

const instancias = [];

function construirOpciones(el) {
    const tipo = el.dataset.agChart;
    const series = JSON.parse(el.dataset.agChartSeries || '[]');
    const labels = JSON.parse(el.dataset.agChartLabels || '[]');
    const colorTokens = JSON.parse(el.dataset.agChartColorTokens || '[]');

    const colores = leerColoresTokens(colorTokens);
    const fuente = leerTokenCrudo('--ag-font-family-base');
    const colorTexto = leerColorToken('--ag-color-text-muted');
    const colorTextoFuerte = leerColorToken('--ag-color-text');
    const colorBorde = leerColorToken('--ag-color-border-row');

    const base = {
        chart: {
            type: tipo === 'donut' ? 'donut' : tipo,
            height: Number(el.dataset.agChartHeight) || 260,
            fontFamily: fuente,
            toolbar: { show: false },
            animations: { easing: 'easeinout' },
        },
        colors: colores,
        series,
    };

    if (tipo === 'pie' || tipo === 'donut') {
        return {
            ...base,
            labels,
            dataLabels: { enabled: false },
            stroke: { width: 0 },
            legend: { show: true, position: 'bottom', fontFamily: fuente, labels: { colors: colorTexto } },
            tooltip: { style: { fontFamily: fuente } },
        };
    }

    if (tipo === 'area') {
        return {
            ...base,
            xaxis: {
                categories: labels,
                labels: { style: { colors: colorTexto, fontFamily: fuente } },
                axisBorder: { show: false },
                axisTicks: { show: false },
            },
            yaxis: { labels: { style: { colors: colorTexto, fontFamily: fuente } } },
            grid: { borderColor: colorBorde },
            legend: { show: false },
            dataLabels: { enabled: false },
            stroke: { curve: 'smooth', width: 2 },
            fill: { type: 'gradient', gradient: { opacityFrom: 0.35, opacityTo: 0.02 } },
            tooltip: { style: { fontFamily: fuente } },
        };
    }

    if (tipo === 'radialBar') {
        return {
            ...base,
            labels,
            plotOptions: {
                radialBar: {
                    hollow: { size: '60%' },
                    dataLabels: {
                        name: { fontFamily: fuente, color: colorTexto, fontSize: '0.75rem' },
                        value: { fontFamily: fuente, color: colorTextoFuerte, fontSize: '1.75rem', fontWeight: 700 },
                    },
                },
            },
        };
    }

    return base;
}

function renderizarTodos() {
    document.querySelectorAll('[data-ag-chart]').forEach((el) => {
        const previa = instancias.find((instancia) => instancia.el === el);

        if (previa) {
            previa.chart.updateOptions(construirOpciones(el), true, true);
            return;
        }

        const chart = new ApexCharts(el, construirOpciones(el));
        chart.render();
        instancias.push({ el, chart });
    });
}

// Este módulo se carga vía import() dinámico DESDE un handler de
// DOMContentLoaded (app.js) — ese evento ya disparó para cuando el chunk
// termina de descargar, así que renderiza directo en vez de esperarlo.
renderizarTodos();
window.addEventListener('agrocom:theme-changed', renderizarTodos);
