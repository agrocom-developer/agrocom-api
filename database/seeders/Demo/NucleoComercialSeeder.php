<?php

namespace Database\Seeders\Demo;

use App\Dominios\Campania\Dominio\EstadoCampania;
use App\Dominios\Campania\Infraestructura\Eloquent\Campania;
use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Dominio\TipoContactoCliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\ClienteContacto;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\ContratoVentana;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Compartido\Infraestructura\Eloquent\RegistraAutoria;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use Illuminate\Database\Seeder;

/**
 * Demo del núcleo comercial: un cliente con su campaña `2025-2026` (`abierta`,
 * ADR 0015 punto 1), contrato vigente, campo, lotes y una orden de aplicación
 * vigente — lo mínimo para que el flujo transaccional (orden → trabajo →
 * sesión → validación → devengo) tenga dónde arrancar.
 *
 * Los números son los del escenario base del contrato residente
 * (docs/negocio/ventana_al_negocio.md §1–2): 4.000 ha × 7 aplicaciones
 * a 65 Bs/ha = Bs 1.820.000, adelanto del 35% (Bs 637.000), soya en el
 * este cruceño; velocidad ≤ 15 km/h impuesta por el cliente (RF-60).
 *
 * Escribe por los modelos Eloquent de cada módulo (regla dura del ADR 0012).
 * La autoría va explícita: en seeders no hay usuario autenticado, así que
 * {@see RegistraAutoria} dejaría `created_by`/`updated_by` en NULL — acá se
 * asignan a la cuenta de {@see PersonalDemoSeeder}, que corre antes.
 *
 * Ese autor era hasta ahora un `SecUser` llamado `demo`, creado acá mismo y
 * en ningún otro lado: una cuenta sin persona, sin roles y sin nadie detrás,
 * que existía solo para firmar estas filas. Se retiró junto con `admin`
 * (nunca sembrado, creado a mano en alguna base local) — las cuentas de la
 * demo son ahora personas de la cuadrilla, con su rol y su tarifa.
 */
class NucleoComercialSeeder extends Seeder
{
    private const NIT_DEMO = '1023456022';

