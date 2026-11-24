<?php

namespace App\Dominios\Calificaciones\Aplicacion;

use App\Dominios\Calificaciones\Dominio\SujetoCalificacion;
use App\Dominios\Flota\Contratos\LecturaIntegrantesDeEquipo;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use App\Dominios\Solicitudes\Contratos\DatosServicio;

/**
 * A quiénes puede calificar el cliente en un servicio (ADR 0031): al proveedor que
 * lo prestó, al piloto del equipo y, si el equipo llevaba uno, a su auxiliar. Los
 * ids salen de los contratos de `Solicitudes`, `Flota` y `Proveedores`.
 */
final class SujetosDelServicio
{
    public function __construct(
        private readonly LecturaIntegrantesDeEquipo $equipos,
        private readonly LecturaProveedores $proveedores,
    ) {}

    /**
     * @return array<string, array{id: int, nombre: string}> por tipo de sujeto (`proveedor`, `piloto`, `auxiliar`).
     */
    public function delLadoProveedor(DatosServicio $servicio): array
    {
        $sujetos = [];

        if ($servicio->proveedorId === null) {
            return $sujetos;
        }

        $proveedor = $this->proveedores->proveedor($servicio->proveedorId);
        $sujetos[SujetoCalificacion::Proveedor->value] = ['id' => $servicio->proveedorId, 'nombre' => $proveedor->razonSocial ?? "#{$servicio->proveedorId}"];

        $equipo = $servicio->equipoId === null ? null : $this->equipos->integrantes($servicio->equipoId);

        if ($equipo !== null) {
            $sujetos[SujetoCalificacion::Piloto->value] = [
                'id' => $equipo->pilotoId,
                'nombre' => $this->proveedores->piloto($equipo->pilotoId)->nombre ?? "#{$equipo->pilotoId}",
            ];

            if ($equipo->auxiliarId !== null) {
                $sujetos[SujetoCalificacion::Auxiliar->value] = [
                    'id' => $equipo->auxiliarId,
                    'nombre' => $this->proveedores->auxiliar($equipo->auxiliarId)->nombre ?? "#{$equipo->auxiliarId}",
                ];
            }
        }

        return $sujetos;
    }
}
