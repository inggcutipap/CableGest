# CableGest — Especificación de desarrollo

Coloca este archivo en la raíz del proyecto y pídele al agente: **"Lee `CableGest_ESPECIFICACION.md` completo. Resume lo que entendiste en 10 líneas y espera mi confirmación antes de empezar el Sprint 1."**

---

## 0. Cómo debes trabajar (reglas para el agente)

1. Trabaja **un sprint a la vez**. Al terminar cada sprint, detente, resume lo hecho y espera mi confirmación.
2. Antes de ejecutar un comando, explícame en una línea qué hace. No ejecutes nada destructivo (borrar carpetas, resetear la base de datos, `migrate:fresh`) sin pedirme permiso.
3. **No inventes datos.** Usa datos ficticios (Faker) en seeders y pruebas. Nunca uses datos reales de personas.
4. Si algo es ambiguo (por ejemplo, una regla de negocio de la empresa), **pregúntame**; no asumas.
5. Explica cada decisión técnica en 1–2 líneas y déjala anotada en `docs/DECISIONES.md`.
6. Haz commits pequeños en Git, con mensajes en español (`feat: ...`, `fix: ...`, `test: ...`, `docs: ...`).
7. Mantén actualizado el `README.md` con: requisitos, instalación, migración, carga del catálogo, cómo correr el sistema y las pruebas.
8. Al cerrar cada sprint, entrégame la **lista de capturas** que debo tomar como evidencia (están indicadas en cada sprint).
9. Este proyecto se desarrolla **en mi PC (Windows con Laragon)**. **No toques el servidor de producción.** El despliegue se hará más adelante, por separado, con respaldo previo.

---

## 1. Contexto del proyecto

**CableGest** es una aplicación web para la gestión de ventas, clientes, cobranzas e inventario de la Corporación Global de Telecomunicaciones S.A.C. (Cable Visión), proveedor de internet y televisión por cable en Mollendo y Congata (Arequipa, Perú). Reemplaza un sistema de escritorio en Visual Basic 6 y hojas de cálculo. Es parte de una investigación de pregrado: la aplicación es la intervención cuyo efecto se mide (tiempos de registro, duplicidad, errores en pagos, exactitud del inventario), por lo que debe ser correcta, trazable y fácil de usar (meta de usabilidad: SUS > 80).

Usuarios: 6 trabajadores. **Un solo rol de acceso** en esta versión (el campo `rol` existe pero no diferencia permisos).

---

## 2. Stack tecnológico (no cambiar)

| Capa | Tecnología |
|---|---|
| Backend / API | PHP 8.3, Laravel 12 (API REST, patrón MVC, Eloquent) |
| Frontend | Vue.js 3 (Composition API) + Vite, en la carpeta `frontend/` |
| Base de datos | MariaDB 10.11 |
| Autenticación | JSON Web Token (JWT) |
| Estilos | Tailwind CSS |
| Dependencias | Composer (PHP), npm con NVM (Node) |
| Pruebas | PHPUnit (pruebas de Feature de Laravel) y Postman |
| Control de versiones | Git |

Paquetes permitidos: `php-open-source-saver/jwt-auth` para JWT (**verifica que sea compatible con Laravel 12 antes de instalarlo; si no lo es, dímelo y propón una alternativa**), `maatwebsite/excel` para leer Excel (verifica compatibilidad), `vue-router` y `axios` en el frontend. No agregues otros frameworks de interfaz.

Convenciones: PSR-12 en PHP; guía de estilo oficial de Vue; **tablas y columnas en español, `snake_case`**; modelos en español singular (`Cliente`, `Venta`, …) con `protected $table` explícito; comentarios breves en español; validaciones con Form Requests; respuestas JSON consistentes (`{ "data": ..., "message": ... }` y errores con código HTTP correcto).

---

## 3. Modelo de datos (13 tablas)