    public function run(): void
    {
        // Idempotente: si el cliente demo ya existe, no duplica nada.
        if (Cliente::query()->where('nit', self::NIT_DEMO)->exists()) {
            return;
        }

        $autorId = PersonalDemoSeeder::autorId();

        $cliente = $this->crear(new Cliente([
            'razon_social' => 'Agropecuaria San Jorge S.R.L.',
            'nit' => self::NIT_DEMO,
        ]), $autorId);

        // Campaña del cliente (ADR 0015 punto 1, tarea 69): `com_contratos.campania_id`
        // es NOT NULL, así que todo contrato demo necesita la suya.
        $campania = $this->crear(new Campania([
            'cliente_id' => $cliente->id,
            'codigo' => '2025-2026',
            'fecha_inicio' => '2025-07-01',
            'fecha_fin' => '2026-06-30',
            'estado' => EstadoCampania::Abierta,
        ]), $autorId);

        $this->crear(new ClienteContacto([
            'cliente_id' => $cliente->id,
            'tipo' => TipoContactoCliente::Dueno,
            'nombre' => 'Jorge Antelo Suárez',
            'telefono' => '+591 70000001',
            'email' => 'jantelo@sanjorge.example',
            'observaciones' => null,
        ]), $autorId);

        $agronomo = $this->crear(new ClienteContacto([
            'cliente_id' => $cliente->id,
            'tipo' => TipoContactoCliente::Agronomo,
            'nombre' => 'Ing. Agr. Carla Justiniano',
            'telefono' => '+591 70000002',
            'email' => 'cjustiniano@sanjorge.example',
            'observaciones' => 'Emite las órdenes de aplicación y firma las actas.',
        ]), $autorId);

        $this->crear(new ClienteContacto([
            'cliente_id' => $cliente->id,
            'tipo' => TipoContactoCliente::EncargadoPropiedad,
            'nombre' => 'Ramón Chávez',
            'telefono' => '+591 70000003',
            'email' => null,
            'observaciones' => 'Indica los lotes, prepara caldo y ordena pausas. No firma documentos.',
        ]), $autorId);

        // Escenario base: 4.000 ha × 7 aplicaciones × 65 Bs/ha = 1.820.000 Bs.
        // El monto_total cuadra exacto desde sus factores (invariante 6).
        $contrato = $this->crear(new Contrato([
            'cliente_id' => $cliente->id,
            'campania_id' => $campania->id,
            'hectareas_contratadas' => '4000.00',
            'aplicaciones_previstas' => 7,
            'precio_ha' => '65.00',
            'monto_total' => '1820000.00',
            'adelanto_pct' => '35.00',
            'adelanto_monto' => '637000.00',
            'fecha_inicio' => '2026-08-01',
            'fecha_fin' => '2026-12-31',
            'estado' => EstadoContrato::Vigente,
            'viento_max_kmh' => '17.00',
            'temperatura_max_c' => '30.00',
            'humedad_min_pct' => '80.00',
            'humedad_max_pct' => '95.00',
            'velocidad_max_kmh' => '15.00',
            'umbral_reporte_avance_ha' => '500.00',
        ]), $autorId);

        // Ventanas horarias permitidas: 06:00–10:00 y 16:00–20:00 (insumos §7.1).
        foreach ([['06:00', '10:00'], ['16:00', '20:00']] as [$inicio, $fin]) {
            $this->crear(new ContratoVentana([
                'contrato_id' => $contrato->id,
                'hora_inicio' => $inicio,
                'hora_fin' => $fin,
            ]), $autorId);
        }

        $campo = $this->crear(new Campo([
            'cliente_id' => $cliente->id,
            'nombre' => 'San Jorge — Cuatro Cañadas',
            'ubicacion' => 'Este cruceño, km 12 camino a Cuatro Cañadas, Santa Cruz, Bolivia',
        ]), $autorId);

        $lotePrimero = $this->crear(new Lote([
            'campo_id' => $campo->id,
            'codigo' => 'L-01',
            'hectareas' => '250.00',
            'geometria' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [-62.8600, -17.3300],
                    [-62.8450, -17.3300],
                    [-62.8450, -17.3450],
                    [-62.8600, -17.3450],
                    [-62.8600, -17.3300],
                ]],
            ],
            'restricciones' => 'Línea de media tensión al norte; vecino con colmenas al este.',
        ]), $autorId);

        $this->crear(new Lote([
            'campo_id' => $campo->id,
            'codigo' => 'L-02',
            'hectareas' => '180.50',
            'geometria' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [-62.8440, -17.3300],
                    [-62.8330, -17.3300],
                    [-62.8330, -17.3440],
                    [-62.8440, -17.3440],
                    [-62.8440, -17.3300],
                ]],
            ],
            'restricciones' => 'Viviendas del casco de la propiedad al sur.',
        ]), $autorId);

        $this->crear(new Lote([
            'campo_id' => $campo->id,
            'codigo' => 'L-03',
            'hectareas' => '320.75',
            'geometria' => [
                'type' => 'Polygon',
                'coordinates' => [[
                    [-62.8600, -17.3460],
                    [-62.8400, -17.3460],
                    [-62.8400, -17.3620],
                    [-62.8600, -17.3620],
                    [-62.8600, -17.3460],
                ]],
            ],
            'restricciones' => 'Cortina rompevientos perimetral; sector anegadizo al oeste.',
        ]), $autorId);

        // Orden de aplicación vigente para L-01: requisito para abrir un trabajo.
        $this->crear(new OrdenAplicacion([
            'contrato_id' => $contrato->id,
            'lote_id' => $lotePrimero->id,
            'nro_aplicacion' => 1,
            'litros_ha' => '10.00',
            'humedad_min_pct' => '80.00',
            'altura_vuelo_m' => '3.00',
            'velocidad_vuelo_kmh' => '15.00',
            'ancho_pasada_m' => '6.00',
            'observaciones' => 'Primera aplicación de la campaña: fungicida + insecticida según receta del agrónomo.',
            'emitida_por_contacto_id' => $agronomo->id,
            'fecha_emision' => '2026-08-25',
            'estado' => EstadoOrdenAplicacion::Vigente,
        ]), $autorId);
    }

    /**
     * Persiste el modelo con la autoría demo explícita. `created_by` /
     * `updated_by` no son fillable (los completa RegistraAutoria desde el
     * usuario autenticado), así que se asignan como propiedades antes del save.
     *
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
}
