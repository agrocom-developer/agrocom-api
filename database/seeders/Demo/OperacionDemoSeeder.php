<?php

namespace Database\Seeders\Demo;

use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Operaciones\Aplicacion\GenerarActaTrabajo;
use App\Dominios\Operaciones\Aplicacion\GenerarReporteTecnico;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosActa;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosSesion;
use App\Dominios\Operaciones\Aplicacion\MaquinaEstados\MaquinaEstadosTrabajo;
use App\Dominios\Operaciones\Aplicacion\ValidarSesion;
use App\Dominios\Operaciones\Dominio\CausaPausa;
use App\Dominios\Operaciones\Dominio\EstadoAlerta;
use App\Dominios\Operaciones\Dominio\TipoAlerta;
use App\Dominios\Operaciones\Dominio\TipoEvidencia;
use App\Dominios\Operaciones\Dominio\TipoIncidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Acta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Alerta;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Condiciones;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Dron;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Evidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\EvidenciaEquipo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Incidencia;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Pausa;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Recarga;
use App\Dominios\Operaciones\Infraestructura\Eloquent\RecepcionCaldo;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Sesion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\Trabajo;
use App\Dominios\Personal\Infraestructura\Eloquent\PerPersona;
use Brick\Math\BigDecimal;
use Brick\Math\RoundingMode;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * El flujo operativo completo, construido sobre las 21 capturas REALES del
 * control remoto del dron que viven en
 * `docs/gestion/respuestas_campo/capturas_rc/`.
 *
 * ### Qué cambia respecto de antes
 *
 * Esas 21 fotos ya estaban en el repo, pero solo como PRESENTACIÓN: el tab
 * "Multimedia" del dashboard las pinta desde
 * la maqueta del dashboard,
 * un mock con identidad de piloto, lote y fecha inventadas, sin una sola fila
 * detrás. Acá ese relato se vuelve datos: cada una de las 10 "sesiones de
 * vuelo" del mock existe como `ope_sesiones` de verdad, sobre los lotes que
 * siembra `CarteraClientesDemoSeeder` (San Marcos, El Carmen, Santa Rosa —
 * los mismos nombres del mock, a propósito), volada por el piloto que
 * corresponde, cerrada con su captura como `captura_rc_id` y validada por
 * alguien que no es su piloto.
 *
 * Las cifras de rendimiento —hectáreas, tiempo de vuelo, litros— NO son
 * inventadas: son las transcritas de las propias capturas en
 * `docs/especificacion/analisis_capturas_rc.md` §8. Por eso los devengos que
 * salen de validar estas sesiones son plata calculada sobre hectáreas reales.
 *
 * ### Dónde queda cada archivo
 *
 * Las 21 se copian al disco `r2` con la ruta del ADR 0009
 * (`evidencias/{tipo}/{yyyy}/{mm}/{uuid}-{id}.{ext}`) y el `hash` sha256 real
 * del archivo — el mismo camino que usaría `RegistrarEvidencia` si llegaran
 * por la API. En local `r2` cae a `storage/app/r2` (ver `config/filesystems.php`),
 * así que se sirven por streaming desde `panel.evidencias.archivo` igual que
 * en producción. Repartidas por tipo para que las cuatro secciones de la
 * galería de evidencias del trabajo tengan contenido:
 * - 10 `captura_rc`: una por sesión (las tomas de mapa de misión y HUD).
 * - 5 `firma_acta`: las cartas de confirmación de efecto laboral firmadas.
 * - 4 `imagen_campo`: una por trabajo, las cuatro restantes.
 * - 2 `foto_incidencia`: las dos que ilustran una incidencia registrada.
 *
 * `sembrarEvidenciaEquipo()` (HU-80, tarea 86) suma un quinto grupo, fuera de
 * las 21 originales: tres copias de `rc_01.jpeg` como `foto_control`/
 * `foto_ciclo_bateria_balanceo`/`foto_dron_limpio` de T1, para que el
 * "Reporte de Equipos" tenga contenido real en la demo.
 *
 * ### Estados repartidos
 *
 * Ocho trabajos cerrados, diez sesiones validadas (con lo que se generan los
 * devengos por el camino real: `SesionValidada` → `GenerarDevengosSesion`,
 * invariante 3), ocho actas de las cuales cinco firmadas y tres pendientes,
 * y un reporte técnico por trabajo. Dos trabajos llevan dos sesiones: uno con
 * relevo de piloto —Abraham Gutiérrez, el jefe de campo, releva a Josue
 * Haenke— que es el caso donde la invariante 4 muerde: Abraham valida las
 * sesiones de todos los demás, pero la suya la valida Jorge Scheidel, el
 * encargado de operaciones.
 *
 * Idempotente: si el primer trabajo ya existe, no hace nada.
 */