Crea migraciones, modelos y relaciones. Claves foráneas con integridad referencial. **Regla general: no hay eliminación física** de ventas, pagos, contratos ni catálogo (solo baja lógica con `activo` o `estado`).

**usuarios**: id, nombre, correo (único), password (hash bcrypt), rol.

**clientes**: id, nombres, dni (varchar(8), único), direccion, telefono, fecha_registro.

**catalogo_servicios**: id, codigo_interno (varchar(10), único; conservar ceros a la izquierda), nombre, tipo (enum: plan_internet, plan_tv, plan_combo, cargo_unico, equipo), segmento (nullable: residencial/comercial/empresarial), velocidad_mbps (int, nullable), precio (decimal 10,2), activo (boolean, por defecto true).

**ventas**: id, cliente_id (FK), usuario_id (FK), fecha_venta, monto_total (decimal 10,2), estado (registrada/anulada).

**venta_detalle**: id, venta_id (FK), catalogo_id (FK), cantidad, precio_unitario (decimal 10,2; precio al momento de la venta), subtotal (decimal 10,2).

**contratos**: id, cliente_id (FK), venta_detalle_id (FK), fecha_inicio, estado (activo/suspendido/baja).

**pagos**: id, contrato_id (FK), monto (decimal 10,2), fecha_vencimiento, fecha_pago (nullable), estado (pendiente/pagado/vencido).

**equipos**: id, catalogo_id (FK; tipo de equipo), marca, serie (único), estado (disponible/instalado/en_reparacion/baja).

**movimientos_inventario**: id, equipo_id (FK), usuario_id (FK), tipo_movimiento (enum: entrada, salida), fecha.

**instalaciones**: id, contrato_id (FK), equipo_id (FK), fecha_instalacion.

**reclamos**: id, cliente_id (FK), contrato_id (FK, nullable), usuario_registro_id (FK), tecnico_id (FK a usuarios, nullable), motivo, diagnostico (enum: pendiente, falla_confirmada, sin_falla), estado (abierto/en_proceso/resuelto), fecha_registro, fecha_atencion (nullable), observaciones.

**auditoria**: id, usuario_id (FK), accion (crear/actualizar/anular), tabla_afectada, registro_id, detalle (text, JSON con valores anteriores y nuevos), fecha (timestamp).

**notificaciones**: id, pago_id (FK), mensaje, estado (pendiente/vista), fecha_generacion (timestamp).

Relaciones clave: una venta tiene varias líneas; una línea de tipo plan origina un contrato; un contrato tiene varios pagos e instalaciones; un equipo tiene varios movimientos; un pago puede tener varias notificaciones.

---

## 4. Requerimientos funcionales

| Código | Requerimiento |
|---|---|
| RF01 | Inicio de sesión con usuario y contraseña, con autenticación JWT. |
| RF02 | Registrar, consultar y actualizar clientes (DNI único). |
| RF03 | Registrar una venta con una o más líneas (planes, cargos únicos y equipos) tomadas de un catálogo unificado. |
| RF04 | Registrar pagos de cobranza y generar el estado de cuenta de cada cliente. |
| RF05 | Emitir alertas cuando vence un pago sin registro de pago y dejar constancia de cada alerta. |
| RF06 | Generar el reporte de morosidad. |
| RF07 | Registrar entradas y salidas de equipos. |
| RF08 | Consultar el stock disponible de equipos (calculado según su estado). |
| RF09 | Calcular la exactitud del inventario a partir de un conteo físico. |
| RF10 | Dashboard con indicadores clave de gestión. |
| RF11 | Registrar el equipo instalado en el domicilio de cada cliente, asociado a su contrato. |
| RF12 | Registrar reclamos, asignarlos a un técnico y registrar el diagnóstico (falla confirmada / sin falla). |
| RF13 | Administrar el catálogo (altas, baja lógica y cambios de precio), clasificado por tipo. |
| RF14 | Bitácora de auditoría de toda operación crítica (ventas, pagos, cambios de estado de contrato o reclamo, cambios de catálogo y clientes). |

