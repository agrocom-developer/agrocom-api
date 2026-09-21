/**
 * Alta de Orden de Trabajo (reforma 18/9/2026; reordenada el 19/9 y el 21/9/2026).
 *
 * Calda, equipos y lotes dependen de la orden de aplicación elegida. Los datos
 * de todas las órdenes disponibles llegan en `data-ag-ordenes`, así que elegir
 * otra NO vuelve a pedir la pantalla: este script rearma esas secciones en el
 * lugar. El estado inicial (orden que llega por `?orden_id=` o por un error de
 * validación) lo dibuja el servidor con el mismo HTML.
 *
 * Se ocupa de:
 * - Calda: mostrar los campos del tipo de insumo de la orden (Ph y litros, o
 *   kilos) y deshabilitar los otros, para que no viajen en el envío.
 * - Equipos: tantos bloques como definió la orden (`cantidad_equipos`), armados
 *   desde el `<template>` de equipo. No se agregan ni se quitan a mano.
 * - Que una cuadrilla elegida en un equipo deje de ofrecerse en los demás.
 * - El cuadro «Datos del contrato» del primer bloque.
 * - El reparto (pedido del dueño, 21/9/2026): de cada equipo se cargan las
 *   HECTÁREAS que se le asignan, en campos ENLAZADOS — entre los equipos que ya
 *   tienen cuadrilla (entre todos, mientras ninguno la tenga, para que se vea
 *   la propuesta) suman siempre lo que a la orden le queda por repartir: subir
 *   uno baja a los demás, en proporción a lo que tenían. Arrancan parejas. Los
 *   LOTES no se tipean: salen de esas hectáreas y del criterio elegido (en
 *   orden, o primero los difíciles enteros), se muestran como texto y viajan
 *   en campos ocultos `equipos[i][lotes][j][…]`, con el turno y el horario del
 *   bloque copiados a cada lote. Cada lote de un equipo nace como un trabajo.
 * - El resumen de hectáreas repartidas.
 * - El borrador propio (`data-ag-borrador="propio"`): al salir por «Crear» a dar
 *   de alta una cuadrilla y volver —guardando allá o con «Volver»—, lo cargado
 *   se repone. El genérico de `shared/borrador-formulario.js` no alcanza acá:
 *   entrando por el menú todavía no hay ningún enlace de alta rápida en el DOM
 *   (los bloques nacen al elegir la orden) y no se engancharía; y los bloques
 *   hay que armarlos ANTES de reponer sus campos, con el reparto en pausa para
 *   que las hectáreas enlazadas no se pisen unas a otras mientras se reponen.
 *
 * Vanilla, sin librerías. Los textos llegan traducidos por `data-*`.
 */

import { initTimeRanges } from '../atoms/time-range.js';
import { descartarBorrador, guardarBorrador, leerBorrador, restaurarCampos } from '../shared/borrador-formulario.js';

