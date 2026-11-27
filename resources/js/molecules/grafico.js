/**
 * molecules/grafico.js — gráficas del panel (Chart.js, MIT) para
 * `<x-molecules.grafico>`. Cada gráfica recibe sus datos ya resueltos por el servidor
 * en `data-ag-grafico` (JSON) y lo único que hace acá es dibujarlos: colores,
 * tipografía, radios y espacios se leen de los tokens CSS en el momento de
 * dibujar (shared/color-tokens.js), así que siguen al tema claro/oscuro y al
 * contraste alto. Sin animación con «reducir movimiento». La tabla con los mismos
 * datos vive en el HTML y no depende de este archivo.
 *
 * Cargado por `import()` dinámico desde app.js, solo en las páginas con un
 * `[data-ag-grafico]`: Chart.js no se baja en el resto del panel.
 */

import {
    BarController,
    BarElement,
    CategoryScale,
    Chart,
    Legend,
    LinearScale,
    LineController,
    LineElement,
    PointElement,
    Tooltip,
} from 'chart.js';
import { leerColorToken, leerDuracionToken, leerFamiliaToken, leerMedidaToken, leerPesoToken } from '../shared/color-tokens.js';

Chart.register(BarController, BarElement, LineController, LineElement, PointElement, CategoryScale, LinearScale, Tooltip, Legend);

const PUNTOS = ['circle', 'rect', 'triangle', 'rectRot'];
const REDUCIR_MOVIMIENTO = window.matchMedia('(prefers-reduced-motion: reduce)');
const CONTRASTE_ALTO = window.matchMedia('(prefers-contrast: more)');

/** Las gráficas vivas, para repintarlas cuando cambia el tema. */
const graficas = new Map();

/** Un formateador de números; con `conSufijo` agrega la unidad (el tooltip la lleva, los ejes no la repiten). */
function formateador(formato, conSufijo = true) {
    const decimales = formato?.decimales ?? 0;
    const numero = new Intl.NumberFormat(document.documentElement.lang || 'es', {
        minimumFractionDigits: decimales,
        maximumFractionDigits: decimales,
    });
    const sufijo = conSufijo && formato?.sufijo ? ` ${formato.sufijo}` : '';

    return (valor) => `${numero.format(valor)}${sufijo}`;
}

/** Todo lo que sale de los tokens, leído de una vez para el tema vigente. */
function leerEstilo() {
    return {
        texto: leerColorToken('--ag-color-text'),
        textoSuave: leerColorToken('--ag-color-text-muted'),
        rejilla: leerColorToken('--ag-color-border-row'),
        eje: leerColorToken('--ag-color-border'),
        superficie: leerColorToken('--ag-color-surface-card'),
        tooltipFondo: leerColorToken('--ag-color-scrim-strong'),
        tooltipTexto: leerColorToken('--ag-color-text-on-scrim'),
        familia: leerFamiliaToken('--ag-font-family-base'),
        familiaMono: leerFamiliaToken('--ag-font-family-mono'),
        tamano: leerMedidaToken('--ag-font-size-xs', 'fontSize'),
        pesoNormal: leerPesoToken('--ag-font-weight-regular'),
        pesoNegrita: leerPesoToken('--ag-font-weight-bold'),
        espacio: leerMedidaToken('--ag-space-2'),
        radio: leerMedidaToken('--ag-radius-sm'),
    };
}

function conjuntos(config, estilo) {
    const grueso = CONTRASTE_ALTO.matches;

    return config.series.map((serie, i) => {
        const color = leerColorToken(`--ag-color-${serie.color}`);
        const base = { label: serie.nombre, data: serie.valores, borderColor: color, backgroundColor: color };

        if (config.tipo === 'lineas') {
            return {
                ...base,
                type: 'line',
                borderWidth: grueso ? 3 : 2,
                // Una forma de punto distinta por serie: el color no es lo único que las separa.
                pointStyle: PUNTOS[i % PUNTOS.length],
                pointRadius: grueso ? 5 : 4,
                pointHoverRadius: 6,
                tension: 0.25,
                spanGaps: false,
            };
        }

        return {
            ...base,
            type: 'bar',
            borderRadius: estilo.radio,
            borderSkipped: false,
            // Un borde del color de la superficie separa las barras apiladas sin inventar otro color.
            borderColor: config.apilado ? estilo.superficie : color,
            borderWidth: config.apilado ? 1 : 0,
            maxBarThickness: leerMedidaToken('--ag-space-6'),
        };
    });
}