## 5. Requerimientos no funcionales

| Código | Requerimiento |
|---|---|
| RNF01 | Usabilidad: SUS > 80/100. Interfaz simple, mensajes claros en español. |
| RNF02 | Acceso remoto desde cualquier navegador moderno. |
| RNF03 | Seguridad: autenticación sin estado con JWT; contraseñas con hash; límite de intentos de inicio de sesión. |
| RNF04 | Comunicación entre capas por HTTP/JSON. |
| RNF05 | Mantenibilidad: MVC, PSR-12, estilo oficial de Vue. |
| RNF06 | Integridad: borrado lógico en ventas, pagos y catálogo. |
| RNF07 | Trazabilidad: toda operación crítica en la bitácora, con usuario y marca de tiempo. |
| RNF08 | HTTPS en producción (certificado emitido para la dirección IP pública del servidor). |
| RNF09 | Respaldo diario de la base de datos, fuera del servidor. |
| RNF10 | Cortafuegos con solo los puertos SSH, HTTP y HTTPS; la base de datos solo acepta conexiones locales. |

---

## 6. Reglas de negocio

- **Venta:** `precio_unitario` se copia del catálogo en el momento de la venta; `subtotal = cantidad × precio_unitario`; `monto_total` = suma de subtotales. No se puede vender un ítem con `activo = false`. Si la línea es de tipo `plan_internet`, `plan_tv` o `plan_combo`, se crea un contrato (estado `activo`, `fecha_inicio` = fecha de la venta). Todo se hace dentro de una transacción de base de datos.
- **Pago:** estados `pendiente → pagado` al registrar el pago (con `fecha_pago`), o `pendiente → vencido` cuando `fecha_vencimiento` es anterior a hoy sin pago; un pago `vencido` puede pasar a `pagado`. Un comando programado `pagos:marcar-vencidos` (diario, con el programador de tareas de Laravel) marca los vencidos y crea una notificación por cada uno (sin duplicarla).
- **Generación de cobros mensuales:** el sistema debe poder generar el cobro de cada periodo para los contratos activos (`monto` = precio vigente del plan). **El día de vencimiento y la forma de generar el cobro dependen de la empresa: pregúntame antes de fijarlos**, y déjalos configurables en `config/cablegest.php`.
- **Estado de cuenta:** lista los pagos del cliente (todos sus contratos) y el saldo pendiente (suma de `pendiente` + `vencido`).
- **Reporte de morosidad:** contratos con al menos un pago `vencido`, con el monto adeudado y la antigüedad (días vencidos).
- **Inventario:** una entrada crea el equipo con estado `disponible` y un movimiento `entrada`. Instalar un equipo exige que esté `disponible`, lo pasa a `instalado`, crea el registro en `instalaciones` y un movimiento `salida`. El **stock** no se almacena: se calcula contando equipos `disponible` agrupados por `catalogo_id`. La `serie` es única.
- **Exactitud del inventario:** el usuario envía las series contadas físicamente en almacén. `coincidentes` = series presentes en ambos lados (contadas y registradas como `disponible`); `verificados` = series distintas de la unión de ambos conjuntos; `exactitud (%) = coincidentes / verificados × 100`. Devuelve también la lista de discrepancias.
- **Reclamos:** se registran con `diagnostico = pendiente` y `estado = abierto`. Al asignar técnico pasa a `en_proceso`. Al registrar el diagnóstico (`falla_confirmada` o `sin_falla`) con observaciones, se guarda `fecha_atencion` y el reclamo pasa a `resuelto`.
- **Auditoría:** con Observers de Eloquent, al crear o actualizar clientes, catálogo, ventas, contratos, pagos, equipos y reclamos, guardar en `auditoria` el usuario autenticado, la acción, la tabla, el id y los valores anteriores y nuevos en JSON.
- **Clientes:** el DNI debe tener 8 dígitos y ser único. No se eliminan.

