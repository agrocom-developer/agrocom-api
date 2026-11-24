<?php

namespace App\Dominios\Calificaciones\Aplicacion;

use App\Dominios\Calificaciones\Dominio\AutorCalificacion;
use App\Dominios\Calificaciones\Dominio\EstadoCalificacion;
use App\Dominios\Calificaciones\Dominio\Excepciones\CalificacionInvalida;
use App\Dominios\Calificaciones\Dominio\SujetoCalificacion;
use App\Dominios\Calificaciones\Infraestructura\Eloquent\Calificacion;
use App\Dominios\Proveedores\Contratos\DatosProveedor;
use App\Dominios\Proveedores\Contratos\LecturaProveedores;
use Illuminate\Pagination\LengthAwarePaginator;

/**
 * Las calificaciones que recibe el proveedor de la cuenta (`GET
 * /calificaciones/recibidas`, especificación §10.2, política de calificación:
 * «El proveedor ve las calificaciones que recibe»). Parte del proveedor de la
 * cuenta (invariante 5 de CLAUDE.md): nunca ve las de otro. No ve las ocultas ni
 * las que él mismo escribió sobre el cliente, que son privadas del lado de quien
 * las escribe. Con el resumen que la app muestra arriba: promedio, conteo,
 * distribución por estrellas y etiquetas más frecuentes de las últimas 100.
 */
final class ListarCalificacionesRecibidas
{
    private const POR_PAGINA = 20;

    public function __construct(
        private readonly LecturaProveedores $proveedores,
        private readonly ParametrosDeCalificacion $parametros,
    ) {}

    /**
     * @return array{proveedor: DatosProveedor, pagina: LengthAwarePaginator<int, Calificacion>, resumen: array{por_estrellas: array<int, int>, etiquetas: array<string, int>}}
     *
     * @throws CalificacionInvalida
     */
    public function ejecutar(int $usuarioId, int $pagina = 1): array
    {
        $proveedor = $this->proveedores->proveedorDeLaCuenta($usuarioId) ?? throw CalificacionInvalida::sinProveedor();

        $visibles = array_map(fn (EstadoCalificacion $estado): string => $estado->value, array_filter(EstadoCalificacion::cases(), fn (EstadoCalificacion $estado): bool => $estado->lasVeElProveedor()));
        $sujetos = array_map(fn (SujetoCalificacion $sujeto): string => $sujeto->value, SujetoCalificacion::delLadoProveedor());

        $consulta = fn () => Calificacion::query()
            ->where('proveedor_id', $proveedor->id)
            ->where('autor_rol', AutorCalificacion::Cliente->value)
            ->whereIn('sujeto_tipo', $sujetos)
            ->whereIn('estado', $visibles)
            ->orderByDesc('id');

        $paginada = $consulta()->paginate(self::POR_PAGINA, ['*'], 'page', max(1, $pagina));

        $ventana = $consulta()->where('sujeto_tipo', SujetoCalificacion::Proveedor->value)->limit($this->parametros->ventana())->get(['estrellas', 'etiquetas']);

        $porEstrellas = [1 => 0, 2 => 0, 3 => 0, 4 => 0, 5 => 0];
        $etiquetas = [];

        foreach ($ventana as $calificacion) {
            $porEstrellas[$calificacion->estrellas]++;

            foreach ($calificacion->etiquetas as $etiqueta) {
                $etiquetas[$etiqueta] = ($etiquetas[$etiqueta] ?? 0) + 1;
            }
        }

        arsort($etiquetas);

        return ['proveedor' => $proveedor, 'pagina' => $paginada, 'resumen' => ['por_estrellas' => $porEstrellas, 'etiquetas' => $etiquetas]];
    }
}
