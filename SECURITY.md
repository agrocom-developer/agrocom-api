# Política de seguridad

Agrocom opera una plataforma que conecta clientes con proveedores de fumigación con drones. Se agradece el reporte responsable de cualquier vulnerabilidad.

## Cómo reportar

No abras un issue público ni un pull request con los detalles de una vulnerabilidad.

1. Usa el reporte privado de GitHub: pestaña **Security**, botón **Report a vulnerability** de este repositorio.
2. Si no puedes usarlo, escribe al dueño del repositorio, `agrocom-developer`, por el contacto que figura en su perfil de GitHub.

Incluye en el reporte:

- Descripción del problema y su impacto.
- Pasos para reproducirlo, con una versión o commit.
- Una prueba de concepto mínima, sin datos reales de clientes.

## Qué esperar

- Acuse de recibo en un máximo de 3 días hábiles.
- Evaluación inicial y severidad en un máximo de 7 días hábiles.
- Corrección de las vulnerabilidades altas y críticas con prioridad sobre cualquier otro trabajo.
- Divulgación coordinada: se acuerda contigo la fecha de publicación una vez corregido el problema.

## Alcance

Entra en el alcance el código de este repositorio y la API de producción que despliega. Quedan fuera los ataques de denegación de servicio volumétricos, la ingeniería social y los hallazgos en servicios de terceros.

## Versiones soportadas

Solo la última versión desplegada (rama `master`, último tag) recibe correcciones de seguridad.

## Prácticas vigentes

El detalle de los controles (CI, dependencias, tokens, despliegue) está en [`docs/seguridad/`](docs/seguridad/README.md).
