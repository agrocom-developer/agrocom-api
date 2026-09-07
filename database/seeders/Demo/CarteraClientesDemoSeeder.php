<?php

namespace Database\Seeders\Demo;

use App\Dominios\Comercial\Dominio\EstadoContrato;
use App\Dominios\Comercial\Dominio\TipoContactoCliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\Campo;
use App\Dominios\Comercial\Infraestructura\Eloquent\Cliente;
use App\Dominios\Comercial\Infraestructura\Eloquent\ClienteContacto;
use App\Dominios\Comercial\Infraestructura\Eloquent\Contrato;
use App\Dominios\Comercial\Infraestructura\Eloquent\ContratoVentana;
use App\Dominios\Comercial\Infraestructura\Eloquent\Lote;
use App\Dominios\Compartido\Infraestructura\Eloquent\ModeloDominio;
use App\Dominios\Operaciones\Dominio\EstadoOrdenAplicacion;
use App\Dominios\Operaciones\Infraestructura\Eloquent\OrdenAplicacion;
use App\Dominios\Seguridad\Infraestructura\Http\Demo\DatosDemoCapturasRc;
use Illuminate\Database\Seeder;

/**
 * Los otros tres clientes de la cartera, además de Agropecuaria San Jorge
 * (que siembra `NucleoComercialSeeder` y del que dependen varios tests, así
 * que no se toca).
 *
 * Los nombres no son decorativos: San Marcos, El Carmen y Santa Rosa son los
 * campos que ya aparecían en {@see DatosDemoCapturasRc},
 * el relato con el que se agruparon las 21 capturas reales del control remoto.
 * Sembrarlos con esa identidad es lo que permite que `OperacionDemoSeeder`
 * convierta ese relato en filas de verdad —trabajos y sesiones sobre estos
 * lotes, con las cifras de rendimiento reales transcritas en
 * `docs/especificacion/analisis_capturas_rc.md` §8— en vez de dejarlo como
 * presentación mock.
 *
 * Cada contrato cuadra exacto desde sus factores (invariante 6):
 * `monto_total = hectareas_contratadas × aplicaciones_previstas × precio_ha`,
 * y `adelanto_monto = monto_total × adelanto_pct`. Los tres quedan
 * `vigente`, con ventanas horarias y una orden de aplicación por lote — sin
 * eso no se puede abrir un trabajo.
 *
 * Idempotente: si el primer cliente ya existe, no hace nada (mismo criterio
 * que `NucleoComercialSeeder`).
 */
class CarteraClientesDemoSeeder extends Seeder
{
    private const NIT_CENTINELA = '1088340017';

    public function run(): void
    {
        if (Cliente::query()->where('nit', self::NIT_CENTINELA)->exists()) {
            return;
        }

        $autorId = PersonalDemoSeeder::autorId();

        $this->sanMarcos($autorId);
        $this->elCarmen($autorId);
        $this->santaRosa($autorId);
    }

    /**
     * Soya en Cuatro Cañadas. Contrato chico y de ciclo corto: 1.200 ha × 4
     * aplicaciones × 70 Bs/ha = 336.000 Bs, adelanto del 30%.
     */
    private function sanMarcos(int $autorId): void
    {
        $cliente = $this->crear(new Cliente([
            'razon_social' => 'Agrícola San Marcos S.R.L.',
            'nit' => self::NIT_CENTINELA,
        ]), $autorId);

        $agronomo = $this->contactos($cliente->id, $autorId, [
            [TipoContactoCliente::Dueno, 'Marcos Áñez Vaca', '+591 70100001', 'manez@sanmarcos.example', null],
            [TipoContactoCliente::Agronomo, 'Ing. Agr. Lorena Suárez', '+591 70100002', 'lsuarez@sanmarcos.example', 'Emite las órdenes y firma las actas.'],
        ]);

        $contrato = $this->crear(new Contrato([
            'cliente_id' => $cliente->id,
            'hectareas_contratadas' => '1200.00',
            'aplicaciones_previstas' => 4,
            'precio_ha' => '70.00',
            'monto_total' => '336000.00',
            'adelanto_pct' => '30.00',
            'adelanto_monto' => '100800.00',
            'fecha_inicio' => '2026-07-15',
            'fecha_fin' => '2026-11-30',
            'estado' => EstadoContrato::Vigente,
            'viento_max_kmh' => '15.00',
            'temperatura_max_c' => '32.00',
            'humedad_min_pct' => '75.00',
            'humedad_max_pct' => '95.00',
            'velocidad_max_kmh' => '15.00',
            'umbral_reporte_avance_ha' => '300.00',
        ]), $autorId);

        $this->ventanas($contrato->id, $autorId, [['05:30', '10:00'], ['16:30', '19:30']]);

        $campo = $this->crear(new Campo([
            'cliente_id' => $cliente->id,
            'nombre' => 'San Marcos — Cuatro Cañadas',
            'ubicacion' => 'Km 28 camino a Cuatro Cañadas, Santa Cruz, Bolivia',
        ]), $autorId);

        $lote = $this->crear(new Lote([
            'campo_id' => $campo->id,
            'codigo' => 'L-12',
            'hectareas' => '320.00',
            'geometria' => $this->poligono(-62.7900, -17.4100),
            'restricciones' => 'Canal de drenaje al norte; camino vecinal al este con tránsito de motos.',
        ]), $autorId);

        $this->orden($contrato->id, $lote->id, $agronomo, $autorId, [
            'nro_aplicacion' => 1,
            'litros_ha' => '12.00',
            'fecha_emision' => '2026-07-28',
            'observaciones' => 'Primera aplicación de la campaña: fungicida preventivo sobre soya en V4.',
        ]);
    }