const aCentesimas = (texto) => Math.round(Number.parseFloat(texto || '0') * 100) || 0;
const formatoHa = new Intl.NumberFormat('es-BO', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

class OrdenesTrabajoForm {
    constructor(formEl) {
        this.form = formEl;
        this.lista = formEl.querySelector('[data-ag-equipos-lista]');
        this.selector = formEl.querySelector('[data-ag-orden-selector]');
        this.reparto = formEl.querySelector('[data-ag-reparto]');
        // En pausa mientras se repone el borrador (ver `reponerBorrador()`).
        this.restaurando = false;

        try {
            this.ordenes = JSON.parse(formEl.dataset.agOrdenes || '{}') ?? {};
        } catch {
            this.ordenes = {};
        }

        this.init();
    }

    init() {
        if (!this.lista || !this.selector) return;

        this.ordenActual = this.selector.value;

        this.selector.addEventListener('change', () => this.cambiarOrden());
        /** @type {Map<Element, number>} centésimas asignadas a cada equipo que participa */
        this.cuotas = new Map();
        this.firma = null;

        // Cuadrilla, turno, horario, hectáreas o criterio: cualquier cambio rehace
        // el reparto. Si lo que cambió son las hectáreas de un equipo, antes se
        // ajustan las de los demás.
        this.lista.addEventListener('input', (evento) => {
            if (evento.target.matches('[data-ag-equipo-hectareas]')) {
                this.ajustarCuotas(evento.target.closest('[data-ag-equipo-bloque]'), evento.target.value);
            }
            this.repartir();
        });
        this.lista.addEventListener('change', () => this.repartir());

        this.form.addEventListener('click', (evento) => {
            if (evento.target.closest('[data-ag-link-accent]')) {
                guardarBorrador(this.form, { orden: this.selector.value });
            }
        });
        this.form.addEventListener('submit', descartarBorrador);
        this.form.querySelectorAll('[data-ag-reparto-modo]').forEach((opcion) => {
            opcion.addEventListener('change', () => this.repartir());
        });

        this.repartir();
        this.reponerBorrador();
    }

    /**
     * Vuelta del alta rápida de cuadrilla: primero la orden (arma calda y
     * bloques), después los campos —con el reparto en pausa— y al final un solo
     * reparto, que toma las hectáreas repuestas si cuadran con la orden.
     */
    reponerBorrador() {
        const borrador = leerBorrador();
        if (!borrador) return;

        this.restaurando = true;

        const orden = String(borrador.extra?.orden ?? '');
        if (orden !== '' && this.ordenes[orden] && this.selector.value !== orden) {
            this.selector.value = orden;
            this.selector.dispatchEvent(new Event('change', { bubbles: true }));
        }

        restaurarCampos(this.form, borrador.campos, { silenciar: (control) => control === this.selector });

        this.restaurando = false;
        this.firma = null;
        this.repartir();
    }

    /** Datos de la orden elegida, o `null` si todavía no hay ninguna. */
    get datos() {
        return this.ordenes[this.selector.value] ?? null;
    }

    get lotesOrden() {
        return this.datos?.lotes ?? [];
    }

    /**
     * Al cambiar la orden se rearman calda y equipos para ESA orden, en el
     * lugar. Clima y vuelo no dependen de la orden: quedan como estaban.
     */
    cambiarOrden() {
        if (this.selector.value === this.ordenActual) return;

        const anterior = this.ordenes[this.ordenActual] ?? null;
        this.ordenActual = this.selector.value;

        this.actualizarUrl();
        this.pintarContrato();
        this.aplicarCalda(anterior);
        this.construirEquipos();
    }

    /**
     * La URL acompaña a la orden elegida: recargar, o volver del acceso rápido
     * «Crear cuadrilla», cae en el mismo formulario con esa orden.
     */
    actualizarUrl() {
        const destino = new URL(this.form.dataset.agUrlCrear || window.location.href, window.location.origin);
        if (this.selector.value !== '') {
            destino.searchParams.set('orden_id', this.selector.value);
        }
        window.history.replaceState(window.history.state, '', destino.toString());

        this.urlCuadrilla = this.form.dataset.agUrlCuadrilla ? new URL(this.form.dataset.agUrlCuadrilla, window.location.origin) : null;
        if (this.urlCuadrilla) {
            this.urlCuadrilla.searchParams.set('volver_a', destino.toString());
            this.urlCuadrilla.searchParams.set('volver_texto', this.form.dataset.agVolverTexto ?? '');
        }
    }

    /** Cuadro «Datos del contrato» del primer bloque, para la orden elegida. */
    pintarContrato() {
        const cuadro = this.form.querySelector('[data-ag-resumen-contrato]');
        if (!cuadro) return;

        const datos = this.datos;
        cuadro.hidden = datos === null;
        if (datos === null) return;

        cuadro.querySelectorAll('[data-ag-dato-orden]').forEach((nodo) => {
            nodo.textContent = datos[nodo.dataset.agDatoOrden] ?? '—';
        });

        const logo = cuadro.querySelector('[data-ag-cliente-logo]');
        if (logo) logo.src = datos.logo_url ?? logo.dataset.logoPlaceholder ?? '';
    }

    aplicarCalda(anterior) {
        const seccion = this.form.querySelector('[data-ag-calda]');
        if (!seccion) return;

        const datos = this.datos;

        seccion.querySelectorAll('[data-ag-calda-vacio]').forEach((nodo) => {
            nodo.hidden = datos !== null;
        });

        seccion.querySelectorAll('[data-ag-calda-campo]').forEach((nodo) => {
            const tipo = nodo.dataset.agCaldaCampo;
            const corresponde = datos !== null && (tipo === '' || (tipo === 'liquido') === datos.es_liquido);

            nodo.hidden = !corresponde;
            if (tipo !== '') {
                nodo.querySelectorAll('input').forEach((campo) => {
                    campo.disabled = !corresponde;
                });
            }
        });

        // Los litros por hectárea arrancan con los de la orden de aplicación; lo
        // que ya se corrigió a mano no se pisa.
        const litros = seccion.querySelector('[data-ag-calda-litros]');
        if (litros && datos?.es_liquido && (litros.value === '' || litros.value === (anterior?.litros_ha ?? ''))) {
            litros.value = datos.litros_ha ?? '';
        }

        const contador = seccion.querySelector('.ag-section-head__count');
        if (contador) {
            contador.textContent = datos === null
                ? seccion.dataset.agContadorVacio ?? ''
                : (seccion.dataset.agContadorPlantilla ?? '').replace(':cantidad', datos.es_liquido ? '4' : '2');
        }
    }

    /**
     * Bloques de equipo de la orden elegida, desde el molde del servidor. Los
     * lotes cargados eran de la orden anterior y se van; la cuadrilla elegida
     * en cada posición se conserva.
     */
    construirEquipos() {
        const seccion = this.form.querySelector('[data-ag-equipos]');
        const datos = this.datos;
        const cantidad = datos?.cantidad_equipos ?? 0;
        const cuadrillas = [...this.lista.querySelectorAll('[data-ag-equipo-selector]')].map((selector) => selector.value);

        this.form.querySelectorAll('[data-ag-equipos-vacio]').forEach((nodo) => {
            nodo.hidden = datos !== null;
        });
        this.form.querySelectorAll('[data-ag-equipos-contenido]').forEach((nodo) => {
            nodo.hidden = datos === null;
        });

        this.lista.replaceChildren();

        for (let indice = 0; indice < cantidad; indice += 1) {
            const molde = this.form.querySelector(`[data-ag-equipo-template="${indice === 0 ? 'obligatorio' : 'opcional'}"]`);
            if (!molde) break;

            const envoltorio = document.createElement('div');
            envoltorio.innerHTML = molde.innerHTML
                .replace(/__INDICE_EQUIPO__/g, String(indice))
                .replace(/__NUMERO_EQUIPO__/g, String(indice + 1));
            const bloque = envoltorio.firstElementChild;
            if (!bloque) break;

            this.lista.appendChild(bloque);

            const cuadrilla = bloque.querySelector('[data-ag-equipo-selector]');
            if (cuadrilla && cuadrillas[indice] && [...cuadrilla.options].some((opcion) => opcion.value === cuadrillas[indice])) {
                cuadrilla.value = cuadrillas[indice];
            }
            if (this.urlCuadrilla) {
                bloque.querySelectorAll('.ag-select__action').forEach((enlace) => {
                    enlace.href = this.urlCuadrilla.toString();
                });
            }

            bloque.dispatchEvent(new CustomEvent('ag:select:inicializar', { bubbles: true }));
            initTimeRanges(bloque);
        }

        // Equipos nuevos, orden nueva: las hectáreas vuelven a arrancar parejas.
        this.cuotas = new Map();
        this.firma = null;

        const ayuda = this.form.querySelector('[data-ag-equipos-ayuda]');
        if (ayuda && cantidad > 0) {
            ayuda.textContent = (cantidad === 1 ? ayuda.dataset.agAyudaUno : ayuda.dataset.agAyudaVarios) ?? '';
        }

        const contador = seccion?.querySelector('.ag-section-head__count');
        if (contador && seccion) {
            const textos = [seccion.dataset.agContadorNinguno, seccion.dataset.agContadorUno];
            contador.textContent = textos[cantidad] ?? (seccion.dataset.agContadorVarios ?? '').replace(':cantidad', String(cantidad));
        }

        this.form.querySelectorAll('[data-ag-reparto-modo-envoltorio]').forEach((nodo) => {
            nodo.hidden = cantidad < 2;
        });

        this.repartir();
    }

    get modo() {
        return [...this.form.querySelectorAll('[data-ag-reparto-modo]')].find((opcion) => opcion.checked)?.value ?? 'parejo';
    }

    /** Los equipos que entran en el reparto: los que tienen cuadrilla o, si ninguno, todos. */
    participantes() {
        const bloques = [...this.lista.querySelectorAll('[data-ag-equipo-bloque]')];
        const conCuadrilla = bloques.filter((bloque) => (bloque.querySelector('[data-ag-equipo-selector]')?.value ?? '') !== '');

        return { bloques, conCuadrilla, participantes: conCuadrilla.length > 0 ? conCuadrilla : bloques };
    }

    get total() {
        return aCentesimas(this.datos?.restantes_total);
    }

    /**
     * Hectáreas de cada participante. Cambia quiénes participan (o la orden) y
     * vuelven a arrancar parejas — salvo que el formulario ya las traiga
     * cargadas y cuadren con la orden (vuelta de un error de validación).
     */
    asegurarCuotas(participantes) {
        const firma = `${this.selector.value}|${participantes.map((bloque) => bloque.dataset.agEquipoPrefijo).join(',')}`;
        if (firma === this.firma) return;

        const primeraVez = this.firma === null;
        this.firma = firma;

        const cargadas = participantes.map((bloque) => aCentesimas(bloque.querySelector('[data-ag-equipo-hectareas]')?.value));
        const valen = primeraVez && this.total > 0 && cargadas.reduce((a, b) => a + b, 0) === this.total;
        const cuotas = valen ? cargadas : this.nivelar(new Array(participantes.length).fill(0), this.total);

        this.cuotas = new Map(participantes.map((bloque, indice) => [bloque, cuotas[indice] ?? 0]));
    }

    /**
     * Campos enlazados: el equipo editado se queda con lo que se tipeó (entre 0
     * y el total) y el resto se reparte entre los demás en proporción a lo que
     * tenían — parejo si no tenían nada. En centésimas enteras, y el redondeo
     * cae en el último, así la suma es siempre exacta.
     */
    ajustarCuotas(bloque, texto) {
        if (this.restaurando) return;

        const { participantes } = this.participantes();
        this.asegurarCuotas(participantes);
        if (!this.cuotas.has(bloque)) return;

        const otros = participantes.filter((uno) => uno !== bloque);
        const valor = otros.length === 0 ? this.total : Math.min(Math.max(aCentesimas(texto), 0), this.total);
        const resto = this.total - valor;
        const base = otros.reduce((suma, uno) => suma + (this.cuotas.get(uno) ?? 0), 0);

        this.cuotas.set(bloque, valor);

        let repartido = 0;
        otros.forEach((uno, indice) => {
            const ultimo = indice === otros.length - 1;
            const parte = ultimo
                ? resto - repartido
                : Math.floor(base > 0 ? (resto * (this.cuotas.get(uno) ?? 0)) / base : resto / otros.length);

            this.cuotas.set(uno, parte);
            repartido += parte;
        });
    }

    /**
     * Deja el reparto a la vista (hectáreas y lotes de cada bloque) y listo para
     * enviar (campos ocultos). Mientras ningún equipo tenga cuadrilla se muestra
     * la propuesta entre todos, sin campos ocultos — confirmar así responde
     * "Elige la cuadrilla".
     */
    repartir() {
        if (this.restaurando) return;

        this.actualizarCuadrillas();

        const { bloques, conCuadrilla, participantes } = this.participantes();
        this.asegurarCuotas(participantes);

        const reparto = this.calcularReparto(this.modo, participantes.map((bloque) => this.cuotas.get(bloque) ?? 0));
        const etiquetas = new Map(this.lotesOrden.map((lote) => [String(lote.lote_id), lote.label]));
        const plantillaLotes = this.lista.dataset.agLotesPlantilla ?? ':lotes';
        const plantillaLote = this.lista.dataset.agLotePlantilla ?? ':lote';
        let repartidas = 0;

        bloques.forEach((bloque) => {
            const posicion = participantes.indexOf(bloque);
            const participa = posicion !== -1;
            const asignados = participa ? [...reparto[posicion].entries()].filter(([, centesimas]) => centesimas > 0) : [];
            const cuota = participa ? this.cuotas.get(bloque) ?? 0 : 0;

            const campo = bloque.querySelector('[data-ag-equipo-hectareas]');
            if (campo) {
                // El campo que se está tipeando no se reescribe: se normaliza al salir.
                if (document.activeElement !== campo) campo.value = participa ? (cuota / 100).toFixed(2) : '';
                campo.disabled = !participa;
                campo.readOnly = participa && participantes.length === 1;
                campo.max = (this.total / 100).toFixed(2);
            }

            const lotes = bloque.querySelector('[data-ag-equipo-lotes]');
            if (lotes) {
                lotes.hidden = asignados.length === 0;
                lotes.textContent = plantillaLotes.replace(':lotes', asignados.map(([loteId, centesimas]) => plantillaLote
                    .replace(':lote', etiquetas.get(loteId) ?? `#${loteId}`)
                    .replace(':hectareas', formatoHa.format(centesimas / 100))).join(', '));
            }

            const aviso = bloque.querySelector('[data-ag-equipo-sin-cuadrilla]');
            if (aviso) aviso.hidden = participa;

            const ocultos = bloque.querySelector('[data-ag-equipo-ocultos]');
            ocultos?.replaceChildren();

            if (ocultos && conCuadrilla.includes(bloque)) {
                repartidas += cuota;
                this.escribirOcultos(bloque, ocultos, asignados);
            }
        });

        this.actualizarResumen(repartidas);
    }

    /** `equipos[i][lotes][j][…]`, con el turno y el horario del bloque en cada lote. */
    escribirOcultos(bloque, contenedor, asignados) {
        const prefijo = bloque.dataset.agEquipoPrefijo ?? '';
        const [inicio, fin] = [...bloque.querySelectorAll('[data-ag-equipo-hora]')].map((campo) => campo.value ?? '');
        const comunes = {
            turno: bloque.querySelector('[data-ag-equipo-turno]')?.value ?? '',
            turno_hora_inicio: inicio ?? '',
            turno_hora_fin: fin ?? '',
        };

        asignados.forEach(([loteId, centesimas], indice) => {
            const campos = { lote_id: loteId, hectareas: (centesimas / 100).toFixed(2), ...comunes };

            Object.entries(campos).forEach(([nombre, valor]) => {
                const campo = document.createElement('input');
                campo.type = 'hidden';
                campo.name = `${prefijo}[lotes][${indice}][${nombre}]`;
                campo.value = valor;
                contenedor.appendChild(campo);
            });
        });
    }

    /**
     * Qué lotes le tocan a cada equipo, dadas las hectáreas de cada uno
     * (`cuotas`, en centésimas, que suman lo que queda por repartir).
     *
     * "Parejo": los lotes se recorren en orden y se van llenando los equipos de
     * a uno, así a cada equipo le tocan lotes contiguos y solo se parte el que
     * cae en el límite entre dos equipos.
     *
     * "Por dificultad": primero los lotes con obstáculos, ENTEROS, del más
     * difícil al menos y cada uno al equipo al que más le falta — nadie se queda
     * con todos los difíciles. El que no entra entero en ningún equipo se parte,
     * junto con los del grado más fácil, que completan las hectáreas de cada uno.
     *
     * Todo en centésimas enteras: las hectáreas no se suman en coma flotante.
     */
    calcularReparto(modo, cuotas) {
        const ordenDificultad = ['muchos_obstaculos', 'algunos_obstaculos', 'pocos_obstaculos', 'limpio'];

        const lotes = this.lotesOrden
            .map((lote) => ({ id: String(lote.lote_id), centesimas: aCentesimas(lote.restantes), limpieza: lote.limpieza ?? null }))
            .filter((lote) => lote.centesimas > 0);

        /** @type {Array<Map<string, number>>} lote → centésimas, por equipo */
        const reparto = cuotas.map(() => new Map());
        const faltan = [...cuotas];
        const asignar = (equipo, lote, centesimas) => {
            reparto[equipo].set(lote.id, (reparto[equipo].get(lote.id) ?? 0) + centesimas);
            faltan[equipo] -= centesimas;
        };

        let porPartir = lotes;

        if (modo === 'dificultad') {
            // Un lote sin el dato del terreno va con los limpios: no hay por qué
            // tratarlo como difícil.
            const grupos = ordenDificultad
                .map((grado) => lotes.filter((lote) => (ordenDificultad.includes(lote.limpieza) ? lote.limpieza : 'limpio') === grado))
                .filter((grupo) => grupo.length > 0);

            const faciles = grupos.pop() ?? [];
            const noEntraron = [];

            grupos.forEach((grupo) => {
                [...grupo].sort((a, b) => b.centesimas - a.centesimas).forEach((lote) => {
                    const equipo = faltan.indexOf(Math.max(...faltan));

                    if (equipo !== -1 && faltan[equipo] >= lote.centesimas) {
                        asignar(equipo, lote, lote.centesimas);
                    } else {
                        noEntraron.push(lote);
                    }
                });
            });

            porPartir = [...noEntraron, ...faciles];
        }

        let equipo = 0;
        porPartir.forEach((lote) => {
            let queda = lote.centesimas;

            while (queda > 0) {
                while (equipo < faltan.length && faltan[equipo] <= 0) {
                    equipo += 1;
                }
                if (equipo >= faltan.length) break;

                const toma = Math.min(queda, faltan[equipo]);
                asignar(equipo, lote, toma);
                queda -= toma;
            }
        });

        return reparto;
    }

    /**
     * Cuánto más le toca a cada equipo para que todos terminen con lo mismo,
     * dado lo que ya lleva cada uno y lo que queda por repartir. Un equipo que
     * ya lleva más que el promedio no recibe nada, y el promedio se recalcula
     * entre los demás.
     */
    nivelar(cargas, porRepartir) {
        let activos = cargas.map((_, indice) => indice);
        let objetivo = 0;
        let sobrante = 0;

        for (;;) {
            const suma = porRepartir + activos.reduce((total, indice) => total + cargas[indice], 0);
            objetivo = Math.floor(suma / activos.length);
            sobrante = suma % activos.length;

            const siguen = activos.filter((indice) => cargas[indice] <= objetivo);
            if (siguen.length === activos.length) break;
            activos = siguen;
        }

        const cuotas = new Array(cargas.length).fill(0);
        activos.forEach((indice, posicion) => {
            cuotas[indice] = objetivo - cargas[indice] + (posicion < sobrante ? 1 : 0);
        });

        return cuotas;
    }

    /**
     * Una cuadrilla cubre un solo equipo de la tanda: la elegida en un bloque
     * se deshabilita en los demás. `atoms/select.js` observa el `disabled` de
     * cada `<option>` y refresca su lista sola.
     */
    actualizarCuadrillas() {
        const selectores = [...this.lista.querySelectorAll('[data-ag-equipo-selector]')];

        selectores.forEach((selector) => {
            const tomadas = new Set(
                selectores.filter((otro) => otro !== selector && otro.value !== '').map((otro) => otro.value),
            );

            [...selector.options].forEach((opcion) => {
                if (opcion.value === '') return;

                const tomada = tomadas.has(opcion.value);
                if (opcion.disabled !== tomada) {
                    opcion.disabled = tomada;
                }
            });
        });
    }

    /**
     * Resumen en vivo: hectáreas repartidas contra lo que le queda por repartir
     * a la orden. Es una ayuda visual — el tope real por lote lo valida el
     * servidor.
     */
    actualizarResumen(repartidas) {
        if (!this.reparto) return;

        const total = aCentesimas(this.datos?.restantes_total);

        this.reparto.textContent = (this.reparto.dataset.agRepartoPlantilla ?? '')
            .replace(':repartidas', formatoHa.format(repartidas / 100))
            .replace(':total', formatoHa.format(total / 100));
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const form = document.querySelector('[data-ag-ordenes-trabajo-form]');
    if (form) {
        new OrdenesTrabajoForm(form);
    }
});
