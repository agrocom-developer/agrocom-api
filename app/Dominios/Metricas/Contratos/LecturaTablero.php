<?php

namespace App\Dominios\Metricas\Contratos;

/**
 * Frontera de lectura de `Metricas` hacia el panel (ADR 0003, regla 2): el
 * tablero de la torre de control y los contadores de pendientes del menú. Solo
 * lectura; los importes y las hectáreas viajan como cadenas decimales.
 */
interface LecturaTablero
{
    /**
     * Lo que espera una decisión del administrador, sin período: aprobaciones
     * pendientes, documentos y fotos por revisar, calificaciones reportadas y
     * disputas sin resolver.
     *
     * @return array{
     *     proveedores: int, pilotos: int, auxiliares: int, drones: int,
     *     documentos: int, fotos_dron: int, calificaciones: int, disputas: int
     * }
     */
    public function pendientes(): array;

    /**
     * Los períodos que ofrece el tablero, en días.
     *
     * @return list<int>
     */
    public function diasValidos(): array;

    /**
     * El tablero: estado actual del mercado (solicitudes por estado y por
     * macrorregión, ofertas vigentes) y lo ocurrido en el período (adjudicación,
     * volumen acordado, calificaciones).
     *
     * @param  string|null  $dias  la clave del período pedido (7, 30 o 90); otra cosa cae en el predeterminado.
     * @return array{
     *     periodo: array{dias: int, desde: string, hasta: string},
     *     solicitudes_por_estado: array<string, int>,
     *     mercado_por_macrorregion: list<array{macrorregion: string, solicitudes: int, hectareas: string}>,
     *     ofertas: array{vigentes: int, del_periodo: int, por_solicitud: string|null},
     *     adjudicacion: array{publicadas: int, adjudicadas: int, tasa: string|null, tiempo_mediana_min: string|null},
     *     volumen: array{hectareas_acordadas: string, monto_acordado: string, precio_acordado_promedio: string|null, precio_propuesto_promedio: string|null},
     *     calificaciones: array{promedio: string|null, conteo: int, reportadas: int},
     *     pendientes: array{proveedores: int, pilotos: int, auxiliares: int, drones: int, documentos: int, fotos_dron: int, calificaciones: int, disputas: int},
     *     recientes: list<array{id: int, estado: string, cliente: string, hectareas: string, precio_ha: string|null, publicada_en: string|null}>
     * }
     */
    public function tablero(?string $dias): array;
}