class OperacionDemoSeeder extends Seeder
{
    /** Origen de las capturas: el repo, no un fixture generado. */
    private const ORIGEN_CAPTURAS = 'docs/gestion/respuestas_campo/capturas_rc';

    /**
     * Trabajos: clave → [lote, fecha, litros de caldo recibidos, litros
     * sobrante, quién entregó el caldo].
     *
     * @var array<string, array{string, string, string, string, string}>
     */
    private const TRABAJOS = [
        'T1' => ['SM-12', '2026-08-01', '120.00', '2.40', 'Nelson Quispe'],
        'T2' => ['EC-03', '2026-08-04', '260.00', '3.80', 'Encargado El Carmen'],
        'T3' => ['EC-08', '2026-08-07', '180.00', '1.20', 'Encargado El Carmen'],
        'T4' => ['SR-01', '2026-08-10', '160.00', '4.60', 'Rosa Melgar de Áñez'],
        'T5' => ['SM-12', '2026-08-13', '150.00', '0.80', 'Nelson Quispe'],
        'T6' => ['EC-03', '2026-08-16', '95.00', '2.10', 'Encargado El Carmen'],
        'T7' => ['EC-08', '2026-08-19', '200.00', '5.50', 'Encargado El Carmen'],
        'T8' => ['SR-01', '2026-08-22', '40.00', '1.90', 'Rosa Melgar de Áñez'],
    ];

    /**
     * Sesiones: clave → [trabajo, piloto, auxiliar|null, dron, hectáreas,
     * litros consumidos, inicio UTC, fin UTC, motivo de cierre, validador,
     * archivo de la captura de RC].
     *
     * Las horas van en UTC (Bolivia es UTC−4): 10:00–12:20 UTC cae entre las
     * 06:00 y las 08:20 locales, dentro de las ventanas matinales que fija
     * cada contrato.
     *
     * @var array<string, array{string, string, string|null, string, string, string, string, string, string, string, string}>
     */
    private const SESIONES = [
        'S-01' => ['T1', 'Josue Haenke', 'David Omar Ríos Lino', 'AG-04', '9.82', '9.80', '2026-08-01 10:30:00', '2026-08-01 10:34:56', 'completado', 'Abraham Gutiérrez Contreras', 'rc_14.jpeg'],
        'S-02' => ['T2', 'Miguelito Justiniano Dorado', 'David Omar Ríos Lino', 'AG-07', '24.50', '6.50', '2026-08-04 10:15:00', '2026-08-04 10:18:34', 'completado', 'Abraham Gutiérrez Contreras', 'rc_15.jpeg'],
        'S-03' => ['T3', 'Carlos Ferrufino', 'David Omar Ríos Lino', 'AG-02', '8.21', '20.80', '2026-08-07 10:00:00', '2026-08-07 10:08:10', 'completado', 'Abraham Gutiérrez Contreras', 'rc_16.jpeg'],
        'S-04' => ['T4', 'Miguelito Justiniano Dorado', null, 'AG-09', '15.80', '2.70', '2026-08-10 10:40:00', '2026-08-10 10:42:54', 'completado', 'Abraham Gutiérrez Contreras', 'rc_05.jpeg'],
        'S-05' => ['T5', 'Josue Haenke', 'David Omar Ríos Lino', 'AG-04', '12.30', '10.40', '2026-08-13 10:00:00', '2026-08-13 10:05:45', 'relevo_piloto', 'Abraham Gutiérrez Contreras', 'rc_08.jpeg'],
        'S-09' => ['T5', 'Abraham Gutiérrez Contreras', 'David Omar Ríos Lino', 'AG-04', '1.04', '16.10', '2026-08-13 12:10:00', '2026-08-13 12:15:59', 'fin_jornada', 'Jorge Richard Scheidel Dorado', 'rc_17.jpeg'],
        'S-06' => ['T6', 'Miguelito Justiniano Dorado', 'David Omar Ríos Lino', 'AG-07', '3.87', '13.20', '2026-08-16 10:20:00', '2026-08-16 10:27:38', 'clima', 'Abraham Gutiérrez Contreras', 'rc_12.jpeg'],
        'S-10' => ['T6', 'Miguelito Justiniano Dorado', null, 'AG-07', '3.82', '6.70', '2026-08-16 12:00:00', '2026-08-16 12:07:10', 'completado', 'Abraham Gutiérrez Contreras', 'rc_20.jpeg'],
        'S-07' => ['T7', 'Carlos Ferrufino', 'David Omar Ríos Lino', 'AG-02', '17.60', '9.00', '2026-08-19 10:05:00', '2026-08-19 10:10:10', 'completado', 'Abraham Gutiérrez Contreras', 'rc_21.jpeg'],
        'S-08' => ['T8', 'Miguelito Justiniano Dorado', null, 'AG-09', '1.82', '11.00', '2026-08-22 10:30:00', '2026-08-22 10:40:40', 'falla_equipo', 'Abraham Gutiérrez Contreras', 'rc_11.jpeg'],
    ];