---

## 7. API (todas bajo `/api`, protegidas con JWT salvo `login`)

| Método y ruta | Descripción | RF |
|---|---|---|
| POST `/login` | Devuelve el token | RF01 |
| GET `/me`, POST `/logout` | Usuario actual; cerrar sesión | RF01 |
| GET/POST `/clientes`, GET/PUT `/clientes/{id}` | Clientes (búsqueda por DNI y nombre) | RF02 |
| GET/POST `/catalogo`, GET/PUT `/catalogo/{id}`, PATCH `/catalogo/{id}/desactivar` | Catálogo | RF13 |
| POST `/ventas`, GET `/ventas`, GET `/ventas/{id}`, PATCH `/ventas/{id}/anular` | Ventas con varias líneas | RF03 |
| GET `/contratos`, GET `/contratos/{id}`, PATCH `/contratos/{id}/estado` | Contratos | RF03 |
| POST `/pagos/{id}/registrar`, GET `/pagos` (filtro por estado) | Pagos | RF04 |
| GET `/clientes/{id}/estado-cuenta` | Estado de cuenta | RF04 |
| GET `/reportes/morosidad` | Reporte de morosidad | RF06 |
| GET `/notificaciones`, PATCH `/notificaciones/{id}/vista` | Alertas de vencimiento | RF05 |
| POST `/equipos` (entrada), GET `/equipos`, POST `/equipos/{id}/instalar`, POST `/equipos/{id}/salida` | Equipos e inventario | RF07, RF11 |
| GET `/inventario/stock` | Stock por tipo de equipo | RF08 |
| POST `/inventario/exactitud` | Exactitud con conteo físico | RF09 |
| POST `/reclamos`, GET `/reclamos`, PATCH `/reclamos/{id}/asignar`, PATCH `/reclamos/{id}/diagnostico` | Reclamos | RF12 |
| GET `/auditoria` | Bitácora (solo lectura) | RF14 |
| GET `/dashboard` | Indicadores | RF10 |

**Dashboard (indicadores):** clientes activos (contratos activos); ingresos del mes (suma de pagos con `fecha_pago` en el mes); tasa de morosidad (pagos vencidos / total de pagos del periodo × 100); reclamos abiertos y resueltos; exactitud del inventario (último cálculo); ítems más vendidos (top 5 por cantidad); alertas de vencimiento pendientes.

---

## 8. Carga de datos desde Excel

**Catálogo** (comando `php artisan catalogo:importar {archivo.xlsx}`): columnas `internal_id`, `description`, `sale_unit_price`.
- `codigo_interno` = `internal_id` (conservar ceros a la izquierda); `nombre` = `description`; `precio` = `sale_unit_price` redondeado a 2 decimales.
- `tipo` por palabras clave del nombre: `cargo_unico` si contiene RECONEXION, INSTALACION, TRASLADO, SERVICIO TECNICO, MATERIAL EXTRA, MULTA o APORTES; `equipo` si contiene VENTA DE EQUIPOS, BATERIA u ONU; `plan_combo` si contiene a la vez INTERNET y TELEVISION/TV; `plan_tv` si solo TELEVISION/TV; `plan_internet` si solo INTERNET.
- `segmento`: `comercial` o `empresarial` si el nombre contiene COMERCIAL o EMPRESARIAL; si no, `residencial` (solo para planes).
- `velocidad_mbps`: número que precede a "MBPS", si existe.
- **No desactives duplicados.** Genera un CSV en `storage/` con los grupos de ítems de igual nombre normalizado, para que yo decida cuál desactivar.
- El comando debe poder ejecutarse varias veces sin duplicar registros (actualiza por `codigo_interno`).

**Clientes, contratos y recibos pendientes:** mi Excel exportado del sistema anterior tiene columnas que aún no te he mostrado. **Cuando llegues a esa tarea, pídeme las cabeceras y una muestra de 5 filas ficticias**, y propón el mapeo a las tablas `clientes`, `contratos` y `pagos` antes de programar el importador. El importador debe: validar DNI de 8 dígitos, reportar (sin cargar) los DNI duplicados, ser repetible y generar un informe de filas rechazadas.

