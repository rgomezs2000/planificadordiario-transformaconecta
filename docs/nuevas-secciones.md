# Actualización del planificador · secciones nuevas y análisis

Esta actualización incorpora la hoja "Mi rutina me fortalece" al sistema: meta semanal,
actividades diarias, seguimiento personal, reflexión del domingo y un resumen con dictamen
clínico-ético y recomendaciones. También amplía las notificaciones y el reporte en Excel.

## 1. Puesta en marcha

```bash
php artisan migrate
php artisan db:seed --class=DailyActivitySeeder
```

`DailyActivitySeeder` carga el catálogo de actividades (clave, nombre, minutos, día de la semana,
hora y pregunta del recordatorio). Es idempotente: se puede volver a ejecutar sin duplicar y además
crea la fila vacía de la actividad nueva en los diarios que ya existían, para que el formulario, el
PDF y las notificaciones la vean sin volver a guardar cada día. Por eso, al agregar una actividad
al catálogo, hay que volver a ejecutar el seeder.

No hace falta volver a sembrar el resto de catálogos.

## 2. Base de datos

| Tabla | Para qué |
| --- | --- |
| `weekly_goals` | Una meta por semana, identificada por el lunes (`week_start`). |
| `daily_activities` | Catálogo de hábitos: minutos, día de la semana, hora y pregunta de aviso. |
| `daily_plan_activity` | Marcado de cada actividad en cada diario: `is_done`, `minutes`, `note`. |
| `weekly_reflections` | Las seis respuestas del domingo, una fila por semana. |
| `daily_plans` | Nuevas columnas: `weekly_goal_id`, `personal_done_today`, `personal_helped`, `personal_hard`. |
| `notificaciones` | Nueva columna `daily_activity_id` para resolver el aviso al marcar la actividad. |

La reflexión semanal y la meta se identifican por el **lunes** de la semana: así no dependen de
cómo numere cada motor las semanas del año y varios diarios de la misma semana comparten la meta.

## 3. Formulario del diario

Orden de la columna izquierda:

1. **Mi meta semanal** (encima de los objetivos): meta + por qué es importante. Obligatoria.
   Se registra una sola vez por semana, de lunes a domingo. Los demás diarios de esa semana la
   reciben bloqueada y no la vuelven a registrar; sólo el botón **Cambiar meta de la semana** la
   desbloquea. Al empezar una semana nueva no existe meta para ese lunes: se pide una nueva.
2. **Mis 3 objetivos principales de hoy** (sin cambios).
3. **Mi horario de hoy** (sin cambios).
4. **Actividades diarias**: las trece de todos los días (tender la cama, caminata, respiración,
   meditación, audios, **temas del día en WhatsApp**, alimentación, estudio/trabajo, actividad
   física, tareas del hogar, práctica de habilidades, tiempo personal y meta semanal) y, sólo el
   sábado, el cambio de sábanas, el cambio de toallas y el espacio limpio y ordenado. Caminata,
   respiración y meditación piden minutos; el resto se marca con una casilla. En la vista de sólo
   lectura también se marcan y se guardan con el botón **Guardar actividades**
   (`PATCH /diario/{id}/actividades`), igual que el horario.
5. **Mi meta personal**: el seguimiento del día, con el símbolo del día actual y tres campos
   (qué hice, qué me ayudó, qué me dificultó).
6. **Notas / recordatorios** (sin cambios).
7. **Reflexión de la semana**: sólo aparece el domingo, con las seis preguntas del cierre.

Los campos de una sección que no aplica al día (sábado/domingo) se deshabilitan y no viajan al
guardar.

## 4. Notificaciones

Además de los objetivos y las franjas, se revisan las actividades diarias. Si su hora ya pasó y
siguen sin marcarse, la campanita pregunta si se realizaron. Si se marcan, el aviso se resuelve
solo (se marca leído) en la siguiente sincronización.

- MySQL guarda todo el historial y el estado.
- Firestore sigue siendo el espejo NoSQL **sólo para las notificaciones** (push).
- Se configura con `NOTIFICACIONES_AVISAR_ACTIVIDADES`,
  `NOTIFICACIONES_MARGEN_ACTIVIDADES` y `NOTIFICACIONES_HORA_ACTIVIDADES`.

## 5. Resumen de desempeño

El PDF del resumen incorpora:

- Cinco números del período (incluye actividades).
- **Dictamen clínico y ético**: lee los registros, distingue asociación de causa, explicita los
  límites de los datos y recuerda el marco ético. No diagnostica.
- **Recomendaciones**: sugerencias concretas basadas en la actividad con menor continuidad, la
  actividad que acompañó mejores días, el umbral de señales y la energía.
- Gráfico de cumplimiento de las actividades diarias.
- Gráfico comparativo "lo que se cumplió y cómo acompañó al rendimiento".
- Tabla día por día con la columna de actividades.
- Metas semanales y reflexiones del período.
- Correlación de Pearson entre señales y rendimiento, con el tamaño de muestra.
- Tipografía, encabezado con el logotipo, banda "Mi rutina me fortalece" y pie institucional.

## 6. Reporte en Excel

La hoja **Diarios** suma:

- Meta semanal y por qué es importante.
- Actividades diarias (una línea por actividad, con minutos).
- Minutos totales y avance de actividades.
- Seguimiento personal (qué hice, qué me ayudó, qué me dificultó).
- Reflexión semanal completa.

La hoja **Resumen** repite el análisis del PDF: números del período, lectura escrita, tabla de
actividades y metas semanales.

## 7. Planificación periódica y cron del diario masivo

El mismo formato nuevo se aplica al libro de Excel de la planificación periódica y a la corrida
`planificacion:importar` que crea los días todos los días a las 00:00.

- La **plantilla** (`php artisan planificacion:plantilla`) trae las secciones **MI META SEMANAL**,
  **ACTIVIDADES DIARIAS** (con WhatsApp y los minutos), **MI META PERSONAL** y **REFLEXIÓN DE LA
  SEMANA**, además de las de siempre.
- El **lector** reconoce esas secciones por su etiqueta y las escribe en las mismas tablas que el
  formulario: la meta semanal se registra una sola vez por semana (la primera que aparezca en el
  libro manda; las demás hojas se vinculan sin pisarla; si una hoja no la trae, se hereda la que ya
  exista), las actividades se guardan con sus minutos y las del sábado solo se aceptan en sábado, el
  seguimiento personal va a las columnas del día y la reflexión solo se guarda el domingo.
- Los libros **anteriores** siguen funcionando: si una hoja no trae las secciones nuevas, se importa
  igual y las actividades se generan vacías.
- Hay que **regenerar la plantilla** después de este cambio para que las descargas tengan el formato
  nuevo.

La prueba `test_la_corrida_lee_las_secciones_nuevas_del_formato` cubre el recorrido completo:
plantilla → libro → corrida → base de datos.

## 8. Documentos y caché

Los PDF y JPG se guardan en `storage/app/private/documentos-v3`. Al cambiar el diseño se cambió la
carpeta base a propósito: los documentos anteriores se regeneran solos la próxima vez que se piden.
Las carpetas viejas (`documentos/` y `documentos-v2/`) se pueden borrar a mano.

## 9. Pruebas

```bash
php artisan test
```

`tests/Feature/NuevasSeccionesTest.php` cubre el formulario, el guardado, la meta compartida por
semana, las actividades del sábado, la reflexión del domingo, el PDF del día, el resumen y el Excel.
