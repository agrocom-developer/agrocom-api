# Política de cancelación

**Agrocom SRL · Vigente** · Decisión: ADR 0033 · Especificación: `docs/especificacion/marketplace.md` §5.1, §8.1

## Principios

- Cancelar debe ser fácil.
- La plataforma no cobra, así que no impone penalidades en dinero; registra quién cancela y deja que la reputación y la moderación actúen.
- Nadie queda marcado por causas que no controla.

## Cancelación del cliente

| Momento | Efecto |
|---|---|
| Antes de adjudicar (`borrador`, `abierta`, `con_ofertas`) | Ninguno; las ofertas vigentes se cierran |
| Adjudicada y antes de iniciar | Motivo obligatorio; queda registrada en su tasa de cancelación; el piloto recibe el aviso |
| En ejecución | No se cancela: se cierra con las hectáreas realmente ejecutadas y, si hay desacuerdo, se abre una disputa |

## Cancelación del proveedor

- Solo antes de iniciar. Motivo obligatorio.
- La solicitud vuelve a `abierta`; el cliente recibe el aviso y las demás ofertas siguen cerradas. Ese proveedor no puede ofertar de nuevo en esa solicitud.
- Queda registrada en su tasa de cancelación, visible al cliente en sus ofertas futuras y al administrador.

## Sin registro para nadie

- Una causa de fuerza mayor declarada con motivo y reconocida por soporte: clima fuera de lo operable, emergencia o incidencia de seguridad, falla comprobada del equipo, error de datos de la solicitud.
- Falta de ofertas o expiración de la solicitud.

## Alertas y decisiones

Un umbral de tasa de cancelación (parámetro) genera una alerta en el panel con el detalle. Un administrador revisa, avisa por escrito y decide; no hay restricción automática. El proveedor puede apelar.

## Registro

Toda cancelación guarda quién, cuándo y motivo en el historial de la solicitud.