---

## 9. Plan por sprint

### Sprint 1 — Base, catálogo y clientes (RF01, RF02, RF13, RF14)

1. Proyecto Laravel 12 con `.env.example` y `README.md` (instalación en Windows con Laragon).
2. Migraciones y modelos de las 13 tablas; seeder con un usuario administrador (`admin@cablevision.pe`, contraseña temporal indicada en el README).
3. Importador de catálogo (sección 8) y reporte de duplicados.
4. JWT: `/login`, `/me`, `/logout`; rutas protegidas responden 401 sin token; límite de intentos de login.
5. CRUD de clientes (crear, consultar, actualizar, buscar; sin eliminar).
6. CRUD de catálogo con baja lógica.
7. Auditoría con Observers (clientes y catálogo).
8. Frontend base en `frontend/`: pantalla de inicio de sesión, layout con menú, listado y formulario de clientes, listado de catálogo.
9. Pruebas CP01–CP06 (sección 10).

**Capturas de evidencia:** (1) proyecto Laravel en ejecución; (2) estructura de tablas en MariaDB; (3) catálogo cargado; (4) login en Postman con token; (5) resultado de las pruebas automatizadas.

### Sprint 2 — Ventas y migración (RF03)

1. Ventas con varias líneas y contrato automático (reglas de la sección 6), en transacción.
2. Anulación lógica de ventas.
3. Pantalla de ventas (cliente, líneas del catálogo, total) y listado de contratos.
4. Importador de clientes y contratos (sección 8; pídeme la muestra).
5. Auditoría de ventas y contratos.
6. Pruebas CP07, CP08, CP20 (parte de ventas) y CP22.

**Capturas:** (6) venta con varias líneas y su contrato; (7) clientes y contratos cargados desde Excel.

### Sprint 3 — Cobranzas (RF04, RF05, RF06)

1. Registro de pagos, estado de cuenta, reporte de morosidad.
2. Comando `pagos:marcar-vencidos` programado y notificaciones; generación de cobros mensuales (pregúntame las reglas).
3. Pantallas de cobranzas y de alertas.
4. Importación de recibos pendientes de cobro (pídeme la muestra).
5. Pruebas CP09–CP12 y CP20 (parte de pagos).

**Capturas:** (8) estado de cuenta y reporte de morosidad; (9) alerta de vencimiento.

### Sprint 4 — Inventario, reclamos, auditoría y dashboard (RF07–RF12, RF14)

1. Equipos, movimientos, instalaciones, stock calculado y exactitud con conteo físico.
2. Reclamos con asignación de técnico y diagnóstico.
3. Pantalla de auditoría (solo lectura) y dashboard con los indicadores de la sección 7.
4. Pantallas de inventario y reclamos.
5. Pruebas CP13–CP19 y CP21.

**Capturas:** (10) inventario; (11) reclamo con diagnóstico; (12) bitácora de auditoría; (13) dashboard.

### Después del Sprint 4 (lo haré yo, con tu guía)

Capacitación, pruebas de aceptación con los trabajadores, despliegue en el servidor (Ubuntu Server 24.04, Nginx, PHP-FPM, MariaDB, HTTPS por IP, cortafuegos, respaldos) y mediciones. **Prepárame la guía de despliegue paso a paso (`docs/DESPLIEGUE.md`) y el script de respaldo diario**, pero no los ejecutes.

---

## 10. Casos de prueba (cada uno debe tener una prueba automatizada de Feature, con el nombre `test_cpNN_...`)

