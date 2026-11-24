<?php

namespace App\Dominios\Negociacion\Contratos;

/**
 * Lectura de solo lectura de todo lo que ocurre alrededor de una solicitud, para
 * la ficha del panel: las negociaciones de cada proveedor con sus rondas, el
 * chat, el avance, las fotos y la última ubicación del piloto. Contrato que
 * consume `Solicitudes` sin conocer las tablas de la negociación.
 */
interface LecturaNegociacionParaPanel
{
    /**
     * @return array{
     *     negociaciones: list<array{
     *         proveedor_id: int,
     *         proveedor: string,
     *         vigente: bool,
     *         ofertas: list<array{numero: int, autor: string, tipo: string, precio_ha: string, monto_estimado: string, estado: string, es_definitiva: bool, nota: ?string, motivo: ?string, vence_en: \DateTimeInterface, respondida_en: ?\DateTimeInterface}>
     *     }>,
     *     mensajes: list<array{autor_rol: string, texto: string, creado_en: \DateTimeInterface}>,
     *     avances: list<array{tipo: string, hectareas_ejecutadas: ?string, porcentaje: ?string, nota: ?string, registrado_en: \DateTimeInterface}>,
     *     fotos: list<array{id: int, tipo: string, bytes: int, subida_en: \DateTimeInterface}>,
     *     ubicacion: ?array{latitud: string, longitud: string, precision_m: ?string, capturado_en: \DateTimeInterface}
     * }
     */
    public function deLaSolicitud(int $solicitudId): array;
}