    /**
     * Actas firmadas: trabajo → [archivo de la carta de conformidad,
     * firmante, fecha de firma].
     *
     * Las «cartas de confirmación de efecto laboral» de las capturas son,
     * literalmente, la conformidad del cliente fotografiada — así que son la
     * evidencia de firma del acta, no una captura de vuelo.
     *
     * @var array<string, array{string, string, string}>
     */
    private const ACTAS_FIRMADAS = [
        'T1' => ['rc_03.jpeg', 'Ing. Agr. Lorena Suárez', '2026-08-01 14:20:00'],
        'T2' => ['rc_06.jpeg', 'Ing. Agr. Daniel Terceros', '2026-08-04 15:05:00'],
        'T3' => ['rc_07.jpeg', 'Ing. Agr. Daniel Terceros', '2026-08-07 16:40:00'],
        'T4' => ['rc_09.jpeg', 'Rosa Melgar de Áñez', '2026-08-10 13:15:00'],
        'T5' => ['rc_13.jpeg', 'Ing. Agr. Lorena Suárez', '2026-08-13 17:30:00'],
    ];

    /**
     * Imagen del campo por trabajo: las cuatro capturas que no son ni cierre
     * de sesión ni carta de conformidad.
     *
     * @var array<string, string>
     */
    private const IMAGENES_CAMPO = [
        'T1' => 'rc_18.jpeg',
        'T3' => 'rc_19.jpeg',
        'T5' => 'rc_01.jpeg',
        'T7' => 'rc_02.jpeg',
    ];

    /**
     * Incidencias: sesión → [tipo, descripción, hora, archivo de la foto].
     *
     * @var array<string, array{TipoIncidencia, string, string, string}>
     */
    private const INCIDENCIAS = [
        'S-03' => [TipoIncidencia::Caldo, 'Filtro de la bomba tapado en la segunda recarga: 40 minutos parados hasta limpiarlo y volver a cargar.', '2026-08-07 10:04:00', 'rc_10.jpeg'],
        'S-08' => [TipoIncidencia::Mecanica, 'Aviso de sobretemperatura del ESC del brazo 3. Se aborta el vuelo y se cierra la sesión.', '2026-08-22 10:39:00', 'rc_04.jpeg'],
    ];

    public function __construct(
        private readonly MaquinaEstadosTrabajo $maquinaTrabajo,
        private readonly MaquinaEstadosSesion $maquinaSesion,
        private readonly MaquinaEstadosActa $maquinaActa,
        private readonly ValidarSesion $validarSesion,
        private readonly GenerarActaTrabajo $generarActa,
        private readonly GenerarReporteTecnico $generarReporte,
    ) {}

    public function run(): void
    {
        if (Trabajo::query()->where('uuid_cliente', $this->uuid('trabajo', 'T1'))->exists()) {
            return;
        }

        $autorId = PersonalDemoSeeder::autorId();

        /** @var array<string, int> $personas */
        $personas = PerPersona::query()->pluck('id', 'nombre')->all();
        /** @var array<string, int> $drones */
        $drones = Dron::query()->pluck('id', 'identificador')->all();
        $lotes = $this->lotesPorClave();

        if ($personas === [] || $drones === [] || $lotes === []) {
            return; // faltan los seeders previos: nada que colgar
        }

        $evidencias = $this->sembrarEvidencias($personas, $autorId);
        $trabajos = $this->sembrarTrabajos($lotes, $evidencias, $autorId);

        $this->sembrarSesiones($trabajos, $personas, $drones, $evidencias, $autorId);
        $this->cerrarTrabajos($trabajos, $autorId);
        $this->sembrarActasYReportes($trabajos, $evidencias, $autorId);
        $this->sembrarAlertas($trabajos, $personas, $drones, $autorId);

        if (isset($trabajos['T1'])) {
            $this->sembrarEvidenciaEquipo($trabajos['T1'], $autorId);
        }
    }