| ID | Módulo | RF | Caso | Resultado esperado |
|---|---|---|---|---|
| CP01 | Autenticación | RF01 | Iniciar sesión con credenciales válidas | Acceso concedido y token emitido |
| CP02 | Autenticación | RF01 | Iniciar sesión con credenciales inválidas | Acceso denegado con mensaje de error |
| CP03 | Autenticación | RF01 | Pedir un recurso protegido sin token | 401 no autorizado |
| CP04 | Clientes | RF02 | Registrar cliente con DNI nuevo | Cliente creado |
| CP05 | Clientes | RF02 | Registrar cliente con DNI existente | Rechazado por duplicado |
| CP06 | Catálogo | RF13 | Desactivar un ítem del catálogo | No aparece en nuevas ventas; ventas anteriores conservan su precio |
| CP07 | Ventas | RF03 | Venta con plan, instalación y equipo | Tres líneas; total = suma de subtotales |
| CP08 | Ventas | RF03 | Venta de un plan | Se genera el contrato asociado |
| CP09 | Cobranzas | RF04 | Registrar el pago de un contrato | Pago `pagado` con su fecha |
| CP10 | Cobranzas | RF05 | Dejar vencer un pago sin registrarlo | Pasa a `vencido` y se genera una notificación |
| CP11 | Cobranzas | RF04 | Estado de cuenta de un cliente | Pagos y saldo pendiente correctos |
| CP12 | Cobranzas | RF06 | Reporte de morosidad | Solo contratos con pagos vencidos |
| CP13 | Inventario | RF07 | Entrada de un equipo con serie nueva | Equipo `disponible` y movimiento registrado |
| CP14 | Inventario | RF07 | Equipo con serie repetida | Rechazado |
| CP15 | Inventario | RF11 | Instalar un equipo en un contrato | Pasa a `instalado`, stock −1, instalación registrada |
| CP16 | Inventario | RF08 | Consultar stock por tipo | Cantidad = equipos `disponible` |
| CP17 | Inventario | RF09 | Exactitud con conteo físico | Resultado = coincidentes / verificados × 100 |
| CP18 | Reclamos | RF12 | Registrar reclamo y asignar técnico | Reclamo abierto con técnico asignado |
| CP19 | Reclamos | RF12 | Registrar diagnóstico y cerrar | `resuelto`, con diagnóstico y fecha de atención |
| CP20 | Auditoría | RF14 | Crear o modificar una venta o un pago | Registro en la bitácora con usuario, acción, fecha y detalle |
| CP21 | Dashboard | RF10 | Abrir el dashboard | Indicadores iguales a las consultas directas |
| CP22 | Migración | — | Cargar clientes depurados desde Excel | Registros cargados = registros del archivo, sin DNI duplicados |

Cuando termines un sprint, dime **qué pruebas pasaron y cuáles fallaron**, con el comando exacto para ejecutarlas (`php artisan test`).

---

## 11. Diseño de interfaz

Las maquetas de referencia están en `docs/maquetas/` (login, ventas y clientes, cobranzas, inventario, catálogo, reclamos y dashboard). Respeta su estilo: barra superior azul marino (`#1e3a5f`) con el nombre "CableGest", fondo crema (`#f5f4ee`), menú lateral claro con el ítem activo resaltado en azul, tarjetas de resumen con cifras grandes, tablas limpias y etiquetas de estado de color (verde = activo/aprobado, ámbar = por revisar, azul = en proceso, rojo = vencido). Interfaz en español, sin dependencias de interfaz distintas de Tailwind. Las cifras de las maquetas son ilustrativas: **no las uses como datos**.

---

## 12. Seguridad y datos

- No subas a Git el `.env`, archivos Excel con datos reales ni respaldos de la base de datos (agrégalos a `.gitignore`).
- Los datos personales de clientes (DNI, dirección, teléfono) están protegidos por la Ley N.° 29733: no los registres en logs ni los muestres en mensajes de error.
- Valida toda entrada del usuario; usa consultas parametrizadas (Eloquent); configura CORS solo para el origen del frontend.
- Las pruebas usan una base de datos de prueba separada y datos ficticios.
