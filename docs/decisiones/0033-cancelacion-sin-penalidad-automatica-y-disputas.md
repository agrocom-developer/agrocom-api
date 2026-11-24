# ADR 0033 — Cancelación sin penalidad automática y disputas

**Estado:** aceptada, revisable · **Fecha:** 2026-11-24 · **Decide:** el dueño

## Contexto

El precio y las condiciones se acuerdan entre cliente y piloto, y el cobro ocurre fuera de la app. La plataforma no cobra, así que no puede aplicar penalidades en dinero; sí puede registrar quién cancela y dar un cauce a los desacuerdos.

## Decisión

1. Cancelar antes de adjudicar es gratuito y no deja registro. Tras adjudicar, el cliente (`adjudicada` → `cancelada`) o el proveedor (`adjudicada` → `abierta`) cancelan con motivo obligatorio.
2. Cada cancelación tras adjudicar se registra en `neg_cancelaciones` y alimenta la tasa de cancelación de quien la hace, visible a la contraparte y al administrador. No hay penalidad en dinero ni restricción automática; un umbral genera una alerta al panel y un administrador decide.
3. Una cancelación del proveedor reabre la solicitud (`abierta`) sin que ese proveedor pueda ofertar de nuevo en ella. Las ofertas anteriores siguen cerradas.
4. No se cancela una solicitud `en_ejecucion`: se cierra con las hectáreas realmente ejecutadas, y si hay desacuerdo se abre una disputa.
5. Una **disputa** la abre el cliente (hasta 72 horas después de `finalizada`) o el proveedor (por cobro pendiente, acceso negado o calificación abusiva). Una por solicitud. Soporte media y un administrador resuelve con evidencia: `a_favor_cliente`, `a_favor_proveedor`, `parcial` o `sin_lugar`, con un `monto_ajustado` que reemplaza el precio final vigente.
6. La resolución es un registro nuevo con autor y motivo; nada se sobrescribe.

## Alternativas descartadas

- **Penalidad escalonada en dinero**: sin cobro dentro de la app no hay cómo ejecutarla y obliga a Agrocom a arbitrar deudas.
- **Restricción automática por cancelaciones**: castiga sin criterio causas ajenas (clima, falta de acceso).
- **Reembolso automático**: abre el servicio al abuso.

## Consecuencias

Documentación operativa en `docs/politicas/cancelacion.md`, `disputas.md` y `seguridad.md`.