    /**
     * Copia las 21 capturas al disco `r2` y deja su fila en `ope_evidencias`.
     *
     * El tipo de cada una sale del rol que cumple en el relato, no del orden
     * del archivo: `sesionesConCaptura()` fija cuáles son cierres de sesión,
     * `ACTAS_FIRMADAS` cuáles son cartas de conformidad, y así.
     *
     * @param  array<string, int>  $personas
     * @return array<string, Evidencia> archivo → evidencia
     */
    private function sembrarEvidencias(array $personas, int $autorId): array
    {
        $tipos = [];

        foreach (self::SESIONES as [, $piloto, , , , , $inicio, , , , $archivo]) {
            $tipos[$archivo] = [TipoEvidencia::CapturaRc, $piloto, $inicio];
        }

        foreach (self::ACTAS_FIRMADAS as $trabajoClave => [$archivo, , $fechaFirma]) {
            $tipos[$archivo] = [TipoEvidencia::FirmaActa, $this->pilotoDelTrabajo($trabajoClave), $fechaFirma];
        }

        foreach (self::IMAGENES_CAMPO as $trabajoClave => $archivo) {
            $fecha = self::TRABAJOS[$trabajoClave][1].' 10:00:00';
            $tipos[$archivo] = [TipoEvidencia::ImagenCampo, $this->pilotoDelTrabajo($trabajoClave), $fecha];
        }

        foreach (self::INCIDENCIAS as $sesionClave => [, , $hora, $archivo]) {
            $tipos[$archivo] = [TipoEvidencia::FotoIncidencia, self::SESIONES[$sesionClave][1], $hora];
        }

        $evidencias = [];

        foreach ($tipos as $archivo => [$tipo, $nombrePersona, $fecha]) {
            $origen = base_path(self::ORIGEN_CAPTURAS.'/'.$archivo);

            if (! is_file($origen)) {
                continue; // captura ausente del repo: se omite, no se inventa
            }

            $uuidCliente = $this->uuid('evidencia', $archivo);

            $evidencia = $this->crear(new Evidencia([
                'uuid_cliente' => $uuidCliente,
                'tipo' => $tipo,
                // Se completa abajo: la ruta del ADR 0009 lleva el `id`, que
                // recién existe después del INSERT (mismo orden que
                // `RegistrarEvidencia`).
                'archivo_url' => $uuidCliente,
                'hash' => (string) hash_file('sha256', $origen),
                'subido_por' => $personas[$nombrePersona] ?? null,
                'fecha' => $fecha,
            ]), $autorId);

            $ruta = sprintf(
                'evidencias/%s/%s/%s-%d.jpeg',
                $tipo->value,
                date('Y/m', (int) strtotime($fecha)),
                $uuidCliente,
                $evidencia->id,
            );

            Storage::disk('r2')->put($ruta, (string) file_get_contents($origen));

            $evidencia->archivo_url = $ruta;
            $evidencia->save();

            $evidencias[$archivo] = $evidencia;
        }

        return $evidencias;
    }

    /**
     * @param  array<string, Lote>  $lotes
     * @param  array<string, Evidencia>  $evidencias
     * @return array<string, Trabajo>
     */
    private function sembrarTrabajos(array $lotes, array $evidencias, int $autorId): array
    {
        $trabajos = [];

        foreach (self::TRABAJOS as $clave => [$loteClave, $fecha, $litrosRecibidos, $litrosSobrante, $entregadoPor]) {
            $lote = $lotes[$loteClave] ?? null;

            if ($lote === null) {
                continue;
            }

            // HU-92 (tarea 107): el lote de la orden ya no es una columna
            // propia, se resuelve por `ope_orden_lotes`.
            $orden = OrdenAplicacion::query()->whereHas(
                'ordenLotes',
                fn ($ordenLotes) => $ordenLotes->where('lote_id', $lote->id),
            )->first();

            if ($orden === null) {
                continue;
            }

            $imagenCampo = isset(self::IMAGENES_CAMPO[$clave])
                ? ($evidencias[self::IMAGENES_CAMPO[$clave]] ?? null)
                : null;

            $trabajo = $this->maquinaTrabajo->abrir([
                'uuid_cliente' => $this->uuid('trabajo', $clave),
                'orden_id' => $orden->id,
                'lote_id' => $lote->id,
                'nro_aplicacion' => $orden->nro_aplicacion,
                'hectareas_declaradas' => '0',
                'inicio' => $fecha.' 10:00:00',
                'litros_sobrante' => $litrosSobrante,
                'imagen_campo_evidencia_id' => $imagenCampo?->id,
            ]);

            $this->autoria($trabajo, $autorId);

            $this->crear(new RecepcionCaldo([
                'uuid_cliente' => $this->uuid('caldo', $clave),
                'trabajo_id' => $trabajo->id,
                'litros' => $litrosRecibidos,
                'entregado_por' => $entregadoPor,
                'hora' => $fecha.' 09:40:00',
            ]), $autorId);

            $trabajos[$clave] = $trabajo;
        }

        return $trabajos;
    }

