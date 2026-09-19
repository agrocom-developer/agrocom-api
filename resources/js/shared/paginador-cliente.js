/**
 * Paginador en el navegador (19/9/2026): para listas que YA están completas en
 * la página —las filas de un modal, la tabla de lotes de un contrato— y solo
 * hay que repartir en páginas. Es el gemelo de `molecules/pagination` (el de los
 * listados con servidor): mismo markup de `.pagination`/`.page-link` de
 * Bootstrap, así que sale igual y con los mismos colores de marca.
 *
 * Solo dibuja los controles y avisa qué página se pidió: quien lo usa decide
 * qué hacer con las filas. Ocultar las de otras páginas con `hidden` (nunca
 * quitarlas ni deshabilitar sus campos) conserva lo marcado y lo que se envía
 * con el formulario.
 *
 * Los textos llegan en el `data-*` del contenedor (ADR 0013), armados por el
 * Blade con `lang/es/ui.php` → `ui.paginador.*`:
 * `data-label-aria`, `-anterior`, `-siguiente`, `-pagina` (`:numero`) y
 * `-resumen` (`:desde`, `:hasta`, `:total`).
 */

const sustituir = (plantilla, valores) => Object.entries(valores).reduce(
    (texto, [marca, valor]) => texto.split(marca).join(String(valor)),
    plantilla ?? '',
);

/** Qué números mostrar: primera, última, la actual y sus vecinas, con «…» donde falten. */
function ventana(actual, ultima) {
    const numeros = [...new Set([1, ultima, actual - 1, actual, actual + 1].filter((n) => n >= 1 && n <= ultima))].sort((a, b) => a - b);
    const salida = [];

    numeros.forEach((numero, i) => {
        if (i > 0 && numero - numeros[i - 1] > 1) salida.push('…');
        salida.push(numero);
    });

    return salida;
}

function icono(nombre) {
    const nodo = document.createElement('span');
    nodo.className = 'material-symbols-rounded ag-icon ag-icon--sm';
    nodo.setAttribute('aria-hidden', 'true');
    nodo.textContent = nombre;
    return nodo;
}

/**
 * @param {HTMLElement} contenedor  donde se dibuja; queda `hidden` si todo cabe en una página.
 * @param {{porPagina: number, alCambiar: (pagina: number) => void}} opciones
 */
export function crearPaginador(contenedor, { porPagina, alCambiar }) {
    const etiquetas = contenedor.dataset;
    let total = 0;
    let pagina = 1;

    const ultimaPagina = () => Math.max(1, Math.ceil(total / porPagina));
    const acotar = (numero) => Math.min(Math.max(numero, 1), ultimaPagina());

    const boton = (contenido, { numero, deshabilitado = false, actual = false, etiqueta = null }) => {
        const item = document.createElement('li');
        item.className = `page-item${deshabilitado ? ' disabled' : ''}${actual ? ' active' : ''}`;
        if (actual) item.setAttribute('aria-current', 'page');

        const control = document.createElement('button');
        control.type = 'button';
        control.className = 'page-link';
        control.disabled = deshabilitado;
        if (etiqueta) control.setAttribute('aria-label', etiqueta);
        control.append(contenido);
        control.addEventListener('click', () => ir(numero));

        item.append(control);
        return item;
    };

    function pintar() {
        contenedor.replaceChildren();
        contenedor.hidden = total <= porPagina;
        if (contenedor.hidden) return;

        const ultima = ultimaPagina();
        const resumen = document.createElement('p');
        resumen.className = 'ag-paginador__resumen';
        resumen.textContent = sustituir(etiquetas.labelResumen, {
            ':desde': (pagina - 1) * porPagina + 1,
            ':hasta': Math.min(pagina * porPagina, total),
            ':total': total,
        });

        const nav = document.createElement('nav');
        nav.setAttribute('aria-label', etiquetas.labelAria ?? '');

        const lista = document.createElement('ul');
        lista.className = 'pagination pagination-sm mb-0';
        lista.append(boton(icono('chevron_left'), { numero: pagina - 1, deshabilitado: pagina === 1, etiqueta: etiquetas.labelAnterior }));

        ventana(pagina, ultima).forEach((numero) => {
            if (numero === '…') {
                const puntos = document.createElement('li');
                puntos.className = 'page-item disabled';
                puntos.innerHTML = '<span class="page-link">&hellip;</span>';
                lista.append(puntos);
            } else {
                lista.append(boton(String(numero), {
                    numero,
                    actual: numero === pagina,
                    etiqueta: sustituir(etiquetas.labelPagina, { ':numero': numero }),
                }));
            }
        });

        lista.append(boton(icono('chevron_right'), { numero: pagina + 1, deshabilitado: pagina === ultima, etiqueta: etiquetas.labelSiguiente }));
        nav.append(lista);
        contenedor.append(resumen, nav);
    }

    function ir(numero) {
        const nueva = acotar(numero);
        if (nueva === pagina) return;

        pagina = nueva;
        pintar();
        alCambiar(pagina);
    }

    return {
        /** Vuelve a dibujar con el total actual, quedándose en `nuevaPagina` (o la última si ya no existe). */
        actualizar(nuevoTotal, nuevaPagina = pagina) {
            total = nuevoTotal;
            pagina = acotar(nuevaPagina);
            pintar();
        },
        ir,
        pagina: () => pagina,
        /** Índices `[desde, hasta)` de los ítems de la página actual. */
        rango: () => [(pagina - 1) * porPagina, pagina * porPagina],
    };
}