    /**
     * El cliente grande: 2.500 ha × 5 aplicaciones × 62 Bs/ha = 775.000 Bs,
     * adelanto del 40%. Dos lotes activos — es el único con más de uno, y por
     * eso el que ejercita el reporte de avance por lote.
     */
    private function elCarmen(int $autorId): void
    {
        $cliente = $this->crear(new Cliente([
            'razon_social' => 'Sociedad Agrícola El Carmen S.A.',
            'nit' => '1099751028',
        ]), $autorId);

        $agronomo = $this->contactos($cliente->id, $autorId, [
            [TipoContactoCliente::Dueno, 'Fernando Roca Melgar', '+591 70200001', 'froca@elcarmen.example', null],
            [TipoContactoCliente::Agronomo, 'Ing. Agr. Daniel Terceros', '+591 70200002', 'dterceros@elcarmen.example', 'Define dosis y ventana de aplicación.'],
            [TipoContactoCliente::EncargadoPropiedad, 'Nelson Quispe', '+591 70200003', null, 'Indica los lotes y coordina la entrega de caldo. No firma documentos.'],
        ]);

        $contrato = $this->crear(new Contrato([
            'cliente_id' => $cliente->id,
            'hectareas_contratadas' => '2500.00',
            'aplicaciones_previstas' => 5,
            'precio_ha' => '62.00',
            'monto_total' => '775000.00',
            'adelanto_pct' => '40.00',
            'adelanto_monto' => '310000.00',
            'fecha_inicio' => '2026-07-01',
            'fecha_fin' => '2026-12-15',
            'estado' => EstadoContrato::Vigente,
            'viento_max_kmh' => '18.00',
            'temperatura_max_c' => '31.00',
            'humedad_min_pct' => '78.00',
            'humedad_max_pct' => '96.00',
            'velocidad_max_kmh' => '16.00',
            'umbral_reporte_avance_ha' => '500.00',
        ]), $autorId);

        $this->ventanas($contrato->id, $autorId, [['06:00', '10:30'], ['16:00', '20:00']]);

        $campo = $this->crear(new Campo([
            'cliente_id' => $cliente->id,
            'nombre' => 'El Carmen — Pailón',
            'ubicacion' => 'Km 9 camino a Pailón Norte, Santa Cruz, Bolivia',
        ]), $autorId);

        $loteTres = $this->crear(new Lote([
            'campo_id' => $campo->id,
            'codigo' => 'L-03',
            'hectareas' => '410.50',
            'geometria' => $this->poligono(-62.7400, -17.6500),
            'restricciones' => 'Colmenas del vecino al sureste: no aplicar con viento del noroeste.',
        ]), $autorId);

        $loteOcho = $this->crear(new Lote([
            'campo_id' => $campo->id,
            'codigo' => 'L-08',
            'hectareas' => '275.25',
            'geometria' => $this->poligono(-62.7200, -17.6650),
            'restricciones' => 'Línea de media tensión cruzando el lote de este a oeste.',
        ]), $autorId);

        $this->orden($contrato->id, $loteTres->id, $agronomo, $autorId, [
            'nro_aplicacion' => 1,
            'litros_ha' => '10.50',
            'fecha_emision' => '2026-07-30',
            'observaciones' => 'Insecticida por presencia de chinche. Respetar restricción de colmenas.',
        ]);

        $this->orden($contrato->id, $loteOcho->id, $agronomo, $autorId, [
            'nro_aplicacion' => 1,
            'litros_ha' => '9.15',
            'fecha_emision' => '2026-08-02',
            'observaciones' => 'Altura de vuelo 4 m por la línea de media tensión.',
            'altura_vuelo_m' => '4.00',
        ]);
    }