    /**
     * Abre, documenta, cierra y valida cada sesión — por las máquinas de
     * estado y el caso de uso reales (invariantes 3 y 7), nunca escribiendo
     * `estado` a mano. Validar es lo que dispara `SesionValidada` y, con él,
     * los devengos del piloto y su auxiliar.
     *
     * @param  array<string, Trabajo>  $trabajos
     * @param  array<string, int>  $personas
     * @param  array<string, int>  $drones
     * @param  array<string, Evidencia>  $evidencias
     */
    private function sembrarSesiones(array $trabajos, array $personas, array $drones, array $evidencias, int $autorId): void
    {
        $acumuladoPorDron = [];

        foreach (self::SESIONES as $clave => [$trabajoClave, $pilotoNombre, $auxiliarNombre, $dronId, $hectareas, $litros, $inicio, $fin, $motivo, $validadorNombre, $archivoCaptura]) {
            $trabajo = $trabajos[$trabajoClave] ?? null;
            $piloto = $personas[$pilotoNombre] ?? null;
            $validador = $personas[$validadorNombre] ?? null;

            if ($trabajo === null || $piloto === null || $validador === null) {
                continue;
            }

            $acumulado = $acumuladoPorDron[$dronId] ?? '0.00';

            $sesion = $this->maquinaSesion->abrir([
                'uuid_cliente' => $this->uuid('sesion', $clave),
                'trabajo_id' => $trabajo->id,
                'secuencia' => $this->secuenciaEnTrabajo($trabajoClave, $clave),
                'piloto_id' => $piloto,
                'auxiliar_id' => $auxiliarNombre === null ? null : ($personas[$auxiliarNombre] ?? null),
                'dron_id' => $drones[$dronId] ?? null,
                'hectarea_inicial_acumulada' => $acumulado,
                'inicio' => $inicio,
            ]);

            $this->autoria($sesion, $autorId);
            // `BigDecimal` y no `bcadd`: `ext-bcmath` no está instalada
            // (ni en el Dockerfile ni en CI), y aritmética decimal exacta
            // sobre hectáreas es innegociable (invariante 6).
            $acumuladoPorDron[$dronId] = (string) BigDecimal::of($acumulado)->plus($hectareas)->toScale(2);

            $this->condiciones($sesion, $trabajo, $clave, $inicio, $autorId);
            $this->recargas($sesion, $clave, $litros, $inicio, $autorId);
            $this->pausas($sesion, $clave, $autorId);

            $this->maquinaSesion->cerrar($sesion, $this->uuid('cierre', $clave), $fin, $motivo, $hectareas);

            $sesion->litros_consumidos = $litros;
            $sesion->captura_rc_id = ($evidencias[$archivoCaptura] ?? null)?->id;
            $sesion->save();

            $this->incidencia($sesion, $clave, $evidencias, $autorId);

            // Validar es lo que genera el devengo (invariante 3). El
            // validador nunca es el piloto de ESTA sesión (invariante 4): la
            // del propio jefe de campo la valida la encargada.
            $this->validarSesion->ejecutar($sesion, $validador);
        }
    }

    private function condiciones(Sesion $sesion, Trabajo $trabajo, string $clave, string $inicio, int $autorId): void
    {
        // Una sesión con condiciones forzadas por el agrónomo (S-06, la que
        // se cerró por clima) y el resto dentro de los límites del contrato.
        $forzada = $clave === 'S-06';

        $this->crear(new Condiciones([
            'uuid_cliente' => $this->uuid('condiciones', $clave),
            'trabajo_id' => $trabajo->id,
            'sesion_id' => $sesion->id,
            'momento' => 'inicio_sesion',
            'viento_kmh' => $forzada ? '21.00' : '11.00',
            'temperatura_c' => $forzada ? '33.00' : '26.00',
            'humedad_pct' => $forzada ? '68.00' : '86.00',
            'autorizado' => ! $forzada,
            'observacion_agronomo' => $forzada
                ? 'Viento por encima del límite del contrato. El agrónomo autoriza igual para no perder la ventana; se corta a los pocos minutos.'
                : null,
            'firma_observacion' => $forzada ? 'Ing. Agr. Daniel Terceros' : null,
        ]), $autorId);
    }

