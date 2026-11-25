<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

/**
 * Lógica de integridad en la base (docs/gestion/auditoria-calidad.md, lotes L3 y L4):
 *
 * - `neg_vencer_ofertas(ahora)`: vence por lote las ofertas vigentes cuyo plazo pasó
 *   y devuelve (oferta_id, solicitud_id) para que el servicio anuncie lo que toca.
 *   Respeta el invariante 12: bloquea primero las solicitudes (en orden de id) y
 *   después las ofertas. Deja su rastro en `plt_bitacoras` como lo hace el observer.
 * - `plt_registrar_bitacora()`: trigger genérico de bitácora (invariante 9) para las
 *   tablas de registros del lote y de negociación que no pasan por `RegistraBitacora`,
 *   de modo que ni un `UPDATE` por SQL directo queda sin rastro. El actor llega con
 *   `SET LOCAL app.user_id` o, si no, con `updated_by`/`created_by` de la fila.
 * - `ope_sesiones_inmutable_validada()`: una sesión validada no se reescribe ni se
 *   borra (invariante 2). Solo admite la marca `anulada_en` y el borrado lógico.
 *
 * Solo PostgreSQL: SQLite no tiene funciones ni triggers y las pruebas unitarias no migran.
 * `down()` retira todo lo creado y no toca datos; las filas de bitácora ya escritas se conservan.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLAS_CON_TRIGGER_DE_BITACORA = [
        'neg_evidencias',
        'neg_pagos_confirmados',
        'ope_condiciones',
        'ope_evidencias',
        'ope_evidencias_equipo',
        'ope_incidencias',
        'ope_pausas',
        'ope_recargas',
        'ope_recepciones_caldo',
        'ope_reportes_tecnicos',
    ];

    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $p = DB::getTablePrefix();

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION {$p}neg_vencer_ofertas(p_ahora timestamp)
            RETURNS TABLE (oferta_id bigint, solicitud_id bigint)
            LANGUAGE plpgsql AS \$fn\$
            BEGIN
                -- Invariante 12: primero la fila de cada solicitud afectada, en orden de id.
                PERFORM 1
                FROM {$p}sol_solicitudes_servicio s
                WHERE s.id IN (
                    SELECT o.solicitud_id FROM {$p}neg_ofertas o
                    WHERE o.estado = 'vigente' AND o.vence_en <= p_ahora AND o.deleted_at IS NULL
                )
                ORDER BY s.id
                FOR UPDATE;

                RETURN QUERY
                WITH vencidas AS (
                    UPDATE {$p}neg_ofertas o
                    SET estado = 'vencida', updated_at = p_ahora
                    WHERE o.id IN (
                        SELECT x.id FROM {$p}neg_ofertas x
                        WHERE x.estado = 'vigente' AND x.vence_en <= p_ahora AND x.deleted_at IS NULL
                        ORDER BY x.id
                        FOR UPDATE
                    )
                    AND o.estado = 'vigente'
                    RETURNING o.id, o.solicitud_id
                ), rastro AS (
                    INSERT INTO {$p}plt_bitacoras (user_id, tabla, registro_id, accion, antes, despues, created_at)
                    SELECT NULL, 'neg_ofertas', v.id, 'actualizado',
                           jsonb_build_object('estado', 'vigente'), jsonb_build_object('estado', 'vencida'), p_ahora
                    FROM vencidas v
                )
                SELECT v.id, v.solicitud_id FROM vencidas v ORDER BY v.id;
            END;
            \$fn\$
        SQL);

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION {$p}plt_registrar_bitacora()
            RETURNS trigger
            LANGUAGE plpgsql AS \$fn\$
            DECLARE
                v_prefijo text := COALESCE(TG_ARGV[0], '');
                v_tabla text := substr(TG_TABLE_NAME, length(v_prefijo) + 1);
                -- Ruido de plataforma y columnas sensibles: nunca entran a antes/despues.
                v_excluir text[] := ARRAY['created_at', 'updated_at', 'created_by', 'updated_by', 'password', 'token', 'remember_token'];
                v_nuevo jsonb;
                v_viejo jsonb;
                v_antes jsonb;
                v_despues jsonb;
                v_accion text;
                v_actor bigint;
            BEGIN
                v_nuevo := to_jsonb(NEW) - v_excluir;

                IF TG_OP = 'INSERT' THEN
                    v_accion := 'creado';
                    v_despues := v_nuevo;
                ELSE
                    v_viejo := to_jsonb(OLD) - v_excluir;

                    IF v_viejo = v_nuevo THEN
                        RETURN NEW;
                    END IF;

                    SELECT jsonb_object_agg(n.key, n.value), jsonb_object_agg(n.key, v_viejo -> n.key)
                    INTO v_despues, v_antes
                    FROM jsonb_each(v_nuevo) n
                    WHERE v_viejo -> n.key IS DISTINCT FROM n.value;

                    IF OLD.deleted_at IS NULL AND NEW.deleted_at IS NOT NULL THEN
                        v_accion := 'eliminado';
                        v_antes := jsonb_build_object('deleted_at', NULL);
                        v_despues := jsonb_build_object('deleted_at', to_jsonb(NEW.deleted_at));
                    ELSE
                        v_accion := 'actualizado';
                    END IF;
                END IF;

                v_actor := NULLIF(current_setting('app.user_id', true), '')::bigint;
                IF v_actor IS NULL THEN
                    v_actor := COALESCE(NULLIF(to_jsonb(NEW) ->> 'updated_by', '')::bigint, NULLIF(to_jsonb(NEW) ->> 'created_by', '')::bigint);
                END IF;
                IF v_actor IS NOT NULL AND NOT EXISTS (SELECT 1 FROM {$p}sec_user u WHERE u.id = v_actor) THEN
                    v_actor := NULL;
                END IF;

                INSERT INTO {$p}plt_bitacoras (user_id, tabla, registro_id, accion, antes, despues, created_at)
                VALUES (v_actor, v_tabla, NEW.id, v_accion, v_antes, v_despues, now());

                RETURN NEW;
            END;
            \$fn\$
        SQL);

        foreach (self::TABLAS_CON_TRIGGER_DE_BITACORA as $tabla) {
            DB::unprepared("CREATE TRIGGER {$p}{$tabla}_bitacora AFTER INSERT OR UPDATE ON {$p}{$tabla} FOR EACH ROW EXECUTE FUNCTION {$p}plt_registrar_bitacora('{$p}')");
        }

        DB::unprepared(<<<SQL
            CREATE OR REPLACE FUNCTION {$p}ope_sesiones_inmutable_validada()
            RETURNS trigger
            LANGUAGE plpgsql AS \$fn\$
            DECLARE
                -- Lo único que admite una sesión validada: la marca de anulación y el borrado lógico.
                v_libres text[] := ARRAY['anulada_en', 'deleted_at', 'updated_at', 'updated_by'];
            BEGIN
                IF OLD.estado <> 'validado' THEN
                    RETURN CASE TG_OP WHEN 'DELETE' THEN OLD ELSE NEW END;
                END IF;

                IF TG_OP = 'DELETE' THEN
                    RAISE EXCEPTION 'La sesión % ya está validada y no se borra', OLD.id USING ERRCODE = '55000';
                END IF;

                IF (to_jsonb(NEW) - v_libres) IS DISTINCT FROM (to_jsonb(OLD) - v_libres) THEN
                    RAISE EXCEPTION 'La sesión % ya está validada: una corrección es un registro nuevo', OLD.id USING ERRCODE = '55000';
                END IF;

                RETURN NEW;
            END;
            \$fn\$
        SQL);

        DB::unprepared("CREATE TRIGGER {$p}ope_sesiones_inmutable_validada BEFORE UPDATE OR DELETE ON {$p}ope_sesiones FOR EACH ROW EXECUTE FUNCTION {$p}ope_sesiones_inmutable_validada()");
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        $p = DB::getTablePrefix();

        DB::unprepared("DROP TRIGGER IF EXISTS {$p}ope_sesiones_inmutable_validada ON {$p}ope_sesiones");
        DB::unprepared("DROP FUNCTION IF EXISTS {$p}ope_sesiones_inmutable_validada()");

        foreach (self::TABLAS_CON_TRIGGER_DE_BITACORA as $tabla) {
            DB::unprepared("DROP TRIGGER IF EXISTS {$p}{$tabla}_bitacora ON {$p}{$tabla}");
        }

        DB::unprepared("DROP FUNCTION IF EXISTS {$p}plt_registrar_bitacora()");
        DB::unprepared("DROP FUNCTION IF EXISTS {$p}neg_vencer_ofertas(timestamp)");
    }
};
