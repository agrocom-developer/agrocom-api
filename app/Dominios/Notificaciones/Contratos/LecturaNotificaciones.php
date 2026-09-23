<?php

namespace App\Dominios\Notificaciones\Contratos;

/**
 * Frontera de lectura de `Notificaciones` hacia la cáscara del panel (ADR
 * 0003, regla 2): la campana muestra los avisos de la cuenta autenticada sin
 * importar el modelo `Notificacion`.
 *
 * El parámetro es SIEMPRE el id de la cuenta que mira, tomado de la sesión
 * autenticada. No hay una lectura «de todos» ni una que reciba un id de la
 * petición: por diseño una cuenta no puede pedir los avisos de otra.
 */
interface LecturaNotificaciones
{
    /**
     * Hasta `$limite` avisos de esa cuenta, priorizando los no leídos (se
     * incluyen todos los no leídos que quepan y se completa con los leídos más
     * recientes), del más nuevo al más viejo.
     *
     * @return list<NotificacionPanel>
     */
    public function recientesDe(int $usuarioId, int $limite): array;

    /**
     * Qué hizo esa cuenta con las alertas técnicas de Operaciones que vio en
     * la campana: cuáles abrió y cuáles limpió. Es estado de lectura de la
     * cuenta, no el estado de la alerta (que es de todos y lo atiende quien
     * tiene el permiso).
     */
    public function alertasDeCuenta(int $usuarioId): AlertasDeCuenta;
}