    private function recargas(Sesion $sesion, string $clave, string $litros, string $inicio, int $autorId): void
    {
        // Dos recargas por sesión; en la primera de S-08 la batería sale
        // caliente — es lo que sostiene la alerta `bateria_caliente`.
        $baterias = ['BAT-01', 'BAT-04'];
        $mitad = (string) BigDecimal::of($litros)->dividedBy('2', 2, RoundingMode::HalfUp);

        foreach ($baterias as $indice => $bateria) {
            $caliente = $clave === 'S-08' && $indice === 0;

            $this->crear(new Recarga([
                'uuid_cliente' => $this->uuid('recarga', $clave.'-'.$indice),
                'sesion_id' => $sesion->id,
                'secuencia' => $indice + 1,
                'litros_caldo' => $mitad,
                'bateria_saliente_id' => $bateria,
                'temperatura_bateria_c' => $caliente ? '68.50' : '42.00',
                'alerta_temperatura' => $caliente,
                // Código del catálogo cerrado de `ope_recargas_motivo_retraso_chk`
                // (filtro_tapado / grumos / decantacion / espuma /
                // color_olor_anormal), no una frase libre: la columna
                // describe QUÉ problema del caldo causó el retraso.
                'motivo_retraso_caldo' => $clave === 'S-03' && $indice === 1 ? 'filtro_tapado' : null,
                'hora_retraso' => $clave === 'S-03' && $indice === 1 ? $inicio : null,
                'litros_combustible_generador' => '4.00',
                'hora' => $inicio,
            ]), $autorId);
        }
    }

    private function pausas(Sesion $sesion, string $clave, int $autorId): void
    {
        // Solo dos sesiones tienen pausa: la pantalla de pausas por causa
        // atribuible (DS-01) necesita que se distinga quién la provocó.
        $catalogo = [
            'S-03' => [CausaPausa::Logistica, '2026-08-07 10:03:00', '2026-08-07 10:05:00', 2],
            'S-06' => [CausaPausa::Clima, '2026-08-16 10:24:00', '2026-08-16 10:27:00', 3],
        ];

        if (! isset($catalogo[$clave])) {
            return;
        }

        [$causa, $inicio, $fin, $minutos] = $catalogo[$clave];

        $this->crear(new Pausa([
            'sesion_id' => $sesion->id,
            'causa' => $causa,
            'inicio' => $inicio,
            'fin' => $fin,
            'duracion_minutos' => $minutos,
        ]), $autorId);
    }

    /**
     * @param  array<string, Evidencia>  $evidencias
     */
    private function incidencia(Sesion $sesion, string $clave, array $evidencias, int $autorId): void
    {
        if (! isset(self::INCIDENCIAS[$clave])) {
            return;
        }

        [$tipo, $descripcion, $hora, $archivo] = self::INCIDENCIAS[$clave];
        $foto = $evidencias[$archivo] ?? null;

        if ($foto === null) {
            return; // `evidencia_foto_id` es NOT NULL: sin foto no hay incidencia
        }

        $this->crear(new Incidencia([
            'uuid_cliente' => $this->uuid('incidencia', $clave),
            'sesion_id' => $sesion->id,
            'tipo' => $tipo,
            'descripcion' => $descripcion,
            'hora' => $hora,
            'evidencia_foto_id' => $foto->id,
        ]), $autorId);
    }

    /**
     * @param  array<string, Trabajo>  $trabajos
     */
    private function cerrarTrabajos(array $trabajos, int $autorId): void
    {
        foreach ($trabajos as $clave => $trabajo) {
            $ultimaSesion = Sesion::query()
                ->where('trabajo_id', $trabajo->id)
                ->orderByDesc('secuencia')
                ->first();

            if ($ultimaSesion?->fin === null) {
                continue;
            }

            $this->maquinaTrabajo->cerrar(
                $trabajo,
                $this->uuid('cierre-trabajo', $clave),
                $ultimaSesion->fin->toDateTimeString(),
            );

            // `hectareas_declaradas` del trabajo = suma de sus sesiones
            // validadas, que es como se factura (invariante 6: todo monto
            // derivado tiene que recalcularse desde su origen y cuadrar).
            $trabajo->hectareas_declaradas = (string) Sesion::query()
                ->where('trabajo_id', $trabajo->id)
                ->sum('hectareas_declaradas');
            $trabajo->updated_by = $autorId;
            $trabajo->save();
        }
    }

    /**
     * @param  array<string, Trabajo>  $trabajos
     * @param  array<string, Evidencia>  $evidencias
     */
    private function sembrarActasYReportes(array $trabajos, array $evidencias, int $autorId): void
    {
        foreach ($trabajos as $clave => $trabajo) {
            $acta = $this->generarActa->ejecutar($trabajo->refresh(), $this->uuid('acta', $clave));
            $this->autoria($acta, $autorId);

            if (isset(self::ACTAS_FIRMADAS[$clave])) {
                [$archivo, $firmante, $fechaFirma] = self::ACTAS_FIRMADAS[$clave];
                $firma = $evidencias[$archivo] ?? null;

                if ($firma !== null) {
                    // Por la máquina de estados y no por `FirmarActa`: ese
                    // caso de uso resuelve la evidencia por `uuid_cliente`,
                    // que acá ya está resuelta. El `estado` sigue pasando por
                    // el servicio de dominio, que es lo que exige la
                    // invariante 7 — nunca un `estado = ...` suelto.
                    $this->maquinaActa->firmar($acta, $firma->id, $firmante, $fechaFirma);
                }
            }

            $reporte = $this->generarReporte->ejecutar($trabajo->refresh());
            $this->autoria($reporte, $autorId);
        }
    }

