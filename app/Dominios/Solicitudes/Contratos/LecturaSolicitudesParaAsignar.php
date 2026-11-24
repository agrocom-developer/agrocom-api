<?php

namespace App\Dominios\Solicitudes\Contratos;

/**
 * Lo que el motor de asignación lee de las solicitudes (ADR 0030 y 0038) sin
 * importar `SolicitudServicio`. Toda la escritura va por
 * {@see AsignacionDeSolicitud}.
 */
interface LecturaSolicitudesParaAsignar
{
    public function datos(int $solicitudId): ?DatosSolicitudParaAsignar;

    /**
     * Igual que {@see datos()} pero bloquea la fila de la solicitud hasta el
     * fin de la transacción en curso. Toda decisión de asignación empieza por
     * acá: es lo que serializa a dos proveedores que aceptan a la vez y evita
     * el interbloqueo con el proceso que vence ofertas (siempre se bloquea
     * primero la solicitud y después sus ofertas).
     */
    public function datosBloqueando(int $solicitudId): ?DatosSolicitudParaAsignar;

    /**
     * Ids de las solicitudes `publicada` que el motor todavía no tomó, de la
     * más vieja a la más nueva. Es la red de seguridad del oyente de
     * `SolicitudPublicada`: lo que se haya quedado sin tomar lo recoge el
     * proceso programado.
     *
     * @return list<int>
     */
    public function publicadasSinTomar(int $limite): array;

    /**
     * Solicitudes que el administrador sigue en la cola de asignación: las que
     * esperan o tienen proveedor sin haber salido todavía (`publicada`,
     * `en_asignacion`, `sin_proveedor`, `asignada` y `en_camino`), las que
     * esperan proveedor primero.
     *
     * @return list<DatosSolicitudParaAsignar>
     */
    public function colaDeAsignacion(?string $estado, int $limite): array;

    /**
     * Ids de los equipos con un servicio que se cruza con la ventana (estados
     * `asignada`, `en_camino`, `en_ejecucion` y `suspendida_por_clima`),
     * salvo el de la solicitud indicada. Un equipo ocupado no recibe ofertas.
     *
     * @return list<int>
     */
    public function equiposOcupados(string $ventanaDesde, string $ventanaHasta, ?int $exceptoSolicitudId = null): array;
}
