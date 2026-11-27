<?php

namespace App\Dominios\Negociacion\Aplicacion\FiltrosDeProveedor;

use App\Dominios\Negociacion\Dominio\Excepciones\NegociacionInvalida;

/**
 * Registro de los filtros del proveedor que el servidor conoce (ADR 0045, punto 5) y reglas del
 * mecanismo (punto 7): un filtro sin valor no restringe; una clave desconocida ya guardada se
 * ignora al evaluar y se conserva (un cliente nuevo no rompe a un servidor viejo), pero al
 * guardar se rechaza con 422. La lista NO está cerrada: agregar un filtro es sumar una clase a
 * {@see self::CATALOGO}, con su prueba.
 */
final class FiltrosDeProveedor
{
    /** @var list<class-string<FiltroDeProveedor>> */
    public const array CATALOGO = [
        HectareasMinimas::class,
        DepartamentosExcluidos::class,
        TiposAplicacionExcluidos::class,
    ];

    /** @var array<string, FiltroDeProveedor> */
    private array $porClave = [];

    /** @param  list<FiltroDeProveedor>|null  $filtros  Solo las pruebas pasan los suyos. */
    public function __construct(?array $filtros = null)
    {
        foreach ($filtros ?? array_map(static fn (string $clase): FiltroDeProveedor => new $clase, self::CATALOGO) as $filtro) {
            $this->porClave[$filtro->clave()] = $filtro;
        }
    }

    /**
     * Lo que la app necesita para pintar los filtros que el servidor conoce.
     *
     * @return list<array{clave: string, tipo: string}>
     */
    public function disponibles(): array
    {
        return array_values(array_map(
            static fn (FiltroDeProveedor $filtro): array => ['clave' => $filtro->clave(), 'tipo' => $filtro->tipo()],
            $this->porClave,
        ));
    }

    /**
     * Cada filtro conocido con su valor guardado, o el de por defecto.
     *
     * @param  array<string, mixed>  $guardados
     * @return array<string, mixed>
     */
    public function conValores(array $guardados): array
    {
        $valores = [];

        foreach ($this->porClave as $clave => $filtro) {
            $valores[$clave] = $guardados[$clave] ?? $filtro->porDefecto();
        }

        return $valores;
    }

    /**
     * Aplica sobre lo guardado los filtros recibidos: los enviados reemplazan, `null` o vacío
     * los quita, lo no enviado se conserva, y las claves desconocidas ya guardadas se mantienen.
     *
     * @param  array<string, mixed>  $guardados
     * @param  array<string, mixed>  $recibidos
     * @return array<string, mixed>
     *
     * @throws NegociacionInvalida 422 `filtro_desconocido` o `filtro_invalido`.
     */
    public function aplicar(array $guardados, array $recibidos): array
    {
        foreach ($recibidos as $clave => $valor) {
            $filtro = $this->porClave[$clave] ?? null;

            if ($filtro === null) {
                throw NegociacionInvalida::dato('filtro_desconocido', 'nuevas_solicitudes.'.$clave);
            }

            $normalizado = $filtro->normalizar($valor);

            if ($normalizado === null) {
                unset($guardados[$clave]);
            } else {
                $guardados[$clave] = $normalizado;
            }
        }

        return $guardados;
    }

    /**
     * ¿Todos los filtros declarados dejan pasar la solicitud? Un filtro conocido sin valor no restringe;
     * una clave desconocida se ignora.
     *
     * @param  array<string, mixed>  $guardados
     */
    public function admite(array $guardados, SolicitudParaAviso $solicitud): bool
    {
        foreach ($this->porClave as $clave => $filtro) {
            if (($guardados[$clave] ?? null) === null) {
                continue;
            }

            // Un valor guardado que ya no es válido para el filtro (dato viejo) no excluye a nadie.
            try {
                $valor = $filtro->normalizar($guardados[$clave]);
            } catch (NegociacionInvalida) {
                continue;
            }

            if ($valor !== null && ! $filtro->admite($valor, $solicitud)) {
                return false;
            }
        }

        return true;
    }
}
