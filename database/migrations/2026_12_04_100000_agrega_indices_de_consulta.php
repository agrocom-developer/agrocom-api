<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Índices de las claves foráneas operativas que PostgreSQL no indexa solo
 * (docs/gestion/auditoria-calidad.md §3.1, lote L1) y de consulta de la bitácora.
 *
 * Las FK de autoría y de actor (created_by, updated_by, aprobado_por,
 * subido_por, autor_usuario_id...) no se indexan: no se consultan por ahí y
 * apuntan a tablas que se borran de forma lógica, así que el índice solo
 * costaría escritura. Los de tablas con soft delete son parciales
 * (WHERE deleted_at IS NULL), como las claves únicas del proyecto.
 */
return new class extends Migration
{
    /** @var array<string, array{0: string, 1: string, 2: bool}> nombre => [tabla, columnas, parcial] */
    private const INDICES = [
        'sol_solicitudes_servicio_proveedor_idx' => ['sol_solicitudes_servicio', 'proveedor_id', true],
        'sol_solicitudes_servicio_terreno_idx' => ['sol_solicitudes_servicio', 'terreno_id', true],
        'sol_terrenos_zona_idx' => ['sol_terrenos', 'zona_id', true],
        'sol_precios_referencia_zona_idx' => ['sol_precios_referencia', 'zona_id', true],
        'prv_proveedores_zona_base_idx' => ['prv_proveedores', 'zona_base_id', true],
        'prv_auxiliares_registrado_por_piloto_idx' => ['prv_auxiliares', 'registrado_por_piloto_id', true],
        'flo_equipos_auxiliar_idx' => ['flo_equipos', 'auxiliar_id', true],
        'neg_ofertas_equipo_idx' => ['neg_ofertas', 'equipo_id', true],
        'neg_ofertas_oferta_anterior_idx' => ['neg_ofertas', 'oferta_anterior_id', true],
        'neg_cancelaciones_solicitud_idx' => ['neg_cancelaciones', 'solicitud_id', true],
        'neg_evidencias_avance_idx' => ['neg_evidencias', 'avance_id', true],
        'ope_sesiones_dron_idx' => ['ope_sesiones', 'dron_id', true],
        'ope_sesiones_auxiliar_idx' => ['ope_sesiones', 'auxiliar_id', true],
        'ope_alertas_sesion_idx' => ['ope_alertas', 'sesion_id', true],
        // plt_bitacoras no tiene soft delete: es append-only.
        'plt_bitacoras_tabla_fecha_idx' => ['plt_bitacoras', 'tabla, created_at', false],
        'plt_bitacoras_usuario_fecha_idx' => ['plt_bitacoras', 'user_id, created_at', false],
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $prefijo = DB::getTablePrefix();

        foreach (self::INDICES as $nombre => [$tabla, $columnas, $parcial]) {
            $donde = $parcial ? ' WHERE deleted_at IS NULL' : '';

            DB::statement("CREATE INDEX IF NOT EXISTS {$prefijo}{$nombre} ON {$prefijo}{$tabla} ({$columnas}){$donde}");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $prefijo = DB::getTablePrefix();

        foreach (array_keys(self::INDICES) as $nombre) {
            DB::statement("DROP INDEX IF EXISTS {$prefijo}{$nombre}");
        }
    }
};