function opciones(config, estilo, formato) {
    const formatoEje = formateador(config.formato, false);
    const horizontal = config.horizontal === true;
    const fuente = (mono) => ({ family: mono ? estilo.familiaMono : estilo.familia, size: estilo.tamano, weight: estilo.pesoNormal });
    const ejeCategorias = {
        type: 'category',
        stacked: config.apilado === true,
        grid: { display: false },
        border: { color: estilo.eje },
        // Las barras muestran todas sus categorías. En las líneas (una etiqueta por semana), si no caben todas
        // en una gráfica angosta, Chart.js omite las que se pisarían en vez de apilarlas.
        ticks: { color: estilo.textoSuave, font: fuente(false), autoSkip: config.tipo === 'lineas', maxRotation: 0 },
    };
    const ejeValores = {
        type: 'linear',
        stacked: config.apilado === true,
        beginAtZero: true,
        grid: { color: estilo.rejilla },
        border: { display: false },
        ticks: {
            color: estilo.textoSuave,
            font: fuente(true),
            maxRotation: 0,
            autoSkip: true,
            precision: config.formato?.decimales ? undefined : 0,
            callback: (valor) => formatoEje(valor),
        },
    };

    return {
        responsive: true,
        maintainAspectRatio: false,
        indexAxis: horizontal ? 'y' : 'x',
        animation: REDUCIR_MOVIMIENTO.matches ? false : { duration: leerDuracionToken('--ag-transition-base') },
        interaction: { mode: 'index', intersect: false, axis: horizontal ? 'y' : 'x' },
        layout: { padding: 0 },
        scales: horizontal ? { y: ejeCategorias, x: ejeValores } : { x: ejeCategorias, y: ejeValores },
        plugins: {
            legend: {
                display: config.series.length > 1,
                position: 'bottom',
                labels: {
                    color: estilo.texto,
                    font: fuente(false),
                    boxWidth: estilo.espacio * 1.5,
                    boxHeight: estilo.espacio * 1.5,
                    padding: estilo.espacio * 2,
                    usePointStyle: config.tipo === 'lineas',
                },
            },
            tooltip: {
                backgroundColor: estilo.tooltipFondo,
                titleColor: estilo.tooltipTexto,
                bodyColor: estilo.tooltipTexto,
                titleFont: { family: estilo.familia, size: estilo.tamano, weight: estilo.pesoNegrita },
                bodyFont: { family: estilo.familiaMono, size: estilo.tamano, weight: estilo.pesoNormal },
                padding: estilo.espacio * 1.5,
                cornerRadius: estilo.radio * 2,
                boxPadding: estilo.espacio / 2,
                usePointStyle: true,
                callbacks: {
                    label: (item) => {
                        const valor = item.parsed[horizontal ? 'x' : 'y'];

                        return valor === null || Number.isNaN(valor) ? `${item.dataset.label}: —` : `${item.dataset.label}: ${formato(valor)}`;
                    },
                },
            },
        },
    };
}

function pintar(contenedor, config) {
    const lienzo = contenedor.querySelector('canvas');
    if (!lienzo) {
        return;
    }

    const estilo = leerEstilo();
    const formato = formateador(config.formato);
    const previa = graficas.get(contenedor);

    if (previa) {
        previa.destroy();
    }

    const grafica = new Chart(lienzo, {
        data: { labels: config.etiquetas, datasets: conjuntos(config, estilo) },
        options: opciones(config, estilo, formato),
    });
    graficas.set(contenedor, { destroy: () => grafica.destroy(), config });
    contenedor.dataset.agGraficoEstado = 'listo';
}

function inicializar(contenedor) {
    if (contenedor.dataset.agGraficoEstado === 'listo') {
        return;
    }

    let config;
    try {
        config = JSON.parse(contenedor.dataset.agGrafico);
    } catch {
        contenedor.dataset.agGraficoEstado = 'error';

        return;
    }

    pintar(contenedor, config);
}

function repintarTodas() {
    graficas.forEach((viva, contenedor) => pintar(contenedor, viva.config));
}

function iniciarTodas() {
    document.querySelectorAll('[data-ag-grafico]').forEach(inicializar);
}

// El tema, el contraste y «reducir movimiento» pueden cambiar con la página abierta.
new MutationObserver(repintarTodas).observe(document.documentElement, { attributes: true, attributeFilter: ['data-bs-theme'] });
CONTRASTE_ALTO.addEventListener('change', repintarTodas);
REDUCIR_MOVIMIENTO.addEventListener('change', repintarTodas);

// Este módulo se carga con `import()` dentro de DOMContentLoaded: ese evento ya pasó.
iniciarTodas();
