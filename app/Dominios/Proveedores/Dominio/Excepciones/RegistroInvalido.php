<?php

namespace App\Dominios\Proveedores\Dominio\Excepciones;

use App\Dominios\Compartido\Infraestructura\Idioma\Texto;
use RuntimeException;

/** Una operación que el solicitante no puede hacer sobre un registro ajeno o fuera de estado. */
final class RegistroInvalido extends RuntimeException
{
    /** `esAjeno`: el registro existe pero no es de quien pregunta; la API responde 404, nunca 403 (invariante 5). */
    private function __construct(string $mensaje, public readonly bool $esAjeno = false)
    {
        parent::__construct($mensaje);
    }

    public static function sinProveedor(): self
    {
        return new self(Texto::de('proveedores.errores.sin_proveedor'));
    }

    public static function proveedorNoAprobado(): self
    {
        return new self(Texto::de('proveedores.errores.proveedor_no_aprobado'));
    }

    public static function proveedorSinPilotos(): self
    {
        return new self(Texto::de('proveedores.errores.titular_no_es_piloto'));
    }

    public static function sujetoAjeno(): self
    {
        return new self(Texto::de('proveedores.errores.sujeto_ajeno'), true);
    }

    public static function documentoYaRevisado(): self
    {
        return new self(Texto::de('proveedores.errores.documento_ya_revisado'));
    }

    public static function documentoVigenteDeSujetoAprobado(): self
    {
        return new self(Texto::de('proveedores.errores.documento_vigente_de_sujeto_aprobado'));
    }

    public static function documentoVencido(): self
    {
        return new self(Texto::de('proveedores.errores.documento_vencido'));
    }

    public static function tipoDeProveedorSinPersonal(): self
    {
        return new self(Texto::de('proveedores.errores.independiente_sin_pilotos'));
    }

    public static function limiteDeDocumentos(int $maximo): self
    {
        return new self(Texto::de('proveedores.errores.limite_de_documentos', ['maximo' => $maximo]));
    }

    public static function archivoNoDisponible(): self
    {
        return new self(Texto::de('proveedores.errores.archivo_no_disponible'));
    }
}