    /**
     * El chico: 800 ha × 3 aplicaciones × 75 Bs/ha = 180.000 Bs, adelanto del
     * 25%. Precio por hectárea más alto porque el lote es cerrado y con
     * obstáculos — menos hectáreas por hora de vuelo.
     */
    private function santaRosa(int $autorId): void
    {
        $cliente = $this->crear(new Cliente([
            'razon_social' => 'Estancia Santa Rosa S.R.L.',
            'nit' => '1071482039',
        ]), $autorId);

        $agronomo = $this->contactos($cliente->id, $autorId, [
            [TipoContactoCliente::Dueno, 'Rosa Melgar de Áñez', '+591 70300001', 'rmelgar@santarosa.example', 'Autoriza personalmente cada aplicación.'],
            [TipoContactoCliente::Agronomo, 'Ing. Agr. Pablo Cuéllar', '+591 70300002', 'pcuellar@santarosa.example', null],
        ]);

        $contrato = $this->crear(new Contrato([
            'cliente_id' => $cliente->id,
            'hectareas_contratadas' => '800.00',
            'aplicaciones_previstas' => 3,
            'precio_ha' => '75.00',
            'monto_total' => '180000.00',
            'adelanto_pct' => '25.00',
            'adelanto_monto' => '45000.00',
            'fecha_inicio' => '2026-08-05',
            'fecha_fin' => '2026-11-15',
            'estado' => EstadoContrato::Vigente,
            'viento_max_kmh' => '14.00',
            'temperatura_max_c' => '29.00',
            'humedad_min_pct' => '80.00',
            'humedad_max_pct' => '94.00',
            'velocidad_max_kmh' => '13.00',
            'umbral_reporte_avance_ha' => '200.00',
        ]), $autorId);

        $this->ventanas($contrato->id, $autorId, [['06:30', '09:30']]);

        $campo = $this->crear(new Campo([
            'cliente_id' => $cliente->id,
            'nombre' => 'Santa Rosa — Okinawa',
            'ubicacion' => 'Colonia Okinawa 1, Santa Cruz, Bolivia',
        ]), $autorId);

        $lote = $this->crear(new Lote([
            'campo_id' => $campo->id,
            'codigo' => 'L-01',
            'hectareas' => '190.00',
            'geometria' => $this->poligono(-62.9600, -17.2100),
            'restricciones' => 'Casco de la estancia y galpones al centro; cortina de eucaliptos perimetral.',
        ]), $autorId);

        $this->orden($contrato->id, $lote->id, $agronomo, $autorId, [
            'nro_aplicacion' => 1,
            'litros_ha' => '10.00',
            'fecha_emision' => '2026-08-08',
            'observaciones' => 'Lote cerrado: velocidad reducida y ancho de pasada menor por los obstáculos.',
            'velocidad_vuelo_kmh' => '13.00',
            'ancho_pasada_m' => '5.00',
        ]);
    }

    /**
     * Crea los contactos del cliente y devuelve el id del agrónomo — el
     * único que la orden de aplicación necesita (`emitida_por_contacto_id`).
     *
     * @param  list<array{TipoContactoCliente, string, string, string|null, string|null}>  $filas
     */
    private function contactos(int $clienteId, int $autorId, array $filas): int
    {
        $agronomoId = 0;

        foreach ($filas as [$tipo, $nombre, $telefono, $email, $observaciones]) {
            $contacto = $this->crear(new ClienteContacto([
                'cliente_id' => $clienteId,
                'tipo' => $tipo,
                'nombre' => $nombre,
                'telefono' => $telefono,
                'email' => $email,
                'observaciones' => $observaciones,
            ]), $autorId);

            if ($tipo === TipoContactoCliente::Agronomo) {
                $agronomoId = $contacto->id;
            }
        }

        return $agronomoId;
    }

    /**
     * @param  list<array{string, string}>  $rangos
     */
    private function ventanas(int $contratoId, int $autorId, array $rangos): void
    {
        foreach ($rangos as [$inicio, $fin]) {
            $this->crear(new ContratoVentana([
                'contrato_id' => $contratoId,
                'hora_inicio' => $inicio,
                'hora_fin' => $fin,
            ]), $autorId);
        }
    }

    /**
     * @param  array<string, string|int>  $datos
     */
    private function orden(int $contratoId, int $loteId, int $agronomoId, int $autorId, array $datos): void
    {
        $this->crear(new OrdenAplicacion([
            'contrato_id' => $contratoId,
            'lote_id' => $loteId,
            'humedad_min_pct' => '80.00',
            'altura_vuelo_m' => '3.00',
            'velocidad_vuelo_kmh' => '15.00',
            'ancho_pasada_m' => '6.00',
            'emitida_por_contacto_id' => $agronomoId,
            'estado' => EstadoOrdenAplicacion::Vigente,
            ...$datos,
        ]), $autorId);
    }

    /**
     * Rectángulo de ~1,5 km de lado desde la esquina noroeste dada. La
     * geometría real la trae el dispositivo; acá alcanza con que sea un
     * polígono cerrado y válido en la zona correcta del este cruceño.
     *
     * @return array{type: string, coordinates: list<list<list<float>>>}
     */
    private function poligono(float $oeste, float $norte): array
    {
        $este = $oeste + 0.0150;
        $sur = $norte - 0.0150;

        return [
            'type' => 'Polygon',
            'coordinates' => [[
                [$oeste, $norte],
                [$este, $norte],
                [$este, $sur],
                [$oeste, $sur],
                [$oeste, $norte],
            ]],
        ];
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
}
