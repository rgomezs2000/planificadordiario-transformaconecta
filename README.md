# Planificador Diario "Transforma-Conecta"

**Versión 1.02** (manejo de errores) · sobre la base **1.0 (MVP)** — ver
[§27.3](#273-versión-102-manejo-de-errores) y [§27.2](#272-versión-10-mvp-versión-base)

Sistema de Planificación Personal del **Programa de Desarrollo Personal "Transforma-Conecta"**.

> **ORGÁNIZATE · ACTÚA · AVANZA**

Aplicación web hecha con **Laravel 12** que digitaliza la hoja del planificador diario del
programa: el registro del día (objetivos, horario, checklist, procrastinación, bloque de
acción y cierre), su consulta con buscador y filtros por período, y la generación de
reportes (PDF del día, reporte detallado en Excel y resumen de desempeño en PDF, con
gráficos dibujados en el servidor).

- **Nombre del sistema:** Planificador Diario "Transforma-Conecta"
- **Nombre comercial en pantalla:** *Mi Planificador Diario*
- **Versión:** **1.0 (MVP)** — la versión base, entregada y en uso (ver [§27.2](#272-versión-10-mvp-versión-base))
- **Tipo:** aplicación web monolítica, de un solo usuario, sin autenticación
- **Estado:** MVP funcional y en uso (ver [§27](#27-estado-del-proyecto-y-hoja-de-ruta))
- **Rama:** `main`

**Cómo abrirlo en local (XAMPP), en una línea:**

```
http://localhost:8088/planificadordiario-transformaconecta/
```

> Este documento es la documentación completa del sistema. La sección de instalación
> paso a paso está en [§18](#18-instalación-y-puesta-en-marcha) y la guía de uso para la
> persona que lo usa todos los días, en [§19](#19-guía-de-uso-paso-a-paso).
> El README original de Laravel (About Laravel, sponsors, licencia) fue reemplazado por
> esta documentación; el marco de trabajo sigue siendo Laravel 12, con su propia licencia MIT,
> mientras que el código de este proyecto se distribuye bajo la **Unlicense** (ver
> [§28](#28-créditos-y-licencia)).

---

## Índice

1. [Ficha del proyecto](#1-ficha-del-proyecto)
2. [Concepto del sistema](#2-concepto-del-sistema)
3. [Objetivos](#3-objetivos)
4. [Misión y visión](#4-misión-y-visión)
5. [Alcances y limitaciones](#5-alcances-y-limitaciones)
6. [Usuarios y roles](#6-usuarios-y-roles)
7. [El modelo de la hoja impresa: las ocho secciones del día](#7-el-modelo-de-la-hoja-impresa-las-ocho-secciones-del-día)
8. [Arquitectura del sistema](#8-arquitectura-del-sistema)
9. [Stack tecnológico y requisitos](#9-stack-tecnológico-y-requisitos)
10. [Estructura del proyecto](#10-estructura-del-proyecto)
11. [Modelo de datos](#11-modelo-de-datos)
12. [Módulos del sistema](#12-módulos-del-sistema)
    - [12.1 Inicio y menú principal](#121-inicio-y-menú-principal)
    - [12.2 Formulario del diario](#122-formulario-del-diario)
    - [12.3 Listado y buscador de diarios](#123-listado-y-buscador-de-diarios)
    - [12.4 Filtros y períodos](#124-filtros-y-períodos)
    - [12.5 Gráfico del día](#125-gráfico-del-día)
    - [12.6 Reporte detallado en Excel](#126-reporte-detallado-en-excel)
    - [12.7 Resumen de desempeño en PDF](#127-resumen-de-desempeño-en-pdf)
    - [12.8 PDF del día e impresión](#128-pdf-del-día-e-impresión)
    - [12.9 Catálogos y datos base](#129-catálogos-y-datos-base)
    - [12.10 Dominio y persistencia](#1210-dominio-y-persistencia)
    - [12.11 Utilidades de formato](#1211-utilidades-de-formato)
    - [12.12 Front-end compartido](#1212-front-end-compartido)
    - [12.13 Plantilla base y experiencia de uso](#1213-plantilla-base-y-experiencia-de-uso)
    - [12.14 Arranque y publicación en Apache](#1214-arranque-y-publicación-en-apache)
    - [12.15 Página de errores](#1215-página-de-errores)
    - [12.16 Bitácora de errores y logs diarios](#1216-bitácora-de-errores-y-logs-diarios)
13. [Rutas y endpoints](#13-rutas-y-endpoints)
14. [Flujos paso a paso](#14-flujos-paso-a-paso)
15. [Reglas de negocio y validaciones](#15-reglas-de-negocio-y-validaciones)
16. [Motor de análisis del desempeño](#16-motor-de-análisis-del-desempeño)
17. [Identidad visual y experiencia de uso](#17-identidad-visual-y-experiencia-de-uso)
18. [Instalación y puesta en marcha](#18-instalación-y-puesta-en-marcha)
19. [Guía de uso paso a paso](#19-guía-de-uso-paso-a-paso)
20. [Configuración del entorno](#20-configuración-del-entorno)
21. [Seguridad](#21-seguridad)
22. [Rendimiento y decisiones técnicas](#22-rendimiento-y-decisiones-técnicas)
23. [Mantenimiento y tareas frecuentes](#23-mantenimiento-y-tareas-frecuentes)
24. [Pruebas](#24-pruebas)
25. [Solución de problemas](#25-solución-de-problemas)
26. [Glosario](#26-glosario)
27. [Estado del proyecto y hoja de ruta](#27-estado-del-proyecto-y-hoja-de-ruta)
    - [27.1 Estado actual](#271-estado-actual)
    - [27.2 Versión 1.0: MVP (versión base)](#272-versión-10-mvp-versión-base)
    - [27.3 Versión 1.02: manejo de errores](#273-versión-102-manejo-de-errores)
    - [27.4 Mejoras propuestas (orden sugerido)](#274-mejoras-propuestas-orden-sugerido)
28. [Créditos y licencia](#28-créditos-y-licencia)

---

## 1. Ficha del proyecto

| Dato | Valor |
| --- | --- |
| Nombre del sistema | Planificador Diario "Transforma-Conecta" |
| Nombre en pantalla | Mi Planificador Diario |
| **Versión actual** | **1.02** — manejo de errores, sobre la base **1.0 (MVP)** (ver [§27.3](#273-versión-102-manejo-de-errores) y [§27.2](#272-versión-10-mvp-versión-base)) |
| Programa al que pertenece | Programa de Desarrollo Personal "Transforma-Conecta" |
| Lema institucional | ORGÁNIZATE · ACTÚA · AVANZA |
| Tipo de aplicación | Web monolítica (Laravel 12), servidor-renderizada, con mejoras por AJAX |
| Usuarios | Un solo usuario (la persona que planifica). Sin autenticación |
| Idioma | Español (interfaz, mensajes, catálogos y reportes) |
| Zona horaria | `America/Caracas` (`APP_TIMEZONE`) |
| Base de datos | MySQL, base `planificador-diario` |
| Servidor local | XAMPP (Apache en el puerto **8088**, DocumentRoot `C:/xampp/htdocs`) |
| URL local | `http://localhost:8088/planificadordiario-transformaconecta/` |
| Framework | Laravel 12 |
| Front-end | Bootstrap 5 + jQuery + DataTables + bootbox, por CDN; CSS y JS propios en `public/` |
| Generación de documentos | dompdf (PDF) y PhpSpreadsheet (XLSX), en memoria |
| Gráficos | GD (PNG en el servidor) y JavaScript propio (línea de tiempo en el navegador) |
| Repositorio | Git, rama `main` |

---

## 2. Concepto del sistema

### 2.1 Qué es

El Planificador Diario es la **versión digital de la hoja de planificación diaria** que la
persona participante del Programa de Desarrollo Personal "Transforma-Conecta" completa cada
día. La hoja de papel tiene ocho bloques numerados; el sistema reproduce esos mismos ocho
bloques como un formulario web, los guarda en una base de datos y agrega lo que el papel no
puede hacer: buscador, filtros por período, estadísticas de desempeño, gráficos y reportes
en PDF y Excel.

El sistema **no** es un gestor de tareas genérico ni un calendario: es el registro de un
ritual diario de organización personal, con una estructura fija y deliberada.

### 2.2 La idea central

1. **Planificar el día antes de vivirlo**: fecha, energía disponible, tres objetivos, el
   horario con sus actividades y el checklist de lo que hace falta tener listo.
2. **Acompañarse durante el día**: cuando aparece la procrastinación, cinco preguntas guía;
   cuando cuesta arrancar, un "bloque de acción" corto (5, 10, 15 o 20 minutos) con un
   resultado esperado.
3. **Cerrar el día con intención**: qué se logró, qué quedó pendiente y cuándo se hará, y de
   qué se siente orgullosa la persona.
4. **Ver la evolución**: leer los días registrados en conjunto para descubrir qué se sostiene,
   qué se posterga, cómo influye la energía y dónde la procrastinación empieza a costar
   rendimiento.

### 2.3 Por qué existe (problema que resuelve)

- La hoja en papel **se pierde, se moja o se archiva mal**: no se puede consultar meses
  después.
- Revisar "cómo me fue este mes" a mano exige **sumar y comparar decenas de hojas**.
- No hay forma práctica de **cruzar la energía del día con el cumplimiento** ni de detectar
  **en qué franja horaria o en qué actividad** se cae siempre.
- Los reportes que el programa necesita (para acompañamiento o devolución) obligan a
  **rehacer el trabajo de transcripción**.

El sistema conserva la hoja tal como está diseñada (mismos títulos, mismos colores, mismo
orden) y resuelve esas cuatro limitaciones.

### 2.4 Principios de diseño

| Principio | Cómo se expresa en el sistema |
| --- | --- |
| Fidelidad a la hoja impresa | Las secciones, sus títulos y sus colores siguen el código de color de la hoja: naranja objetivos/procrastinación, turquesa preparación/bloque de acción, azul horario, rojo cierre |
| Un dato se carga una vez | El día de la semana (L M M J V S D) se deduce de la fecha; no se guarda ni se pide |
| Nada se pierde | Todo se guarda en transacción; los reportes se generan al vuelo desde la base |
| El idioma es el del usuario | Fechas, meses, días y mensajes en español, escritos en el código (no dependen de `intl` ni del locale del servidor) |
| Tono neutro en el análisis | El resumen describe los registros, no juzga a la persona: "aspectos a mejorar", nunca "lo que haces mal" |
| Sin dependencias innecesarias | El front-end no usa NPM, Vite, Tailwind, Vue ni React: Bootstrap, jQuery y DataTables se cargan por CDN |

---

## 3. Objetivos

### 3.1 Objetivo general

Digitalizar y dar continuidad al planificador diario del Programa de Desarrollo Personal
"Transforma-Conecta", de modo que la persona registre su día con la misma estructura de la
hoja impresa y pueda, además, consultar su histórico, medir su desempeño y obtener reportes
sin trabajo manual.

### 3.2 Objetivos específicos

1. **Registrar un día completo** en un único formulario: fecha, energía, 3 objetivos
   principales, checklist "antes de empezar", horario, reflexión de procrastinación, bloque
   de acción, cierre del día y notas.
2. **Garantizar la integridad del registro**: un solo diario por fecha, exactamente 3
   objetivos, al menos una franja de horario y el cierre del día completo.
3. **Consultar el histórico** con un listado que permita buscar por palabra clave, filtrar
   por fecha, por nivel de energía y por período (semana, quincena, mes, trimestre, semestre,
   año o rango de fechas).
4. **Visualizar el día en el tiempo** con una línea de tiempo que se redibuja sola mientras
   se carga el horario, marcando lo cumplido, lo pendiente y los solapamientos.
5. **Exportar el detalle** de los días filtrados a Excel (`.xlsx`), con todas las secciones
   desglosadas en columnas.
6. **Generar el PDF del día** que reproduce la hoja institucional, con marca de agua
   `SPECIMEN` opcional para documentos de muestra.
7. **Producir un resumen de desempeño** en PDF con números, lecturas redactadas y gráficos:
   rendimiento, aspectos sostenidos, aspectos a mejorar, metas alcanzadas, relación con la
   energía, impacto de la procrastinación y evolución del período.
8. **Detectar el punto crítico de procrastinación**: a partir de cuántas señales marcadas el
   rendimiento empieza a caer.
9. **Mantener los catálogos en base de datos** (energías, tipos de objetivo, ítems de
   preparación, franjas horarias, duraciones y resultados del bloque, preguntas de reflexión)
   para poder ampliarlos sin tocar el código de las vistas.
10. **Publicar el sistema en la raíz del proyecto** (no en `public/`) con las carpetas
    internas y los archivos sensibles bloqueados por HTTP.

---

## 4. Misión y visión

### 4.1 Misión

Ofrecer a las personas del Programa de Desarrollo Personal "Transforma-Conecta" una
herramienta digital sencilla, ordenada y en su propio idioma, que convierta la planificación
diaria en un hábito sostenible: **organizar el día, actuar sobre lo planificado y avanzar con
evidencia de lo hecho**, sin que el registro se convierta en una carga.

### 4.2 Visión

Ser el instrumento de referencia del programa para el acompañamiento personal: que cada
participante pueda mirar su propio proceso con datos claros y un lenguaje respetuoso, y que
quienes acompañan cuenten con reportes confiables —sin transcripciones ni cuentas a mano—
para sostener el proceso.

### 4.3 Valores que se reflejan en el sistema

| Valor | Traducción concreta |
| --- | --- |
| Autoconocimiento | Cruces de energía, franjas y procrastinación contra el rendimiento real, sin supuestos: solo lo que la persona marcó |
| Respeto | Tono neutral y diplomático en todos los textos del resumen; advertencia explícita de que el documento no es un diagnóstico ni una valoración personal |
| Orden | Estructura fija de ocho secciones, catálogos en base de datos, un diario por fecha |
| Sencillez | Un formulario, un listado, tres salidas (PDF del día, Excel, resumen). Sin pasos innecesarios |
| Transparencia | Todo lo que se muestra proviene de los registros; cuando un dato no alcanza para afirmar algo, el sistema lo dice |
| Continuidad | Histórico consultable y reportes reproducibles en cualquier momento |

---

## 5. Alcances y limitaciones

### 5.1 Alcance funcional (lo que el sistema sí hace)

**Gestión del diario**

- Crear el diario de un día cualquiera (`/diario`) o el de hoy con la fecha bloqueada
  (`/diario/today`).
- Ver un diario guardado en modo solo lectura (`/diario/{id}`).
- Modificar un diario guardado (`/diario/{id}/editar`).
- Eliminar un diario, con confirmación previa (`DELETE /diario/{id}`).
- Un único diario por fecha (validación de unicidad).
- **Página de errores propia** (`/error/{codigo}`) que explica los códigos por familia (3xx,
  4xx y 5xx) y que es la que aparece cuando una página no existe, el acceso no está permitido,
  la sesión venció o el servidor falla.
- **Registro de errores con detalle**: cada fallo queda anotado con su **código de incidente**
  en `storage/logs/errores-AAAA-MM-DD.log` (dirección, IP, ruta, excepción y traza recortada);
  los logs rotan por día y conservan los anteriores.

**Secciones del día**

- Fecha y día de la semana derivado automáticamente.
- Nivel de energía del día (Baja, Media, Alta) tomado de catálogo.
- Los 3 objetivos principales con su tipo del catálogo y su casilla "Cumplido".
- Checklist "Antes de empezar" con descripción libre por ítem.
- Horario de hoy: filas libres (hora + actividad + cumplida), agregar y quitar.
- Preguntas de procrastinación con casilla de señal y respuesta corta.
- Bloque de acción: duración, resultado esperado y tarea.
- Cierre del día: logros, pendiente, cuándo y orgullo.
- Notas y recordatorios en texto libre.
- Línea de tiempo del día (gráfico) en pantalla y en el PDF.

**Consulta y filtrado**

- Listado con DataTables (buscador propio, orden, paginación 5/10/25/50/100).
- Búsqueda libre por palabra clave dentro de objetivos, horario, notas, cierre y bloque de
  acción (basta una letra).
- Filtro por fecha exacta, por energía y por período: semana, quincena, mes, trimestre,
  semestre, año o rango de fechas; los filtros se combinan entre sí.
- Los mismos filtros alimentan la tabla, el Excel y el resumen en PDF.

**Salidas y documentos**

- PDF del día que reproduce la hoja institucional (carta, una página).
- Marca de agua diagonal `SPECIMEN` opcional en el PDF del día y en el resumen.
- Reporte detallado en Excel, un diario por fila y todas las secciones en columnas.
- Resumen de desempeño en PDF con números, textos, tabla día por día y tres gráficos.
- Todos los documentos se generan al vuelo; no se guardan archivos en el servidor.

**Administración de datos**

- Catálogos ampliables por *seeders* (y por SQL) sin tocar el código de las vistas: energías,
  tipos de objetivo, ítems de preparación, franjas horarias, duraciones del bloque,
  resultados del bloque y preguntas de reflexión.
- Cada catálogo tiene orden (`sort_order`) y activación (`is_active`): desactivar un ítem lo
  saca del formulario sin borrar el histórico.

### 5.2 Alcance técnico

- Aplicación Laravel 12 servida desde Apache (XAMPP) con PHP 8.2 y MySQL.
- Arquitectura MVC con la lógica de consulta y persistencia concentrada en el modelo
  `DailyPlan` y servicios de apoyo en `app/Filtros`, `app/Graficos`, `app/Reportes` y
  `app/Excel`.
- API interna en JSON con sobre uniforme `{ ok, message, data }` / `{ ok, message, errors }`
  para las acciones de AJAX.
- Documentos PDF y XLSX generados en memoria (sin archivos temporales).
- Front-end sin compilación: assets propios en `public/` y librerías por CDN.

### 5.3 Fuera de alcance (lo que el sistema no hace hoy)

- **No tiene autenticación ni multiusuario.** No hay registro, inicio de sesión ni
  separación de datos por persona: la base es de un único usuario. (`users` existe, pero
  está vacía y no se usa.)
- **No hay app móvil ni versión offline.** La interfaz es responsive (funciona en el
  navegador del teléfono), pero requiere conexión al servidor.
- **No hay edición de catálogos desde la interfaz.** Se administran por *seeders* o SQL.
- **No hay notificaciones, recordatorios ni envío de correo** (`MAIL_MAILER=log`).
- **No hay API pública** para terceros ni integraciones externas (calendarios, Google, etc.).
- **No hay multi-idioma**: la interfaz está en español.
- **No hay historial de cambios por diario** (auditoría de versiones).
- **No hay carga de archivos** ni imágenes adjuntas al diario.
- **No hay gráficos por trimestre/semestre desglosados individualmente** más allá del filtro
  de período aplicado al resumen.
- **No hay pruebas automatizadas propias**: los archivos en `tests/` son los ejemplos de
  Laravel (ver [§24](#24-pruebas)).
- **No hay panel de administración** ni métricas de uso del sistema.

---

## 6. Usuarios y roles

| Rol | Quién es | Qué hace en el sistema | Cómo accede |
| --- | --- | --- | --- |
| **Participante** (usuario único) | La persona del programa que planifica y registra sus días | Crea el diario del día, lo consulta, lo modifica, lo imprime, exporta el Excel y genera el resumen | Navegador, sin credenciales |
| **Acompañante / facilitador** | Quien lee los reportes junto a la persona | Recibe el PDF del día, el Excel o el resumen de desempeño como documento | No entra al sistema: usa los documentos que se generan |
| **Administrador técnico** | Quien instala y mantiene la aplicación | Configura `.env`, corre migraciones y *seeders*, ajusta catálogos, respalda la base | Acceso al servidor XAMPP y a MySQL |

> **Nota sobre el diseño de un solo usuario:** el sistema se pensó como libreta personal. Si
> en el futuro se publica en un servidor compartido, conviene agregar autenticación y asociar
> cada `daily_plan` a un `user_id` (ver [§27](#27-estado-del-proyecto-y-hoja-de-ruta)).

---

## 7. El modelo de la hoja impresa: las ocho secciones del día

El formulario, el PDF del día y el Excel respetan este orden. Los colores son los de la hoja
institucional y se repiten en pantalla, en el PDF y en el Excel.

| # | Sección (título en la hoja) | Contenido | Color | Obligatoria |
| --- | --- | --- | --- | --- |
| 1 | Cabecera del día | Fecha · Día de la semana · Mi energía hoy | Neutro | Sí (fecha y energía) |
| 2 | **Mis 3 objetivos principales de hoy** | 1 Debo hacer · 2 Quiero hacer · 3 Algo para mí, cada uno con "Cumplido" | Naranja | Sí (las tres descripciones) |
| 3 | **Antes de empezar** | ¿Qué necesito tener listo? Checklist con descripción libre por ítem | Turquesa | No |
| 4 | **Mi horario de hoy** | Hora · ¿Qué vas a hacer hoy? · cumplida | Azul | Sí (al menos una franja) |
| 5 | **Si estoy procrastinando me pregunto** | Cinco preguntas con casilla de señal y respuesta corta | Naranja | No |
| 6 | **Bloque de acción** | Voy a trabajar durante · Cuando termine este bloque · ¿En qué vas a trabajar? | Turquesa | No |
| 7 | **Cierre del día** | Lo que logré hoy · Lo que quedó pendiente · ¿Cuándo lo haré? · Hoy estoy orgulloso/a de mí porque | Rojo | Sí (los cuatro campos) |
| 8 | **Notas / recordatorios** | Texto libre multilínea | Azul oscuro | No |

---

## 8. Arquitectura del sistema

### 8.1 Estilo arquitectónico

Aplicación **monolítica Laravel 12** con patrón **MVC enriquecido**:

- **Rutas** (`routes/web.php`) → declaran los 15 endpoints web detrás del middleware `web`.
- **Controladores** → delgados: validan, invocan y arman la respuesta (HTML, JSON, PDF o
  XLSX). Comparten el trait `DatosDelFormulario` para los datos de la vista del formulario.
- **Modelo de dominio** (`App\Models\DailyPlan`) → concentra **todas** las consultas y las
  operaciones de escritura del planificador (scopes de filtrado, transacciones, generación de
  la estructura hija, sincronización con el formulario y armado de los resúmenes para las
  respuestas).
- **Servicios de apoyo** (no hay contenedor de servicios propio; son clases estáticas):
  - `App\Filtros\Periodo` → convierte un período elegido en un par de fechas.
  - `App\Graficos\AgendaDelDia` → convierte las franjas del horario en tramos y piezas.
  - `App\Graficos\AgendaPng` → dibuja la línea de tiempo como PNG con GD (para el PDF).
  - `App\Reportes\AnalisisDeDesempeno` → calcula los números del resumen.
  - `App\Reportes\RedaccionDelResumen` → convierte esos números en frases.
  - `App\Reportes\GraficosDelResumen` → dibuja los tres gráficos del resumen como PNG.
  - `App\Excel\ReporteDiarioExport` → construye el libro `.xlsx`.
  - `App\Helpers\Helper` → fechas, cifras y textos en español.
- **Vistas Blade** → `layouts.app` es la plantilla base con cabecera, menú lateral y pie;
  las vistas del diario rellenan `@section('contenido')`. Dos vistas son documentos
  independientes (HTML para dompdf): `diario/pdf.blade.php` y `diario/resumen.blade.php`.
- **Front-end** → namespace `window.Planificador` con módulos (`Util`, `Alerta`, `Ajax`,
  `Tabla`, `Impresion`, `Reporte`, `Formulario`, `Grafico`, `Menu`, `Reloj`) y una plantilla
  base que carga las librerías por CDN y luego los scripts propios.

### 8.2 Capas y responsabilidades

```
┌──────────────────────────────────────────────────────────────────────────┐
│  NAVEGADOR                                                               │
│  Blade + Bootstrap 5 + jQuery + DataTables + bootbox                     │
│  window.Planificador: Formulario · Grafico · Tabla · Impresion · Reporte │
└───────────────┬──────────────────────────────────────────────┬───────────┘
                │ HTTP (HTML / AJAX JSON / descarga)            │
┌───────────────▼──────────────────────────────────────────────▼───────────┐
│  RUTAS · routes/web.php                          (middleware web + CSRF) │
└───────────────┬──────────────────────────────────────────────────────────┘
                │
┌───────────────▼──────────────────────────────────────────────────────────┐
│  CONTROLADORES                                                            │
│  HomeController · DailyController · DailyPlanController                   │
│  trait DatosDelFormulario (catálogos y datos de la vista)                 │
│  Sobre JSON: { ok, message, data } / { ok, message, errors }              │
└───────┬──────────────────────────┬───────────────────────────┬───────────┘
        │                          │                           │
┌───────▼────────────┐  ┌──────────▼───────────────┐  ┌────────▼──────────┐
│ MODELO DE DOMINIO  │  │ SERVICIOS DE APOYO       │  │ SALIDAS           │
│ DailyPlan          │  │ Periodo                  │  │ Blade (pantalla)  │
│  · scopes/filtros  │  │ AgendaDelDia/AgendaPng   │  │ dompdf (PDF)      │
│  · transacciones   │  │ AnalisisDeDesempeno      │  │ PhpSpreadsheet    │
│  · sync formulario │  │ RedaccionDelResumen      │  │  (XLSX)           │
│  · toListArray     │  │ GraficosDelResumen       │  │ JSON (AJAX)       │
│  · toDetailArray   │  │ ReporteDiarioExport      │  │                   │
│  · toReportArray   │  │ Helper                   │  │                   │
└───────┬────────────┘  └──────────────────────────┘  └───────────────────┘
        │ Eloquent
┌───────▼──────────────────────────────────────────────────────────────────┐
│  MySQL · base «planificador-diario»                                      │
│  daily_plans + 7 tablas hijas/pivote + 6 catálogos                       │
└──────────────────────────────────────────────────────────────────────────┘
```

### 8.3 Flujo de una petición (ejemplo: guardar el diario)

```
1. El navegador envía POST /diario con el formulario serializado y el testigo CSRF.
2. El middleware web verifica CSRF y abre la sesión (driver database).
3. La ruta diario.store llama a DailyPlanController@store.
4. El controlador valida con validateDay() (reglas + mensajes en español).
   · Si falla y la petición espera JSON, bootstrap/app.php responde 422 con
     { ok:false, message, errors } (mismo sobre que el resto del sistema).
5. DailyPlan::createDay($data) abre una transacción:
   a. crea la cabecera (plan_date, energy_level_id, cierre del día);
   b. refreshDayStructure(): genera las 15 franjas del horario, el checklist del
      pivote y las respuestas vacías de las preguntas de reflexión (idempotente);
   c. applyDayData(): sincroniza objetivos, horario, preparación, reflexiones,
      notas y bloques de acción.
   · Si algo falla, se deshace todo, se reporta la excepción y el controlador
     responde 500 con un mensaje amable.
6. El controlador responde 201 con { ok:true, message, data:{ plan: … } }.
7. El navegador muestra la alerta de éxito y redirige al inicio (o al listado si
   estaba modificando).
```

### 8.4 Decisiones de arquitectura y su motivo

| Decisión | Motivo |
| --- | --- |
| Lógica de datos en el modelo (`DailyPlan`) y controlador delgado | Un único lugar donde se consulta y se escribe el planificador; el controlador solo arma respuestas |
| Clases de servicio **estáticas** en `app/Filtros`, `app/Graficos`, `app/Reportes`, `app/Excel` | No requieren estado ni inyección: se invocan desde modelos, controladores y vistas sin *container* |
| Catálogos en base de datos con `is_active` y `sort_order` | Ampliar o retirar ítems sin tocar vistas ni código; el histórico se conserva |
| Un solo formulario para crear, ver y editar | Una sola vista y un solo JS que mantener; el modo cambia el método HTTP, el destino y si los campos son editables |
| Hijos generados al crear el día (horario, checklist, reflexiones) | El día queda "completo" desde el inicio y el reporte tiene siempre las mismas columnas |
| Gráficos del PDF dibujados con GD en el servidor | El PDF no ejecuta JavaScript: los gráficos viajan como PNG en `data:` URI |
| Línea de tiempo en pantalla dibujada con JS propio (sin librería de gráficos) | Se redibuja sin ir al servidor mientras se carga el horario y no agrega dependencias |
| Assets propios + librerías por CDN, sin Vite/NPM | El despliegue es copiar la carpeta en XAMPP: no hay paso de compilación |
| PDF y XLSX en memoria (`php://output` con buffer) | No dejan archivos temporales en el servidor ni requieren permisos de escritura |
| Respuestas sin caché en PDF/XLSX | La URL es siempre la misma; sin `no-store` el navegador mostraría una versión vieja |
| Zona horaria `America/Caracas` en la configuración | "Hoy", el bloqueo de la fecha y las marcas de tiempo hablan en la hora local del usuario |

---

## 9. Stack tecnológico y requisitos

### 9.1 Back-end

| Componente | Versión declarada | Versión instalada | Para qué se usa |
| --- | --- | --- | --- |
| PHP | `^8.2` | 8.2.12 | Lenguaje del servidor |
| Laravel Framework | `^12.0` | 12.69.3 | MVC, ORM Eloquent, validación, sesiones, migraciones |
| barryvdh/laravel-dompdf | `^3.1` | 3.1.2 | Generación de los PDF (día y resumen) con el motor dompdf |
| dompdf/dompdf | transitiva | 3.1.6 | Motor PDF; el sistema lee sus fuentes DejaVu Sans desde `vendor/dompdf/dompdf/lib/fonts/` |
| phpoffice/phpspreadsheet | `^5.10` | 5.10.0 | Generación del reporte detallado `.xlsx` |
| laravel/tinker | `^2.10.1` | 2.11.1 | Consola interactiva para tareas de mantenimiento |

Extensiones de PHP necesarias y verificadas en el entorno: `gd` (gráficos PNG), `pdo_mysql`,
`mbstring`, `openssl`, `fileinfo`, `zip`, `curl`, `dom`, `xml`. **No** se usa `intl`: los
nombres de meses y días están escritos en español dentro de `Helper`.

### 9.2 Dependencias de desarrollo

`fakerphp/faker`, `laravel/pail` (visor de logs), `laravel/pint` (estilo de código),
`laravel/sail`, `mockery/mockery`, `nunomaduro/collision`, `phpunit/phpunit ^11.5.50`.

### 9.3 Front-end (todo por CDN, desde `layouts/app.blade.php`)

| Librería | Versión | Uso |
| --- | --- | --- |
| Bootstrap | 5.3.8 (CSS + bundle JS) | Rejilla, componentes, Offcanvas del menú, modales |
| Bootstrap Icons | 1.13.1 | Iconografía de la interfaz |
| jQuery | 3.7.1 | Base del JS propio y de las librerías |
| bootbox | 6.0.4 | Alertas y confirmaciones con el estilo de Bootstrap |
| DataTables | 2.3.8 (+ integración Bootstrap 5) | Tabla del listado: buscador, orden, paginación |
| bootstrap-datepicker | 1.10.1 (+ locale `es`) | Calendario de la fecha del diario y de los filtros |

### 9.4 Assets propios

| Archivo | Función |
| --- | --- |
| `public/js/app.js` | Utilidades, alertas, AJAX, DataTables, impresión y descarga de reportes |
| `public/js/script.js` | Comportamiento del formulario (fecha, horario, validación, guardado) |
| `public/js/grafico.js` | Línea de tiempo del día en el navegador |
| `public/js/menu.js` | Menú lateral (Offcanvas) |
| `public/js/reloj.js` | Fecha y hora dinámicas de la cabecera |
| `public/css/styles.css` | Paleta institucional, layout y todos los componentes (`tc-*`, `tf-*`) |

### 9.5 Requisitos de infraestructura

- XAMPP (o equivalente) con **Apache** y **MySQL**; Apache escuchando en el puerto **8088**
  con `DocumentRoot "C:/xampp/htdocs"`.
- Base de datos MySQL llamada `planificador-diario`.
- Composer para instalar las dependencias de PHP.
- Permisos de escritura en `storage/` y `bootstrap/cache/` (requisito habitual de Laravel).

---

## 10. Estructura del proyecto

```
planificadordiario-transformaconecta/
│
├── app/                                Código de la aplicación (PSR-4 App\)
│   ├── Excel/
│   │   └── ReporteDiarioExport.php     Reporte detallado .xlsx (25 columnas)
│   ├── Errores/
│   │   ├── CatalogoDeErrores.php       Texto de cada familia y código de error HTTP
│   │   └── RegistroDeErrores.php       Bitácora detallada en el log diario de errores
│   ├── Filtros/
│   │   └── Periodo.php                 Semana/quincena/mes/trimestre/semestre/año/rango → fechas
│   ├── Graficos/
│   │   ├── AgendaDelDia.php            Franjas del horario → tramos y piezas (minutos)
│   │   └── AgendaPng.php               Línea de tiempo dibujada con GD, para el PDF
│   ├── Helpers/
│   │   └── Helper.php                  Fechas, cifras y textos en español
│   ├── Http/
│   │   └── Controllers/
│   │       ├── Concerns/
│   │       │   └── DatosDelFormulario.php  Catálogos y datos comunes del formulario
│   │       ├── Controller.php              Controlador base de Laravel
│   │       ├── DailyController.php         Formulario en blanco y "hoy"
│   │       ├── DailyPlanController.php     Listar, crear, ver, editar, imprimir, exportar, eliminar
│   │       ├── ErrorController.php         Página de errores: /error/{codigo}
│   │       └── HomeController.php          Portada con el menú principal
│   ├── Models/                         Eloquent (DailyPlan es el modelo eje)
│   │   ├── ActionBlock.php             Bloque de acción ejecutado
│   │   ├── ActionBlockDuration.php     Catálogo de duraciones (5/10/15/20 min)
│   │   ├── ActionBlockOutcome.php      Catálogo de resultados del bloque
│   │   ├── DailyPlan.php               Modelo eje: consultas, transacciones y sincronización
│   │   ├── EnergyLevel.php             Catálogo de energía (Baja/Media/Alta)
│   │   ├── GoalType.php                Catálogo de tipos de objetivo
│   │   ├── PlanGoal.php                Uno de los 3 objetivos del día
│   │   ├── PlanNote.php                Nota o recordatorio
│   │   ├── PreparationItem.php         Catálogo "Antes de empezar"
│   │   ├── ReflectionAnswer.php        Respuesta del día a una pregunta
│   │   ├── ReflectionQuestion.php      Catálogo de preguntas de reflexión
│   │   ├── ScheduleEntry.php           Franja del horario del día
│   │   ├── ScheduleSlot.php            Catálogo de franjas horarias
│   │   └── User.php                    Modelo de usuario de Laravel (sin uso)
│   ├── Providers/
│   │   └── AppServiceProvider.php      Proveedor vacío (sin bindings propios)
│   └── Reportes/
│       ├── AnalisisDeDesempeno.php     Cálculo de todos los números del resumen
│       ├── GraficosDelResumen.php      Tres gráficos del resumen dibujados con GD
│       └── RedaccionDelResumen.php     Números → frases (tono neutral)
│
├── bootstrap/
│   ├── app.php                         Arranque de Laravel 12: rutas, middleware, excepciones
│   ├── cache/                          Caché de arranque (debe ser escribible)
│   └── providers.php                   Proveedores registrados
│
├── config/                             Configuración estándar de Laravel
│   ├── app.php · auth.php · cache.php · database.php · filesystems.php
│   └── logging.php · mail.php · queue.php · services.php · session.php
│
├── database/
│   ├── factories/UserFactory.php       Fábrica de usuarios (sin uso)
│   ├── migrations/                     19 migraciones: 3 base de Laravel + 16 del dominio
│   ├── seeders/                        Catálogos: energía, objetivos, preparación, horario,
│   │                                   duraciones, resultados y preguntas
│   └── database.sqlite                 Archivo de SQLite de Laravel (no se usa: la app va con MySQL)
│
├── public/                             Raíz web real de Laravel
│   ├── css/styles.css                  Todos los estilos y la paleta institucional
│   ├── js/{app,script,grafico,menu,reloj}.js
│   ├── .htaccess                       Reglas estándar de Laravel para public/
│   ├── index.php                       Front controller de Laravel
│   ├── favicon.ico · robots.txt
│
├── resources/
│   ├── css/app.css · js/app.js · js/bootstrap.js   Restos del esqueleto (no se usan)
│   └── views/
│       ├── layouts/app.blade.php       Plantilla base: cabecera, menú, pie y CDN
│       ├── home/index.blade.php        Portada con el día de hoy y accesos directos
│       ├── diario/
│       │   ├── formulario.blade.php    Formulario único (crear / ver / editar)
│       │   ├── index.blade.php         Listado, buscador, filtros y acciones de reporte
│       │   ├── _grafico.blade.php      Tarjeta de la línea de tiempo + JSON de datos
│       │   ├── _modal_imprimir.blade.php  Modal de marca de agua del PDF del día
│       │   ├── _modal_resumen.blade.php   Modal de marca de agua del resumen
│       │   ├── pdf.blade.php           Documento HTML del PDF del día
│       │   └── resumen.blade.php       Documento HTML del resumen de desempeño
│       ├── errores/index.blade.php     Página de errores (3xx, 4xx y 5xx)
│       └── welcome.blade.php           Vista de bienvenida de Laravel (sin ruta que la use)
│
├── routes/
│   ├── web.php                         Las 15 rutas del sistema
│   └── console.php                     Comando de ejemplo de Laravel
│
├── storage/                            Logs, sesiones y caché de vistas (escribible)
├── tests/                              Pruebas (hoy: solo los ejemplos de Laravel)
├── vendor/                             Dependencias de Composer (no se edita)
│
├── .env                                Configuración real del entorno (no se publica)
├── .env.example                        Plantilla de configuración
├── .htaccess                           Puente raíz → public/ y bloqueos de seguridad
├── index.php                           Reenvía a public/index.php (arranque de Laravel)
├── artisan                             Consola de Laravel
├── composer.json / composer.lock       Dependencias de PHP
├── package.json / vite.config.js       Esqueleto de front-end con Vite (no se usa)
├── phpunit.xml                         Configuración de pruebas
├── LICENSE                             Unlicense (dominio público)
└── README.md                           Este documento
```

### 10.1 Archivos sueltos en la raíz y carpeta `.tmp-chrome4`

En la raíz del proyecto quedaron archivos de ejemplo que **no** son parte de la aplicación:
`pdf-con-grafico.pdf`, `resumen-desempeno.pdf`, `resumen-muestra.pdf`, `resumen-un-dia.pdf`,
`reporte-detallado.xlsx` y las imágenes `cuadro-grafico.png`, `filtro-periodo.png`,
`grafico-dias.png`, `grafico-energia.png`, `grafico-pdf.png`, `grafico-procrastinacion.png` y
`ver-dia-energia.png`. Son capturas y salidas de prueba de las funciones de reporte, están
versionadas en git y **pueden eliminarse sin afectar al sistema**.

> **Advertencia.** Esos archivos son **descargables por HTTP** con la URL directa (por
> ejemplo `…/resumen-muestra.pdf` o `…/reporte-detallado.xlsx`): el `.htaccess` bloquea
> `.env`, `composer.json`, `artisan` y las extensiones `log`, `sqlite`, `sql`, `bak`, `old`,
> `ini`, `dist`, `sh`, `yaml`/`yml`, pero **no** `pdf`, `xlsx` ni `png`. Si el proyecto se
> publica, conviene moverlos a una carpeta interna o agregar sus extensiones al bloqueo.

> **Advertencia de seguridad (acción pendiente).** La carpeta oculta `.tmp-chrome4` es un
> **perfil completo de Chrome/Chromium** que quedó versionado en git (71 archivos,
> incluidos `Default/Network/Cookies`, `Default/Login Data`, `Default/History` y
> `Default/Web Data`), y hoy también **no está en `.gitignore`**. Se usó durante tareas de
> verificación en navegador. Antes de compartir o publicar el repositorio conviene:
>
> ```
> git rm -r --cached .tmp-chrome4
> echo "/.tmp-chrome4/" >> .gitignore
> ```
>
> y, si el repositorio ya se compartió, limpiar ese contenido del historial
> (`git filter-repo` o BFG). Un perfil de navegador puede contener cookies y credenciales
> guardadas.

---

## 11. Modelo de datos

### 11.1 Diagrama entidad-relación

```text
  energy_levels                 daily_plans                    goal_types
  ┌──────────────┐        ┌───────────────────────┐       ┌──────────────┐
  │ id           │1      *│ id                    │*     1│ id           │
  │ slug (uq)    ├────────┤ plan_date (uq, date)  │       │ slug (uq)    │
  │ name         │        │ energy_level_id (fK)  │       │ name         │
  │ emoji        │        │ achievements          │       │ subtitle     │
  │ sort_order   │        │ pending               │       │ sort_order   │
  │ is_active    │        │ pending_when          │       │ is_active    │
  └──────────────┘        │ proud_of              │       └──────┬───────┘
                          │ timestamps            │              │1
                          └───┬───┬───┬───┬───┬───┘              │
                              │   │   │   │   │                  │
              ┌───────────────┘   │   │   │   └────────────┐     │
              │1                 │1  │1  │1               │1    │
              ▼*                 ▼*  ▼*  ▼*               ▼*    ▼*
        plan_goals        schedule_  plan_   action_   reflection_   (* 1..*)
        ┌──────────┐      entries    notes   blocks    answers
        │ id       │      ┌────────┐ ┌──────┐┌────────┐┌──────────────┐
        │ plan_id  │      │ id     │ │ id   ││ id     ││ id           │
        │ goal_typ │      │ plan_id│ │plan  ││plan_id ││ plan_id      │
        │ slot 1-3 │      │ slot_id│ │cont. ││dur_id  ││ question_id  │
        │ descript.│      │ start_ │ │order ││outc_id ││ is_checked   │
        │ is_done  │      │ time   │ └──────┘│ task   ││ answer       │
        │ completed│      │ activity│        │started │└──────┬───────┘
        │ uq(plan, │      │ is_done │        │finished│       │*
        │    slot) │      │ uq(plan,│        └────────┘       │1
        └──────────┘      │  slot)  │   action_block_   reflection_
                          └─────────┘   durations       questions
                    schedule_slots      action_block_
                                        outcomes

        daily_plan_preparation  (pivote N:M)
        ┌───────────────────────────────────────┐
        │ daily_plan_id  ─► daily_plans         │
        │ preparation_item_id ─► preparation_items
        │ is_checked                            │
        │ preparation_items_description         │
        │ uq(daily_plan_id, preparation_item_id)│
        └───────────────────────────────────────┘
```

### 11.2 Tablas del dominio

#### `daily_plans` — tabla eje

Un registro por día planificado. Cabecera + cierre del día.

| Columna | Tipo | Notas |
| --- | --- | --- |
| `id` | bigint PK | |
| `plan_date` | date, **único** | Un solo diario por fecha. El nombre del día no se guarda: se deduce |
| `energy_level_id` | FK `energy_levels`, nullable | `nullOnDelete` |
| `achievements` | text, nullable | "Lo que logré hoy" |
| `pending` | text, nullable | "Lo que quedó pendiente" |
| `pending_when` | text, nullable | "¿Cuándo lo haré?" |
| `proud_of` | text, nullable | "Hoy estoy orgulloso/a de mí porque" |
| `created_at`, `updated_at` | timestamps | |

#### Hijas del día

| Tabla | Columnas | Reglas y relaciones |
| --- | --- | --- |
| `plan_goals` | `id`, `daily_plan_id` (FK cascade), `goal_type_id` (FK null), `slot` tinyint, `description` varchar(255), `is_done` bool, `completed_at` timestamp | `unique(daily_plan_id, slot)`. `slot` ∈ 1..3. `completed_at` se sella al marcar cumplido y se limpia al desmarcar |
| `schedule_entries` | `id`, `daily_plan_id` (FK cascade), `schedule_slot_id` (FK cascade, **nullable** desde la última migración), `start_time` time nullable, `activity` varchar(255) nullable, `is_done` bool | `unique(daily_plan_id, schedule_slot_id)`. La hora vive en la propia franja; el `slot_id` solo recuerda de qué franja del catálogo se generó |
| `action_blocks` | `id`, `daily_plan_id` (FK cascade), `action_block_duration_id` (FK null), `action_block_outcome_id` (FK null), `task` varchar(255), `started_at`, `finished_at` | Uno a muchos por día. La suma de `minutes` es el "foco" del día |
| `plan_notes` | `id`, `daily_plan_id` (FK cascade), `content` text, `sort_order` tinyint | Ordenadas por `sort_order` |
| `reflection_answers` | `id`, `daily_plan_id` (FK cascade), `reflection_question_id` (FK cascade), `is_checked` bool, `answer` text nullable | `unique(daily_plan_id, reflection_question_id)` (nombre `reflection_answers_unique`) |
| `daily_plan_preparation` | `id`, `daily_plan_id` (FK cascade), `preparation_item_id` (FK cascade), `is_checked` bool, `preparation_items_description` text nullable, timestamps | Pivote N:M. `unique(daily_plan_id, preparation_item_id)` (nombre `daily_plan_preparation_unique`) |

#### Catálogos

| Tabla | Columnas | Contenido sembrado |
| --- | --- | --- |
| `energy_levels` | `id`, `slug` (uq), `name`, `emoji`(16), `sort_order`, `is_active` | Baja 😞 · Media 😐 · Alta 😃 |
| `goal_types` | `id`, `slug` (uq), `name`, `subtitle`, `sort_order`, `is_active` | Debo hacer (Responsabilidad) · Quiero hacer (Algo que me motiva) · Algo para mí (Autocuidado/Bienestar) |
| `preparation_items` | `id`, `name`, `sort_order`, `is_active` | Materiales · Ropa adecuada · Alimentación · Cargar dispositivos · Espacio organizado · Todo lo necesario para mis actividades |
| `schedule_slots` | `id`, `start_time` time (uq), `sort_order`, `is_active` | 15 franjas: 07:00 a 21:00, una por hora |
| `action_block_durations` | `id`, `minutes` (uq), `label`, `sort_order`, `is_active` | 5, 10, 15 y 20 minutos |
| `action_block_outcomes` | `id`, `slug` (uq), `name`, `sort_order`, `is_active` | Lo terminé · Avancé · Necesito otro bloque · Necesito pedir orientación |
| `reflection_questions` | `id`, `category` (index), `question` text, `input_type` enum(`checkbox`,`textarea`), `sort_order`, `is_active` | 5 preguntas de la categoría `procrastinacion`: ¿Qué estoy evitando? · ¿Por qué lo estoy postergando? · ¿Qué me está distrayendo? · ¿Necesito desglosarlo en pasos más pequeños? · ¿Qué puedo hacer AHORA en 5 minutos? |

> Todos los catálogos usan `scopeActive()` (filtra `is_active = true` y ordena por
> `sort_order`). Desactivar un ítem lo retira del formulario sin perder el histórico.

### 11.3 Tablas del esqueleto de Laravel (presentes pero sin uso funcional)

`users` (vacía), `password_reset_tokens`, `sessions` (**en uso**: `SESSION_DRIVER=database`),
`cache`, `cache_locks`, `jobs`, `job_batches`, `failed_jobs`. Las migraciones
`0001_01_01_00000*` son del esqueleto; las 16 migraciones `2026_09_29_*` y `2026_09_30_*`
son del dominio.

### 11.4 Estado de la base de datos de desarrollo

Al momento de redactar esta documentación, la base `planificador-diario` tenía los 7
catálogos sembrados (3 energías, 3 tipos de objetivo, 6 ítems de preparación, 15 franjas,
4 duraciones, 4 resultados y 5 preguntas) y **7 diarios registrados** entre el 28/09/2026 y el
04/10/2026, con 21 objetivos, 114 franjas de horario, 7 bloques de acción, 7 notas, 42
filas de preparación y 35 respuestas de reflexión. Las 19 migraciones estaban aplicadas.

---

## 12. Módulos del sistema

Cada módulo se describe con **qué hace**, **de qué archivos se compone** y **cómo funciona
paso a paso**.

---

### 12.1 Inicio y menú principal

**Propósito.** Es la portada del sistema (`/`). Muestra en una sola pantalla el estado del
día de hoy y los dos caminos principales: crear un diario o consultar los ya registrados.

**Archivos.**

| Archivo | Rol |
| --- | --- |
| `app/Http/Controllers/HomeController.php` | Consulta si existe el diario de hoy y arma la vista |
| `resources/views/home/index.blade.php` | Portada: tarjeta de hoy + dos tarjetas de acceso |
| `resources/views/layouts/app.blade.php` | Cabecera, menú lateral y pie (común a todas las pantallas) |
| `public/js/menu.js`, `public/js/reloj.js` | Menú lateral y fecha/hora de la cabecera |

**Paso a paso.**

1. El usuario abre `/`. La ruta `home` llama a `HomeController@index`.
2. El controlador ejecuta `DailyPlan::findToday()`, que busca por la fecha de hoy
   (`Helper::toCarbon(now())`) y devuelve el día con todas sus relaciones o `null`.
3. Envía a la vista `today` (el modelo o `null`) y `todayLabel` ("martes 29 de septiembre de
   2026", con `Helper::longDate`).
4. **Si existe el diario de hoy**, la tarjeta turquesa muestra el resumen de avance
   (`$today->progressSummary()`): objetivos cumplidos, porcentaje del horario, minutos de
   foco y si el cierre está escrito; y ofrece *Abrir*, *Modificar* e *Imprimir*.
5. **Si no existe**, la tarjeta invita a crear el diario de hoy con el botón que va a
   `/diario/today` (que abre el formulario con la fecha de hoy bloqueada).
6. Debajo, dos tarjetas de acceso directo: *Crear diario* (→ `/diario`) y *Consultar diario*
   (→ `/diario/listado`), las mismas opciones del menú lateral.

**Datos derivados.** `progressSummary()` calcula: objetivos hechos/total y porcentaje,
franjas hechas/total y porcentaje, minutos de foco (suma de las duraciones de los bloques de
acción) y el texto humano de esos minutos ("1 h 30 min").

---

### 12.2 Formulario del diario

**Propósito.** Es el corazón del sistema: un único formulario que sirve para **crear**, **ver**
(solo lectura) y **modificar** un día, con las ocho secciones, validación en el navegador y
en el servidor, línea de tiempo en vivo y guardado por AJAX.

**Archivos.**

| Archivo | Rol |
| --- | --- |
| `app/Http/Controllers/DailyController.php` | `index()` formulario en blanco · `today()` con la fecha de hoy bloqueada |
| `app/Http/Controllers/DailyPlanController.php` | `show()` ver · `edit()` modificar · `store()` crear · `update()` actualizar · `detail()` JSON |
| `app/Http/Controllers/Concerns/DatosDelFormulario.php` | Catálogos y datos comunes de los tres modos |
| `resources/views/diario/formulario.blade.php` | La vista (478 líneas) con las ocho secciones |
| `resources/views/diario/_grafico.blade.php` | Tarjeta del gráfico + `@json($grafico)` con los datos |
| `resources/views/diario/_modal_imprimir.blade.php` | Modal de la marca de agua del PDF |
| `public/js/script.js` | Fecha/día, filas del horario, casillas con detalle, limpiar, validación y guardado |
| `public/js/grafico.js` | Redibujo de la línea de tiempo con cada cambio |

**Los tres modos.**

| Modo | Rutas | Método | Fecha | Campos | Destino al guardar |
| --- | --- | --- | --- | --- | --- |
| `crear` | `/diario` y `/diario/today` | POST a `diario.store` | Editable (en `/today`, bloqueada) | Editables | Inicio (`/`) |
| `ver` | `/diario/{id}` | — | Solo lectura | Todos `readonly`/`disabled` | — (botón Imprimir) |
| `editar` | `/diario/{id}/editar` | PUT a `diario.update` | Editable | Editables | Listado (`/diario/listado`) |

**Paso a paso — crear un diario.**

1. **Apertura.** `DailyController@index` llama a `datosFormulario(plan: null, modo: 'crear')`.
   El trait carga los catálogos activos (energías, tipos de objetivo, ítems de preparación,
   preguntas de procrastinación, duraciones y resultados del bloque, y los días de la semana)
   y arma la acción del formulario, el método HTTP, si la fecha está bloqueada y el JSON del
   gráfico vacío. La vista reutiliza el formulario con todos los campos en blanco.
2. **Fecha y día.** El campo `plan_date` usa `bootstrap-datepicker` en formato `aaaa-mm-dd`
   con idioma español. Al elegir una fecha, `P.Formulario.marcarDia()` calcula el día con
   `Date.getDay()` y marca la letra correspondiente (L M M J V S D). Las letras son radios
   `disabled`: son informativas, no se envían.
3. **Energía.** Tres opciones tipo botón (Baja/Media/Alta) con su emoji, tomadas del catálogo.
4. **Mis 3 objetivos principales.** Tres filas fijas, una por tipo del catálogo, numeradas 1,
   2 y 3. Cada fila lleva `goals[i][slot]` y `goals[i][goal_type_id]` como campos ocultos, el
   texto en `goals[i][description]` (obligatorio, 255 caracteres) y la casilla
   `goals[i][is_done]`. El `slot` se deriva del orden del catálogo (índice + 1).
5. **Antes de empezar.** Un bloque por ítem del catálogo: casilla `preparation[i][is_checked]`,
   el id oculto y un `textarea` de descripción
   (`preparation[i][preparation_items_description]`) que permanece deshabilitado hasta marcar
   la casilla y se habilita con `data-tf-habilita`.
6. **Mi horario de hoy.** Tabla dinámica. Por cada franja: `schedule[i][id]` (oculto, solo si
   ya existe), `schedule[i][start_time]` (tipo `time`), `schedule[i][activity]` y
   `schedule[i][is_done]`. Los botones permiten *Agregar franja* (clona la plantilla
   `<template id="plantilla-horario">` sustituyendo `__INDICE__`), *Quitar franja* y *marcar
   todas* desde la cabecera de la tabla. La fila "Presiona + para agregar…" solo se ve cuando
   no hay franjas.
7. **Si estoy procrastinando.** Un bloque por pregunta del catálogo: casilla
   `reflections[i][is_checked]`, id oculto y respuesta en `reflections[i][answer]`
   (habilitada al marcar la casilla).
8. **Bloque de acción.** Un único bloque: `action_blocks[0][id]` (oculto, para que el PUT
   actualice en vez de crear), duración (`action_block_duration_id`), resultado
   (`action_block_outcome_id`) y tarea (`task`).
9. **Cierre del día.** Cuatro campos obligatorios: `achievements`, `pending`, `pending_when`
   y `proud_of`, todos marcados con `data-tf-requerido="cierre"`.
10. **Notas.** Un `textarea` `notes[0][content]`; al guardar, cada línea no vacía se convierte
    en una fila de `plan_notes` con su `sort_order`.
11. **Gráfico.** Debajo del formulario, `diario._grafico` pinta la tarjeta y deja los datos en
    `<script type="application/json" id="grafico-agenda-datos">`. `P.Grafico` dibuja y
    vuelve a dibujar con cada cambio del horario, sin ir al servidor.
12. **Validación en el navegador.** Al enviar, `P.Formulario.validar()` revisa los campos con
    `data-tf-requerido` (energía, objetivos, al menos una hora y una actividad del horario y
    los cuatro campos del cierre), pinta en rojo lo que falta, escribe los mensajes en
    `#formulario-mensajes` y enfoca el primer campo con problema. Si todo está bien, pide
    confirmación con bootbox antes de enviar.
13. **Guardado.** `P.Formulario.guardar()` envía por AJAX (`POST` o `PUT`, según el modo) el
    formulario serializado con el testigo CSRF.
14. **Validación en el servidor.** `DailyPlanController@store` ejecuta `validateDay()`. Si
    falla, `bootstrap/app.php` responde **422** con `{ ok:false, message, errors }` y el
    navegador pinta los errores por campo bajo el formulario.
15. **Persistencia.** `DailyPlan::createDay($data)` abre una transacción y hace: crear la
    cabecera; `refreshDayStructure()` (genera las 15 franjas desde el catálogo, las filas del
    checklist y las respuestas vacías de reflexión, sin duplicar lo existente); y
    `applyDayData($data)` (sincroniza objetivos, horario, preparación, reflexiones, notas y
    bloques).
16. **Respuesta.** **201** con `{ ok:true, message, data:{ plan } }`. El mensaje incluye la
    fecha larga ("Diario del martes 29 de septiembre de 2026 registrado correctamente.").
    Al aceptar la alerta, el navegador va al inicio.

**Paso a paso — ver un diario.**

1. `DailyPlanController@show` busca el registro; si no existe, redirige al listado con el
   error "El diario que intentas ver no existe.".
2. Si existe, llama a `$plan->loadFull()` (carga `energyLevel`, `goals.goalType`,
   `scheduleEntries.scheduleSlot`, `actionBlocks.duration`, `actionBlocks.outcome`, `notes`,
   `preparationItems` y `reflectionAnswers.question`) y arma la vista con `modo: 'ver'`.
3. La vista pone `readonly` en los campos de texto y `disabled` en casillas, radios y
   selectores (no admiten `readonly`), oculta los botones de agregar/quitar y los avisos de
   "obligatorio", y deja a la vista los botones *Imprimir* y *Volver al listado*.

**Paso a paso — modificar un diario.**

1. `edit` carga el día completo con `loadFull()` y arma el mismo formulario con
   `modo: 'editar'`; el `action` es `diario.update` y el método, `PUT` (campo `_method`).
2. Al guardar, `update` primero comprueba que el diario exista (**404** si no) y **después**
   valida, para que un id inexistente no devuelva un error de campos. La regla de unicidad de
   la fecha ignora el propio id.
3. `DailyPlan::updateDay($data)` abre una transacción, actualiza solo las claves presentes en
   la cabecera y vuelve a aplicar `applyDayData($data)`.
4. Responde con el día actualizado y el mensaje "Diario del … actualizado correctamente.".

**Reglas de sincronización de las secciones (dentro de `applyDayData`).**

| Sección | Comportamiento |
| --- | --- |
| Objetivos | Uno por ranura (`firstOrNew`). Descripción vacía = se borra la ranura. `completed_at` se sella la primera vez que se marca cumplido y se limpia al desmarcar |
| Horario | La lista enviada es la definitiva: se actualizan las franjas con `id`, se crean las nuevas y se borran las que ya no vienen |
| Preparación | `syncWithoutDetaching` por ítem: marca/desmarca y guarda la descripción libre |
| Reflexiones | `firstOrNew` por pregunta: guarda el check y la respuesta |
| Notas | Se borran las del día y se recrean desde el texto (una por línea no vacía) |
| Bloques de acción | Se actualizan los que traen `id` y se crean los nuevos; los que ya no vienen se eliminan. El formulario envía siempre `action_blocks[0]`, así que **no se crea una fila vacía** si la sección no se completó |

**Casos borde previstos.**

- El formulario llega con `action_blocks[0]` aunque la sección esté vacía: se descarta por no
  tener contenido (duración, resultado, tarea ni horas).
- `schedule_slot_id` puede ser nulo: las franjas agregadas a mano no dependen del catálogo.
- Si el día no tiene franjas, el gráfico muestra "Todavía no hay franjas de horario para
  graficar" y el servidor parte de una ventana de 07:00 a 19:00.
- El controlador envuelve todo en `try/catch` con `report()`: cualquier fallo inesperado deja
  un mensaje amable al usuario y la traza en el log (no una pantalla de error).

---

### 12.3 Listado y buscador de diarios

**Propósito.** Consultar todos los días registrados, con buscador y filtros, y desde ahí ver,
modificar, imprimir, eliminar y generar los reportes.

**Archivos.**

| Archivo | Rol |
| --- | --- |
| `app/Http/Controllers/DailyPlanController.php` | `index()` (página), `list()` (JSON de la tabla), `destroy()` (eliminar) |
| `app/Models/DailyPlan.php` | `allForList()` y `filtrado()` (consulta filtrada) |
| `resources/views/diario/index.blade.php` | Miniformulario de búsqueda, tabla, botones de reporte y los dos modales |
| `public/js/app.js` | `P.Tabla` (DataTables en español), `P.Alerta`, `P.Ajax`, `P.Impresion`, `P.Reporte` |

**Paso a paso.**

1. La ruta `diario.listado` llama a `DailyPlanController@index`, que entrega los catálogos que
   necesita el miniformulario (niveles de energía activos y los días de la semana).
2. La vista dibuja la tabla `#tabla-diarios` **vacía**: la llena DataTables por AJAX contra
   `diario.tabla`. La tabla lleva en atributos `data-*` las plantillas de URL de ver, editar,
   eliminar, reporte y resumen (con `__ID__`, que el JS sustituye).
3. `P.Tabla.crear()` inicializa DataTables con:
   - idioma español, 10 filas por página y selector de 5, 10, 25, 50 y 100;
   - orden inicial por la **fecha del diario** (columna 1), del más antiguo al más reciente;
   - `autoWidth: true` y sin `scrollX` (con `scrollX` DataTables reemplazaba la cabecera por
     una copia que quedaba invisible al existir un `<tfoot>`);
   - cuatro columnas: Nº, Fecha (con la letra del día), Energía (chip de color con emoji) y
     Acción (cuatro botones);
   - `dataSrc` que valida el sobre: si la respuesta trae `ok:false`, avisa con bootbox y
     devuelve una tabla vacía;
   - manejo del error `abort`: DataTables cancela la petición anterior al cambiar filtros y
     eso **no** es una falla (avisarlo mostraba un error falso al escribir).
4. `GET /diario/tabla` ejecuta `DailyPlanController@list`: lee los filtros de la dirección,
   llama a `DailyPlan::allForList($filtros)` (con `energyLevel` precargado y orden por fecha)
   y devuelve `{ ok:true, data:{ plans, total, filtros } }`, donde `plans[]` es la fila
   `toListArray()`.
5. **Eliminar**: el botón abre una confirmación ("¿Deseas eliminar el diario del …? Esta
   acción no se puede deshacer."). Al aceptar, `P.Ajax.peticion` hace `DELETE` a
   `diario.destroy`; el controlador `deleteDay()` (dentro de transacción) borra el día y las
   claves foráneas en cascada limpian sus hijos. Se muestra **un solo** aviso de éxito
   (`avisoExito:false` en el ayudante y luego la alerta propia) y al aceptarlo se recarga la
   página completa.
6. Cada fila `toListArray()` incluye: `id`, `date` (ISO, para ordenar), `date_label`
   (29/09/2026), `day_letter`, `energy`, `energy_slug`, `energy_emoji`, un resumen de 80
   caracteres de `achievements`, y los contadores `goals_count`, `goals_done_count`,
   `action_blocks_count`, `notes_count` e `is_closed`.

---

### 12.4 Filtros y períodos

**Propósito.** Acotar lo que se ve en la tabla, en el Excel y en el resumen de desempeño, con
los mismos criterios. Los filtros se combinan y se aplican solos, sin botón "Filtrar".

**Archivos.**

| Archivo | Rol |
| --- | --- |
| `app/Filtros/Periodo.php` | Traduce el período elegido a un rango de fechas |
| `app/Models/DailyPlan.php` (`scopeFiltrado`, `scopeSearch`, `scopeBetweenDates`) | Aplican los filtros a la consulta |
| `app/Http/Controllers/DailyPlanController.php` (`filtrosDeLaPeticion`) | Lee los filtros en un solo lugar |
| `resources/views/diario/index.blade.php` | Miniformulario y su lógica de aplicación |

**Filtros disponibles.**

| Filtro | Parámetro | Formato | Comportamiento |
| --- | --- | --- | --- |
| Palabra clave | `palabra` | texto (basta una letra) | `LIKE %texto%` en cierre del día, objetivos, horario, bloque de acción y notas. Se aplica 350 ms después de dejar de escribir (y con Enter, al instante) |
| Fecha | `fecha` | `dd/mm/aaaa` | Día exacto; al elegirla se marca la letra del día |
| Energía | `energia` | `baja` / `media` / `alta` | Por `slug` del nivel. Volver a pulsar la opción marcada la quita |
| Período | `periodo` + campos | ver abajo | Rango de fechas completo |

**Períodos soportados** (`Periodo::TIPOS`):

| Período | Parámetros | Rango resultante |
| --- | --- | --- |
| Semanal | `semana` = `2026-W40` | Lunes a domingo de esa semana ISO |
| Quincenal | `mes` = `2026-09`, `mitad` = 1 o 2 | Día 1 al 15, o 16 al fin de mes |
| Mensual | `mes` = `2026-09` | Mes completo |
| Trimestral | `anio`, `trimestre` = 1..4 | Tres meses |
| Semestral | `anio`, `semestre` = 1 o 2 | Seis meses |
| Anual | `anio` | 1 de enero al 31 de diciembre |
| Rango | `desde`, `hasta` en `dd/mm/aaaa` | Elegido a mano; si falta una punta se usa la otra; si vienen invertidas, se acomodan |

**Paso a paso.**

1. El usuario elige qué filtrar en "Filtrar por" (Todos / Fecha / Energía / Período). El JS
   muestra solo el panel correspondiente (`pintarPaneles`) y, dentro de Período, solo los
   campos del tipo elegido (`pintarPeriodo`).
2. `leerPeriodo()` lee **solo los campos del bloque visible** (así el "mes" de la quincena no
   se confunde con el del mes completo) y exige los campos requeridos de ese tipo: un período a
   medio llenar **no filtra**.
3. `aplicar()` arma el objeto de filtros, lo compara con la última búsqueda
   (`filtrosIguales`) y, si es el mismo, **no vuelve a pedir** la tabla: así no se cancelan
   peticiones sin motivo.
4. Los filtros viajan como parámetros de la dirección en **todas** las peticiones de
   DataTables (`data: function (datos) { return $.extend({}, datos, filtros); }`), de modo que
   también se respetan al recargar la tabla después de eliminar.
5. `DailyPlan::scopeFiltrado()` aplica, en este orden:
   - si hay fecha exacta, manda esa fecha; si no, el rango del período (`whereBetween`);
   - la energía por `whereHas('energyLevel', slug = …)`;
   - la búsqueda libre con `scopeSearch`, que escapa `%`, `_` y `\` antes de armar el `LIKE`.
6. **Limpiar** deja el buscador en blanco (`limpiar()`), olvida la última búsqueda y vuelve a
   traer todos los diarios.
7. El objeto de filtros queda expuesto como `P.filtrosDelListado()`, que el botón *Generar
   resumen* usa para armar la URL del PDF con exactamente lo que se está viendo.

> **Coherencia de salidas:** la tabla, el Excel y el resumen llaman al mismo
> `scopeFiltrado()`, por eso los tres hablan siempre del mismo conjunto de días.

---

### 12.5 Gráfico del día

**Propósito.** Mostrar el día como una línea de tiempo: cada actividad es una barra ubicada en
su hora, con color propio; las cumplidas se ven nítidas, las pendientes claras y los
solapamientos translúcidos. En pantalla se dibuja con JavaScript; en el PDF, con GD.

**Archivos.**

| Archivo | Rol |
| --- | --- |
| `app/Graficos/AgendaDelDia.php` | Convierte franjas en tramos, detecta cruces y arma el JSON |
| `app/Graficos/AgendaPng.php` | Dibuja el mismo gráfico como PNG (con leyenda) para el PDF |
| `public/js/grafico.js` | Dibuja y redibuja la línea de tiempo en el navegador |
| `resources/views/diario/_grafico.blade.php` | Tarjeta y JSON de datos iniciales |

**Cómo se calcula (`AgendaDelDia`).**

1. Cada franja se convierte a **minutos desde las 00:00** (`aMinutos`, acepta `8:15`,
   `08:15` o `08:15:00`; descarta horas o minutos inválidos).
2. Los tramos se ordenan por hora de inicio y, a igualdad, por orden de aparición.
3. **El fin de cada tramo es el inicio del siguiente**; el último dura 60 minutos
   (`DURACION_ULTIMA`). Si el siguiente empieza antes o a la misma hora, al tramo se le dan
   60 minutos para que se vea y quede marcado el cruce.
4. Se asigna color de la paleta institucional de forma cíclica (`PALETA`, 8 colores) y se
   calculan `hora_inicio` y `hora_fin` como texto.
5. `seCruza()` detecta si un tramo se pisa con otro (comparando intervalos).
6. `piezas()` parte el eje en los cortes de todos los tramos y, en cada pedacito, informa qué
   tramos lo cubren: si lo cubre más de uno, la pieza se marca `cruce = true` y se pinta
   transparente. Así solo se aclara la parte efectivamente solapada.
7. `datos()` devuelve `{ inicio, fin, tramos, piezas }` con los bordes redondeados a la hora y
   un mínimo de dos horas de ventana; si no hay franjas, devuelve 07:00–19:00 vacío.

**En pantalla (`grafico.js`: SVG dibujado a mano, sin librería de gráficos).**

- Al cargar, `iniciar()` dibuja con el JSON que dejó el servidor (`datosIniciales()`) y se
  queda escuchando (`escuchar()`). Si no existe `#grafico-agenda` (por ejemplo en el listado),
  sale sin hacer nada. **No tiene auto-arranque:** lo inicia `P.arrancar()` de `app.js`.
- `franjasDelFormulario()` recorre las filas de `#tabla-horario tbody` (hora, actividad y
  casilla), salta la fila de "no hay franjas", recalcula y vuelve a dibujar en cada cambio
  (`actualizar()`), sin ir al servidor.
- `dibujar()` genera un `<svg viewBox="0 0 1000 190" width="100%" role="img">` con la rejilla
  de horas (cada 60 min, o 120 si el rango supera 12 horas) y las barras como rectángulos:
  **opacidad 1** si la actividad está cumplida, **0.42** si no, y **0.12** en las piezas donde
  dos actividades se pisan. Si no hay tramos escribe en el centro "Todavía no hay franjas de
  horario para graficar".
- El gráfico no lleva leyenda: cada barra tiene una zona transparente
  (`.tf-grafico__zona`, con `data-desde`, `data-hasta` y `data-actividad`) y, al pasar el
  puntero o enfocar con el teclado, `avisar()` crea un aviso flotante fijo (`#grafico-aviso`)
  con el horario y la actividad; `callar()` lo oculta y se oculta antes de cada redibujo para
  no quedar huérfano.
- Los listeners son delegados (`'input change'` sobre los campos de la tabla y un `click` en
  `document` sobre `#horario-agregar` y `.tf-horario__quitar` con `setTimeout(…, 0)`, para
  redibujar **después** de que el formulario cambie la tabla).
- La paleta y las proporciones del navegador replican las del servidor
  (`#0080D0, #F0600C, #00AE9C, #002060, #009CE4, #D90B0B, #006C60, #FC9000`) para que la
  pantalla y el PDF coincidan.

**En el PDF (`AgendaPng`).**

- Lienzo de 1040 px de ancho, alto 196 px más la leyenda (una fila por cada tres actividades,
  13 px por fila).
- Rejilla de horas (cada hora, o cada dos si el rango supera 12 horas).
- Barras partidas en piezas con opacidad: cumplida = sólida; pendiente = `56`; cruce = `98`.
- Contorno del color de cada actividad y leyenda en tres columnas con la hora y el nombre.
- Usa **DejaVu Sans** (la fuente que trae dompdf) y cae a Arial del sistema si no la
  encontrara. Se entrega como `data:image/png;base64,…` para incrustarlo en el HTML del PDF.

**Por qué dos implementaciones.** El PDF no ejecuta JavaScript, por lo que el gráfico debe
llegar dibujado como imagen; en pantalla, en cambio, conviene que se redibuje al instante
mientras se carga el horario. Ambas parten del **mismo objeto de datos** (`start_time`,
`activity`, `is_done`), así el gráfico de pantalla y el del PDF muestran lo mismo.

---

### 12.6 Reporte detallado en Excel

**Propósito.** Exportar a `.xlsx` los diarios que coinciden con la búsqueda: un diario por
fila, con todas las secciones desglosadas en columnas, listo para imprimir en horizontal o
para analizar.

**Archivos.**

| Archivo | Rol |
| --- | --- |
| `app/Excel/ReporteDiarioExport.php` | Construye el libro con PhpSpreadsheet y lo devuelve en memoria |
| `app/Models/DailyPlan.php` (`toReportArray`) | Arma las celdas de cada día |
| `app/Http/Controllers/DailyPlanController.php` (`reporte`) | Ruta, filtros, nombre del archivo y respuesta |
| `public/js/app.js` (`P.Reporte`) | Descarga el archivo por AJAX y guarda el blob |

**Paso a paso.**

1. El botón *Generar reporte detallado* llama a `P.Reporte.descargar(url, filtros, $boton)`,
   que pide `GET /diario/reporte` con los filtros activos (y bloquea el botón mientras espera).
2. El controlador lee los filtros y obtiene `DailyPlan::forReport($filtros)`, que precarga
   `energyLevel`, `preparationItems`, `reflectionAnswers.question`, `actionBlocks.duration`,
   `actionBlocks.outcome` y `notes` (así no se dispara una consulta por cada diario).
3. **Si no hay días** que coincidan, responde JSON 404 con "No hay diarios que coincidan con
   la búsqueda, así que no hay nada que exportar." y el navegador lo muestra como alerta (en
   vez de descargar un archivo vacío).
4. `ReporteDiarioExport::generar($planes, $filtros)` arma la hoja:
   - **Fila 1:** título combinado "MI PLANIFICADOR DIARIO · REPORTE DETALLADO" en azul oscuro.
   - **Fila 2:** subtítulo en turquesa con el programa, la fecha/hora de generación, la
     cantidad de diarios y los filtros aplicados.
   - **Fila 4:** encabezados de las **25 columnas**, cada uno con el color de su sección.
   - **Desde la fila 5:** un diario por fila, con bordes finos, texto ajustado, alto estimado
     según el contenido y filas pares sombreadas.
   - Filtro automático desde la fila de encabezados, panel congelado debajo, orientación
     horizontal ajustada al ancho y márgenes de impresión de 0,5 / 0,4 pulgadas.
5. Escribe con `Xlsx::save('php://output')` dentro de un búfer `ob_start()`, así el archivo
   **nunca toca el disco**; luego libera las hojas (`disconnectWorksheets`).
6. Responde con `Content-Type` de `.xlsx`, `Content-Disposition: attachment` con el nombre
   `reporte-diario[-filtrado]-AAAA-MM-DD.xlsx` y cabeceras `no-store`.
7. `P.Reporte` detecta si la respuesta es JSON (error) o binaria (archivo), lee el nombre del
   `Content-Disposition` y guarda el archivo con un enlace temporal.

**Las 25 columnas.**

| Sección (color) | Columnas |
| --- | --- |
| Cabecera (azul oscuro) | Fecha · Día · Energía |
| Mis 3 objetivos (naranja) | Debo hacer · Quiero hacer · Algo para mí |
| Antes de empezar (turquesa) | Materiales · Ropa adecuada · Alimentación · Cargar dispositivos · Espacio organizado · Todo lo necesario [Sí/No cada uno] |
| Procrastinación (naranja) | ¿Qué estoy evitando? · ¿Por qué lo estoy postergando? · ¿Qué me está distrayendo? · ¿Necesito desglosarlo? · ¿Qué puedo hacer en 5 minutos? [Sí/No cada uno] |
| Bloque de acción (turquesa oscuro) | Voy a trabajar durante · Cuando termine este bloque · ¿En qué vas a trabajar? |
| Cierre del día (rojo) | Logré · Pendiente · ¿Cuándo lo haré? · Orgulloso/a de mí |
| Notas (azul) | Notas / recordatorios |

> El orden de las columnas de la constante `COLUMNAS` es exactamente el orden de las celdas
> que devuelve `DailyPlan::toReportArray()`: si se agrega una columna, hay que tocar los dos
> lugares. Las filas de "Antes de empezar" y de procrastinación son dinámicas: se emiten en el
> orden del catálogo (`preparationItems` por id, respuestas por id de pregunta).

---

### 12.7 Resumen de desempeño en PDF

**Propósito.** Leer el conjunto de días filtrados y devolver un documento con números,
interpretación redactada y gráficos: qué se sostuvo, qué mejorar, metas, energía,
procrastinación y evolución.

**Archivos.**

| Archivo | Rol |
| --- | --- |
| `app/Reportes/AnalisisDeDesempeno.php` | Calcula todos los números (los detalles están en [§16](#16-motor-de-análisis-del-desempeño)) |
| `app/Reportes/RedaccionDelResumen.php` | Convierte los números en frases, con tono neutral |
| `app/Reportes/GraficosDelResumen.php` | Dibuja los tres gráficos PNG |
| `resources/views/diario/resumen.blade.php` | Documento HTML que consume dompdf |
| `app/Http/Controllers/DailyPlanController.php` (`resumen`, `alcanceDelResumen`, `resumenFileName`) | Ruta, alcance, gráficos y respuesta |
| `resources/views/diario/_modal_resumen.blade.php` | Modal para decidir si el PDF es documento de muestra |

**Paso a paso.**

1. En el listado, el botón *Generar resumen* abre el modal `#modalResumen`, que explica que se
   analizarán los diarios **según los filtros aplicados** y ofrece la casilla de marca de agua
   `SPECIMEN`.
2. Al pulsar *Generar PDF*, el navegador arma la URL con los filtros activos
   (`P.filtrosDelListado()`) más `marca=1` si corresponde, y la abre en una pestaña nueva.
3. `DailyPlanController@resumen` obtiene `DailyPlan::forReport($filtros)`. Si está vacío,
   responde JSON 404 ("No hay diarios que coincidan con la búsqueda, así que no hay resumen
   que generar.").
4. Llama a `AnalisisDeDesempeno::de($plans, $alcance)`, donde el **alcance** es la frase que
   describe los filtros: "Todos los diarios", o "Búsqueda por «x», el día …", o "…, energía
   media, el período 01/09/2026 al 30/09/2026".
5. Obtiene los textos con `RedaccionDelResumen::observaciones($analisis)` y los títulos con
   `RedaccionDelResumen::titulos()`.
6. Genera los tres gráficos como `data:` URI (procrastinación, día por día y energía) y, si
   el alcance es **un solo día**, agrega la línea de tiempo de ese día (`AgendaPng`).
7. Renderiza `diario.resumen` a HTML, lo pasa a dompdf en tamaño **carta**, lo renderiza y,
   si se pidió `marca=1`, pinta la marca `SPECIMEN` sobre el lienzo del PDF.
8. Devuelve el PDF en línea (`inline`) con nombre
   `resumen-desempeno[-DESDE][-HASTA][-muestra].pdf` y cabeceras `no-store`.

**Estructura del documento.**

| Bloque | Contenido |
| --- | --- |
| Encabezado | Marca TRANSFORMA Conecta, "MI PLANIFICADOR DIARIO", "RESUMEN DE DESEMPEÑO" y fecha de generación |
| Banda | ORGÁNIZATE - ACTÚA - AVANZA |
| Qué cubre este resumen | El alcance en palabras + cantidad de días + rango de fechas |
| Números | Rendimiento promedio · % del horario (hechas/total) · % de objetivos (hechos/total) · señales por día (total) |
| Textos | Una sección por tema: Desempeño del período · Aspectos que se sostuvieron · Aspectos a mejorar · Metas y objetivos alcanzados · Relación con la energía del día · Procrastinación y rendimiento · Evolución en el período |
| Performance vs procrastinación | Gráfico de puntos con el promedio de referencia y los dos puntos críticos |
| Día por día | Tabla: día, energía, rendimiento, horario, objetivos, preparativos y señales |
| Rendimiento de cada día | Gráfico de barras (colores por tramo: ≥80 turquesa, ≥50 azul, ≥25 amarillo, resto rojo) |
| Rendimiento según la energía | Barras horizontales (solo si hay más de un nivel de energía en el período) |
| Cómo se repartió el día | Línea de tiempo (solo si el resumen cubre un único día) |
| Nota | Aclaración de que el documento describe los propios registros y **no es un diagnóstico ni una valoración personal** |
| Avisos | Advertencias de contexto (sin señales registradas, sin franjas de horario, diarios sin cierre) |
| Pie | Marca institucional, lema y fecha de generación |

**Tono.** El módulo de redacción está separado del de cálculo a propósito: uno mide, el otro
escribe. Las frases evitan imperativos y valoraciones ("Aspectos a mejorar" en lugar de "lo
que hago mal"), y cuando un dato no alcanza para afirmar algo, lo dice con serenidad (por
ejemplo: "Con un solo registro no hay una evolución que comparar." o "En los registros de
este período el punto crítico todavía no puede ubicarse con precisión…").

---

### 12.8 PDF del día e impresión

**Propósito.** Reproducir la hoja institucional del planificador en un PDF tamaño carta, con
la marca de agua `SPECIMEN` opcional. El diseño está pensado para entrar en **una sola
página** (el gráfico del día se calcula con un alto ajustado justamente por eso), aunque un
horario con muchas franjas puede hacer que el documento ocupe dos.

**Archivos.**

| Archivo | Rol |
| --- | --- |
| `resources/views/diario/pdf.blade.php` | Documento HTML con tablas y colores institucionales |
| `app/Http/Controllers/DailyPlanController.php` (`printPdf`, `marcarComoMuestra`, `pdfFileName`) | Render, marca de agua y respuesta |
| `resources/views/diario/_modal_imprimir.blade.php` | Modal de la marca de agua |
| `public/js/app.js` (`P.Impresion`) | Comprueba que el diario exista y abre el PDF |

**Paso a paso.**

1. Desde el listado o desde la vista del día, el botón *Imprimir* llama a
   `P.Impresion.abrir(id)`, que primero consulta `GET /diario/{id}/detalle` (JSON) para
   verificar que el diario existe: si no, el aviso sale en una alerta en lugar de abrir una
   pestaña con un error.
2. Si existe, se muestra el modal `#modalImprimir` con la casilla "Incluir marca de agua
   SPECIMEN (sólo para pruebas)" y el botón *Generar PDF*.
3. `P.Impresion.generar()` abre en pestaña nueva
   `GET /diario/{id}/imprimir[?marca=1]`.
4. `printPdf` carga el día completo (`loadFull()`) y renderiza `diario.pdf` con el plan, la
   bandera de marca y el gráfico del día ya dibujado como PNG (`AgendaPng`).
5. dompdf procesa el HTML en tamaño carta; si hay marca, `marcarComoMuestra()` la pinta.
6. Responde el PDF en línea con nombre `planificador-diario-AAAA-MM-DD[-muestra].pdf` y
   cabeceras `no-store` (el PDF se pide siempre con la misma URL: sin eso, el navegador
   mostraría la versión anterior del diario).

**Detalles del documento.** El HTML usa **tablas** (dompdf no entiende flexbox ni variables
CSS) y colores hexadecimales; la fuente es **Helvetica** (base del PDF: no se incrusta, el
archivo pesa pocos KB y el texto queda seleccionable, con acentos, ñ, ¿ y ¡). La disposición
copia la hoja: cabecera con marca y títulos, banda turquesa, franja de datos del día, dos
columnas de secciones (objetivos, horario y notas a la izquierda; preparación,
procrastinación, bloque de acción y cierre a la derecha), la línea de tiempo abajo y el pie.

**Detalles de la marca de agua `SPECIMEN`.** Se dibuja con el lienzo de dompdf
(`page_script`) porque el PDF no permite rotar texto con CSS:

- texto de 88 pt, ángulo **−45°** (dompdf compone la matriz como `[cos, −sin, sin, cos]`, así
  que un ángulo positivo haría bajar la marca de izquierda a derecha);
- color gris oscuro `[0.28, 0.28, 0.28]` con opacidad **0.16**: sobre el papel blanco queda
  casi invisible y sobre el texto negro no lo tapa;
- la posición se calcula a mano restando media longitud del texto en la dirección de la
  diagonal y descontando la altura de la fuente dividida por `getFontHeightRatio()`, para que
  el centro óptico de las mayúsculas caiga en el centro de la página;
- la opacidad se fija en cada página justo antes de pintar y se devuelve a 1, porque en PDF
  la transparencia es un estado de dibujo que afecta a lo que se pinta después.

---

### 12.9 Catálogos y datos base

**Propósito.** Proveer los valores seleccionables del formulario y garantizar que una
instalación nueva quede lista para usar con un solo comando.

**Archivos.**

| Archivo | Rol |
| --- | --- |
| `database/seeders/DatabaseSeeder.php` | Llama a los siete seeders de catálogo |
| `EnergyLevelSeeder`, `GoalTypeSeeder`, `PreparationItemSeeder`, `ScheduleSlotSeeder`, `ActionBlockDurationSeeder`, `ActionBlockOutcomeSeeder`, `ReflectionQuestionSeeder` | Sembrado idempotente con `updateOrCreate` |
| `app/Models/{EnergyLevel,GoalType,PreparationItem,ScheduleSlot,ActionBlockDuration,ActionBlockOutcome,ReflectionQuestion}.php` | Modelos de catálogo con `scopeActive()` |
| `database/migrations/2026_09_29_000001..000007_*` | Creación de las tablas de catálogo |

**Paso a paso.**

1. `php artisan migrate --seed` ejecuta las 19 migraciones y luego `DatabaseSeeder`.
2. Cada seeder usa `updateOrCreate` sobre una clave natural (el `slug`, el nombre, la hora, los
   minutos o la pregunta), de modo que **se puede volver a correr sin duplicar filas** y sirve
   también para actualizar los catálogos en una base existente.
3. `ScheduleSlotSeeder` genera una franja por hora desde las 7:00 hasta las 21:00
   (`FIRST_HOUR` a `LAST_HOUR`), 15 en total. Al crear un día,
   `DailyPlan::generateScheduleEntries()` copia esas horas como punto de partida (el usuario
   puede agregar, quitar o cambiar horas a gusto).
4. `DatabaseSeeder` **no** crea usuarios de prueba: la aplicación es de un único usuario y no
   usa autenticación.
5. En el formulario, cada catálogo se consume con `scopeActive()`: solo llega lo activo, en el
   orden definido por `sort_order`.

**Cómo se relaciona un catálogo con el día.**

| Catálogo | Al crear el día | Al guardar |
| --- | --- | --- |
| `energy_levels` | — | Se guarda `energy_level_id` en la cabecera |
| `goal_types` | — | Se guarda `goal_type_id` de cada objetivo |
| `preparation_items` | Se crea una fila en el pivote por cada ítem activo (`is_checked = false`) | Se actualiza el check y la descripción |
| `schedule_slots` | Se crea una franja por cada hora del catálogo | Las franjas enviadas se actualizan o se crean; el resto se borra |
| `reflection_questions` | Se crea una respuesta vacía por cada pregunta activa | Se actualiza el check y la respuesta |
| `action_block_durations` / `action_block_outcomes` | — | Se guardan en el bloque de acción |

---

### 12.10 Dominio y persistencia

**Propósito.** `App\Models\DailyPlan` es el modelo eje y concentra todas las consultas y
escrituras del planificador, de modo que el controlador no toque la base directamente.

**Relaciones.**

| Método | Tipo | Detalle |
| --- | --- | --- |
| `energyLevel()` | `belongsTo` | Nivel de energía del día |
| `goals()` | `hasMany` `PlanGoal` | Ordenados por `slot` (1-2-3) |
| `scheduleEntries()` | `hasMany` `ScheduleEntry` | Ordenados por `start_time` y luego por `id` |
| `actionBlocks()` | `hasMany` `ActionBlock` | Bloques de acción del día |
| `notes()` | `hasMany` `PlanNote` | Ordenadas por `sort_order` |
| `reflectionAnswers()` | `hasMany` `ReflectionAnswer` | Respuestas a las preguntas |
| `preparationItems()` | `belongsToMany` | Pivote `daily_plan_preparation` con `is_checked` y `preparation_items_description` |

**Constantes y atributos.**

- `FULL_RELATIONS`: lista de relaciones para *eager loading* del día completo.
- `MAX_GOALS = 3`: ranuras válidas de objetivos.
- `weekday_letter`: atributo calculado que devuelve L M M J V S D desde `plan_date` (no se
  guarda en la base).

**Consultas.**

| Método | Qué devuelve |
| --- | --- |
| `findByDate($date)` | El día de una fecha con todo cargado, o `null` |
| `findToday()` | El día de hoy con todo cargado |
| `allForList($filtros)` | Todos los días filtrados, ordenados por fecha (alimenta la tabla) |
| `forReport($filtros)` | Los días filtrados con las relaciones del reporte precargadas |
| `paginateList()` | Listado paginado con contadores (disponible; la tabla usa `allForList`) |
| `scopeForDate`, `scopeBetweenDates`, `scopeSearch`, `scopeFiltrado` | Filtros reutilizables |

**Escrituras (todas en transacción con `enTransaccion`, que reporta y relanza el error).**

| Método | Qué hace |
| --- | --- |
| `createDay($data)` | Crea la cabecera, genera la estructura hija y aplica los datos del formulario; devuelve el día recargado |
| `updateDay($data)` | Actualiza solo las claves presentes y vuelve a aplicar los datos del formulario |
| `deleteDay()` | Elimina el día (las claves foráneas en cascada limpian los hijos) |
| `refreshDayStructure()` | Genera horario, checklist y respuestas vacías de forma idempotente |
| `generateScheduleEntries()` | Crea solo las franjas del catálogo que falten |
| `generatePreparationItems()` | `syncWithoutDetaching` de los ítems activos |
| `generateReflectionAnswers()` | `firstOrCreate` de las respuestas de las preguntas activas |
| `applyDayData($data)` | Reparte los datos a los `sync*` de cada sección |
| `syncGoals` / `syncScheduleActivities` / `syncPreparationItems` / `syncReflections` / `syncNotes` / `syncActionBlocks` | Ver [§12.2](#122-formulario-del-diario) |

**Resúmenes para las respuestas.**

| Método | Devuelve |
| --- | --- |
| `progressSummary()` | Avance del día: objetivos, horario, minutos de foco, cierre |
| `toListArray()` | Fila de la tabla del listado |
| `toDetailArray()` | Día completo con todas las secciones (JSON de detalle) |
| `toReportArray()` | Celdas del reporte en Excel, más la fecha |
| `isClosed()` | `true` si hay algo escrito en el cierre del día |

---

### 12.11 Utilidades de formato

**Propósito.** `App\Helpers\Helper` reúne fechas, cifras y textos en español para no repetir
formateos en modelos, controladores y vistas. Todos los métodos son **estáticos**.

**Fechas.**

| Método | Resultado |
| --- | --- |
| `toCarbon($valor)` | Convierte Carbon, DateTime, texto o `null` a Carbon. **Prueba primero los formatos con barras como día/mes/año** (`d/m/Y`, `d/m/Y H:i`, `d/m/Y H:i:s`, `d-m-Y`) y recién después deja que Carbon decida: si no, `05/10/2026` se leería como 10 de mayo |
| `date`, `dateTime`, `time`, `timeLabel` | `29/09/2026`, `29/09/2026 14:35`, `14:35`, `7:00` |
| `dayIndex`, `dayName`, `dayLetter` | Índice 0-6, "martes"/"mar"/"Martes", "M" |
| `monthIndex`, `monthName` | Índice 1-12, "septiembre"/"sep"/"Septiembre" |
| `longDate`, `shortDate` | "martes 29 de septiembre de 2026", "29 sep 2026" |
| `isToday`, `isPast`, `isFuture`, `isWeekend`, `diffInDays`, `humanDiff` | Comparaciones y distancias ("hace 3 días") |
| `startOfWeek`, `rangeLabel`, `weekLabels` | Lunes de la semana, "1 al 30 de septiembre de 2026" |

**Cifras.** `number` (1.234,5), `currency` ($ 1.234,50), `percent`, `percentageOf` (devuelve
"0 %" sin dividir por cero), `minutesToHuman` ("1 h 30 min"), `ordinal`, `fileSize`, `average`,
`sum`.

**Textos.** `limit`, `words`, `slug`, `title`, `sentence`, `upper`, `lower`, `initials`,
`maskEmail`, `yesNo`, `listToText`, `pluralize`, `strip` (colapsa espacios), `isBlank`,
`fallback`.

**Constantes en español.** `MONTHS`, `MONTHS_SHORT`, `DAYS`, `DAYS_SHORT`, `DAYS_LETTER`
(0 = domingo … 6 = sábado). Están escritas en el código a propósito: no dependen del locale de
la aplicación ni de la extensión `intl` (que no está cargada en este servidor).

**Uso en Blade.**

```blade
@use('App\Helpers\Helper')
{{ Helper::longDate($plan->plan_date, withWeekday: true) }}
{{ Helper::timeLabel($franja->start_time) }}
```

---

### 12.12 Front-end compartido

**Propósito.** Un espacio de nombres (`window.Planificador`) con módulos pequeños y
coherentes, cargados por la plantilla base y por las vistas que los necesitan.

**Módulos.**

| Módulo | Archivo | Métodos principales |
| --- | --- | --- |
| `P.config` | `app.js` | Títulos y textos de los avisos, botones, cantidad de filas de las tablas |
| `P.token` | `app.js` | Testigo CSRF leído de `<meta name="csrf-token">` |
| `P.Util` | `app.js` | `escapar` (escapa antes de inyectar HTML), `numero`, `minutos`, `limpiar` |
| `P.Alerta` | `app.js` | `disponible`, `mostrar`, `exito`, `error`, `aviso`, `info`, `confirmar` (bootbox) |
| `P.Ajax` | `app.js` | `mensajeDeError(xhr)`, `peticion({url, tipo, datos, alExito, …})`, `get`, `post`, `put`, `eliminar`; agrega el testigo CSRF y el sobre `{ok, message, data/errors}` |
| `P.Tabla` | `app.js` | `crear(selector, opciones)` con idioma español, longitud de página y opciones heredadas |
| `P.Impresion` | `app.js` | `url(plantilla, id)`, `abrir(id)` (verifica y abre el modal), `generar()` |
| `P.Reporte` | `app.js` | `descargar(url, filtros, $boton)`, `esJson`, `leerAviso`, `nombreDe`, `guardarArchivo` |
| `P.arrancar` | `app.js` | Arranca reloj, menú, impresión, gráfico y activa DataTables cuando el documento está listo |
| `P.Formulario` | `script.js` | `iniciar`, `iniciarFecha`, `marcarDia`, `iniciarHorario`, `agregarFila`, `sincronizarTodos`, `actualizarVacio`, `iniciarDetalles`, `habilitarDetalle`, `sincronizarDetalles`, `iniciarAcciones`, `limpiar`, `requeridos`, `validar`, `mostrarErrores`, `limpiarErrores`, `refrescarErrores`, `iniciarValidacion`, `revisarCampo`, `enfocar`, `iniciarGuardado`, `enviar`, `guardar`, `erroresDelServidor`, `nombreDeCampo` |
| `P.Grafico` | `grafico.js` | `iniciar`, `datosIniciales`, `franjasDelFormulario`, `calcular`, `seCruza`, `piezas`, `dibujar`, `actualizar`, `escuchar`, `avisar`, `callar`, `aMinutos`, `aHora`, `colorDeTexto`, `escapar` |
| `P.Menu` | `menu.js` | `iniciar`, `abrir`, `cerrar`, `alternar`, `estaAbierto`, `marcarActivo` |
| `P.Reloj` | `reloj.js` | `fechaLarga`, `fechaCorta`, `fechaNumerica`, `hora`, `pintar`, `iniciar`, `detener` |

**Convenciones.**

- Todos los módulos son IIFE `(function ($, P) { 'use strict'; … })(jQuery, window.Planificador)`.
- La comunicación entre archivos es por el objeto compartido `P`, y entre el servidor y el
  navegador por atributos `data-*` y por el bloque
  `<script type="application/json" id="grafico-agenda-datos">`.
- Los textos se insertan escapados (`P.Util.escapar`) para evitar inyección de HTML.
- Los mensajes al usuario son en español y con tono cercano ("No se pudo cargar el listado de
  diarios.", "¿Deseas eliminar el diario del …? Esta acción no se puede deshacer.").
- Los listeners usan **espacios de nombres de jQuery** (`click.pfImprimir`, `click.pfMenu`,
  `hidden.bs.offcanvas.pfMenu`, `resize.pfMenu`, `submit.pfFormulario`, `blur.pfFormulario`,
  `change.pfFormulario`) para poder reengancharlos sin duplicarlos.
- Cuando un cambio se hace por código (marcar todas las franjas, quitar filas, limpiar el
  formulario) se dispara el evento a mano o se llama directamente a `P.Grafico.actualizar()`,
  porque esos cambios no avisan solos.

**Orden de carga y arranque.** El layout carga primero las librerías de CDN y luego
`reloj.js`, `menu.js` y `app.js`; `@stack('scripts')` va **después**, así que al dispararse
`DOMContentLoaded` los callbacks registrados se ejecutan en este orden: `P.arrancar()` de
`app.js` (que inicia Reloj, Menú, Impresión y Gráfico) y luego el auto-arranque de
`script.js` (`P.Formulario.iniciar()`), que solo actúa si existe `#formulario-diario`.

**API declarada sin uso hoy.** Algunos ayudantes están disponibles pero ninguna vista los
llama: `P.config.segundosParaCerrar`, `P.Util.numero`, `P.Util.minutos`, `P.Util.limpiar`,
los atajos `P.Ajax.get/post/put/eliminar` (el listado usa `P.Ajax.peticion` directamente),
el auto-arranque de `table[data-tabla="1"]`, `P.Reloj.fechaCorta`, `P.Reloj.fechaNumerica`,
`P.Grafico.colorDeTexto` y `P.Menu.alternar`. No son un error: son ayudantes previstos para
reutilizar; conviene no documentarlos como funcionalidad en uso.

**Trampa documentada en el código (bootbox 6).** `P.Alerta.mostrar()` coloca el callback a
nivel del diálogo y **no** dentro de `buttons.ok`, porque bootbox 6 no llama al callback
declarado en `buttons.ok`; si se mueve, el aviso se cierra sin ejecutar lo que venía después
(por ejemplo, la redirección tras guardar). Está comentado en `public/js/app.js`.

---

### 12.13 Plantilla base y experiencia de uso

**Propósito.** `resources/views/layouts/app.blade.php` reúne lo común a todas las pantallas y
define el look institucional.

**Contenido.**

1. **Metadatos:** `lang="es"`, viewport, `csrf-token`, color de tema `#002060`, descripción.
2. **Hojas de estilo:** Bootstrap 5.3.8, Bootstrap Icons 1.13.1, DataTables 2.3.8 (tema
   Bootstrap 5) y `css/styles.css` (los estilos propios). Las vistas pueden agregar con
   `@push('estilos')` (por ejemplo, el *datepicker*).
3. **Cabecera** (`tc-cabecera`): botón hamburguesa, marca "TRANSFORMA Conecta", los tres
   títulos en el mismo orden de la hoja impresa (programa en h3, "MI PLANIFICADOR DIARIO" en
   h1, "SISTEMA DE PLANIFICACIÓN PERSONAL" en h2) y el reloj con fecha y hora.
4. **Banda:** "ORGÁNIZATE · ACTÚA · AVANZA".
5. **Menú lateral** (Offcanvas): Inicio, Crear diario y Consultar diario, con icono, título y
   descripción; cerrar al elegir, marcar la opción activa y devolver el foco al botón.
6. **Contenido:** `@yield('contenido')` dentro de `tc-principal` y `tc-contenedor`.
7. **Pie:** marca, lema, programa y año actual.
8. **Scripts** (el orden importa): jQuery 3.7.1, Bootstrap bundle, bootbox 6.0.4, DataTables y
   su integración con Bootstrap 5; luego `reloj.js`, `menu.js` y `app.js`; al final
   `@stack('scripts')` (donde el formulario agrega el *datepicker*, `script.js` y
   `grafico.js`, y el listado su propio bloque).

> **Nota:** la plantilla avisa en un comentario que las librerías se cargan por CDN **a
> propósito**: este proyecto no usa NPM, ni Tailwind, ni Vue, ni React, ni Angular. Para
> sustituir el texto de la marca por el logotipo real existe el punto exacto donde reemplazar
> el bloque por `<img src="{{ asset('img/logo.png') }}" class="tc-marca__logo">`.

---

### 12.14 Arranque y publicación en Apache

**Propósito.** Servir Laravel desde la **raíz** del proyecto
(`http://localhost:8088/planificadordiario-transformaconecta/`) en lugar de desde `public/`,
sin romper el acceso tradicional con `/public/`.

**Archivos.**

| Archivo | Rol |
| --- | --- |
| `index.php` (raíz) | Reenvía a `public/index.php` |
| `.htaccess` (raíz) | Publica los estáticos de `public/`, manda las rutas al front controller y bloquea lo interno |
| `public/index.php` | Arranque real de Laravel (autoload, bootstrap y manejo de la petición) |
| `public/.htaccess` | Reglas estándar de Laravel para la carpeta `public/` |
| `bootstrap/app.php` | Rutas, middleware y manejo de excepciones de Laravel 12 |

**Paso a paso de una petición en la raíz.**

1. Apache recibe, por ejemplo, `GET /planificadordiario-transformaconecta/diario/listado`.
2. El `.htaccess` de la raíz comprueba que no sea un archivo ni un directorio existente y, como
   no termina en una extensión estática, reescribe la petición al **front controller de la
   raíz** (`index.php`).
3. `index.php` de la raíz hace `require __DIR__.'/public/index.php'`, y desde ahí Laravel
   arranca normalmente (autoload, bootstrap de la aplicación y `handle` de la petición).
4. Si la dirección termina en una extensión estática (`css`, `js`, `png`, `woff2`, `pdf`…), el
   `.htaccess` reescribe hacia `public/$0`, con lo que `asset('css/styles.css')` funciona sin
   `/public/` de por medio.
5. Las rutas **no** se reescriben hacia `public/`: si se hiciera, Laravel recibiría `/public/…`
   como dirección y no encontraría ninguna ruta (404).

**Bloqueos que aplica el `.htaccess` de la raíz.**

| Bloque | Qué hace |
| --- | --- |
| 1. Carpetas internas | `app`, `bootstrap`, `config`, `database`, `node_modules`, `resources`, `routes`, `storage`, `tests`, `vendor` responden **403** |
| 1b. Archivos ocultos | Cualquier archivo o carpeta que empiece con punto (`\.env`, `.git`, `.editorconfig`…) responde **403**; se exceptúa `/.well-known/` para los certificados SSL |
| 2. Estáticos | Reescribe a `public/` solo las peticiones que terminan en extensión de archivo estático (lista en el propio archivo) |
| 3. Rutas | Todo lo demás va al front controller de la raíz |
| 4. Listados | `Options -Indexes`: no se navega el contenido de las carpetas |
| 5. Archivos sueltos | Deniega por HTTP `.env*`, `composer.json`, `composer.lock`, `artisan`, `package.json`, `package-lock.json`, `phpunit.xml`, `vite.config.js` y las extensiones `log`, `sqlite`, `sql`, `bak`, `old`, `ini`, `dist`, `sh`, `yaml`, `yml`. Es un `<FilesMatch>`, así que aplica también a los subdirectorios; **no** cubre `pdf`, `xlsx` ni `png` (ver [§21.3](#213-hallazgos-de-seguridad-a-resolver)) |
| — | El `.htaccess` **no define cabeceras HTTP** (ni de seguridad ni de caché) |

**Consecuencias prácticas.**

- Las reglas son **relativas a la carpeta del proyecto**: si se renombra la carpeta o se sube
  a un hosting dentro de otra carpeta, no hay que cambiar nada.
- Las direcciones antiguas con `/public/` siguen funcionando (por si hay enlaces guardados).
- `APP_URL` en `.env` debe apuntar a la dirección de la raíz: la usan los comandos de
  `artisan`.
- Si se agregan archivos estáticos nuevos en `public/` con una extensión que no esté en la
  lista del `.htaccess` (por ejemplo `.docx`), hay que añadirla ahí.

---

### 12.15 Página de errores

**Propósito.** Mostrar en pantalla, con la identidad del sistema, qué pasó cuando algo falla:
la ruta `/error/{codigo}` explica cualquier código HTTP agrupado por familia, y el **mismo**
diseño es el que aparece cuando de verdad ocurre un error (una página que no existe, un acceso
no permitido, la sesión vencida o una falla del servidor).

**Archivos.**

| Archivo | Rol |
| --- | --- |
| `app/Http/Controllers/ErrorController.php` | `index(int $codigo)`: arma la respuesta con el código real |
| `app/Errores/CatalogoDeErrores.php` | El texto de cada familia y de cada código (título, mensaje, salidas, icono y color) |
| `resources/views/errores/index.blade.php` | La vista, sobre la plantilla institucional |
| `routes/web.php` | `GET /error/{codigo}` (nombre `error`), con el parámetro restringido a números |
| `bootstrap/app.php` | Manejador de excepciones que dibuja los errores reales con esta misma vista |
| `public/css/styles.css` | Bloque 8: estilos `.tf-error*` (azul 3xx, naranja 4xx, rojo 5xx) |

**Familias y códigos.**

| Familia | Qué significa | Códigos con texto propio |
| --- | --- | --- |
| **300** Redirección (azul) | El servidor te lleva a otra dirección; no es una falla | 300, 301, 302, 303, 304, 307, 308 |
| **400** Error del cliente (naranja) | Lo pedido no es válido, no está permitido o ya no existe | 400, 401, 402, 403, 404, 405, 406, 408, 409, 410, 413, 414, 415, 419, 422, 429, 451 |
| **500** Error del servidor (rojo) | La solicitud llegó bien, pero el sistema no pudo completarla | 500, 501, 502, 503, 504, 505, 507 |

Los códigos que no están en la lista **no quedan sin texto**: heredan el mensaje y las salidas
de su familia (así 418 o 507 ya salen explicados). Un valor fuera de 300-599 se muestra como
**500**, avisando en pantalla que se ajustó.

**Paso a paso.**

1. El navegador pide `/error/404` (o cualquier otro código). La ruta solo acepta dígitos
   (`whereNumber`), así que `/error/abc` cae en un 404 normal del sistema.
2. `ErrorController@index` le pide los datos a `CatalogoDeErrores::datos($codigo)`, que
   normaliza el código, calcula su familia (`intdiv($codigo, 100) * 100`) y completa lo que
   falte con el texto de esa familia.
3. La respuesta se devuelve **con el mismo estado HTTP que explica** (`response()->view(...,
   $error['codigo'])`): `/error/403` contesta 403, `/error/500` contesta 500. Así el navegador
   y cualquier monitorización ven el código real y no un 200 disfrazado. Se agrega
   `Cache-Control: no-store` para que el aviso no quede guardado.
4. La vista pinta la tarjeta: chip con la familia y su icono, el número grande, el título, la
   explicación, la nota de "se ajustó" cuando corresponde, la lista de **qué puedes hacer** y
   cuatro salidas: *Volver al inicio*, *Consultar mis diarios*, *Crear un diario* y *Reintentar*
   (recarga la misma dirección).

**Captura de los errores reales (lo que hace que la página sirva).** En `bootstrap/app.php` se
registraron dos manejadores, después del de validación:

| Manejador | Cuándo actúa | Qué responde |
| --- | --- | --- |
| `HttpExceptionInterface` | 403, 404, 405, 419, 429 y demás errores HTTP que se navegan | La página del sistema con el código real. Si la petición espera JSON, no interviene (devuelve `null`) |
| `Throwable` (cualquier otra excepción) | Errores 500 | La página del sistema **solo con `APP_DEBUG=false`**; con la depuración encendida se deja la pantalla de Laravel, que dice qué pasó y dónde. `ValidationException` queda excluida para no romper el volver-al-formulario-con-errores |

**Decisiones y detalles.**

- **Un solo diseño de error.** La ruta y el manejador usan el mismo controlador y la misma
  vista: no hay dos pantallas de error que mantener.
- **El AJAX no se toca.** Las peticiones que esperan JSON (`Accept: application/json`, las que
  hace DataTables, el guardado del formulario, el reporte y el resumen) siguen recibiendo JSON,
  porque los manejadores salen antes de responder. Verificado: un 404 pedido como JSON devuelve
  `application/json` y el listado sigue contestando `{"ok":true,…}`.
- **La validación tiene su propio camino.** `ValidationException` no entra en el manejador
  genérico: en las peticiones normales Laravel vuelve al formulario con los errores, y en las
  AJAX responde el sobre `{ ok:false, message, errors }` (422).
- **Con depuración encendida** (`APP_DEBUG=true`, el valor local) los errores HTTP igual usan la
  página institucional, pero un 500 inesperado conserva la pantalla de depuración para no
  perder el detalle mientras se desarrolla.
- **Los 3xx no son errores.** Una redirección real nunca llega al manejador de excepciones: el
  navegador la sigue y listo. La familia 300 existe en `/error/{codigo}` para poder mostrar la
  explicación cuando se pide a propósito.

---

### 12.16 Bitácora de errores y logs diarios

**Propósito.** Que ningún fallo se pierda y que, cuando haya que investigarlo, esté todo dicho:
la clase `RegistroDeErrores` anota el error con el máximo detalle en un log propio que **rota
por día**, y devuelve un **código de incidente** que se muestra en la página de error para poder
cruzar lo que vio la persona con lo que quedó registrado.

**Archivos.**

| Archivo | Rol |
| --- | --- |
| `app/Errores/RegistroDeErrores.php` | Arma y escribe la entrada detallada; genera el código de incidente |
| `config/logging.php` | Canal `errores` (driver `daily`) y los días que se conserva |
| `.env` / `.env.example` | `LOG_STACK=daily`, `LOG_DAILY_DAYS=30` y `LOG_ERRORES_DAYS=60` |
| `bootstrap/app.php` | Registra los errores que atiende el manejador de excepciones |
| `app/Http/Controllers/DailyPlanController.php` | Registra lo que atrapan sus `try/catch` |
| `app/Models/DailyPlan.php` | Registra lo que falla dentro de una transacción |

**Archivos de log (uno por día).**

| Archivo | Qué guarda | Rotación |
| --- | --- | --- |
| `storage/logs/laravel-AAAA-MM-DD.log` | El log general de Laravel (canal `daily` desde la V 1.02) | Diaria; 30 días (`LOG_DAILY_DAYS`) |
| `storage/logs/errores-AAAA-MM-DD.log` | El detalle del manejo de errores (canal `errores`) | Diaria; 60 días (`LOG_ERRORES_DAYS`) |
| `storage/logs/laravel.log` | El archivo del esquema anterior (`single`); queda como histórico y no recibe entradas nuevas | — |

**Qué se guarda de cada error.**

```
[2026-10-06 11:53:08] local.WARNING: [QY102A4U] Error 404 (Página no encontrada) · manejador · NotFoundHttpException
{"incidente":"QY102A4U","origen":"manejador","codigo":404,"familia":400,"familia_titulo":"Error del cliente",
 "titulo":"Página no encontrada","excepcion":"…NotFoundHttpException","mensaje":"The route … could not be found.",
 "archivo":"…/AbstractRouteCollection.php:44","metodo":"GET","url":"http://…/ruta?pagina=3",
 "ruta":null,"accion":null,"ip":"::1","navegador":"Mozilla/5.0 …","referencia":null,
 "espera_json":false,"traza":["#0 …","#1 …", …12 líneas]}
```

| Dato | Detalle |
| --- | --- |
| Código de incidente | 8 caracteres, único por error; **encabeza la entrada y se muestra en la página** |
| Origen | `manejador` (excepción), `controlador` (`try/catch`) o `modelo` (transacción) |
| Error | Código, familia, título, clase de la excepción, mensaje, archivo y línea |
| Petición | Método, URL completa, nombre y acción de la ruta, IP, navegador, referencia y si esperaba JSON |
| Traza | Las **12 primeras líneas**, recortadas, para no inflar el archivo |
| Contexto | Lo que aporta quien detecta el error: operación, id del diario, filtros, fecha… |
| Nivel | `error` para 5xx, `warning` para 4xx |

**Paso a paso.**

1. Algo falla: una excepción no controlada, un `catch` del controlador o una transacción del
   modelo.
2. `RegistroDeErrores::registrar()` genera el incidente, arma el contexto (recortando los textos
   largos y la traza) y escribe con `Log::channel('errores')` en el archivo del día.
3. El manejador de `bootstrap/app.php` recibe ese incidente y lo pasa a la página de error, que
   lo muestra ("Código de incidente XXXXXXXX · el detalle quedó registrado en el log de errores").
4. Los `try/catch` de los controladores siguen llamando a `report()` —el log general, como
   siempre— y además registran el detalle con `registrarFallo($e, 'operación', $contexto)`; las
   transacciones del modelo usan `RegistroDeErrores::deModelo(...)` con el nombre de la operación.
5. Laravel rota los dos archivos por su cuenta: al empezar un día nuevo estrena
   `laravel-AAAA-MM-DD.log` y `errores-AAAA-MM-DD.log`, y borra los que superan los días
   configurados.

**Cuidados a propósito.**

- **El registro nunca puede tumbar la respuesta.** Si el log no se puede escribir (permisos,
  disco lleno), el fallo se anota con `error_log()` y la respuesta sigue su curso: no hay
  recursión ni error en cascada.
- **No se guarda el cuerpo de la petición.** El contenido del formulario son los datos
  personales del diario; se registran la dirección, la ruta, la IP y el contexto explícito, no
  los campos enviados.
- **Se recorta todo.** Mensajes (500 caracteres), textos del contexto (300), navegador (200) y
  traza (12 líneas): el log sirve para diagnosticar, no para llenar el disco.
- **Niveles separados.** Los 4xx quedan como `warning` y los 5xx como `error`, así se pueden
  filtrar sin ruido.

---

## 13. Rutas y endpoints

Todas las rutas viven en `routes/web.php` y pasan por el middleware `web` (sesión y CSRF).
Las que responde AJAX devuelven JSON con el sobre `{ ok, message, data }` o
`{ ok, message, errors }`; las que se navegan devuelven una vista.

| Método | URI | Nombre | Acción | Respuesta |
| --- | --- | --- | --- | --- |
| GET | `/` | `home` | `HomeController@index` | Vista `home.index` (portada) |
| GET | `/error/{codigo}` | `error` | `ErrorController@index` | Vista `errores.index`, con **el mismo estado HTTP** que explica (404 contesta 404) |
| GET | `/diario` | `diario.index` | `DailyController@index` | Vista `diario.formulario` (crear) |
| GET | `/diario/today` | `diario.today` | `DailyController@today` | Formulario con la fecha de hoy bloqueada |
| GET | `/diario/listado` | `diario.listado` | `DailyPlanController@index` | Vista `diario.index` (tabla y filtros) |
| GET | `/diario/tabla` | `diario.tabla` | `DailyPlanController@list` | **JSON** con los días filtrados |
| GET | `/diario/reporte` | `diario.reporte` | `DailyPlanController@reporte` | **XLSX** (o JSON 404 si no hay días) |
| GET | `/diario/resumen` | `diario.resumen` | `DailyPlanController@resumen` | **PDF** en línea (o JSON 404) |
| POST | `/diario` | `diario.store` | `DailyPlanController@store` | **JSON 201** con el día creado |
| GET | `/diario/{dailyPlan}` | `diario.show` | `DailyPlanController@show` | Vista del día en solo lectura |
| GET | `/diario/{dailyPlan}/detalle` | `diario.detail` | `DailyPlanController@detail` | **JSON** con el día completo |
| GET | `/diario/{dailyPlan}/editar` | `diario.edit` | `DailyPlanController@edit` | Formulario en modo edición |
| PUT/PATCH | `/diario/{dailyPlan}` | `diario.update` | `DailyPlanController@update` | **JSON** con el día actualizado |
| GET | `/diario/{dailyPlan}/imprimir` | `diario.print` | `DailyPlanController@printPdf` | **PDF** del día (`?marca=1` para SPECIMEN) |
| DELETE | `/diario/{dailyPlan}` | `diario.destroy` | `DailyPlanController@destroy` | **JSON** con el resultado |
| GET | `/up` | — | Laravel (health check) | 200 si la aplicación responde |

**Orden de las rutas.** Las rutas con identificador van **al final** del grupo y el parámetro
está restringido a números (`Route::whereNumber('dailyPlan')`): así `/today`, `/listado`,
`/tabla`, `/reporte` y `/resumen` no se confunden con un id, y cualquier otro valor cae en un
404. La ruta `/error/{codigo}` también restringe el parámetro a números: `/error/abc` no
coincide y termina en el 404 normal del sistema.

**Parámetros de los filtros** (los aceptan `diario.tabla`, `diario.reporte` y `diario.resumen`):

| Parámetro | Valores | Uso |
| --- | --- | --- |
| `fecha` | `dd/mm/aaaa` | Día exacto |
| `energia` | `baja`, `media`, `alta` | Nivel de energía |
| `palabra` | texto | Búsqueda libre (basta una letra) |
| `periodo` | `semana`, `quincena`, `mes`, `trimestre`, `semestre`, `anio`, `rango` | Tipo de período |
| `semana` | `AAAA-Wnn` | Semana ISO |
| `mes` | `AAAA-MM` | Mes (o mes de la quincena) |
| `mitad` | `1`, `2` | Primera o segunda quincena |
| `anio` | 1900-2200 | Año (o año de trimestre/semestre) |
| `trimestre` | `1`-`4` | Trimestre |
| `semestre` | `1`, `2` | Semestre |
| `desde`, `hasta` | `dd/mm/aaaa` | Rango a mano |
| `marca` | `1` | Solo en `diario.print` y `diario.resumen`: marca de agua SPECIMEN |

**Códigos de respuesta usados.** 200 (éxito), 201 (día creado), 404 (no existe, o reporte sin
datos que coincidan), 422 (validación), 500 (error inesperado reportado al log).

**Rutas que agrega el framework.** `php artisan route:list` muestra 18 rutas: las 15 de la
aplicación, la de *health check* (`GET /up`, definida con `health: '/up'` en
`bootstrap/app.php`) y dos que publica Laravel para el disco `local` cuando
`filesystems.disks.local.serve` es `true` (`GET storage/{path}` y `PUT storage/{path}`). Las de
`storage/` **no las usa el sistema** (no hay carga ni descarga de archivos) y sirven
`storage/app/private`; ver la advertencia en [§21](#21-seguridad). No existe `routes/api.php`
ni grupo de middleware `api`: todo pasa por la pila `web` (sesión y CSRF).

---

## 14. Flujos paso a paso

### 14.1 Flujo diario de la persona (recorrido completo)

```
Abrir /  ─►  ¿Ya existe el diario de hoy?
              │
              ├─ NO ─► «Crear el diario de hoy» → /diario/today (fecha bloqueada)
              │        1. Elegir energía
              │        2. Escribir los 3 objetivos
              │        3. Marcar el checklist «Antes de empezar»
              │        4. Cargar el horario (el gráfico se dibuja solo)
              │        5. Marcar reflexiones de procrastinación
              │        6. Registrar el bloque de acción
              │        7. Escribir el cierre del día y las notas
              │        8. Guardar (confirmación → AJAX → alerta → inicio)
              │
              └─ SÍ ─► «Abrir el diario de hoy» (/diario/{id})
                        · Revisar el avance y el gráfico
                        · «Modificar» si hay que ajustar algo
                        · «Imprimir» para el PDF del día
```

### 14.2 Guardar un día nuevo (detalle técnico)

1. `GET /diario` → `DailyController@index` → `datosFormulario(plan: null, modo: 'crear')` →
   catálogos activos + JSON del gráfico vacío.
2. El usuario completa el formulario. Cada cambio del horario redibuja el gráfico
   (`P.Grafico.actualizar`).
3. `submit` → `P.Formulario.validar()`: campos `data-tf-requerido` presentes, al menos una
   franja con hora y actividad, y los cuatro campos del cierre.
4. Confirmación con bootbox → `P.Formulario.guardar()` → `$.ajax` POST a `diario.store` con
   `_token`.
5. `DailyPlanController@store` → `validateDay()`, que valida y devuelve los datos validados.
   - Falla → 422 con `{ ok:false, message, errors }` → `mostrarErrores()` pinta cada error
     junto a su campo.
6. `DailyPlan::createDay()` (transacción):
   - `create()` de la cabecera;
   - `refreshDayStructure()`: franjas del catálogo, checklist y respuestas vacías;
   - `applyDayData()`: objetivos, horario, preparación, reflexiones, notas y bloques.
7. Respuesta 201 → alerta de éxito → redirección al inicio.

### 14.3 Modificar un día

1. Listado o portada → *Modificar* → `GET /diario/{id}/editar` → `loadFull()` → formulario con
   los datos cargados y `_method=PUT`.
2. Al guardar, el navegador manda `POST` con `_method=PUT` (o `PUT` directo). El controlador
   comprueba primero la existencia (404) y después valida (422).
3. `updateDay()` actualiza la cabecera y vuelve a aplicar los datos; los hijos se sincronizan
   con las reglas por sección.
4. Respuesta 200 → alerta → redirección al listado.

### 14.4 Consultar y filtrar

1. `GET /diario/listado` → vista con el miniformulario y la tabla vacía.
2. DataTables pide `GET /diario/tabla` (con los filtros activos si los hay) → `allForList()`.
3. Al cambiar un filtro, `aplicar()` compara con la búsqueda anterior, actualiza la tabla y
   guarda el estado en `filtros` (que expone como `P.filtrosDelListado`).
4. Ordenar, buscar en DataTables o cambiar de página no vuelven a consultar los filtros: los
   filtros viajan en todas las peticiones de la tabla.

### 14.5 Imprimir el PDF del día

1. *Imprimir* (en el listado o en la vista del día) → `P.Impresion.abrir(id)`.
2. `GET /diario/{id}/detalle` verifica la existencia. Si no existe → alerta; si existe →
   modal.
3. Casilla SPECIMEN y *Generar PDF* → nueva pestaña a `/diario/{id}/imprimir[?marca=1]`.
4. `printPdf` → `loadFull()` → gráfico PNG con GD → HTML `diario.pdf` → dompdf (carta) →
   marca de agua si corresponde → PDF `inline` sin caché.

### 14.6 Exportar el reporte detallado (Excel)

1. *Generar reporte detallado* → `P.Reporte.descargar('/diario/reporte', filtros, $boton)`.
2. `forReport($filtros)` con las relaciones precargadas.
3. Sin días → JSON 404 → alerta. Con días → `ReporteDiarioExport::generar()` escribe en
   memoria → respuesta `attachment` con el nombre según filtros y fecha.
4. El navegador detecta el `Content-Disposition`, lee el nombre y guarda el archivo.

### 14.7 Generar el resumen de desempeño

1. *Generar resumen* → modal → casilla de muestra → nueva pestaña con los filtros y `marca=1`
   si corresponde.
2. `forReport($filtros)` → `AnalisisDeDesempeno::de()` → `RedaccionDelResumen::observaciones()`
   → tres gráficos PNG con GD (+ línea de tiempo si es un solo día) → HTML
   `diario.resumen` → dompdf → marca de agua opcional → PDF `inline`.

### 14.8 Eliminar un diario

1. Botón de eliminar en la fila → confirmación con el nombre del día.
2. `DELETE /diario/{id}` → `destroy()` → `deleteDay()` (transacción). La base borra en cascada
   objetivos, franjas, bloques, notas, respuestas y filas del pivote.
3. Un único aviso de éxito; al aceptarlo se recarga la página.

---

## 15. Reglas de negocio y validaciones

### 15.1 Reglas de validación del servidor

Definidas en `DailyPlanController::rules()` y aplicadas por `validateDay()` (parámetro
`$ignorarId` para que, al modificar, la propia fecha no cuente como duplicada).

| Campo | Reglas |
| --- | --- |
| `plan_date` | requerido, fecha, **único** en `daily_plans.plan_date` |
| `energy_level_id` | requerido, entero, existe en `energy_levels` |
| `goals` | requerido, arreglo, **exactamente 3** (`size:3`) |
| `goals.*.slot` | requerido, entero, entre 1 y 3, sin repetir |
| `goals.*.goal_type_id` | opcional, entero, existe en `goal_types` |
| `goals.*.description` | requerido, texto, máximo 255 |
| `goals.*.is_done` | opcional, booleano |
| `schedule` | requerido, arreglo, **mínimo 1** |
| `schedule.*.id` | opcional, entero, existe en `schedule_entries` |
| `schedule.*.schedule_slot_id` | opcional, entero, existe en `schedule_slots` |
| `schedule.*.start_time` | requerido, formato `H:i` |
| `schedule.*.activity` | requerido, texto, máximo 255 |
| `schedule.*.is_done` | opcional, booleano |
| `achievements`, `pending`, `proud_of` | requeridos, texto, máximo 2000 |
| `pending_when` | requerido, texto, máximo 255 |
| `preparation` | opcional, arreglo |
| `preparation.*.preparation_item_id` | requerido si hay arreglo, entero, existe, sin repetir |
| `preparation.*.is_checked` | opcional, booleano |
| `preparation.*.preparation_items_description` | opcional, texto, máximo 2000 |
| `reflections` | opcional, arreglo |
| `reflections.*.reflection_question_id` | requerido si hay arreglo, entero, existe, sin repetir |
| `reflections.*.is_checked` | opcional, booleano |
| `reflections.*.answer` | opcional, texto, máximo 2000 |
| `action_blocks` | opcional, arreglo |
| `action_blocks.*.id` | opcional, entero, existe en `action_blocks` |
| `action_blocks.*.action_block_duration_id` | opcional, entero, existe en el catálogo |
| `action_blocks.*.action_block_outcome_id` | opcional, entero, existe en el catálogo |
| `action_blocks.*.task` | opcional, texto, máximo 255 |
| `action_blocks.*.started_at` | opcional, fecha |
| `action_blocks.*.finished_at` | opcional, fecha, **posterior o igual** a `started_at` |
| `notes` | opcional, arreglo |
| `notes.*.content` | opcional, texto, máximo 1000 |
| `notes.*.sort_order` | opcional, entero, mínimo 0 |

Los mensajes están escritos en español y son específicos por campo (por ejemplo: "Ya existe un
diario con esa fecha.", "Debes registrar exactamente 3 objetivos principales.", "Agrega al
menos una franja en tu horario de hoy.", "El bloque no puede terminar antes de empezar.").

### 15.2 Validación en el navegador

`P.Formulario.validar()` revisa lo mismo antes de enviar, guiándose por `data-tf-requerido`:

| Marca | Campos |
| --- | --- |
| `energia` | Un nivel de energía elegido |
| `objetivo` | Los 3 textos de objetivos |
| `hora` | La hora de cada franja del horario |
| `actividad` | La actividad de cada franja del horario |
| `cierre` | Los 4 campos del cierre del día |

Además exige al menos una franja de horario. Los errores se muestran bajo el formulario
(`#formulario-mensajes`), el campo se marca en rojo y el foco va al primero con problema. El
formulario nace con `novalidate` para controlar los mensajes con el estilo propio.

### 15.3 Invariantes del modelo de datos

- **Un diario por fecha** (índice único en `plan_date`).
- **Una ranura de objetivo por día** (único `daily_plan_id` + `slot`).
- **Una franja por slot del catálogo y día** (único `daily_plan_id` + `schedule_slot_id`;
  varias franjas con `schedule_slot_id` nulo son válidas porque MySQL admite varios NULL en un
  índice único).
- **Una respuesta por pregunta y día**, y **una fila de preparación por ítem y día** (únicos
  con nombre propio).
- **Borrado en cascada**: eliminar un `daily_plan` limpia todos sus hijos.
- **Los catálogos no se borran**: se desactivan (`is_active = false`), así el histórico
  conserva sus referencias.

### 15.4 Reglas de presentación y cálculo

- El "día de la semana" se **deriva** de la fecha (`Helper::dayLetter`), nunca se guarda.
- Los minutos de foco del día son la **suma de las duraciones** de los bloques de acción.
- Un día se considera **cerrado** si tiene algo escrito en logros, pendientes u orgullo.
- En el resumen, un componente sin datos (por ejemplo preparativos en un período sin
  checklist) **no resta**: su peso se reparte entre los componentes que sí tienen datos.
- Las comparaciones del resumen son **descriptivas del propio período** (primera mitad contra
  segunda mitad), no proyecciones.

---

## 16. Motor de análisis del desempeño

`App\Reportes\AnalisisDeDesempeno` calcula todo lo que muestra el resumen. Solo usa lo que la
persona marcó; no infiere nada.

### 16.1 Rendimiento del día (0 a 100)

| Componente | Peso | Se mide con |
| --- | --- | --- |
| Horario | 60 | Franjas cumplidas sobre franjas totales |
| Objetivos | 30 | Objetivos cumplidos sobre objetivos totales |
| Preparativos | 10 | Ítems marcados sobre ítems del checklist |

Fórmula: para cada componente **con datos** se acumula `peso × (logrado / total)`; al final se
divide por el peso realmente usado y se multiplica por 100. Si ningún componente tiene datos,
el rendimiento es 0. Si falta un componente (por ejemplo no hay preparativos), **su peso se
reparte** entre los demás: así un diario sin checklist no queda castigado por algo que no
cargó.

### 16.2 Totales del período (`global`)

Rendimiento promedio, mejor y peor; totales y porcentajes de horario, objetivos y
preparativos; total de señales de procrastinación y promedio por día; y cuántos días tienen el
cierre escrito.

### 16.3 Cruces

| Cruce | Cómo se agrupa |
| --- | --- |
| `porEnergia` | Por nivel de energía: días, rendimiento promedio, % del horario y señales promedio. Ordenado de mayor a menor rendimiento |
| `porDiaSemana` | Por día de la semana: días y rendimiento promedio. Ordenado de mayor a menor |
| `actividades` | Por nombre de actividad **normalizado** (minúsculas y espacios colapsados): total, hechas y porcentaje. Ordenado por porcentaje y luego por cantidad. Es lo que revela "lo que siempre se hace" y "lo que siempre se posterga" |
| `franjas` | Por franja del día: Madrugada (0-5), Mañana (6-11), Tarde (12-17), Noche (18-23), según la hora de inicio de cada actividad |
| `objetivos` | Por tipo de objetivo: total, cumplidos, porcentaje y hasta 6 descripciones pendientes por tipo |
| `procrastinacion` | Por pregunta: respuestas y señales; además días con señales y días sin señales |

### 16.4 Punto crítico de procrastinación

1. Los días se agrupan por **cantidad de señales** marcadas y se calcula el rendimiento
   promedio de cada grupo (ordenado de menos a más señales).
2. `referencia` = rendimiento promedio del período completo.
3. `limite` = referencia **− 8 puntos** (`CAIDA_CRITICA`).
4. Recorriendo los grupos:
   - **A favor** (`aFavor`): el último grupo (con más señales) cuyo rendimiento **no baja** del
     límite, siempre que no se haya detectado aún un punto en contra.
   - **En contra** (`enContra`): el primer grupo cuyo rendimiento **sí baja** del límite.
5. `caida` = diferencia de rendimiento entre el punto a favor y el punto en contra.
6. Si no hay suficientes grupos, el resumen lo dice expresamente en lugar de forzar una
   conclusión.

### 16.5 Avisos de contexto (`avisos`)

Se generan sin calificar el análisis: no hay señales registradas (no se puede medir su
impacto), los diarios del período no tienen franjas de horario, o hay N diarios sin el cierre
escrito. La vista del resumen filtra además cualquier aviso sobre tamaño de muestra, porque el
documento muestra los cruces con los días que haya y los explica en su lugar.

### 16.6 Redacción (`RedaccionDelResumen`)

| Sección | Contenido |
| --- | --- |
| Desempeño del período | Cuántos días abarca y el rango, rendimiento promedio, mejor y peor, y los cumplimientos |
| Aspectos que se sostuvieron | Actividades al 100 %, la franja con mayor cumplimiento (si supera el 80 % y hay más de una) y el mejor día de la semana |
| Aspectos a mejorar | Las actividades con menor cumplimiento, la franja más floja y hasta tres objetivos pendientes |
| Metas y objetivos alcanzados | Una línea por tipo: cumplidos sobre total y porcentaje |
| Relación con la energía | Comparación entre el nivel con mejor y peor rendimiento (o aviso si todos los días tienen el mismo nivel) |
| Procrastinación y rendimiento | Total de señales, promedio por día, días con señales, preguntas más marcadas y el punto crítico |
| Evolución en el período | Primera mitad contra segunda mitad del período, con la diferencia en puntos y la aclaración de que es una comparación, no una proyección |

Cuando una sección no tiene material (por ejemplo, ninguna actividad se cumplió siempre), en
lugar de quedar vacía escribe una frase que explica por qué: ese es el tono del sistema.

---

## 17. Identidad visual y experiencia de uso

### 17.1 Paleta institucional

Definida como variables CSS en `public/css/styles.css` (sección 1) y repetida en los
documentos PDF y en el Excel.

| Variable | Hex | Uso |
| --- | --- | --- |
| `--tc-naranja` | `#f0600c` | Objetivos, procrastinación, botones de acción, borde superior de la cabecera y del pie, columna "Hora" del horario |
| `--tc-naranja-claro` | `#fc9000` | Declarada para la paleta de gráficos; **hoy sin uso en el CSS** (el gráfico usa su propio arreglo de colores) |
| `--tc-naranja-oscuro` | `#c74a05` | Hover de botones naranjas, hora del reloj |
| `--tc-naranja-tenue` | `#fef0e6` | Fondos suaves: reloj, día actual del calendario, etiqueta "Obligatorio" |
| `--tc-azul-oscuro` | `#002060` | Títulos, encabezados de sección, color de tema del navegador |
| `--tc-azul-profundo` | `#004090` | Etiquetas y acentos |
| `--tc-azul` | `#0080d0` | Horario, enlaces, botones informativos |
| `--tc-celeste` | `#009ce4` | Acento de la paleta |
| `--tc-celeste-tenue` | `#e8f4fd` | Fondos suaves |
| `--tc-turquesa` | `#00ae9c` | Preparación, bloque de acción, banda institucional |
| `--tc-turquesa-oscuro` | `#006c60` | Bloque de acción en el Excel, textos turquesa |
| `--tc-turquesa-tenue` | `#e6f7f5` | Fondos suaves |
| `--tc-rojo` | `#d90b0b` | Cierre del día, marca de muestra, eliminación, errores |
| `--tc-arena` | `#fce4a8` | Declarada como acento cálido; **hoy sin uso** |
| `--tc-blanco` | `#ffffff` | Fondos y texto sobre color |
| `--tc-gris-100/200/300` | `#f4f7fb` / `#e4eaf2` / `#cfd9e6` | Fondos, bordes y líneas de los gráficos |
| `--tc-gris-500` | `#6b7a90` | Textos secundarios |
| `--tc-texto` | `#10233f` | Texto principal |

Además: `--tc-sombra` / `--tc-sombra-alta` (sombras con tinte azul), `--tc-radio` (1 rem),
`--tc-radio-sm` (0.6 rem) y `--tc-transicion` (180 ms). El ancho del menú lateral se controla
con `--bs-offcanvas-width` (`min(86vw, 320px)`, 420 px desde 1920 px de ancho) y, dentro del
formulario, un segundo `:root` define el **semáforo de energía**:
`--tf-baja: #d90b0b`, `--tf-media: #f2b705`, `--tf-alta: #1e9e5a`.

**Código de color de las secciones** (idéntico en pantalla, PDF y Excel): naranja = objetivos y
procrastinación; turquesa = preparación y bloque de acción; azul = horario; rojo = cierre del
día; azul oscuro = cabecera y notas.

### 17.2 Organización del CSS

`public/css/styles.css` está dividido en secciones numeradas:

1. Paleta institucional
2. Base (tipografía, contenedores, foco, breakpoints 576 / 768 / 992 / 1200 / 1400 / 1920 y
   `prefers-reduced-motion`)
3. Cabecera
4. Menú lateral
5. Contenido (tarjetas, botones, chips, resumen)
6. Ajustes a Bootstrap y DataTables (mismos colores institucionales)
7. Formulario del diario (secciones, horario, checklist, gráfico, mensajes)
8. Formatos de pantalla (ajustes para pantallas muy chicas y muy grandes)

**No hay reglas `@media print`**: la impresión se resuelve con los PDF generados en el
servidor, no con el diálogo de impresión del navegador.

### 17.3 Convenciones de nombres

- `tc-` para los componentes de la identidad (cabecera, tarjetas, botones, menú, tabla).
- `tf-` para los componentes funcionales del planificador (formulario, filtros, gráfico,
  mensajes).
- Los módulos de JavaScript cuelgan de `window.Planificador` con nombres en español.

### 17.4 Accesibilidad y detalles de experiencia

- Etiquetas `<label>` asociadas a sus campos, `aria-label` en los botones de icono,
  `aria-hidden` en los iconos decorativos, `role="alert"` y `aria-live="polite"` en el bloque
  de mensajes de validación.
- El menú lateral devuelve el foco al botón hamburguesa al cerrarse.
- El buscador del listado filtra mientras se escribe, con una espera de 350 ms para no pedir en
  cada tecla, y con Enter aplica al instante.
- Confirmación antes de eliminar y antes de registrar el diario.
- Diseño responsive: dos columnas en el formulario a partir de `lg`, tarjetas apiladas en
  pantallas chicas, tabla con anchos automáticos.
- Mensajes de error en español, concretos y sin tecnicismos.

---

## 18. Instalación y puesta en marcha

### 18.1 Requisitos previos

- **XAMPP** (o equivalente) con Apache y MySQL/MariaDB.
- **PHP 8.2 o superior** con las extensiones `gd`, `pdo_mysql`, `mbstring`, `openssl`,
  `fileinfo`, `zip`, `curl`, `dom` y `xml`.
- **Composer**.
- Apache escuchando en el puerto **8088** (`Listen 8088` en `httpd.conf`) y con
  `DocumentRoot "C:/xampp/htdocs"`.

### 18.2 Instalación paso a paso

1. **Copiar el proyecto** en `C:\xampp\htdocs\`:

   ```
   C:\xampp\htdocs\planificadordiario-transformaconecta\
   ```

2. **Instalar las dependencias de PHP:**

   ```
   composer install
   ```

3. **Crear el archivo de entorno** copiando `.env.example` a `.env` y completar:

   ```
   APP_URL=http://localhost:8088/planificadordiario-transformaconecta
   APP_TIMEZONE=America/Caracas
   APP_LOCALE=es
   DB_CONNECTION=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_DATABASE=planificador-diario
   DB_USERNAME=root
   DB_PASSWORD=
   SESSION_DRIVER=database
   CACHE_STORE=database
   QUEUE_CONNECTION=database
   ```

4. **Generar la clave de la aplicación:**

   ```
   php artisan key:generate
   ```

5. **Crear la base de datos** (por ejemplo desde phpMyAdmin o por consola):

   ```
   mysql -u root -e "CREATE DATABASE \`planificador-diario\` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
   ```

6. **Crear las tablas y sembrar los catálogos:**

   ```
   php artisan migrate --seed
   ```

   (Las 19 migraciones y los 7 catálogos. Los seeders son idempotentes: se pueden volver a
   correr.)

7. **Iniciar Apache y MySQL** desde el panel de control de XAMPP.

8. **Abrir el sistema:**

   ```
   http://localhost:8088/planificadordiario-transformaconecta/
   ```

### 18.3 Instalación en hosting

1. Copiar el proyecto completo (incluida la carpeta `public/`) dentro de la carpeta deseada
   del hosting: el `.htaccess` de la raíz es relativo y no hay que cambiar rutas.
2. Si el proveedor obliga a que la raíz web sea `public_html`, se puede mover el contenido de
   `public/` a la raíz y ajustar las rutas de `public/index.php`, o mantener la estructura con
   el `index.php` y el `.htaccess` de la raíz (que es la solución ya prevista).
3. Ajustar `.env`: `APP_ENV=production`, `APP_DEBUG=false`, `APP_URL` con la dirección real,
   credenciales de la base y del dominio.
4. Ejecutar `composer install --no-dev --optimize-autoloader` y
   `php artisan config:cache route:cache view:cache`.
5. Verificar permisos de escritura en `storage/` y `bootstrap/cache/`.
6. Si se agregan tipos de archivo estáticos nuevos en `public/`, añadir su extensión a la lista
   del `.htaccess` de la raíz.

### 18.4 Comprobación de que todo está bien

Valores verificados en el entorno de desarrollo de referencia (XAMPP, 06/10/2026):

| Comprobación | Resultado obtenido |
| --- | --- |
| `php artisan --version` | `Laravel Framework 12.69.3` |
| `php artisan migrate:status` | 19 migraciones, todas en estado `Ran` (contra MySQL `planificador-diario`) |
| `GET /` (portada) | **200**, con la fecha de hoy y los accesos |
| `GET /diario/listado` | **200**, con el buscador y la tabla |
| `GET /diario` | Formulario con los 3 objetivos, 6 ítems de preparación, 5 preguntas y los catálogos |
| `GET /up` | **200** (`Application up`) |
| `GET /css/styles.css` | **200**, mismo tamaño que `public/css/styles.css` (44.462 bytes) |
| `GET /error/404` | **404** con la página de errores del sistema ("Página no encontrada") |
| `GET /ruta-que-no-existe` | **404** con la misma página: la excepción real se dibuja con el módulo de errores |
| `GET /.env` | **403** (bloqueado por el `.htaccess` de la raíz) |
| `GET /vendor/` | **403** (bloqueado por el `.htaccess` de la raíz) |
| `php artisan route:list` | 18 rutas (15 de la aplicación + `/up` + 2 que publica el framework para el disco `local`) |
| `php artisan test` | 1 prueba `Unit` correcta y **1 prueba `Feature` fallando** (ver [§24](#24-pruebas)) |
| `git status --short` | Con los cambios de la V 1.02 sin confirmar (ver [§27.3](#273-versión-102-manejo-de-errores)) |
| `storage/logs/` tras provocar un error | Se crean `errores-AAAA-MM-DD.log` y `laravel-AAAA-MM-DD.log` (un archivo por día) |

---

## 19. Guía de uso paso a paso

### 19.1 Crear el diario de hoy

1. En la portada, pulsar **Crear el diario de hoy** (o en el menú lateral, **Crear diario**).
2. La fecha ya viene con el día de hoy y **bloqueada**; el día de la semana se marca solo.
3. Elegir **Mi energía hoy**: Baja, Media o Alta.
4. Escribir los **3 objetivos principales**: 1 Debo hacer, 2 Quiero hacer, 3 Algo para mí.
5. Marcar lo que ya está listo en **Antes de empezar**; al marcar un ítem se abre su campo para
   detallar qué incluye (por ejemplo "PC, cuaderno, calculadora").
6. Cargar **Mi horario de hoy**: con **Agregar franja** se suman filas con hora y actividad; la
   barra de abajo ("Mi día en el tiempo") se dibuja sola. Marcar la casilla de lo cumplido.
7. Si aparece la procrastinación, marcar las preguntas que correspondan y anotar la respuesta.
8. Registrar el **Bloque de acción**: cuánto vas a trabajar, cómo quieres terminarlo y en qué.
9. Escribir el **Cierre del día**: qué lograste, qué quedó pendiente, cuándo lo harás y por qué
   estás orgulloso/a de ti. Este bloque es obligatorio.
10. Agregar **Notas / recordatorios** si hace falta.
11. Pulsar **Guardar**, confirmar el aviso y listo: vuelves a la portada con el día registrado.

### 19.2 Consultar los días registrados

1. Menú lateral → **Consultar diario** (o la tarjeta *Consultar diario* en la portada).
2. La tabla lista los días del más antiguo al más reciente; se puede ordenar por cualquier
   columna, buscar con el buscador de DataTables y cambiar la cantidad de filas por página.
3. En cada fila: **Modificar**, **Ver**, **Generar PDF** y **Eliminar**.

### 19.3 Buscar y filtrar

- **Palabra clave**: escribir en "Palabra clave" filtra sola, mientras se escribe, buscando en
  objetivos, horario, notas, cierre y bloque de acción. Basta una letra.
- **Fecha**: elegir "Filtrar por → Fecha" y seleccionar el día en el calendario.
- **Energía**: "Filtrar por → Energía" y pulsar un nivel; volver a pulsarlo lo quita.
- **Período**: "Filtrar por → Período" y elegir semana, quincena, mes, trimestre, semestre, año
  o rango de fechas. Se aplica solo al completar los datos del tipo elegido.
- **Limpiar** deja todo en blanco y vuelve a mostrar todos los diarios.
- Los filtros activos son los que usan el Excel y el resumen: lo que ves es lo que se exporta.

### 19.4 Imprimir el diario del día (PDF)

1. En la fila del listado, pulsar el icono de PDF (o **Imprimir** dentro del día).
2. Si el diario existe, se abre el modal; marcar la casilla **solo** si quieres el documento
   con la marca de agua `SPECIMEN` (para pruebas o muestras).
3. **Generar PDF** abre el documento en una pestaña nueva, listo para imprimir o guardar.

### 19.5 Generar el reporte detallado (Excel)

1. Aplicar los filtros que se quieran (o ninguno, para todos los días).
2. Pulsar **Generar reporte detallado**.
3. Se descarga `reporte-diario[-filtrado]-AAAA-MM-DD.xlsx`, con un diario por fila y todas las
   secciones en columnas, filtro automático y encabezado congelado.
4. Si no hay días que coincidan, el sistema avisa con una alerta en lugar de descargar un
   archivo vacío.

### 19.6 Generar el resumen de desempeño (PDF)

1. Aplicar los filtros del período que se quiere analizar.
2. Pulsar **Generar resumen**; el modal explica que el análisis cubre lo que está filtrado.
3. Marcar la casilla si se quiere como documento de muestra.
4. **Generar PDF** abre el resumen en una pestaña nueva: números del período, lecturas por
   tema, tabla día por día y gráficos.

### 19.7 Modificar y eliminar

- **Modificar**: abre el mismo formulario con los datos cargados; al guardar vuelve al listado.
- **Eliminar**: pide confirmación con el nombre del día y avisa que no se puede deshacer. El
  borrado arrastra todas las secciones del día.

---

## 20. Configuración del entorno

Variables de `.env` (nombres y valores funcionales; no se reproducen secretos):

| Variable | Valor en el entorno local | Para qué |
| --- | --- | --- |
| `APP_NAME` | `Laravel` | Nombre interno (el sistema muestra su propio nombre en las vistas) |
| `APP_ENV` | `local` | Entorno |
| `APP_KEY` | clave generada | Cifrado de Laravel |
| `APP_DEBUG` | `true` | Mostrar detalles de error (debe ser `false` en producción) |
| `APP_URL` | `http://localhost:8088/planificadordiario-transformaconecta` | Dirección usada por `artisan` y por `route()` |
| `APP_TIMEZONE` | `America/Caracas` | **Zona horaria del sistema**: define "hoy", el bloqueo de la fecha, los reportes y el reloj |
| `APP_LOCALE` | `en` | Locale de Laravel (la interfaz no depende de él) |
| `APP_FALLBACK_LOCALE` | `es` | Locale de respaldo |
| `APP_FAKER_LOCALE` | `es_VE` | Locale de los datos falsos |
| `LOG_CHANNEL` / `LOG_STACK` / `LOG_LEVEL` | `stack` / `daily` / `debug` | Logs **diarios** en `storage/logs/laravel-AAAA-MM-DD.log` |
| `LOG_DAILY_DAYS` / `LOG_ERRORES_DAYS` | `30` / `60` | Días que se conservan el log general y el de errores |
| `DB_CONNECTION` | `mysql` | Motor de base de datos |
| `DB_HOST` / `DB_PORT` | `localhost` / `3306` | Servidor MySQL |
| `DB_DATABASE` | `planificador-diario` | Base de datos del sistema |
| `DB_USERNAME` / `DB_PASSWORD` | `root` / vacío | Credenciales locales de XAMPP |
| `SESSION_DRIVER` | `database` | Sesiones en la tabla `sessions` |
| `SESSION_LIFETIME` | `120` | Minutos de vida de la sesión |
| `CACHE_STORE` | `database` | Caché en la tabla `cache` |
| `QUEUE_CONNECTION` | `database` | Cola (no se usa: no hay trabajos) |
| `FILESYSTEM_DISK` | `local` | Disco por defecto (no se suben archivos) |
| `BROADCAST_CONNECTION` | `log` | Difusión (no se usa) |
| `MAIL_MAILER` | `log` | Los correos se escriben en el log (no hay envíos) |
| `VITE_APP_NAME` | `${APP_NAME}` | Variable del esqueleto de Vite (no se usa) |

**Configuración relevante de `config/`** (todo lo demás son valores por defecto de Laravel):

- `config/app.php`: `timezone` tomado de `APP_TIMEZONE`.
- `config/database.php`: cola MySQL por defecto (`utf8mb4`).
- `config/session.php`: driver `database`.
- `config/filesystems.php`: disco `local` en `storage/app/private`.
- `config/logging.php`: canal `stack` con `single`.
- `config/mail.php`: `log`, sin servidor SMTP configurado.

**Regla de oro de la zona horaria.** Todo lo que se refiere a "hoy" (la portada, el formulario
de hoy, los rangos de fechas y las marcas de generación de los documentos) usa
`APP_TIMEZONE=America/Caracas`. Cambiarla cambia el significado de "hoy".

### 20.1 Detalles de configuración que conviene conocer

| Tema | Estado actual | Comentario |
| --- | --- | --- |
| `APP_NAME` | `Laravel` (sin personalizar) | El sistema muestra su propio nombre en las vistas, pero el valor de `APP_NAME` sí se usa en otras partes: la cookie de sesión se llama `laravel-session`, el prefijo de caché es `laravel-cache-` y `mail.from.name` es "Laravel". Poner `APP_NAME="Planificador Diario Transforma-Conecta"` los alinea |
| `APP_LOCALE` / `APP_FALLBACK_LOCALE` | `en` / `es` | La interfaz **no usa el sistema de traducciones**: no existe la carpeta `lang/` ni llamadas a `__()` o `@lang`. Todos los textos están escritos en español dentro de las vistas, los controladores y el JS |
| `SESSION_PATH` / `SESSION_DOMAIN` | `/` / `null` | La cookie se comparte con cualquier otra aplicación servida en `localhost`. Funciona; si se publica, conviene acotarla a la subcarpeta o al dominio real |
| `SESSION_SECURE_COOKIE` | No definida | La cookie no exige HTTPS (coherente con el uso local). En producción debe activarse |
| `config/filesystems.php` | Disco `local` con `serve => true` | Es lo que crea las rutas `storage/{path}` del framework (ver [§21](#21-seguridad)). Además, `public/storage` **no existe**: nunca se ejecutó `php artisan storage:link` (no hace falta, el sistema no guarda archivos) |
| Caché de configuración | No generada | `bootstrap/cache/` solo tiene los archivos base; en desarrollo es correcto, en producción conviene `php artisan config:cache` y `route:cache` |
| Logs | Dos canales `daily`, con retención propia | El general va a `storage/logs/laravel-AAAA-MM-DD.log` (30 días) y el detalle de errores a `storage/logs/errores-AAAA-MM-DD.log` (60 días). El archivo `laravel.log` del esquema anterior queda como histórico. En producción conviene `LOG_LEVEL=warning` |
| Tareas programadas y colas | No hay | `routes/console.php` solo tiene el comando de ejemplo `inspire`; no hay `app/Jobs` ni `Schedule::`; la conexión de cola existe pero nadie despacha trabajos |
| Proveedores | Solo `AppServiceProvider`, con `register()` y `boot()` vacíos | Sin *bindings* ni personalizaciones; los alias no se declaran (Laravel 12 resuelve los del framework) |
| Metadatos de `composer.json` | `name: laravel/laravel`, descripción del esqueleto, `license: MIT` | El proyecto nunca se renombró, y `license` contradice al archivo `LICENSE`, que es **Unlicense** (ver [§28](#28-créditos-y-licencia)) |

---

## 21. Seguridad

### 21.1 Modelo de seguridad actual

El sistema está pensado como **libreta personal en un entorno local**: no hay autenticación ni
separación de datos entre usuarios. En consecuencia, **cualquiera que pueda alcanzar la URL
puede leer y modificar todos los diarios**. Esto es una decisión de alcance, no un descuido;
si el sistema se publica en internet, hay que agregar autenticación (ver
[§27](#27-estado-del-proyecto-y-hoja-de-ruta)).

### 21.2 Controles que sí existen

| Control | Dónde |
| --- | --- |
| **CSRF** en todos los formularios y peticiones AJAX (`@csrf` y `P.token`) | Vistas y `public/js/app.js` |
| **Validación del lado del servidor** de todos los campos, con reglas y mensajes | `DailyPlanController::rules()` |
| **Escape de salida** en Blade (`{{ }}`) y en el JS antes de inyectar HTML (`P.Util.escapar`) | Vistas y `public/js/*.js` |
| **Escapado del comodín** `LIKE` (`%`, `_`, `\`) en la búsqueda | `DailyPlan::scopeSearch()` |
| **Bloqueo HTTP de carpetas internas** (`app`, `config`, `storage`, `vendor`…) | `.htaccess` de la raíz |
| **Bloqueo de archivos ocultos** (`.env`, `.git`, `.editorconfig`…) | `.htaccess` de la raíz |
| **Bloqueo de archivos sueltos** (`composer.json`, `artisan`, `phpunit.xml`, `*.sqlite`, `*.log`…) | `.htaccess` de la raíz |
| **Sin listado de directorios** (`Options -Indexes`) | `.htaccess` de la raíz |
| **Consultas parametrizadas** por Eloquent (sin SQL armado con concatenación) | Todo el acceso a datos |
| **Transacciones** con `report()` de errores al log, sin exponer trazas al usuario | `DailyPlan::enTransaccion()` y los `try/catch` de los controladores |
| **Documentos sin caché** (`no-store`) | PDF del día y resumen |
| **Errores de validación AJAX** con el mismo sobre JSON, sin redirecciones confusas | `bootstrap/app.php` |
| **Página de error sin datos técnicos**: el aviso institucional explica el código en lenguaje llano y no muestra trazas, rutas del servidor ni consultas | `errores.index` y el manejador de excepciones (con `APP_DEBUG=false`, el 500 usa esa página) |
| **Captura de errores HTTP navegados** (403, 404, 405, 419, 429…) con el estado real | `bootstrap/app.php` + `ErrorController` |
| **Bitácora de errores sin datos personales**: cada fallo queda con incidente, URL, IP, ruta y traza, pero **nunca** con el cuerpo del formulario; los textos y la traza se recortan | `RegistroDeErrores` + canal `errores` (log diario) |
| **Rotación diaria de los logs**, con retención propia por canal | `config/logging.php` (driver `daily`) y `LOG_DAILY_DAYS` / `LOG_ERRORES_DAYS` |

### 21.3 Hallazgos de seguridad a resolver

| # | Hallazgo | Riesgo | Acción sugerida |
| --- | --- | --- | --- |
| 1 | La carpeta `.tmp-chrome4` (perfil completo de Chrome con cookies, `Login Data`, historial y `Web Data`) está **versionada en git y publicada en el remoto** | Alto: puede contener credenciales y sesiones guardadas del navegador | `git rm -r --cached .tmp-chrome4`, agregarla al `.gitignore` y limpiar el historial si el repositorio se compartió (ver [§10.1](#101-archivos-sueltos-en-la-raíz-y-carpeta-tmp-chrome4)) |
| 2 | Los archivos de ejemplo de la raíz (`resumen-muestra.pdf`, `reporte-detallado.xlsx`, `*.png`) son **descargables por HTTP**: el `.htaccess` no bloquea `pdf`, `xlsx` ni `png` | Bajo: solo exponen documentos derivados de datos de prueba | Moverlos a una carpeta interna o agregar esas extensiones al `<FilesMatch>` |
| 3 | El disco `local` tiene `serve => true`, lo que publica `GET storage/{path}` y `PUT storage/{path}` sirviendo `storage/app/private` **sin autenticación** | Medio: cualquier archivo que se deposite ahí queda accesible (hoy esa carpeta está vacía y el sistema no sube archivos) | Poner `'serve' => false` en `config/filesystems.php` si no se va a usar, o mantenerlo bajo autenticación |
| 4 | Ninguno de los dos `.htaccess` define **cabeceras de seguridad** (`X-Frame-Options`, `X-Content-Type-Options`, HSTS) ni de caché | Bajo en local, relevante al publicar | Agregar las cabeceras en el servidor o en el `.htaccess` |
| 5 | `APP_DEBUG=true`, `LOG_LEVEL=debug` y canal `single` | Medio en producción: expone detalles de error y acumula un solo log | `APP_DEBUG=false`, `LOG_LEVEL=warning`, canal `daily` |

### 21.4 Recomendaciones antes de publicar en internet

1. **Agregar autenticación** (Laravel Breeze o similar) y asociar `daily_plans.user_id` a cada
   registro; filtrar todas las consultas por el usuario autenticado.
2. `APP_DEBUG=false` y `APP_ENV=production`.
3. HTTPS obligatorio y cookies de sesión con `SESSION_SECURE_COOKIE=true`.
4. Respaldos periódicos de la base de datos.
5. Revisar el `.htaccess` si el hosting no permite `AllowOverride` (las reglas no se
   aplicarían).
6. Limitar el acceso por IP o con la protección del panel del hosting si es de uso interno.

---

## 22. Rendimiento y decisiones técnicas

| Tema | Cómo se resolvió |
| --- | --- |
| Consultas del listado | Se traen todos los días filtrados con `energyLevel` precargado y solo las columnas necesarias; DataTables pagina, ordena y busca en el navegador |
| Consulta por cada fila | El reporte y el resumen usan *eager loading* de todas las relaciones, así el número de consultas no crece con la cantidad de diarios |
| Contadores de la tabla | `toListArray()` usa `withCount` (`goals`, `goals_done_count`, `actionBlocks`, `notes`) en lugar de contar en el navegador |
| Excel en memoria | `Xlsx::save('php://output')` dentro de `ob_start()`: sin archivos temporales ni permisos de escritura |
| PDF | dompdf con la fuente base Helvetica (no incrustada): archivos de pocos KB, texto seleccionable |
| Gráficos del PDF | PNG con GD generados al vuelo y embebidos como `data:` URI (el PDF no ejecuta JavaScript) |
| Gráficos en pantalla | Redibujado local con JavaScript propio: cero peticiones al mover el horario |
| Caché del navegador | `no-store` en PDF y XLSX: la URL es fija y debe reflejar siempre el último estado |
| Índices de la base | Únicos en `plan_date`, `daily_plan_id + slot`, `daily_plan_id + schedule_slot_id`, `daily_plan_id + preparation_item_id`, `daily_plan_id + reflection_question_id`, y claves foráneas en cascada |
| Front-end sin compilación | Assets propios servidos directo desde `public/`, librerías por CDN: no hay `npm run build` en el despliegue |
| Idempotencia de los seeders | `updateOrCreate` con clave natural: se pueden reejecutar sin duplicar |
| Reintentos y dobles envíos | El botón de guardado se bloquea mientras hay una petición en curso y el formulario confirma antes de registrar |
| Robustez del listado | Se ignora la cancelación de DataTables (`abort`) para no mostrar errores falsos al escribir |

**Deuda técnica conocida y de bajo impacto.**

- `package.json`, `vite.config.js`, `resources/css/app.css`, `resources/js/app.js`,
  `resources/js/bootstrap.js`, `resources/views/welcome.blade.php` y
  `database/database.sqlite` son restos del esqueleto de Laravel: el pipeline de Vite/Tailwind
  y el `axios` de `bootstrap.js` **solo los usa `welcome.blade.php`**, que no tiene ninguna
  ruta. `npm run build` no afecta a la aplicación; de hecho, `package.json` no declara ninguna
  dependencia del front real (jQuery, Bootstrap, DataTables, bootbox y datepicker se cargan
  por CDN y se sirven desde `public/`).
- `User`, `UserFactory` y la tabla `users` existen pero no se usan.
- Hay ayudantes declarados y sin uso (listados en [§12.12](#1212-front-end-compartido)) y dos
  variables CSS sin uso (`--tc-naranja-claro`, `--tc-arena`).
- `laravel/sail` está instalado sin `compose.yaml` (el flujo Docker no se usa) y
  `config.allow-plugins` habilita `pestphp/pest-plugin` aunque Pest no está instalado.
- Los scripts `composer setup` y `composer dev` invocan `npm install` / `npm run dev`, pasos
  que en este proyecto no aportan nada (el front no se compila). `composer test` ejecuta
  `php artisan test`, que hoy termina con error por la prueba de ejemplo (ver [§24](#24-pruebas)).
- `database/database.sqlite` (94 KB) es un residuo de la primera etapa con SQLite; el sistema
  funciona con MySQL y ese archivo puede borrarse.
- Los archivos de muestra en la raíz pueden eliminarse (ver [§10.1](#101-archivos-sueltos-en-la-raíz-y-carpeta-tmp-chrome4)).
- El CSS propio tiene 1890 líneas en un solo archivo; si el sistema crece, conviene dividirlo
  por bloques (los comentarios numerados del archivo ya marcan los cortes naturales).

---

## 23. Mantenimiento y tareas frecuentes

### 23.1 Agregar un ítem al checklist "Antes de empezar"

1. Agregar el nombre en `database/seeders/PreparationItemSeeder.php`.
2. Ejecutar `php artisan db:seed --class=PreparationItemSeeder`.
3. Los diarios ya creados no cambian (cada uno tiene sus filas); a partir de ahí, los días
   nuevos incluirán el ítem. Para que un día existente lo incorpore, basta con abrirlo y
   guardarlo: `refreshDayStructure()` se ejecuta al crear, y `syncPreparationItems()` al
   guardar.
4. **Ojo con el Excel:** cada ítem es una columna. Si se agrega un ítem al catálogo, conviene
   añadir su columna en `ReporteDiarioExport::COLUMNAS` (y el orden debe coincidir con
   `toReportArray()`).

### 23.2 Cambiar o agregar una pregunta de procrastinación

1. Editar `database/seeders/ReflectionQuestionSeeder.php` (la categoría es
   `ReflectionQuestion::CATEGORY_PROCRASTINATION`).
2. `php artisan db:seed --class=ReflectionQuestionSeeder`.
3. Agregar la columna correspondiente en `ReporteDiarioExport::COLUMNAS` si se quiere en el
   Excel.
4. Para desactivar una pregunta sin perder el histórico:
   `UPDATE reflection_questions SET is_active = 0 WHERE id = …;`

### 23.3 Ajustar el horario base (franjas de 7:00 a 21:00)

1. Cambiar `FIRST_HOUR` / `LAST_HOUR` en `ScheduleSlotSeeder`.
2. `php artisan db:seed --class=ScheduleSlotSeeder`.
3. Los días nuevos generarán las franjas del catálogo actualizado; los días viejos conservan
   las suyas (y el usuario siempre puede agregar horas a mano en el formulario).
4. En días existentes, `generateScheduleEntries()` solo agrega las que faltan, nunca borra.

### 23.4 Cambiar las duraciones o los resultados del bloque de acción

Editar `ActionBlockDurationSeeder` o `ActionBlockOutcomeSeeder` y resembrar; son catálogos
independientes, no requieren tocar código.

### 23.5 Tocar la paleta o los estilos

- Pantalla: variables CSS del bloque 1 de `public/css/styles.css` (cambiar ahí se propaga a
  toda la interfaz).
- PDF del día y resumen: los colores están escritos en el `<style>` de
  `resources/views/diario/pdf.blade.php` y `resumen.blade.php` (dompdf no entiende variables
  CSS).
- Excel: constantes de color en `ReporteDiarioExport` (`AZUL_OSCURO`, `NARANJA`, `TURQUESA`,
  `TURQUESA_OSCURO`, `ROJO`, `AZUL`, `GRIS_BORDE`, `GRIS_CLARO`).
- Gráficos: paleta en `AgendaDelDia::PALETA`, `GraficosDelResumen` y `grafico.js` (las tres
  deben quedar iguales para que pantalla y PDF coincidan).

### 23.6 Comandos útiles

```
php artisan migrate                 # aplicar migraciones pendientes
php artisan migrate:status          # ver el estado
php artisan migrate:fresh --seed    # recrear la base y sembrar (¡borra los datos!)
php artisan db:seed                 # volver a sembrar los catálogos
php artisan config:clear            # limpiar la caché de configuración
php artisan view:clear              # limpiar la caché de vistas
php artisan route:list              # listar las rutas
php artisan tinker                  # consola interactiva
php artisan test                    # ejecutar las pruebas (hoy falla la de ejemplo: ver §24)
```

> `php artisan db:show` y otras órdenes que consultan el esquema pueden fallar en servidores
> MySQL sin `performance_schema`; no afecta a la aplicación. Si `artisan` no puede escribir en
> `storage/logs/`, revisar los permisos de `storage/` y `bootstrap/cache/`.

### 23.7 Respaldo y restauración

```
# Respaldo
mysqldump -u root planificador-diario > respaldo-planificador-AAAA-MM-DD.sql

# Restauración
mysql -u root planificador-diario < respaldo-planificador-AAAA-MM-DD.sql
```

Conviene respaldar también el `.env` (o al menos recordar sus valores) y no versionarlo.

### 23.8 Actualizar dependencias

```
composer update
php artisan migrate
php artisan config:clear
```

Tras actualizar dompdf o PhpSpreadsheet, conviene volver a comprobar los tres documentos (PDF
del día, Excel y resumen de desempeño) porque son las salidas más sensibles a cambios de
librería.

### 23.9 Revisar los logs

Los logs **rotan por día**: cada jornada estrena archivo y los anteriores se conservan los días
configurados (30 para el general, 60 para el de errores).

| Archivo | Qué mirar |
| --- | --- |
| `storage/logs/errores-AAAA-MM-DD.log` | El detalle de cada error: incidente, código, URL, IP, ruta, traza y contexto de la operación. **Es el primero que conviene abrir** |
| `storage/logs/laravel-AAAA-MM-DD.log` | El log general de Laravel (excepciones no controladas y lo que se anota con `report()`) |
| `storage/logs/laravel.log` | Archivo del esquema anterior (canal `single`); queda como histórico |

- Para encontrar un incidente concreto, se busca el **código que muestra la página de error**
  (por ejemplo `QY102A4U`) en `errores-*.log`.
- En PowerShell: `Select-String -Path storage\logs\errores-*.log -Pattern 'QY102A4U'`.
- Los 4xx se anotan como `WARNING` y los 5xx como `ERROR`, así se pueden filtrar por nivel.
- Si el log no se puede escribir (permisos de `storage/logs`), el sistema **no falla**: deja el
  aviso en el log de PHP y la respuesta sigue su curso. En ese caso, revisar los permisos.
- Para vaciar los logs viejos, basta con borrar los archivos `*.log` que ya no se necesiten;
  Laravel crea el del día cuando haga falta.

```
# Ver los últimos errores registrados
Get-Content storage\logs\errores-*.log -Tail 20

# Buscar por código de incidente
Select-String -Path storage\logs\errores-*.log -Pattern 'QY102A4U'
```

### 23.10 Cambiar la retención o el nivel de los logs

Todo se ajusta en `.env`, sin tocar código:

```
LOG_STACK=daily        # archivo nuevo cada día
LOG_DAILY_DAYS=30      # días que se conserva el log general
LOG_ERRORES_DAYS=60    # días que se conserva el log de errores
LOG_LEVEL=debug        # debug en local; warning o error en producción
```

El canal `errores` está definido en `config/logging.php`; si se quiere cambiar su nombre de
archivo o su nivel, ese es el lugar. Después de tocar el `.env`, ejecutar
`php artisan config:clear`.

---

## 24. Pruebas

### 24.1 Estado actual

`tests/` contiene únicamente los archivos de ejemplo del esqueleto de Laravel:

| Archivo | Contenido |
| --- | --- |
| `tests/TestCase.php` | Clase base de las pruebas |
| `tests/Feature/ExampleTest.php` | Prueba de ejemplo que visita `/` y espera 200 |
| `tests/Unit/ExampleTest.php` | Prueba unitaria de ejemplo (verdadero) |

**No hay pruebas propias del planificador.** Las verificaciones se han hecho de forma manual
sobre la aplicación en funcionamiento.

**Estado real de la suite:** `php artisan test` (equivalente a `composer test`) termina hoy con
**código de salida 1**: la prueba `Tests\Unit\ExampleTest` pasa, pero
`Tests\Feature\ExampleTest` (que visita `/` y espera 200) falla por dos causas encadenadas:

1. `APP_URL` incluye la subcarpeta del proyecto, así que en las pruebas la petición se resuelve
   contra esa raíz y el camino resultante (`/planificadordiario-transformaconecta`) no coincide
   con ninguna ruta → **404**.
2. Aun corrigiendo lo anterior, `HomeController@index` llama a `DailyPlan::findToday()` y la
   base SQLite en memoria **no tiene migraciones aplicadas** (en la prueba de ejemplo,
   `RefreshDatabase` está comentado) → error `no such table: daily_plans`.

Es una deuda conocida y de bajo impacto para el uso diario (la aplicación funciona), pero
conviene resolverla antes de escribir pruebas propias: activar `RefreshDatabase`, sembrar los
catálogos que la prueba necesite y ajustar la resolución de URL en el entorno `testing`.

### 24.2 Cómo ejecutarlas

```
php artisan test
# o directamente
vendor/bin/phpunit
```

`phpunit.xml` define dos suites (`Unit` y `Feature`) y, para las pruebas, cambia el entorno:
`APP_ENV=testing`, **SQLite en memoria** (`DB_CONNECTION=sqlite`, `DB_DATABASE=:memory:`),
caché y sesión en `array`, cola `sync` y mail `array`. Es decir, las pruebas **no tocan la base
de datos de desarrollo** (por eso, sin migrar la base en memoria, cualquier consulta falla).

> Herramientas disponibles para pruebas de estilo: `laravel/pint` está instalado, pero no hay
> `pint.json` ni script de composer que lo invoque; `laravel/sail` está instalado sin
> `compose.yaml`, así que el flujo Docker no se usa en este proyecto.

### 24.3 Qué conviene probar cuando se agreguen pruebas propias

1. **Validación** del día: sin objetivos, sin franjas, sin cierre y con fecha duplicada.
2. **Creación**: que `createDay()` genere las 15 franjas, los 6 ítems del checklist y las 5
   respuestas de reflexión.
3. **Sincronización**: que modificar el horario borre las franjas quitadas y conserve las
   existentes; que las notas se reemplacen; que un bloque de acción vacío no cree una fila.
4. **Filtros**: fecha exacta, energía, palabra clave y cada tipo de período (`Periodo::rango`).
5. **Rendimiento**: la fórmula con pesos y la redistribución cuando falta un componente.
6. **Salidas**: que `diario.reporte` devuelva un XLSX válido y `diario.resumen` un PDF; y que
   ambos avisen por JSON cuando no hay datos.
7. **Rutas**: que `/diario/abc` devuelva 404 (restricción numérica).

---

## 25. Solución de problemas

| Síntoma | Causa probable | Solución |
| --- | --- | --- |
| Estilos o JS no cargan (404 en `css/styles.css` o `js/app.js`) | La extensión del archivo no está en la lista del `.htaccess` de la raíz | Agregar la extensión a la regla de estáticos del `.htaccess` |
| Error "could not find driver" o "Connection refused" | MySQL apagado o credenciales distintas | Iniciar MySQL en XAMPP y revisar `DB_*` en `.env`; luego `php artisan config:clear` |
| "Base table or view not found" | Faltan migraciones | `php artisan migrate` |
| El formulario queda en blanco: sin objetivos, checklist ni preguntas | Catálogos sin sembrar o todos desactivados | `php artisan db:seed` y verificar `is_active = 1` en los catálogos |
| "Ya existe un diario con esa fecha." al guardar | Ya hay un diario para ese día | Abrir ese día y modificarlo, o elegir otra fecha |
| Las fechas se guardan corridas un día | Zona horaria mal configurada | `APP_TIMEZONE=America/Caracas` y `php artisan config:clear` |
| `05/10/2026` se interpreta como mayo | Formato día/mes/año | El sistema ya lo resuelve en `Helper::toCarbon()`, que prueba primero `d/m/Y`; pasar siempre `dd/mm/aaaa` en los filtros |
| El PDF no muestra el gráfico | GD no está habilitada en PHP | Habilitar `extension=gd` en `php.ini` y reiniciar Apache |
| El PDF sale con acentos raros | Fuente distinta de Helvetica o codificación alterada | No cambiar la fuente base del PDF; dompdf resuelve acentos con Helvetica |
| El resumen dice "Sin datos suficientes para el cruce" | No hay señales de procrastinación marcadas en el período | Es esperado: el sistema lo informa en lugar de forzar una conclusión |
| El Excel no trae algunas columnas nuevas | Se agregó un ítem o pregunta al catálogo sin tocar `COLUMNAS` | Añadir la columna en `ReporteDiarioExport::COLUMNAS` (mismo orden que `toReportArray()`) |
| El navegador muestra un PDF viejo | Caché del navegador | El sistema envía `no-store`; forzar recarga (Ctrl+F5) para descartar |
| Al borrar salen dos avisos | Se activó el aviso automático del ayudante | No usar `avisoExito:true` cuando la vista muestra su propia alerta (ya resuelto en el listado) |
| Error 403 al pedir `/storage/...` o `/.env` | Protección intencional del `.htaccess` | Es el comportamiento esperado |
| Aparece la página "La sesión expiró" (419) al guardar | Pasó demasiado tiempo desde que se abrió el formulario | Volver a abrir el diario y guardar de nuevo; la página de errores tiene el botón *Reintentar* |
| Cualquier dirección desconocida muestra la página de errores del sistema | Es el módulo de errores capturando el 404 | Es el comportamiento esperado; se puede ver un código concreto en `/error/404`, `/error/403`, `/error/500` |
| En producción aparece la página del sistema en lugar del detalle del 500 | El manejador solo dibuja el 500 cuando `APP_DEBUG=false` | Es lo buscado: sin depuración no se exponen trazas. Para verlas, `storage/logs/laravel.log` |
| Con `APP_DEBUG=true` un 500 sigue mostrando la pantalla de Laravel | Decisión de diseño del manejador | Es intencional, para no perder el detalle mientras se desarrolla; los 403, 404, 405 y 419 sí usan la página del sistema |
| La página de error muestra un "Código de incidente" | Es el identificador con el que quedó anotado ese fallo | Buscarlo en `storage/logs/errores-AAAA-MM-DD.log` para ver el detalle completo |
| Ya no hay entradas nuevas en `storage/logs/laravel.log` | Desde la V 1.02 el log general es diario | El archivo vigente es `storage/logs/laravel-AAAA-MM-DD.log`; el viejo queda como histórico |
| No aparece el archivo `errores-AAAA-MM-DD.log` | Solo se crea cuando hay un error registrado | Provocar una dirección inexistente (`/error/404` no cuenta: se registra al pedir una ruta inválida) o revisar los permisos de `storage/logs` |
| El log no se escribe y la página igual responde | El registro está protegido a propósito | Revisar permisos de `storage/logs`; el aviso queda en el log de PHP del servidor |
| Aparece "SPECIMEN" en un PDF | Se marcó la casilla de documento de muestra | Volver a generar sin marcar la casilla |
| `php artisan` avisa de `APP_KEY` faltante | `.env` sin clave | `php artisan key:generate` |
| Los logs crecen sin control | `LOG_LEVEL=debug` en local | En producción, `LOG_LEVEL=warning` o `error` y rotación de `storage/logs` |

---

## 26. Glosario

| Término | Significado en este sistema |
| --- | --- |
| **Diario / día** | Un registro de `daily_plans`: el plan y el cierre de una fecha |
| **Objetivo** | Una de las tres ranuras del día (1 Debo hacer, 2 Quiero hacer, 3 Algo para mí), con su tipo, descripción y estado de cumplimiento |
| **Ranura (`slot`)** | Posición 1, 2 o 3 del objetivo dentro del día |
| **Franja del horario** | Una fila del "Mi horario de hoy": hora, actividad y si se cumplió |
| **Slot del catálogo** | Franja del horario base (07:00 a 21:00) que sirve de punto de partida |
| **Antes de empezar** | Checklist de lo que hace falta tener listo, con descripción libre por ítem |
| **Señal de procrastinación** | Pregunta de reflexión marcada en el día |
| **Bloque de acción** | Unidad de foco del día: duración (5/10/15/20 min), resultado esperado y tarea |
| **Cierre del día** | Los cuatro campos finales: logros, pendiente, cuándo y orgullo |
| **Rendimiento** | Puntaje de 0 a 100 del día o del período (horario 60 %, objetivos 30 %, preparativos 10 %) |
| **Punto crítico** | Cantidad de señales a partir de la cual el rendimiento del período cae respecto al promedio, con un umbral de 8 puntos |
| **Franja del día** | Madrugada (0-5), Mañana (6-11), Tarde (12-17), Noche (18-23) |
| **Alcance del resumen** | La frase que describe los filtros aplicados al resumen |
| **Marca de muestra** | Marca de agua diagonal `SPECIMEN` que se agrega al PDF cuando se genera como documento de prueba |
| **Sobre JSON** | Estructura uniforme de las respuestas AJAX: `{ ok, message, data }` o `{ ok, message, errors }` |
| **Catálogo** | Tabla de valores seleccionables con `sort_order` e `is_active` |
| **Seed** | Comando que carga los catálogos iniciales |
| **MVP** | Producto mínimo viable: el conjunto de funciones ya implementadas y en uso |

---

## 27. Estado del proyecto y hoja de ruta

### 27.1 Estado actual

**Implementado y funcionando en la versión actual (V 1.02, sobre la base V 1.0):**

- Registro completo del día con las ocho secciones de la hoja institucional.
- Crear, ver, modificar y eliminar diarios, con validación en navegador y servidor.
- Listado con DataTables, búsqueda libre y filtros por fecha, energía y siete tipos de período.
- Línea de tiempo del día en pantalla y en el PDF.
- PDF del día con la hoja institucional y marca de agua `SPECIMEN` opcional.
- Reporte detallado en Excel con 25 columnas.
- Resumen de desempeño en PDF con números, redacción neutral, tabla día por día y tres
  gráficos.
- Catálogos sembrados y ampliables; publicación en la raíz del proyecto con bloqueos de
  seguridad.
- Página de errores propia para los códigos 3xx, 4xx y 5xx, usada también como pantalla cuando
  ocurre un error real de navegación.

**Historial de versiones (git, rama `main`):** el proyecto se desarrolló en etapas sucesivas:
inicialización del repositorio, creación de modelos y controladores, primera fase del MVP,
MVP del planificador completo, mejoras de guardado (persistencia de secciones opcionales y
confirmación antes de enviar) y limpieza del gráfico. Todo eso culminó en la **V 1.0 (MVP)**
(commit `ece6edd`), que se detalla en [§27.2](#272-versión-10-mvp-versión-base), y sobre ella se
publicó la actualización **V 1.02** de manejo de errores, que se detalla en
[§27.3](#273-versión-102-manejo-de-errores). Último commit: `db3d44f`.

**Estado del repositorio (verificado el 06/10/2026):**

| Dato | Valor |
| --- | --- |
| Rama | `main`, siguiendo a `origin/main` |
| Remoto | `https://github.com/rgomezs2000/planificadordiario-transformaconecta.git` |
| Último commit | `db3d44f` — "documentacion del sistema a partir del MVP" (06/10/2026) |
| Cantidad de commits | 15 |
| Árbol de trabajo | Con los cambios de la **V 1.02** sin confirmar (el módulo de errores) |
| Pendientes de higiene | `.tmp-chrome4` versionado (ver [§10.1](#101-archivos-sueltos-en-la-raíz-y-carpeta-tmp-chrome4)), 12 artefactos de ejemplo en la raíz y `database/database.sqlite` en disco |

### 27.2 Versión 1.0: MVP (versión base)

La **V 1.0** es la **versión base del sistema**: el MVP (producto mínimo viable) que se
construyó, se probó y está en uso. Es la base sobre la que se publicó la actualización
**V 1.02** (manejo de errores, [§27.3](#273-versión-102-manejo-de-errores)); todo lo demás que
describe este README corresponde a esta versión base.

| Dato | Valor |
| --- | --- |
| Versión | **1.0 (MVP)** — versión base |
| Nombre de la entrega | MVP del Planificador Diario "Transforma-Conecta" |
| Estado | Entregada, verificada y en uso |
| Fecha de cierre | Octubre de 2026 (último commit: 05/10/2026) |
| Commit que la define | `ece6edd` — "se implementa limpieza del grafico" (rama `main`, sincronizada con `origin/main`) |
| Alcance de la versión | Un solo usuario y sin autenticación: registro diario completo, consulta con filtros, tres salidas documentales y publicación en Apache |
| Base de datos | `planificador-diario` (MySQL): 19 migraciones aplicadas y 7 catálogos sembrados |
| Documentación de la versión | Este README |

> **Nota sobre el alcance de la V 1.0.** La versión quedó definida por el commit `ece6edd`.
> La **página de errores** ([§12.15](#1215-página-de-errores)) se agregó **después** de ese
> cierre y no forma parte del alcance congelado de la V 1.0: viaja en la actualización
> **V 1.02** ([§27.3](#273-versión-102-manejo-de-errores)).

#### 27.2.1 Todo lo que se construyó en la V 1.0

| Área | Entregable de la V 1.0 | Detalle en |
| --- | --- | --- |
| Registro del día | Formulario único con las **8 secciones** de la hoja institucional, en tres modos: crear, ver (solo lectura) y modificar | [§12.2](#122-formulario-del-diario) |
| Mis 3 objetivos | Tres ranuras fijas (1 Debo hacer · 2 Quiero hacer · 3 Algo para mí) con tipo del catálogo, descripción y casilla "Cumplido"; la fecha de cumplimiento se sella y se limpia sola | [§12.2](#122-formulario-del-diario) |
| Antes de empezar | Checklist con los 6 ítems del catálogo y descripción libre por ítem ("PC, cuaderno, calculadora") | [§12.2](#122-formulario-del-diario) |
| Mi horario de hoy | Tabla dinámica: agregar y quitar franjas, hora libre por fila, casilla de cumplida y botón "marcar todas" | [§12.2](#122-formulario-del-diario) |
| Procrastinación | Las 5 preguntas del catálogo con casilla de señal y respuesta corta | [§12.2](#122-formulario-del-diario) |
| Bloque de acción | Duración (5, 10, 15 o 20 minutos), resultado esperado y tarea; es la unidad de medida del foco del día | [§12.2](#122-formulario-del-diario) |
| Cierre del día | Logros, pendientes, cuándo se hará y orgullo (los cuatro obligatorios) | [§12.2](#122-formulario-del-diario) |
| Notas y recordatorios | Texto libre; cada línea no vacía se guarda como una nota con su orden | [§12.2](#122-formulario-del-diario) |
| Alta, baja y modificación | Crear, ver, modificar y eliminar diarios con transacción y borrado en cascada; **un diario por fecha** | [§12.2](#122-formulario-del-diario) y [§12.10](#1210-dominio-y-persistencia) |
| Validación | Reglas y mensajes en español en el servidor (422 con detalle por campo) y validación en el navegador con foco en el primer error | [§15](#15-reglas-de-negocio-y-validaciones) |
| Listado | Tabla DataTables en español: buscador, orden por fecha del diario, paginación 5/10/25/50/100 y cuatro acciones por fila (modificar, ver, PDF, eliminar) | [§12.3](#123-listado-y-buscador-de-diarios) |
| Búsqueda y filtros | Palabra clave (basta una letra), fecha exacta, energía y **siete tipos de período** (semana, quincena, mes, trimestre, semestre, año y rango), todos combinables y aplicados sin botón | [§12.4](#124-filtros-y-períodos) |
| Gráfico del día | Línea de tiempo **SVG en el navegador** que se redibuja sola al cambiar el horario, y el mismo gráfico como **PNG con GD** dentro del PDF | [§12.5](#125-gráfico-del-día) |
| PDF del día | Hoja institucional en tamaño carta, con marca de agua diagonal `SPECIMEN` opcional | [§12.8](#128-pdf-del-día-e-impresión) |
| Reporte detallado | `.xlsx` de **25 columnas**, un diario por fila, con filtro automático, panel congelado y colores por sección | [§12.6](#126-reporte-detallado-en-excel) |
| Resumen de desempeño | PDF con los números del período, **7 secciones redactadas**, tabla día por día, **3 gráficos**, punto crítico de procrastinación y nota de alcance | [§12.7](#127-resumen-de-desempeño-en-pdf) y [§16](#16-motor-de-análisis-del-desempeño) |
| Catálogos | 7 catálogos en base de datos con `sort_order` e `is_active`, sembrados de forma idempotente | [§12.9](#129-catálogos-y-datos-base) |
| Navegación e identidad | Portada con el estado del día y accesos directos, menú lateral, cabecera con fecha y hora en vivo, paleta institucional y diseño responsive | [§12.1](#121-inicio-y-menú-principal), [§12.13](#1213-plantilla-base-y-experiencia-de-uso) y [§17](#17-identidad-visual-y-experiencia-de-uso) |
| Publicación | Acceso desde la raíz del proyecto con `index.php` de reenvío y `.htaccess` con bloqueos de seguridad | [§12.14](#1214-arranque-y-publicación-en-apache) |

#### 27.2.2 Rutas entregadas en la V 1.0

Las **14 rutas** de la aplicación (portada, formulario, "hoy", listado, tabla JSON, reporte,
resumen, alta, ver, detalle, editar, actualizar, imprimir y eliminar) más el *health check*
`/up` que aporta el framework. El detalle completo, con método, nombre, acción y respuesta,
está en [§13](#13-rutas-y-endpoints). Con la ruta de errores que sumó la V 1.02
([§27.3](#273-versión-102-manejo-de-errores)), la versión actual tiene **15**:
`GET /error/{codigo}`.

#### 27.2.3 Modelo de datos entregado en la V 1.0

- **14 tablas del dominio**: los 7 catálogos (`energy_levels`, `goal_types`,
  `preparation_items`, `schedule_slots`, `action_block_durations`, `action_block_outcomes`,
  `reflection_questions`), la tabla eje `daily_plans` y las 6 hijas o de pivote (`plan_goals`,
  `schedule_entries`, `action_blocks`, `plan_notes`, `reflection_answers`,
  `daily_plan_preparation`), con sus índices únicos y borrados en cascada (ver
  [§11.2](#112-tablas-del-dominio)).
- **19 migraciones aplicadas**: 3 del esqueleto de Laravel (usuarios, caché y trabajos), 14 de
  creación del dominio y 2 de ajuste (`preparation_items_description` en el pivote y
  `start_time` en las franjas del horario, que además volvió opcional el `schedule_slot_id`).
- **Datos base sembrados**: 3 niveles de energía (Baja 😞, Media 😐, Alta 😃), 3 tipos de objetivo,
  6 ítems de preparación, 15 franjas horarias (07:00 a 21:00), 4 duraciones del bloque
  (5/10/15/20 min), 4 resultados del bloque y 5 preguntas de procrastinación.
- **Datos de uso registrados durante la versión**: 7 diarios (28/09/2026 a 04/10/2026), 21
  objetivos, 114 franjas de horario, 7 bloques de acción, 7 notas, 42 filas de preparación y
  35 respuestas de reflexión (ver [§11.4](#114-estado-de-la-base-de-datos-de-desarrollo)).

#### 27.2.4 Comportamientos destacados que quedaron en la V 1.0

- **Persistencia de las secciones opcionales**: aunque el navegador envíe `action_blocks[0]`
  vacío, no se crea una fila fantasma; y las franjas, el checklist y las respuestas se generan
  de forma **idempotente** al crear el día y al volver a guardarlo.
- **Confirmación antes de registrar o modificar**, con el botón bloqueado mientras hay una
  petición en curso (evita dobles envíos).
- **Sobre JSON uniforme** `{ ok, message, data }` / `{ ok, message, errors }` en toda la capa
  AJAX, con traducción de los nombres de campo de Laravel (`goals.0.description`) a los del
  formulario (`goals[0][description]`).
- **Documentos en memoria**: el Excel y los PDF se generan al vuelo, sin archivos temporales en
  el servidor, y se sirven sin caché para que siempre reflejen el último estado.
- **Gráficos sin JavaScript en el PDF**: la línea de tiempo del día y los tres gráficos del
  resumen se dibujan con GD y viajan como imágenes embebidas.
- **Tono y alcance del análisis**: el resumen describe los propios registros, avisa cuando no
  hay datos suficientes en lugar de forzar conclusiones y aclara que no es un diagnóstico ni
  una valoración personal.
- **Publicación cuidada**: las carpetas internas y los archivos sensibles responden 403, no hay
  listado de directorios y las reglas del `.htaccess` son relativas a la carpeta del proyecto
  (se puede renombrar o mover sin tocarlas).

#### 27.2.5 Infraestructura de la V 1.0

| Elemento | Cómo quedó en la V 1.0 |
| --- | --- |
| Servidor | XAMPP: Apache en el puerto **8088** con `DocumentRoot` en `C:/xampp/htdocs` |
| Base de datos | MySQL, base `planificador-diario`, conexión `utf8mb4` / `utf8mb4_unicode_ci` |
| Publicación | Raíz del proyecto con `index.php` (reenvío) y `.htaccess` (estáticos + seguridad); `/public/` sigue funcionando |
| Zona horaria | `America/Caracas` en `APP_TIMEZONE` |
| Sesiones y caché | Driver `database` (tablas `sessions` y `cache`) |
| Front-end | CSS y JS propios en `public/` + jQuery, Bootstrap 5, DataTables, bootbox y datepicker por CDN; **sin NPM ni compilación** |
| Documentos | dompdf (PDF del día y resumen) y PhpSpreadsheet (Excel), con fuentes DejaVu Sans en los gráficos y Helvetica en el texto |
| Colas, correo y API | No se usan: sin `app/Jobs`, sin envío de correo y sin `routes/api.php` |
| Pruebas | Solo los ejemplos del esqueleto (ver la limitación en [§24.1](#241-estado-actual)) |

#### 27.2.6 Cómo se construyó la versión (los 14 commits de la V 1.0)

| # | Hito | Commits | Qué aportó |
| --- | --- | --- | --- |
| 1 | Inicialización del proyecto | `fdef4d4` Initial commit · `7f3d158` se inicializa el proyecto, ahi se registran las actividades diarias | Repositorio, esqueleto de Laravel y primer registro diario |
| 2 | Modelos y controladores | `3002fe1` y `1c5ec95` se crea modelos y controladores · `9ff0163` se realiza mas avances | Modelo de datos, catálogos y operaciones del diario |
| 3 | Correcciones de guardado | `7f1c8f4` Fix optional diary form persistence (PR #1) · `5bec520` Confirm diary saves before submitting (PR #2) | Persistencia de las secciones opcionales y confirmación antes de enviar |
| 4 | Primera fase del MVP | `cec3434` se completa primera fese MVP del planificador Transforma-Conecta · `1134375` se acutualiza | Funcionalidad principal del planificador |
| 5 | MVP completo | `80bd664` se completa MVP del planificador | Reportes, gráficos, listado y pulido general (aquí entran también los artefactos de ejemplo) |
| 6 | Ajustes finales y cierre | `98f58b5` se mejora el apuntado · `ece6edd` se implementa limpieza del grafico | Ajustes del gráfico y cierre de la V 1.0 |

(Los *merge* `8c116f3` y `965a614` corresponden a las dos *pull requests* del hito 3.)

#### 27.2.7 Verificación con la que se cerró la V 1.0

Las comprobaciones ejecutadas sobre el entorno de referencia están en
[§18.4](#184-comprobación-de-que-todo-está-bien): las 19 migraciones en estado `Ran`, portada y
listado respondiendo **200**, `/up` respondiendo 200, `css/styles.css` sirviéndose desde
`public/`, `.env` respondiendo **403** y el árbol de git limpio. La única comprobación que **no**
pasa es la prueba de ejemplo `Feature` de `php artisan test` (dos causas identificadas en
[§24.1](#241-estado-actual)); se documenta como limitación, no como función entregada.

#### 27.2.8 Limitaciones conocidas de la V 1.0

Las de alcance están enumeradas en [§5.3](#53-fuera-de-alcance-lo-que-el-sistema-no-hace-hoy)
(sin autenticación ni multiusuario, sin app móvil, sin administración de catálogos por
interfaz, sin notificaciones, sin API pública, sin multi-idioma, sin adjuntos) y los hallazgos
técnicos y de seguridad que conviene resolver, en
[§21.3](#213-hallazgos-de-seguridad-a-resolver). En resumen, la V 1.0 es **completa para su
propósito** —el registro diario y sus reportes para una sola persona— y deja pendiente todo lo
que hace falta para un uso multiusuario y publicado.

#### 27.2.9 Cómo reproducir la V 1.0

1. Situarse en el commit de la versión: `git checkout ece6edd`.
2. Seguir la instalación paso a paso de [§18.2](#182-instalación-paso-a-paso) (`composer install`,
   `.env`, `key:generate`, crear la base `planificador-diario`, `php artisan migrate --seed`).
3. Abrir `http://localhost:8088/planificadordiario-transformaconecta/` y comprobar con la tabla
   de [§18.4](#184-comprobación-de-que-todo-está-bien).

### 27.3 Versión 1.02: manejo de errores

La **V 1.02** (también escrita V 1.0.2) es la **primera actualización sobre la versión base**.
Agrega el manejo de errores del sistema: una página propia para los códigos 3xx, 4xx y 5xx, y
la captura de los errores HTTP reales de navegación, que hasta la V 1.0 terminaban en las
pantallas genéricas de Laravel.

| Dato | Valor |
| --- | --- |
| Versión | **1.02** — actualización de manejo de errores |
| Tipo | Actualización funcional sobre la V 1.0 (MVP); no hay migraciones ni dependencias nuevas |
| Estado | Implementada y verificada; los cambios están en el árbol de trabajo, **pendientes de confirmar** |
| Fecha | Octubre de 2026 |
| Base | V 1.0 (MVP), commit `ece6edd`; documentación de la base en `db3d44f` |
| Alcance | Página de errores propia (`/error/{codigo}`), captura de los errores HTTP reales y **registro detallado en un log diario de errores**, sin tocar el comportamiento AJAX |
| Documentación | [§12.15](#1215-página-de-errores) y [§12.16](#1216-bitácora-de-errores-y-logs-diarios) (los módulos) y esta ficha |

**Historial de versiones del sistema.**

| Versión | Qué trajo | Commit / estado |
| --- | --- | --- |
| **1.0 (MVP)** | Versión base: registro diario, consulta con filtros, PDF del día, Excel y resumen de desempeño | `ece6edd` (05/10/2026) |
| **1.02** | Manejo de errores: página propia 3xx/4xx/5xx, captura de excepciones y **log diario con el detalle de cada error** | Árbol de trabajo (pendiente de confirmar) |

#### 27.3.1 Qué cambió en la V 1.02

**Archivos nuevos.**

| Archivo | Rol |
| --- | --- |
| `app/Errores/CatalogoDeErrores.php` | Catálogo: las 3 familias, 23 códigos con texto propio, icono y color; los códigos sin texto heredan el de su familia y un valor fuera de 300-599 se muestra como 500 avisando del ajuste |
| `app/Errores/RegistroDeErrores.php` | Bitácora detallada: arma la entrada (incidente, petición, excepción, traza recortada y contexto), la escribe en el log de errores y devuelve el código de incidente |
| `app/Http/Controllers/ErrorController.php` | `index(int $codigo, ?string $incidente)`: devuelve la vista con **el mismo estado HTTP** que explica, cabecera `no-store` y el incidente del error real |
| `resources/views/errores/index.blade.php` | La vista sobre la plantilla institucional: chip de familia, número grande, título, explicación, código de incidente, "qué puedes hacer" y cuatro salidas (inicio, listado, crear diario, reintentar) |

**Archivos modificados.**

| Archivo | Cambio |
| --- | --- |
| `routes/web.php` | Se agregó `GET /error/{codigo}` (nombre `error`), con el parámetro restringido a números |
| `bootstrap/app.php` | Dos manejadores de excepciones nuevos, después del de validación; ambos registran el error con detalle y pasan el incidente a la página |
| `config/logging.php` | Canal `errores` con driver `daily`: `storage/logs/errores-AAAA-MM-DD.log` |
| `.env` y `.env.example` | `LOG_STACK` pasa de `single` a `daily`; se suman `LOG_DAILY_DAYS=30` y `LOG_ERRORES_DAYS=60` |
| `app/Http/Controllers/DailyPlanController.php` | Los `try/catch` llaman a `registrarFallo()`: siguen anotando en el log general y ahora también en el log de errores, con el nombre de la operación y su contexto |
| `app/Models/DailyPlan.php` | `enTransaccion()` recibe el nombre de la operación y el contexto, y registra el fallo antes de relanzarlo |
| `public/css/styles.css` | Bloque **8. Página de errores**: estilos `.tf-error*`, con azul para 3xx, naranja para 4xx y rojo para 5xx |

#### 27.3.2 Cómo quedó el manejo de errores

1. **Página propia por código.** `/error/{codigo}` muestra el aviso institucional del código
   pedido, agrupado en tres familias: **300** redirecciones (azul), **400** errores del cliente
   (naranja: acceso no permitido, página que no existe, método no permitido, sesión expirada,
   demasiadas solicitudes…) y **500** errores del servidor (rojo).
2. **El código real, no un 200 disfrazado.** La respuesta sale con el estado que explica:
   `/error/403` contesta 403, `/error/404` contesta 404, `/error/500` contesta 500.
3. **Captura de los errores de verdad.** En `bootstrap/app.php` se registraron dos manejadores:

   | Manejador | Cuándo actúa | Qué responde |
   | --- | --- | --- |
   | `HttpExceptionInterface` | 403, 404, 405, 419, 429 y demás errores HTTP que se navegan | La página del sistema con el código real. Si la petición espera JSON, no interviene |
   | `Throwable` (cualquier otra excepción) | Errores 500 | La página del sistema **solo con `APP_DEBUG=false`**; con la depuración encendida se conserva la pantalla de Laravel para no perder el detalle mientras se desarrolla |

4. **El AJAX no cambia.** Las peticiones que esperan JSON (DataTables, guardado del formulario,
   reporte y resumen) siguen recibiendo JSON, y la validación conserva su camino (volver al
   formulario con los errores, o el sobre `{ ok:false, message, errors }` en AJAX).
5. **Sin datos técnicos a la vista.** La página explica qué pasó en lenguaje llano y no muestra
   trazas, rutas del servidor ni consultas.

#### 27.3.3 Registro detallado en el log y archivos diarios

**Qué se anota.** Cada error queda con su **código de incidente**, el origen (`manejador`,
`controlador` o `modelo`), el código y la familia HTTP, la clase de la excepción, el mensaje, el
archivo y la línea, la petición (método, URL, ruta, IP, navegador y referencia), si esperaba
JSON, las **12 primeras líneas de la traza** y el contexto de la operación (`diario_id`,
filtros, fecha…). Los 4xx se anotan como `WARNING` y los 5xx como `ERROR`. El detalle completo,
con el ejemplo de una entrada real, está en
[§12.16](#1216-bitácora-de-errores-y-logs-diarios).

**Dónde queda.** Laravel estrena un archivo por día y conserva los anteriores los días
configurados:

| Archivo | Qué guarda | Retención |
| --- | --- | --- |
| `storage/logs/laravel-AAAA-MM-DD.log` | Log general de Laravel (antes era un único `laravel.log`) | `LOG_DAILY_DAYS=30` |
| `storage/logs/errores-AAAA-MM-DD.log` | Detalle del manejo de errores | `LOG_ERRORES_DAYS=60` |

**El incidente viaja a la pantalla.** El manejador pasa el código de incidente a la página de
error, que lo muestra bajo el mensaje: así lo que vio la persona se puede buscar tal cual en
`errores-*.log`.

**De dónde sale cada registro.**

| Origen | Cuándo | Quién lo escribe |
| --- | --- | --- |
| `manejador` | Excepciones que llegan a `bootstrap/app.php` (404, 419, 500…) | `RegistroDeErrores::registrar()` |
| `controlador` | Lo que atrapan los `try/catch` de `DailyPlanController` | `registrarFallo()` → `deControlador()` |
| `modelo` | Lo que falla dentro de una transacción del diario | `enTransaccion()` → `deModelo()` |

**Sin datos personales.** No se guarda el cuerpo de la petición —el formulario tiene el
contenido del diario—: se registran la dirección, la ruta, la IP y el contexto explícito. Todos
los textos y la traza se recortan, y si el log no se puede escribir el sistema sigue
respondiendo (el aviso va al log de PHP).

#### 27.3.4 Cómo probar la V 1.02

| Dirección | Qué debe verse |
| --- | --- |
| `/error/301` | Redirección, en azul |
| `/error/403` | "Acceso no permitido" |
| `/error/404` | "Página no encontrada" |
| `/error/419` | "La sesión expiró" |
| `/error/500` | "Error interno del servidor" |
| `/error/999` | El aviso de 500 con la nota de que el código se ajustó |
| Cualquier dirección inventada | La misma página de 404 (la excepción real se dibuja con este módulo) |
| Guardar un formulario dejado mucho tiempo | La página de 419 con el botón *Reintentar* |
| Una dirección inventada, mirando la página | Aparece el **código de incidente**; ese mismo código está en `storage/logs/errores-AAAA-MM-DD.log` |
| `storage/logs` después de esos pedidos | Hay un `errores-AAAA-MM-DD.log` (y un `laravel-AAAA-MM-DD.log` desde la V 1.02) |

#### 27.3.5 Verificación con la que se cerró la V 1.02

| Comprobación | Resultado |
| --- | --- |
| `/error/301`, `/error/403`, `/error/404`, `/error/419`, `/error/500` | 301, 403, 404, 419 y 500 con la página del sistema |
| `/error/999` (fuera de rango) | 500 con la nota de ajuste |
| `/error/abc` | 404 normal del sistema |
| `/ruta-que-no-existe` y `/diario/abc` | 404 con la página del sistema |
| `POST /diario` sin testigo CSRF | 419 con la página del sistema |
| `/ruta-que-no-existe` con `Accept: application/json` | JSON limpio, sin HTML |
| `/diario/tabla` (AJAX del listado) | `{"ok":true,…}` intacto |
| Portada, formulario, listado y `/up` | 200 |
| Manejadores de excepciones probados aislados (con y sin depuración, web y JSON) | 5 de 5 escenarios con el resultado esperado |
| 404 real pedido por HTTP con parámetros | Se creó `storage/logs/errores-2026-10-06.log` con la entrada completa (código, familia, excepción, mensaje, archivo, URL con parámetros, IP `::1`, navegador y traza de 12 líneas) |
| Incidente de la página contra el del log | Coinciden: el código que muestra la página es el que encabeza la entrada |
| Error 500 forzado con una ruta temporal | Se creó `storage/logs/laravel-2026-10-06.log` (rotación diaria del log general) y el log de errores sumó la entrada con `origen: manejador` y `codigo: 500`; la ruta temporal se eliminó después |
| `php -l` de los archivos nuevos y modificados | Sin errores de sintaxis |

En esa verificación se detectó y corrigió un `use Throwable;` innecesario en
`bootstrap/app.php` que emitía un aviso de PHP y se filtraba dentro de las respuestas JSON.

#### 27.3.6 Compatibilidad y pendientes de la V 1.02

- **No rompe nada existente**: no hay migraciones, ni dependencias nuevas, ni cambios en la
  base de datos, y todas las direcciones que ya funcionaban siguen respondiendo igual.
- **Solo cambia lo que se ve cuando algo falla**: antes las páginas de error eran las genéricas
  de Laravel; ahora son las del sistema.
- **Cambia dónde se escriben los logs**: `LOG_STACK` pasa de `single` a `daily`. El archivo
  `storage/logs/laravel.log` deja de recibir entradas y pasan a usarse
  `laravel-AAAA-MM-DD.log` y `errores-AAAA-MM-DD.log`. Requiere que `storage/logs` sea
  escribible (ya lo era para el esquema anterior).
- **Pendiente de confirmar**: los archivos de la actualización todavía están en el árbol de
  trabajo (sin commit). Al confirmarlos conviene anotar el hash aquí como commit de la versión.
- **Mejora sugerida para más adelante**: avisar del error en el momento (por correo o un aviso
  visible en el sistema) cuando ocurre un 500; hoy el detalle queda en el log diario de errores.

### 27.4 Mejoras propuestas (orden sugerido)

| Prioridad | Mejora | Por qué |
| --- | --- | --- |
| Alta | Autenticación y multiusuario (`user_id` en `daily_plans`) | Hoy cualquiera con la URL accede a todo |
| Alta | Pruebas automatizadas de validación, creación, filtros y salidas | Proteger el núcleo antes de seguir creciendo |
| Media | Administración de catálogos desde la interfaz | Evitar editar seeders para cambiar una pregunta o agregar un ítem |
| Media | Gráficos de evolución entre períodos (comparar mes contra mes) | El resumen compara mitades dentro de un período |
| Media | Exportar el resumen también a Excel | Algunos destinatarios prefieren planilla |
| Media | Aviso cuando ocurre un error 500 (registro visible o correo) | Hoy el detalle queda en el log diario de errores, pero nadie se entera en el momento |
| Media | Rango de fechas rápidos ("últimos 30 días") | Un atajo frecuente en la consulta |
| Baja | Recordatorios o aviso si el día de hoy no está registrado | Sostener el hábito |
| Baja | Adjuntar imágenes o archivos al día | Evidencia fotográfica del trabajo |
| Baja | Modo oscuro y ajustes de accesibilidad ampliados | Confort de uso |
| Baja | Limpieza de restos del esqueleto (Vite, Tailwind, `welcome`, `users`, SQLite) | Reducir ruido en el repositorio |
| Baja | Multi-idioma | Solo si el programa se extiende a otros países |

---

## 28. Créditos y licencia

- **Programa:** Programa de Desarrollo Personal "Transforma-Conecta".
- **Sistema:** Planificador Diario "Transforma-Conecta" — *Mi Planificador Diario*.
- **Versiones:** **V 1.0 (MVP)**, la versión base (commit `ece6edd`, [§27.2](#272-versión-10-mvp-versión-base)), y **V 1.02**, actualización de manejo de errores ([§27.3](#273-versión-102-manejo-de-errores)).
- **Marco de trabajo:** [Laravel 12](https://laravel.com) (licencia MIT). El README original
  del esqueleto de Laravel (sección "About Laravel", patrocinadores y guía de contribución)
  fue reemplazado por esta documentación.
- **Librerías principales:** [barryvdh/laravel-dompdf](https://github.com/barryvdh/laravel-dompdf)
  · [PhpSpreadsheet](https://github.com/PHPOffice/PhpSpreadsheet) ·
  [Bootstrap](https://getbootstrap.com) · [Bootstrap Icons](https://icons.getbootstrap.com) ·
  [jQuery](https://jquery.com) · [DataTables](https://datatables.net) ·
  [bootbox.js](https://bootboxjs.com) · [bootstrap-datepicker](https://github.com/uxsolutions/bootstrap-datepicker).
- **Tipografía de los documentos:** DejaVu Sans (gráficos) y Helvetica (texto del PDF).
- **Licencia del proyecto:** [LICENSE](LICENSE) contiene la **Unlicense** (dominio público).
  Atención: `composer.json` todavía declara `"license": "MIT"` y conserva el `name`
  `laravel/laravel` y la descripción del esqueleto; conviene alinearlos si se publica el
  paquete o el repositorio.

---

<p align="center">
  <strong>MI PLANIFICADOR DIARIO</strong> · ORGÁNIZATE · ACTÚA · AVANZA<br>
  Programa de Desarrollo Personal "Transforma-Conecta"
</p>