    /**
     * "Reporte de Equipos" de HU-80 (ronda del dueño, 13/9/2026): un único
     * ejemplo, sobre T1, para que la sección tenga contenido real en la
     * demo. Reutiliza `rc_01.jpeg` (las 21 capturas ya están repartidas 1 a 1
     * entre `captura_rc`/`firma_acta`/`imagen_campo`/`foto_incidencia`, mismo
     * criterio del docblock de esta clase) — el contenido de la foto es
     * irrelevante para las tres evidencias de chequeo, así que copiarla tres
     * veces no inventa nada que el relato no pueda sostener.
     *
     * Los ciclos de batería del reporte NO se siembran acá: `BAT-01`/`BAT-04`
     * (`FlotaDemoSeeder`) ya son las que usan `recargas()` de todas las
     * sesiones — el contrato de lectura hacia `Mantenimiento` los resuelve
     * solos, sin dato adicional.
     */
    private function sembrarEvidenciaEquipo(Trabajo $trabajo, int $autorId): void
    {
        $archivo = 'rc_01.jpeg';
        $origen = base_path(self::ORIGEN_CAPTURAS.'/'.$archivo);

        if (! is_file($origen)) {
            return; // captura ausente del repo: se omite, no se inventa
        }

        $fecha = '2026-08-01 13:00:00';
        $fotos = [];

        foreach ([TipoEvidencia::FotoControl, TipoEvidencia::FotoCicloBateriaBalanceo, TipoEvidencia::FotoDronLimpio] as $tipo) {
            $uuidCliente = $this->uuid('evidencia', $tipo->value.'-T1');

            $evidencia = $this->crear(new Evidencia([
                'uuid_cliente' => $uuidCliente,
                'tipo' => $tipo,
                // Se completa abajo, mismo motivo que `sembrarEvidencias()`.
                'archivo_url' => $uuidCliente,
                'hash' => (string) hash_file('sha256', $origen),
                'fecha' => $fecha,
            ]), $autorId);

            $ruta = sprintf(
                'evidencias/%s/%s/%s-%d.jpeg',
                $tipo->value,
                date('Y/m', (int) strtotime($fecha)),
                $uuidCliente,
                $evidencia->id,
            );

            Storage::disk('r2')->put($ruta, (string) file_get_contents($origen));
            $evidencia->archivo_url = $ruta;
            $evidencia->save();

            $fotos[$tipo->value] = $evidencia;
        }

        $this->crear(new EvidenciaEquipo([
            'uuid_cliente' => $this->uuid('evidencia_equipo', 'T1'),
            'trabajo_id' => $trabajo->id,
            'horas_vuelo_dron' => '38.50',
            'foto_control_id' => $fotos[TipoEvidencia::FotoControl->value]->id,
            'foto_ciclo_bateria_balanceo_id' => $fotos[TipoEvidencia::FotoCicloBateriaBalanceo->value]->id,
            'foto_dron_limpio_id' => $fotos[TipoEvidencia::FotoDronLimpio->value]->id,
        ]), $autorId);
    }

    /**
     * Tres alertas que salen de datos ya sembrados: la batería que salió a
     * 68,5 °C en S-08, las condiciones forzadas de S-06 y el dron que quedó
     * con una diferencia de acumulado tras la falla de equipo.
     *
     * @param  array<string, Trabajo>  $trabajos
     * @param  array<string, int>  $personas
     * @param  array<string, int>  $drones
     */
    private function sembrarAlertas(array $trabajos, array $personas, array $drones, int $autorId): void
    {
        $sesionPorClave = fn (string $clave): ?Sesion => Sesion::query()
            ->where('uuid_cliente', $this->uuid('sesion', $clave))
            ->first();

        $s08 = $sesionPorClave('S-08');
        $s06 = $sesionPorClave('S-06');

        if ($s08 !== null) {
            $this->crear(new Alerta([
                'tipo' => TipoAlerta::BateriaCaliente,
                'trabajo_id' => $trabajos['T8']->id ?? null,
                'sesion_id' => $s08->id,
                'dron_id' => $drones['AG-09'] ?? null,
                'mensaje' => 'BAT-01 salió del dron a 68,5 °C durante la sesión S-08. Retirar de rotación hasta enfriar y revisar.',
                'estado' => EstadoAlerta::Pendiente,
            ]), $autorId);

            $this->crear(new Alerta([
                'tipo' => TipoAlerta::DronSospechoso,
                'trabajo_id' => $trabajos['T8']->id ?? null,
                'sesion_id' => $s08->id,
                'dron_id' => $drones['AG-09'] ?? null,
                'mensaje' => 'AG-09 cerró la sesión por falla de equipo con solo 1,82 ha aplicadas y 11 L consumidos: relación litros/hectárea fuera de rango.',
                'estado' => EstadoAlerta::Atendida,
                'atendida_por' => $personas['Jorge Richard Scheidel Dorado'] ?? null,
                'atendida_en' => '2026-08-23 13:00:00',
            ]), $autorId);
        }

        if ($s06 !== null) {
            $this->crear(new Alerta([
                'tipo' => TipoAlerta::CondicionesForzadas,
                'trabajo_id' => $trabajos['T6']->id ?? null,
                'sesion_id' => $s06->id,
                'dron_id' => $drones['AG-07'] ?? null,
                'mensaje' => 'Sesión S-06 autorizada con viento de 21 km/h sobre un límite contractual de 18 km/h, bajo firma del agrónomo del cliente.',
                'estado' => EstadoAlerta::Pendiente,
            ]), $autorId);
        }
    }

