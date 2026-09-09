<?php

namespace App\Dominios\Inventario\Aplicacion;

use App\Dominios\Inventario\Contratos\Excepciones\StockInsuficiente;
use App\Dominios\Inventario\Dominio\SentidoAjusteInventario;
use App\Dominios\Inventario\Dominio\TipoMovimientoInventario;
use App\Dominios\Inventario\Infraestructura\Eloquent\MovimientoStock;
use App\Dominios\Inventario\Infraestructura\Eloquent\Repuesto;
use App\Dominios\Inventario\Infraestructura\Eloquent\Stock;
use Brick\Math\BigDecimal;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Registra un movimiento de stock (HU-36, tarea 52): `compra`, `salida`,
 * `ajuste` o `traslado`. Es el único punto de escritura de `inv_movimientos`
 * e `inv_stock` — nadie más los toca (ni el controlador ni ninguna otra
 * clase de `Aplicacion/`).
 *
 * Guarda de "el stock nunca queda negativo" (CA esencial): DENTRO de la
 * transacción, `lockForUpdate()` sobre la(s) fila(s) de `inv_stock`
 * afectada(s) ANTES de decidir si hay cantidad suficiente. Si no alcanza,
 * lanza {@see StockInsuficiente} — el `CHECK (cantidad >= 0)` de la base es
 * el backstop, nunca el mecanismo primario (nunca se deja llegar la resta a
 * violarlo).
 *
 * Un `traslado` afecta DOS filas de `inv_stock` (decrementa origen,
 * incrementa destino) desde un único `inv_movimientos` — nunca dos
 * movimientos separados (la especificación lo describe como un asiento
 * único con dos bases). Las dos filas se bloquean en un orden determinístico
 * (por `base_id` ascendente, conocido de antemano) para no dejar abierta la
 * puerta a un deadlock entre dos traslados cruzados concurrentes
 * (base A → B a la vez que B → A).
 *
 * `costo_unitario` del movimiento se guarda también en una `Salida` (HU-37,
 * tarea 53), no solo en una `Compra`: es el "último costo conocido" vigente
 * en `Repuesto` al momento de consumir, para que quien registró el
 * movimiento (p. ej. `EscrituraConsumoStock` de una orden de mantenimiento)
 * pueda calcular el costo total aplicado sin volver a leer `Repuesto`.
 */
final class RegistrarMovimientoStock
{
    public function ejecutar(
        TipoMovimientoInventario $tipo,
        Repuesto $repuesto,
        int $baseId,
        string $cantidad,
        ?int $baseDestinoId = null,
        ?SentidoAjusteInventario $sentido = null,
        ?string $costoUnitario = null,
        ?string $motivo = null,
        ?int $ordenMantenimientoId = null,
    ): MovimientoStock {
        return DB::transaction(function () use ($tipo, $repuesto, $baseId, $cantidad, $baseDestinoId, $sentido, $costoUnitario, $motivo, $ordenMantenimientoId) {
            $magnitud = BigDecimal::of($cantidad);

            [$stockBase, $stockDestino] = $this->bloquearFilasDeStock($repuesto->id, $baseId, $tipo === TipoMovimientoInventario::Traslado ? $baseDestinoId : null);

            $this->aplicarDelta($tipo, $sentido, $stockBase, $stockDestino, $magnitud, $repuesto->codigo, $baseId);

            $movimiento = new MovimientoStock([
                'repuesto_id' => $repuesto->id,
                'base_id' => $baseId,
                'base_destino_id' => $tipo === TipoMovimientoInventario::Traslado ? $baseDestinoId : null,
                'tipo' => $tipo->value,
                'cantidad' => (string) $magnitud,
                'sentido' => $tipo === TipoMovimientoInventario::Ajuste ? $sentido?->value : null,
                'costo_unitario' => match ($tipo) {
                    TipoMovimientoInventario::Compra => $costoUnitario,
                    TipoMovimientoInventario::Salida => $repuesto->costo_unitario,
                    default => null,
                },
                'motivo' => $motivo,
                'orden_mantenimiento_id' => $ordenMantenimientoId,
            ]);
            $movimiento->save();

            if ($tipo === TipoMovimientoInventario::Compra && $costoUnitario !== null) {
                $repuesto->costo_unitario = $costoUnitario;
                $repuesto->save();
            }

            return $movimiento->refresh();
        });
    }

