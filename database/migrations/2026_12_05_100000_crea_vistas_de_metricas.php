<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Vistas `vw_metricas_*` de solo lectura sobre las que el módulo `Metricas`
 * agrega sus cifras (docs/gestion/auditoria-calidad.md, lote L2; ADR 0012).
 * Cada vista da una fila por hecho (solicitud, oferta, resolución…) ya
 * filtrada por borrado lógico, y deja al módulo la agregación por período.
 *
 * El SQL es el mismo en PostgreSQL y SQLite (las pruebas unitarias corren en
 * SQLite en memoria); solo difiere la expresión de minutos entre dos instantes.
 * Dinero y hectáreas viajan como DECIMAL sin conversión (invariante 6).
 */
return new class extends Migration
{
    private const VISTAS = [
        'vw_metricas_solicitudes',
        'vw_metricas_aceptaciones',
        'vw_metricas_negociaciones',
        'vw_metricas_finalizaciones',
        'vw_metricas_respuestas_proveedor',
        'vw_metricas_ofertas_proveedor',
        'vw_metricas_cancelaciones_proveedor',
        'vw_metricas_catalogo',
        'vw_metricas_calificaciones',
        'vw_metricas_disputas',
        'vw_metricas_resoluciones',
    ];

    public function up(): void
    {
        $p = DB::getTablePrefix();
        $minutos = fn (string $desde, string $hasta): string => $this->minutosEntre($desde, $hasta);

        DB::statement(<<<SQL
            CREATE VIEW {$p}vw_metricas_solicitudes AS
            SELECT s.id AS solicitud_id, s.publicada_en, s.adjudicada_en, s.estado, s.proveedor_id,
                   COALESCE(z.macrorregion, 'sin_region') AS region,
                   s.hectareas_solicitadas, s.hectareas_ejecutadas, s.monto_final
            FROM {$p}sol_solicitudes_servicio s
            LEFT JOIN {$p}sol_terrenos t ON t.id = s.terreno_id
            LEFT JOIN {$p}sol_zonas z ON z.id = t.zona_id
            WHERE s.deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE VIEW {$p}vw_metricas_aceptaciones AS
            SELECT o.id AS oferta_id, s.id AS solicitud_id, o.proveedor_id, s.publicada_en, s.adjudicada_en,
                   {$minutos('s.publicada_en', 's.adjudicada_en')} AS minutos
            FROM {$p}neg_ofertas o
            JOIN {$p}sol_solicitudes_servicio s ON s.id = o.solicitud_id AND o.id = s.oferta_adjudicada_id
            JOIN {$p}prv_proveedores p ON p.id = o.proveedor_id
            WHERE o.deleted_at IS NULL
              AND o.estado IN ('aceptada', 'cerrada')
              AND s.publicada_en IS NOT NULL
              AND s.adjudicada_en IS NOT NULL
        SQL);

        DB::statement(<<<SQL
            CREATE VIEW {$p}vw_metricas_negociaciones AS
            SELECT solicitud_id, proveedor_id, MAX(numero) AS rondas, COUNT(*) AS ofertas
            FROM {$p}neg_ofertas
            WHERE deleted_at IS NULL
            GROUP BY solicitud_id, proveedor_id
        SQL);

        DB::statement(<<<SQL
            CREATE VIEW {$p}vw_metricas_finalizaciones AS
            SELECT solicitud_id, created_at AS finalizada_en
            FROM {$p}sol_solicitud_historial
            WHERE deleted_at IS NULL AND estado_nuevo = 'finalizada'
        SQL);

        DB::statement(<<<SQL
            CREATE VIEW {$p}vw_metricas_respuestas_proveedor AS
            SELECT id AS oferta_id, proveedor_id, created_at AS ofrecida_en, respondida_en,
                   {$minutos('created_at', 'respondida_en')} AS minutos
            FROM {$p}neg_ofertas
            WHERE deleted_at IS NULL AND autor = 'cliente' AND respondida_en IS NOT NULL
        SQL);

        DB::statement(<<<SQL
            CREATE VIEW {$p}vw_metricas_ofertas_proveedor AS
            SELECT id AS oferta_id, estado, created_at AS ofrecida_en, respondida_en
            FROM {$p}neg_ofertas
            WHERE deleted_at IS NULL AND autor = 'proveedor'
        SQL);

        DB::statement(<<<SQL
            CREATE VIEW {$p}vw_metricas_cancelaciones_proveedor AS
            SELECT id AS cancelacion_id, created_at AS cancelada_en
            FROM {$p}neg_cancelaciones
            WHERE deleted_at IS NULL AND parte = 'proveedor'
        SQL);

        DB::statement(<<<SQL
            CREATE VIEW {$p}vw_metricas_catalogo AS
            SELECT 'proveedor' AS clase, estado FROM {$p}prv_proveedores WHERE deleted_at IS NULL
            UNION ALL
            SELECT 'dron' AS clase, estado FROM {$p}flo_drones WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE VIEW {$p}vw_metricas_calificaciones AS
            SELECT id AS calificacion_id, created_at AS calificada_en, estrellas, estado,
                   (autor_rol = 'cliente' AND sujeto_tipo = 'proveedor') AS es_de_cliente_a_proveedor
            FROM {$p}cal_calificaciones
            WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE VIEW {$p}vw_metricas_disputas AS
            SELECT id AS disputa_id, created_at AS abierta_en, estado
            FROM {$p}dsp_disputas
            WHERE deleted_at IS NULL
        SQL);

        DB::statement(<<<SQL
            CREATE VIEW {$p}vw_metricas_resoluciones AS
            SELECT disputa_id, ronda, resuelta_en, monto_reembolso, comision_ajuste
            FROM {$p}dsp_resoluciones
            WHERE deleted_at IS NULL
        SQL);
    }

    public function down(): void
    {
        $p = DB::getTablePrefix();

        foreach (array_reverse(self::VISTAS) as $vista) {
            DB::statement("DROP VIEW IF EXISTS {$p}{$vista}");
        }
    }

    /** Minutos de `desde` a `hasta`, sin bajar de cero (un reloj atrasado no resta tiempo). */
    private function minutosEntre(string $desde, string $hasta): string
    {
        $segundos = DB::getDriverName() === 'pgsql'
            ? "EXTRACT(EPOCH FROM ({$hasta} - {$desde}))"
            : "((julianday({$hasta}) - julianday({$desde})) * 86400.0)";

        return "(CASE WHEN {$segundos} < 0 THEN 0 ELSE {$segundos} END / 60.0)";
    }
};