    /**
     * Sigla de propiedad → prefijo del nombre en `com_propiedades` (ADR
     * 0020: sin `Campo` de por medio, el lote cuelga directo de la
     * propiedad).
     *
     * El código de lote NO identifica un lote: `L-01` y `L-03` existen a la
     * vez en San Jorge, El Carmen y Santa Rosa, porque cada cliente numera
     * los suyos desde uno. Sin la propiedad por delante, `TRABAJOS` terminaría
     * colgando el trabajo de El Carmen del lote homónimo de San Jorge — que
     * además no tiene orden vigente, así que el trabajo se caía en silencio.
     *
     * @var array<string, string>
     */
    private const CAMPOS = [
        'SM' => 'San Marcos',
        'EC' => 'El Carmen',
        'SR' => 'Santa Rosa',
        'SJ' => 'San Jorge',
    ];

    /**
     * Los lotes de la demo por una clave `SIGLA-codigo` estable.
     *
     * @return array<string, Lote>
     */
    private function lotesPorClave(): array
    {
        $porClave = [];

        foreach (Lote::query()->with('propiedad')->get() as $lote) {
            $nombrePropiedad = $lote->propiedad?->nombre ?? '';

            foreach (self::CAMPOS as $sigla => $prefijo) {
                if (! str_starts_with($nombrePropiedad, $prefijo)) {
                    continue;
                }

                $porClave[$sigla.'-'.substr($lote->codigo, 2)] ??= $lote;

                break;
            }
        }

        return $porClave;
    }

    private function secuenciaEnTrabajo(string $trabajoClave, string $sesionClave): int
    {
        $secuencia = 0;

        foreach (self::SESIONES as $clave => $fila) {
            if ($fila[0] !== $trabajoClave) {
                continue;
            }

            $secuencia++;

            if ($clave === $sesionClave) {
                return $secuencia;
            }
        }

        return 1;
    }

    private function pilotoDelTrabajo(string $trabajoClave): string
    {
        foreach (self::SESIONES as $fila) {
            if ($fila[0] === $trabajoClave) {
                return $fila[1];
            }
        }

        return '';
    }

    /**
     * UUID determinista a partir de una clave del seeder: dos corridas
     * producen el mismo `uuid_cliente`, así que la idempotencia por
     * `UNIQUE (uuid_cliente)` (invariante 1) funciona igual que con un
     * dispositivo real reintentando un envío.
     */
    private function uuid(string $tipo, string $clave): string
    {
        $hash = md5("agrocom-demo:{$tipo}:{$clave}");

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hash, 0, 8),
            substr($hash, 8, 4),
            substr($hash, 12, 4),
            substr($hash, 16, 4),
            substr($hash, 20, 12),
        );
    }

    /**
     * @template TModelo of ModeloDominio
     *
     * @param  TModelo  $modelo
     * @return TModelo
     */
    private function crear(ModeloDominio $modelo, int $autorId): ModeloDominio
    {
        $modelo->created_by = $autorId;
        $modelo->updated_by = $autorId;
        $modelo->save();

        return $modelo;
    }

    /**
     * Autoría sobre un modelo que ya persistió una máquina de estados o un
     * caso de uso: en un seeder no hay usuario autenticado, así que
     * `RegistraAutoria` la habría dejado en NULL.
     */
    private function autoria(ModeloDominio $modelo, int $autorId): void
    {
        $modelo->created_by ??= $autorId;
        $modelo->updated_by = $autorId;
        $modelo->save();
    }
}