    /**
     * Suma o resta la magnitud sobre la(s) fila(s) de stock ya bloqueadas,
     * lanzando {@see StockInsuficiente} antes de dejar una resta violar el
     * mínimo de cero.
     */
    private function aplicarDelta(
        TipoMovimientoInventario $tipo,
        ?SentidoAjusteInventario $sentido,
        Stock $stockBase,
        ?Stock $stockDestino,
        BigDecimal $magnitud,
        string $codigoRepuesto,
        int $baseId,
    ): void {
        match ($tipo) {
            TipoMovimientoInventario::Compra => $this->incrementar($stockBase, $magnitud),
            TipoMovimientoInventario::Salida => $this->decrementar($stockBase, $magnitud, $codigoRepuesto, $baseId),
            TipoMovimientoInventario::Ajuste => $sentido === SentidoAjusteInventario::Decremento
                ? $this->decrementar($stockBase, $magnitud, $codigoRepuesto, $baseId)
                : $this->incrementar($stockBase, $magnitud),
            TipoMovimientoInventario::Traslado => $this->trasladar($stockBase, $stockDestino, $magnitud, $codigoRepuesto, $baseId),
        };
    }

    private function incrementar(Stock $stock, BigDecimal $magnitud): void
    {
        $stock->cantidad = (string) BigDecimal::of($stock->cantidad)->plus($magnitud);
        $stock->save();
    }

    /** @throws StockInsuficiente si la cantidad disponible no alcanza. */
    private function decrementar(Stock $stock, BigDecimal $magnitud, string $codigoRepuesto, int $baseId): void
    {
        $disponible = BigDecimal::of($stock->cantidad);

        if ($disponible->isLessThan($magnitud)) {
            throw StockInsuficiente::paraMovimiento($codigoRepuesto, $baseId, (string) $disponible, (string) $magnitud);
        }

        $stock->cantidad = (string) $disponible->minus($magnitud);
        $stock->save();
    }

    private function trasladar(Stock $stockOrigen, ?Stock $stockDestino, BigDecimal $magnitud, string $codigoRepuesto, int $baseId): void
    {
        if ($stockDestino === null) {
            throw new LogicException('Un traslado necesita la fila de stock de destino ya bloqueada.');
        }

        $this->decrementar($stockOrigen, $magnitud, $codigoRepuesto, $baseId);
        $this->incrementar($stockDestino, $magnitud);
    }

    /**
     * Bloquea (`lockForUpdate()`) la fila de stock de la base dada, o la crea
     * en cantidad 0 si es el primer movimiento de ese repuesto en esa base
     * (ver `obtenerOCrearStockConLock()`). Cuando hay destino, bloquea ambas
     * filas en orden de `base_id` ascendente (ver docblock de la clase) y
     * devuelve `[stockDeLaBaseAfectada, stockDelDestino]` en el orden que
     * pidió el llamador, no en el orden de bloqueo.
     *
     * @return array{0: Stock, 1: Stock|null}
     */
    private function bloquearFilasDeStock(int $repuestoId, int $baseId, ?int $baseDestinoId): array
    {
        if ($baseDestinoId === null) {
            return [$this->obtenerOCrearStockConLock($repuestoId, $baseId), null];
        }

        $primeraBase = min($baseId, $baseDestinoId);
        $segundaBase = max($baseId, $baseDestinoId);

        $stockPrimero = $this->obtenerOCrearStockConLock($repuestoId, $primeraBase);
        $stockSegundo = $this->obtenerOCrearStockConLock($repuestoId, $segundaBase);

        return $baseId <= $baseDestinoId
            ? [$stockPrimero, $stockSegundo]
            : [$stockSegundo, $stockPrimero];
    }

    private function obtenerOCrearStockConLock(int $repuestoId, int $baseId): Stock
    {
        $stock = Stock::query()
            ->where('repuesto_id', $repuestoId)
            ->where('base_id', $baseId)
            ->lockForUpdate()
            ->first();

        if ($stock !== null) {
            return $stock;
        }

        try {
            // `DB::transaction()` anidado: Postgres no permite seguir usando
            // una transacción después de que uno de sus statements viola un
            // constraint (la marca "aborted" hasta el próximo ROLLBACK). El
            // anidado crea un SAVEPOINT propio, así que si el INSERT choca
            // con el `unique(repuesto_id, base_id)` de otra transacción
            // concurrente que ganó la carrera, el rollback es solo hasta ese
            // punto — la transacción exterior sigue viva para el SELECT de
            // abajo.
            DB::transaction(function () use ($repuestoId, $baseId): void {
                Stock::query()->create([
                    'repuesto_id' => $repuestoId,
                    'base_id' => $baseId,
                    'cantidad' => 0,
                    'stock_minimo' => 0,
                ]);
            });
        } catch (QueryException) {
            // Ídem comentario de arriba: la fila ya existe, la trae el
            // SELECT ... FOR UPDATE de abajo, ya bloqueada a nuestro favor.
        }

        return Stock::query()
            ->where('repuesto_id', $repuestoId)
            ->where('base_id', $baseId)
            ->lockForUpdate()
            ->firstOrFail();
    }
}
