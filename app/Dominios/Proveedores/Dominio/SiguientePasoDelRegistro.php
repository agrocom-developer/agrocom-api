<?php

namespace App\Dominios\Proveedores\Dominio;

use App\Dominios\Compartido\Dominio\EstadoAprobacion;

/**
 * Qué sigue para quien ya tiene su cuenta de proveedor creada (ADR 0046, punto 26). Registrarse con un teléfono o un
 * documento que ya tiene cuenta responde `409 cuenta_ya_registrada` y la persona inicia sesión en lugar de crear otra
 * cuenta; con la sesión, `GET /api/proveedores/mi` le dice en qué punto del registro está y la app la lleva al paso
 * que falta, sin repetir el formulario.
 *
 * Orden de los pasos de un proveedor `pendiente` (el primero que no está cumplido): `subir_documentos`, `registrar_dron`,
 * `subir_fotos_dron` y `en_revision`. Los dos del dron solo los pide quien opera drones propios (independiente o tercero
 * titular); el piloto de una empresa y el proveedor propio no registran un dron suyo en el registro.
 */
enum SiguientePasoDelRegistro: string
{
    /** Aprobado: nada pendiente del registro. */
    case Listo = 'listo';

    /** Pendiente y le faltan documentos obligatorios por subir (`documentos_faltantes`). */
    case SubirDocumentos = 'subir_documentos';

    /** Pendiente, con los documentos al día, y sin ningún dron registrado (`POST /api/proveedores/drones`). */
    case RegistrarDron = 'registrar_dron';

    /** Pendiente, con un dron registrado al que le faltan las fotos obligatorias (`POST /api/proveedores/drones/{id}/fotos`). */
    case SubirFotosDron = 'subir_fotos_dron';

    /** Pendiente con todo subido (documentos y, si opera drones, dron con sus fotos): espera la revisión de un administrador. */
    case EnRevision = 'en_revision';

    /** Rechazado: corrige lo observado y reenvía a revisión (`POST /api/proveedores/mi/reenviar`). */
    case Corregir = 'corregir';

    case Suspendido = 'suspendido';

    case Baja = 'baja';

    /**
     * @param  list<string>  $documentosFaltantes
     * @param  bool|null  $conDron  `null` si el sujeto no necesita un dron propio; si lo necesita, si ya tiene uno registrado.
     * @param  bool  $fotosDelDronSubidas  si algún dron suyo ya tiene subidas las fotos obligatorias (solo cuenta con `$conDron`).
     */
    public static function de(EstadoAprobacion $estado, array $documentosFaltantes, ?bool $conDron = null, bool $fotosDelDronSubidas = true): self
    {
        return match ($estado) {
            EstadoAprobacion::Aprobado => self::Listo,
            EstadoAprobacion::Pendiente => match (true) {
                $documentosFaltantes !== [] => self::SubirDocumentos,
                $conDron === false => self::RegistrarDron,
                $conDron === true && ! $fotosDelDronSubidas => self::SubirFotosDron,
                default => self::EnRevision,
            },
            EstadoAprobacion::Rechazado => self::Corregir,
            EstadoAprobacion::Suspendido => self::Suspendido,
            EstadoAprobacion::Baja => self::Baja,
        };
    }
}
