<?php

namespace App\Dominios\Seguridad\Dominio;

/**
 * Cómo terminó una vista "como otro usuario" (tarea 140). Es lo que queda en
 * `sec_vistas_como.motivo_fin` y, por la bitácora, en `plt_bitacoras`.
 *
 * Solo dos, y a propósito: una vista que muere con la sesión (el navegador se
 * cierra, la sesión vence) no tiene ningún request que la cierre, y adivinarlo
 * al siguiente ingreso del administrador daría falsos positivos con dos
 * sesiones abiertas a la vez. Esa fila queda sin `finalizada_at`: la bitácora
 * muestra la entrada y ninguna salida, que es justamente lo que pasó.
 */
enum MotivoFinVistaComo: string
{
    /** El administrador tocó «Volver a mi vista». */
    case Manual = 'manual';

    /**
     * La vista dejó de ser válida a mitad de camino: la cuenta observada se
     * bloqueó, se dio de baja o perdió el rol con el que se veía, o el propio
     * administrador perdió el permiso. Se corta sola, en el request siguiente.
     */
    case Invalidada = 'invalidada';
}
