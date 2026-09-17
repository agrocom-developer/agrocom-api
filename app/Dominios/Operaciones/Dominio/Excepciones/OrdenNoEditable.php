<?php

namespace App\Dominios\Operaciones\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/**
 * Se intentó editar una orden que no está `emitida` (HU-25, tarea 38).
 * Decisión de esta tarea, documentada en `Aplicacion/ActualizarOrden`: una
 * orden `vigente` puede ya estar en el pull de catálogo de la app de campo
 * (`GET /api/sync/catalogo`) con el piloto operando sobre esos parámetros —
 * cambiarlos en silencio desde el panel, sin ningún mecanismo que reabra la
 * sincronización, es más peligroso que bloquear la edición.
 */
final class OrdenNoEditable extends RuntimeException
{
    public static function porEstado(string $estado): self
    {
        return new self(Texto::de('operaciones.errores.orden_no_editable', ['estado' => $estado]));
    }
}
