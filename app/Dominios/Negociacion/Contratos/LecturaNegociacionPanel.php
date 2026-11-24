<?php

namespace App\Dominios\Negociacion\Contratos;

/**
 * Frontera de lectura de `Negociacion` hacia el panel (ADR 0003, regla 2): lo
 * que la ficha de una solicitud muestra del trato y de su ejecución. Solo
 * lectura y sin alcance de cuenta: quien la usa es el administrador, y la
 * pantalla ya verificó su permiso contra el rol activo. Precios, montos y
 * hectáreas viajan como cadenas decimales (invariante 6).
 */
interface LecturaNegociacionPanel
{
    /**
     * Las ofertas de la solicitud, agrupadas por proveedor (una negociación) y
     * dentro de cada una por número de ronda.
     *
     * @return list<array{id: int, numero: int, autor: string, tipo: string, proveedor_id: int, equipo_id: int|null, precio_ha: string, monto_estimado: string, estado: string, es_definitiva: bool, nota: string|null, vence_en: string, respondida_en: string|null, creada_en: string}>
     */
    public function ofertas(int $solicitudId): array;

    /**
     * Cuántas ofertas `vigente` tiene cada solicitud de la lista (las que no
     * tienen ninguna no figuran).
     *
     * @param  list<int>  $solicitudIds
     * @return array<int, int> solicitud → cantidad
     */
    public function ofertasVigentesPorSolicitud(array $solicitudIds): array;

    /**
     * El chat entre el cliente y el proveedor adjudicado, en orden de creación.
     *
     * @return list<array{id: int, autor_usuario_id: int, autor_rol: string, texto: string, creado_en: string}>
     */
    public function mensajes(int $solicitudId): array;

    /**
     * Inicio, avances y cierre que registró el piloto, en orden causal.
     *
     * @return list<array{id: int, tipo: string, hectareas: string|null, porcentaje: string|null, nota: string|null, motivo_correccion: string|null, registrado_en: string}>
     */
    public function avances(int $solicitudId): array;

    /**
     * Las fotos del servicio (sin el archivo: se piden una por una).
     *
     * @return list<array{id: int, tipo: string, bytes: int, avance_id: int|null, creada_en: string}>
     */
    public function evidencias(int $solicitudId): array;

    /**
     * La última ubicación del piloto, que solo existe durante la ejecución.
     *
     * @return array{latitud: string, longitud: string, precision_m: string|null, capturado_en: string}|null
     */
    public function ubicacion(int $solicitudId): ?array;

    /** Ruta absoluta del archivo de una foto, o `null` si no existe en el disco privado. */
    public function rutaDeEvidencia(int $evidenciaId): ?string;
}
