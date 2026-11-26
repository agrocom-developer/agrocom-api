# Guía de redacción del panel

Cómo se escriben los textos visibles del panel: etiquetas, botones, títulos, ayudas, estados vacíos y mensajes. Todo texto vive en `lang/es/*.php` (ADR 0013). Esta guía fija el registro; `docs/glosario.md` fija el vocabulario; `tests/Unit/RedaccionNeutraTest.php` vigila lo que se puede vigilar a máquina.

## 1. Registro

- **Español neutro, tuteo.** Se habla a la persona de «tú» («Revisa», «Elige», «Tu cuenta»). Nada de voseo («Elegí», «podés»), de usted («Seleccione», «su contraseña») ni de regionalismos locales («apenas la confirmes», «acá»).
- **Sin anglicismos ni jerga técnica** hacia quien administra. No aparecen «registro» (por fila de base de datos), «entidad», «request», «payload», «soft delete», «SDK», «toggle», «ítem».
- **Frases cortas y directas.** Una idea por frase. Si hace falta una consecuencia, va en una segunda frase.
- **Mensajes completos con punto final; etiquetas, botones y títulos sin punto.**
- **Mayúscula solo inicial** en etiquetas, botones y títulos («Solicitudes por estado», no «Solicitudes Por Estado»). Un rótulo en mayúsculas se logra con CSS (`text-transform`), no escribiéndolo así.
- **Signos y acentos completos:** `¿ ?`, `¡ !`, tildes siempre. Nunca ASCII por tilde.
- **Números y unidades:** `ha` y `Bs` en minúscula/forma fija, con espacio («12 ha», «Bs 250»); `%` con espacio en los textos corridos («5 %»).

## 2. Etiquetas: el sustantivo primero

Una etiqueta nombra algo. Si lleva un adjetivo, lleva también el sustantivo que ese adjetivo califica; el adjetivo suelto o la frase armada al revés no se entiende sin el contexto.

| Mal | Bien | Por qué |
|---|---|---|
| Abiertas a ofertas | Ofertas abiertas | Frase armada al revés |
| En curso | Solicitudes en curso | Adjetivo sin sustantivo |
| Pendientes de aprobación de proveedores | Proveedores por aprobar | Cabe en tres palabras |
| Pendiente de tu decisión | Esperan tu decisión | Verbo con complemento natural |
| Apto / No apto | Puede ofertar: Sí / No | Adjetivo huérfano |
| Parcial | Reembolso parcial | Adjetivo sin sustantivo |
| definitiva | Última oferta | Etiqueta suelta |
| Sujeto | Destinatario / Pertenece a | Jerga de código |
| Alta | Fecha de registro | «Alta» es jerga de oficina |
| Instante | Fecha y hora | Calco del código |
| Entidad | Elemento | Jerga técnica |
| Enviar solicitud (recuperar clave) | Enviar enlace | «Solicitud» es la del servicio |

## 3. Concordancia

El género y el número siguen al sustantivo del contexto, no al del estado en el código. El **reclamo** es masculino: «Abierto», «En revisión», «Resuelto», «Retirado», «Apelado». La **solicitud** y la **calificación** son femeninas: «Abierta», «Oculta». Un texto con `:cantidad` usa la forma plural o singular según el caso (`|` en el catálogo), nunca «1 calificaciones».

## 4. Una palabra por concepto

| Concepto | Se escribe | No se escribe |
|---|---|---|
| Pedido del cliente | solicitud | pedido, orden, trabajo |
| Propuesta de precio | oferta (contraoferta) | cotización, propuesta |
| Quien presta el servicio | proveedor; piloto; dron | operador, prestador, vehículo |
| Queja sobre un servicio | reclamo | disputa, caso |
| Valoración | calificación | puntuación, reseña |
| Quien pide el servicio | cliente | usuario, comprador |
| Decidir un alta | aprobar / rechazar / suspender / rehabilitar | validar, habilitar, bloquear |
| Quitar | **eliminar** | dar de baja, borrar |
| Crear una cuenta o un registro a mano | registrar / crear | dar de alta |
| Subir un archivo | subir | cargar |
| Iniciar la sesión | iniciar sesión | loguearse, ingresar a la cuenta |
| Teléfono | teléfono | celular (como etiqueta) |

Avisos: «Avisos del sistema» es la auditoría de lo que el sistema avisó; «Mis notificaciones» es la campana de cada persona. No se mezclan «aviso», «notificación» y «alerta» sin motivo.

## 5. Mensajes

- **Ayuda:** orienta y dice la consecuencia. «Déjalo vacío para conservarlo.»
- **Estado vacío:** dice por qué está vacío y qué hacer. «Todavía no hay solicitudes. Cuando llegue la primera, aparece aquí.»
- **Error:** dice qué falló y qué hacer. «El motivo no puede tener más de 500 caracteres.», «Vuelve a intentarlo en unos minutos.»
- **Confirmación de acción irreversible:** lo dice con las mismas palabras en todo el panel: «No se puede deshacer.»
- **Reintentar:** «Vuelve a intentarlo» (con «lo»), no «Vuelve a intentar» ni «Intenta nuevamente».
- **Límites:** «no puede tener más de 80 caracteres», «no puede ser mayor que :maximo», «no puede superar :monto»; no «no puede pasar de».

## 6. Qué no se toca al corregir un texto

- Las **claves** de idioma y los marcadores `:variable`.
- Los identificadores técnicos, las rutas y los valores de enum.
- `lang/es/legal.php` y las páginas de términos y privacidad.
- El significado: ante la duda, se deja el texto y se consulta.

## 7. Verificación

- `tests/Unit/ClavesDeIdiomaTest.php`: todo `__()` tiene su clave.
- `tests/Unit/RedaccionNeutraTest.php`: sin voseo ni trato de usted, y sin las construcciones de la lista de frases prohibidas («Abiertas a ofertas», «dar de baja», «acá», «nuevamente»…). Si se corrige una construcción nueva y se repite, se agrega a esa lista con criterio.
- Al terminar una pantalla, se leen sus textos en el navegador de corrido: si una etiqueta no se entiende sin la pantalla, falta su sustantivo.

Ver también `docs/diseno/guia_pantalla_panel.md` (cómo se arma la pantalla) y `docs/glosario.md` (vocabulario).
