# Actualizaciones pendientes para la tesis (`PA2_TDI_CutipaPerez.docx`)

Este documento recopila todas las incongruencias y diferencias detectadas al contrastar el informe de investigación (`PA2_TDI_CutipaPerez.docx`), la especificación técnica (`CableGest_ESPECIFICACION.md`) y el entorno real de desarrollo en WSL Ubuntu 24.04 (paridad con el servidor Ubuntu Server 24.04 LTS), incluyendo un análisis crítico y la estructura corregida de la base de datos.

---

## 1. Entorno de desarrollo, herramientas y servidor

### 1.1 Motor de base de datos: MariaDB 10.11.14 (coincidente)
- **Dónde aparece en el Word:** Resumen, tablas de 3.2, sección 2.3.3, Anexo 2 (A.1, B.1, B.2, B.4, D) y pies de figuras (3.7.1).
- **Situación verificada:** El servidor de producción tiene **`10.11.14-MariaDB-0ubuntu0.24.04.1`** y WSL quedó configurado exactamente con **`10.11.14-MariaDB-0ubuntu0.24.04.1`**. **No requiere cambio en el Word.**

### 1.2 Servidor web en producción y desarrollo: Apache 2.4.58 vs. Nginx + PHP-FPM
- **Dónde aparece en el Word:** Sección 3.2 (*Entorno de despliegue*, tabla), Anexo 1 (Tabla 10, fase 3) y Anexo 2 (B.4, tabla y diagrama de despliegue).
- **Incongruencia detectada:** El documento plantea **Nginx + PHP-FPM 8.3**, pero el servidor real de producción y el entorno WSL ejecutan **Apache/2.4.58 (Ubuntu)** con **PHP 8.3.6** (`libapache2-mod-php8.3`).
- **Actualización requerida en el Word:**
  - En la tabla de 3.2 (*Entorno de despliegue*) y en la fase 3 del Anexo 1 (Tabla 10): reemplazar `Nginx` y `PHP-FPM 8.3` por **`Apache 2.4.58`** y **`PHP 8.3.6 (mod_php)`**.
  - En la sección **B.4** (diagrama de despliegue): reemplazar los bloques `Nginx` y `PHP-FPM 8.3 + Laravel 12` por `Apache 2.4.58 + PHP 8.3.6 + Laravel 12`.

### 1.3 Entorno local (Laragon en Windows vs. WSL2 Ubuntu 24.04)
- **Dónde aparece en la especificación:** `CableGest_ESPECIFICACION.md` menciona "Windows con Laragon".
- **Realidad observada:** El desarrollo se ejecuta en **WSL2 con Ubuntu 24.04.4 LTS**, **PHP 8.3.6**, **Apache 2.4.58**, **MariaDB 10.11.14**, **Node.js v20.20.2 / npm 10.8.2**, **Composer 2.7.1** y **Git 2.43.0**, logrando paridad exacta con el servidor Ubuntu Server 24.04 LTS.
- **Actualización sugerida en 3.2 (*Entorno de construcción*):** Especificar el entorno WSL2 Ubuntu 24.04 LTS con esas versiones exactas.

---

## 2. Análisis crítico y estructura corregida de la base de datos (Anexo 2, B.2)

### 2.1 Qué está bien planteado en el modelo original
Antes de corregir fallas, conviene conservar los aciertos estructurales del modelo propuesto:
1. **Catálogo unificado (`catalogo_servicios`)**: resuelve correctamente que la empresa mezcle planes recurrentes, cargos únicos y equipos en una sola lista de precios.
2. **Cabecera-detalle con precio congelado (`ventas` / `venta_detalle`)**: guardar `precio_unitario` en `venta_detalle` evita que cambios futuros en el catálogo alteren ventas históricas.
3. **Inventario serializado sin campo `stock` redundante (`equipos`)**: calcular el stock contando registros con `estado = 'disponible'` evita desincronización entre movimientos y existencias.
4. **Integridad histórica (`activo` / `estado`)**: evitar el borrado físico preserva la trazabilidad contable y estadística.

### 2.2 Qué está mal planteado o es incongruente (7 fallas detectadas)

| # | Tabla(s) afectada(s) | Problema / Incongruencia detectada | Impacto si no se corrige | Solución correcta |
|---|---|---|---|---|
| **1** | `contratos` | `venta_detalle_id` es `NOT NULL` y **no existe `catalogo_id`**. | En el **Sprint 2** se migran contratos vigentes desde el Excel del sistema VB6 sin historial de ventas (`ventas`/`venta_detalle`). Si es `NOT NULL`, no se pueden insertar sin inventar ventas ficticias. Además, en el **Sprint 3** sería imposible saber qué plan corresponde cobrar cada mes en contratos migrados. Tampoco hay clave de contrato para re-importar sin duplicar. | Hacer **`venta_detalle_id` `NULLABLE`** (con índice `UNIQUE`), añadir **`catalogo_id` (`FK -> catalogo_servicios.id`, `NOT NULL`)** y añadir **`codigo_contrato` (`VARCHAR(20)`, `NULLABLE`, `UNIQUE`)** para trazabilidad e idempotencia al migrar desde Excel. |
| **2** | `equipos` / Dashboard (`RF09` + `RF10`) | El Dashboard exige *"exactitud del inventario (último cálculo)"*, pero **ninguna de las 13 tablas almacena los conteos físicos**. | `POST /inventario/exactitud` calcularía el porcentaje en memoria y se perdería al instante; `GET /dashboard` no tendría de dónde leer el último cálculo ni habría historial para comparar pre-test vs. post-test. | Incorporar la tabla **`conteos_inventario`** (14.ª tabla) para persistir cada conteo físico (`usuario_id`, `coincidentes`, `verificados`, `exactitud`, `discrepancias`, `fecha`). *(Alternativa si deseas mantener exactamente 13 tablas en la tesis: guardar el resultado como evento en `auditoria`, aunque una tabla dedicada es técnicamente superior).* |
| **3** | `pagos` | No tiene **`periodo`** (`YYYY-MM`), no tiene clave única contra doble facturación mensual, ni estado `anulado` ni registro de quién/cómo cobró. | Si se ejecuta dos veces la generación mensual de cobros (Sprint 3), se duplican los recibos del mes para el mismo contrato (causando precisamente el error operativo que la tesis busca eliminar). Tampoco permite anular lógicamente un recibo sin borrarlo físicamente. | Añadir **`periodo` (`CHAR(7)`, nullable)** con **`UNIQUE(contrato_id, periodo)`** (en MariaDB múltiples `NULL` no colisionan, pero dos cobros `'2026-11'` para un mismo contrato quedan bloqueados a nivel BD). Añadir estado `'anulado'` al enum y campos opcionales **`usuario_cobro_id`**, **`metodo_pago`** y **`observaciones`**. |
| **4** | `auditoria` | `usuario_id` es **`NOT NULL`**, `detalle` es `TEXT` (64 KB) y faltan índices. | Cuando corran comandos sin sesión JWT (`catalogo:importar`, `pagos:marcar-vencidos` en cron o seeders), los Observers de Eloquent lanzarán error SQL de integridad al intentar insertar `usuario_id = null`. | Hacer **`usuario_id` `NULLABLE`** (`ON DELETE RESTRICT`), usar `LONGTEXT` / `JSON` para `detalle` e indexar `(tabla_afectada, registro_id)` y `fecha`. |
| **5** | `movimientos_inventario`, `instalaciones` | `movimientos_inventario.fecha` es `DATE` (sin hora) y carece de **`motivo`**; `instalaciones` no distingue un equipo **actualmente instalado** de uno retirado/reemplazado. | Si un equipo entra y sale el mismo día, no se puede saber el orden cronológico real. Si un cliente cambia de router/ONU por falla, no queda registro de qué equipo sigue en su domicilio y cuál regresó a almacén. | Cambiar `movimientos_inventario.fecha` a **`TIMESTAMP`** y añadir **`motivo` (`VARCHAR(100)`, nullable)**. En `instalaciones`, añadir **`fecha_retiro` (`DATE`, nullable)**, **`activo` (`BOOLEAN`, default `true`)** y **`usuario_id` (`FK`, nullable)**. |
| **6** | `notificaciones`, `ventas` | `notificaciones` no impide duplicados a nivel BD; `ventas` no define qué pasa con el contrato al anular una venta. | Un reintento del comando `pagos:marcar-vencidos` podría duplicar alertas. Anular una venta dejaría el `contrato` activo y seguiría generando cobros mensuales y sumando en *Clientes activos*. | En `notificaciones`, agregar **`tipo` (`VARCHAR(30)`, default `'vencimiento'`)** y **`UNIQUE(pago_id, tipo)`**. En `ventas`, añadir **`motivo_anulacion` (`VARCHAR(255)`, nullable)** y regla transaccional: al anular venta, pasar sus contratos asociados a `baja` (si no tienen pagos `pagado`) y sus pagos `pendiente`/`vencido` a `anulado`. |
| **7** | Varias tablas y Diagrama ER | Caracteres `ñ` y tildes en nombres/enums (`contraseña_hash`, `dirección`, `teléfono`, `fecha_instalación`, `"en reparación"`), `reclamos.observaciones` sin `nullable`, falta de `timestamps` uniformes y Diagrama ER incompleto. | Problemas de codificación/convenciones en Laravel y migraciones, fallos al crear reclamos sin observaciones previas, empates cronológicos en fechas `DATE` y contradicción visual entre el ER del Word y las tablas reales. | Eliminar toda `ñ` y tilde en esquema y código; hacer `reclamos.observaciones` `nullable`; agregar `timestamps` (`created_at`, `updated_at`) de Laravel; agregar `usuarios.activo` (`boolean`); aplicar `ON DELETE RESTRICT` en todas las FKs y actualizar la figura del Diagrama ER. |

---

### 2.3 Estructura corregida propuesta (Diccionario de datos actualizado — 14 tablas)

Convenciones aplicadas en **todas** las tablas:
- Motor **InnoDB**, codificación **`utf8mb4_unicode_ci`**.
- Cero caracteres `ñ`, tildes o espacios en identificadores y valores `ENUM`.
- Claves foráneas con **`ON DELETE RESTRICT`** y **`ON UPDATE CASCADE`** (prohibición de borrado físico, RNF06).
- Todas las tablas maestras y transaccionales incluyen `created_at` y `updated_at` (`TIMESTAMP NULL`) gestionados por Laravel.

#### 1. `usuarios`
| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | `PK`, `AUTO_INCREMENT` | Identificador único |
| `nombre` | `VARCHAR(100)` | `NOT NULL` | Nombre completo del trabajador |
| `correo` | `VARCHAR(100)` | `NOT NULL`, `UNIQUE` | Correo electrónico de acceso |
| `password` | `VARCHAR(255)` | `NOT NULL` | Hash bcrypt (sin `ñ`) |
| `rol` | `VARCHAR(30)` | `NOT NULL`, `DEFAULT 'operador'` | Reservado para control de acceso futuro |
| `activo` | `BOOLEAN` | `NOT NULL`, `DEFAULT 1` | Baja lógica de usuario sin perder trazabilidad |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | Auditoría temporal de Laravel |

#### 2. `clientes`
| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | `PK`, `AUTO_INCREMENT` | Identificador único |
| `nombres` | `VARCHAR(150)` | `NOT NULL`, `INDEX` | Nombres y apellidos del cliente |
| `dni` | `CHAR(8)` | `NOT NULL`, `UNIQUE` | DNI de 8 dígitos exactos (conserva ceros a la izquierda) |
| `direccion` | `VARCHAR(200)` | `NOT NULL` | Dirección del domicilio (sin tilde) |
| `telefono` | `VARCHAR(20)` | `NULL` | Teléfono de contacto (sin tilde; soporta espacios o anexo) |
| `fecha_registro` | `DATE` | `NOT NULL` | Fecha de alta del cliente |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | Auditoría temporal de Laravel |

#### 3. `catalogo_servicios`
| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | `PK`, `AUTO_INCREMENT` | Identificador único |
| `codigo_interno` | `VARCHAR(10)` | `NOT NULL`, `UNIQUE` | Código heredado de VB6 (conserva ceros, ej. `00011`) |
| `nombre` | `VARCHAR(200)` | `NOT NULL` | Descripción comercial del ítem (permite duplicados migrados hasta revisión) |
| `tipo` | `ENUM('plan_internet','plan_tv','plan_combo','cargo_unico','equipo')` | `NOT NULL`, `INDEX` | Naturaleza del ítem |
| `segmento` | `ENUM('residencial','comercial','empresarial')` | `NULL` | Solo aplica a planes |
| `velocidad_mbps` | `INT UNSIGNED` | `NULL` | Velocidad contratada (solo internet/combo) |
| `precio` | `DECIMAL(10,2)` | `NOT NULL` | Precio de venta vigente (S/) |
| `activo` | `BOOLEAN` | `NOT NULL`, `DEFAULT 1`, `INDEX` | `false` = baja lógica |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | Auditoría temporal de Laravel |

#### 4. `ventas`
| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | `PK`, `AUTO_INCREMENT` | Identificador único |
| `cliente_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> clientes.id` | Cliente comprador |
| `usuario_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> usuarios.id` | Trabajador que registró la venta |
| `fecha_venta` | `DATE` | `NOT NULL`, `INDEX` | Fecha de la venta |
| `monto_total` | `DECIMAL(10,2)` | `NOT NULL` | Suma exacta de `venta_detalle.subtotal` |
| `estado` | `ENUM('registrada','anulada')` | `NOT NULL`, `DEFAULT 'registrada'` | Estado lógico de la venta |
| `motivo_anulacion` | `VARCHAR(255)` | `NULL` | Sustento si la venta fue anulada |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | Auditoría temporal de Laravel |

#### 5. `venta_detalle`
| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | `PK`, `AUTO_INCREMENT` | Identificador único |
| `venta_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> ventas.id` | Cabecera de venta |
| `catalogo_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> catalogo_servicios.id` | Ítem vendido |
| `cantidad` | `INT UNSIGNED` | `NOT NULL` | Cantidad vendida (`>= 1`) |
| `precio_unitario` | `DECIMAL(10,2)` | `NOT NULL` | Precio copiado al momento de la venta |
| `subtotal` | `DECIMAL(10,2)` | `NOT NULL` | `cantidad * precio_unitario` |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | Auditoría temporal de Laravel |

#### 6. `contratos` *(corregida)*
| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | `PK`, `AUTO_INCREMENT` | Identificador único |
| `codigo_contrato` | `VARCHAR(20)` | `NULL`, `UNIQUE` | Código heredado de VB6 o correlativo interno (clave idempotente para migración Excel) |
| `cliente_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> clientes.id` | Titular del servicio |
| `catalogo_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> catalogo_servicios.id` | Plan vigente del contrato (permite cobros mensuales y contratos migrados sin venta previa) |
| `venta_detalle_id` | `BIGINT UNSIGNED` | `NULL`, `UNIQUE`, `FK -> venta_detalle.id` | Línea de venta origen (`NULL` si proviene de migración VB6) |
| `fecha_inicio` | `DATE` | `NOT NULL` | Fecha de inicio del servicio |
| `estado` | `ENUM('activo','suspendido','baja')` | `NOT NULL`, `DEFAULT 'activo'`, `INDEX` | Estado del contrato |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | Auditoría temporal de Laravel |

#### 7. `pagos` *(corregida)*
| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | `PK`, `AUTO_INCREMENT` | Identificador único |
| `contrato_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> contratos.id` | Contrato facturado |
| `periodo` | `CHAR(7)` | `NULL`, `UNIQUE(contrato_id, periodo)` | Mes de facturación `YYYY-MM` (evita doble cobro mensual) |
| `monto` | `DECIMAL(10,2)` | `NOT NULL` | Monto a cobrar (S/) |
| `fecha_vencimiento` | `DATE` | `NOT NULL`, `INDEX` | Fecha límite de pago |
| `fecha_pago` | `DATE` | `NULL`, `INDEX` | Fecha efectiva de pago (`NULL` si pendiente/vencido) |
| `estado` | `ENUM('pendiente','pagado','vencido','anulado')` | `NOT NULL`, `DEFAULT 'pendiente'`, `INDEX` | Estado del cobro |
| `usuario_cobro_id` | `BIGINT UNSIGNED` | `NULL`, `FK -> usuarios.id` | Trabajador que registró el cobro |
| `metodo_pago` | `VARCHAR(30)` | `NULL` | Efectivo, transferencia, Yape/Plin, etc. |
| `observaciones` | `VARCHAR(255)` | `NULL` | N.° de operación o nota de cobranza |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | Auditoría temporal de Laravel |

#### 8. `equipos`
| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | `PK`, `AUTO_INCREMENT` | Identificador único |
| `catalogo_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> catalogo_servicios.id` | Tipo de equipo (`tipo = 'equipo'`) |
| `marca` | `VARCHAR(50)` | `NOT NULL` | Marca del equipo |
| `serie` | `VARCHAR(80)` | `NOT NULL`, `UNIQUE` | Número de serie único (normalizado en mayúsculas) |
| `estado` | `ENUM('disponible','instalado','en_reparacion','baja')` | `NOT NULL`, `DEFAULT 'disponible'`, `INDEX` | Estado físico actual |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | Auditoría temporal de Laravel |

#### 9. `movimientos_inventario` *(corregida)*
| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | `PK`, `AUTO_INCREMENT` | Identificador único |
| `equipo_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> equipos.id` | Equipo involucrado |
| `usuario_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> usuarios.id` | Trabajador que registró el movimiento |
| `tipo_movimiento` | `ENUM('entrada','salida')` | `NOT NULL` | Entrada a almacén o salida |
| `motivo` | `VARCHAR(100)` | `NULL` | Compra, instalacion, reparacion, baja, devolucion, etc. |
| `fecha` | `TIMESTAMP` | `NOT NULL`, `USE_CURRENT`, `INDEX` | Fecha y hora exacta del movimiento |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | Auditoría temporal de Laravel |

#### 10. `instalaciones` *(corregida)*
| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | `PK`, `AUTO_INCREMENT` | Identificador único |
| `contrato_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> contratos.id` | Contrato donde se instala |
| `equipo_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> equipos.id` | Equipo instalado |
| `usuario_id` | `BIGINT UNSIGNED` | `NULL`, `FK -> usuarios.id` | Trabajador que registró la instalación |
| `fecha_instalacion` | `DATE` | `NOT NULL` | Fecha de instalación (sin tilde) |
| `fecha_retiro` | `DATE` | `NULL` | Fecha de retiro si se reemplaza o da de baja |
| `activo` | `BOOLEAN` | `NOT NULL`, `DEFAULT 1`, `INDEX` | `true` si sigue instalado actualmente en el domicilio |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | Auditoría temporal de Laravel |

#### 11. `reclamos`
| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | `PK`, `AUTO_INCREMENT` | Identificador único |
| `cliente_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> clientes.id` | Cliente reclamante |
| `contrato_id` | `BIGINT UNSIGNED` | `NULL`, `FK -> contratos.id` | Servicio afectado (opcional) |
| `usuario_registro_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> usuarios.id` | Trabajador que abrió el reclamo |
| `tecnico_id` | `BIGINT UNSIGNED` | `NULL`, `FK -> usuarios.id` | Trabajador asignado a la revisión |
| `motivo` | `VARCHAR(255)` | `NOT NULL` | Motivo del reclamo |
| `diagnostico` | `ENUM('pendiente','falla_confirmada','sin_falla')` | `NOT NULL`, `DEFAULT 'pendiente'` | Resultado técnico |
| `estado` | `ENUM('abierto','en_proceso','resuelto')` | `NOT NULL`, `DEFAULT 'abierto'`, `INDEX` | Estado del reclamo |
| `fecha_registro` | `DATE` | `NOT NULL` | Fecha de registro |
| `fecha_atencion` | `DATE` | `NULL` | Fecha de cierre técnico |
| `observaciones` | `VARCHAR(500)` | `NULL` | Detalle de la revisión técnica (nulo al crear) |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | Auditoría temporal de Laravel |

#### 12. `auditoria` *(corregida)*
| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | `PK`, `AUTO_INCREMENT` | Identificador único |
| `usuario_id` | `BIGINT UNSIGNED` | `NULL`, `FK -> usuarios.id` | Trabajador (`NULL` si proviene de cron/Artisan/seeder) |
| `accion` | `ENUM('crear','actualizar','anular')` | `NOT NULL` | Operación ejecutada |
| `tabla_afectada` | `VARCHAR(50)` | `NOT NULL`, `INDEX(tabla_afectada, registro_id)` | Tabla afectada |
| `registro_id` | `BIGINT UNSIGNED` | `NOT NULL` | ID del registro afectado |
| `detalle` | `LONGTEXT` | `NOT NULL` | JSON con `antes` y `despues` (excluye `password`) |
| `fecha` | `TIMESTAMP` | `NOT NULL`, `USE_CURRENT`, `INDEX` | Marca de tiempo exacta |

#### 13. `notificaciones` *(corregida)*
| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | `PK`, `AUTO_INCREMENT` | Identificador único |
| `pago_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> pagos.id` | Pago vencido que originó la alerta |
| `tipo` | `VARCHAR(30)` | `NOT NULL`, `DEFAULT 'vencimiento'`, `UNIQUE(pago_id, tipo)` | Impide duplicar alertas de vencimiento para un mismo pago |
| `mensaje` | `VARCHAR(200)` | `NOT NULL` | Texto de la alerta |
| `estado` | `ENUM('pendiente','vista')` | `NOT NULL`, `DEFAULT 'pendiente'`, `INDEX` | Estado de lectura |
| `fecha_generacion` | `TIMESTAMP` | `NOT NULL`, `USE_CURRENT` | Fecha y hora de emisión |

#### 14. `conteos_inventario` *(nueva tabla recomendada para RF09 y RF10)*
| Campo | Tipo | Restricciones | Descripción |
|---|---|---|---|
| `id` | `BIGINT UNSIGNED` | `PK`, `AUTO_INCREMENT` | Identificador único |
| `usuario_id` | `BIGINT UNSIGNED` | `NOT NULL`, `FK -> usuarios.id` | Trabajador que realizó el conteo físico |
| `total_sistema` | `INT UNSIGNED` | `NOT NULL` | Equipos `disponible` en sistema al momento del conteo |
| `total_fisico` | `INT UNSIGNED` | `NOT NULL` | Series distintas contadas físicamente |
| `coincidentes` | `INT UNSIGNED` | `NOT NULL` | Series presentes en ambos conjuntos |
| `verificados` | `INT UNSIGNED` | `NOT NULL` | Total de series distintas en la unión de ambos conjuntos |
| `exactitud` | `DECIMAL(5,2)` | `NOT NULL` | `coincidentes / verificados * 100` |
| `discrepancias` | `LONGTEXT` | `NOT NULL` | JSON con `sobrantes_fisicos` y `faltantes_sistema` |
| `fecha` | `TIMESTAMP` | `NOT NULL`, `USE_CURRENT`, `INDEX` | Fecha y hora del conteo |
| `created_at` / `updated_at` | `TIMESTAMP` | `NULL` | Auditoría temporal de Laravel |

### 2.4 Actualizaciones obligatorias en el Diagrama ER del Word (Anexo 2, B.2)
Actualizar la figura del Diagrama ER en `PA2_TDI_CutipaPerez.docx` para corregir:
- **Relaciones faltantes/corregidas:**
  - Agregar relación `CATALOGO_SERVICIOS (1) ── (N) CONTRATOS` (`catalogo_id`).
  - Marcar `VENTA_DETALLE (0..1) ── (0..1) CONTRATOS` (`venta_detalle_id` nullable y único).
  - Agregar relación `USUARIOS (1) ── (N) CONTEOS_INVENTARIO` (si se aprueba la 14.ª tabla).
  - Eliminar la doble relación `realiza`/`registra` entre `USUARIOS` y `VENTAS` (dejar una sola `1:N`).
  - Agregar `USUARIOS (0..1) ── (N) AUDITORIA` (`usuario_id` nullable).
- **Columnas omitidas en la imagen actual:** `usuarios.password`, `usuarios.activo`, `clientes.fecha_registro`, `catalogo_servicios.segmento`, `catalogo_servicios.velocidad_mbps`, `ventas.estado`, `venta_detalle.subtotal`, `contratos.fecha_inicio`, `reclamos.usuario_registro_id`, `reclamos.fecha_registro`, `reclamos.fecha_atencion`, `reclamos.observaciones`, `auditoria.registro_id`, `auditoria.detalle`, `auditoria.fecha`, `notificaciones.fecha_generacion`, más los nuevos campos de ajuste (`periodo`, `motivo`, `fecha_retiro`, etc.).

---

## 3. Requerimientos funcionales y diseño de interfaz (Anexo 2)

### 3.1 Corrección de redacción en RF09 (Anexo 2, A.2)
- **Texto actual en Word:** *"El sistema debe calcular la exactitud del inventario **a partir de los movimientos registrados**."*
- **Texto corregido para el Word:** *"El sistema debe calcular la exactitud del inventario **a partir de un conteo físico** de los equipos disponibles en almacén."*

### 3.2 Ampliación del alcance de RF14 (Anexo 2, A.2)
- **Texto actual en Word:** Menciona solo *"ventas, pagos, cambios de estado de contrato o reclamo"*.
- **Texto corregido para el Word:** Incluir explícitamente *"ventas, pagos, cambios de estado de contrato o reclamo, catálogo, clientes y equipos"*.

### 3.3 Coherencia entre maquetas (Figuras 17–23) y producto construido (Anexo 2, B.3)
- **Figura 18 (Ventas y clientes):** Muestra estado por cliente y retención (`94%`), cuando en el modelo el estado pertenece al `contrato` (`activo/suspendido/baja`).
- **Figura 19 (Cobranzas):** Muestra botón `Reporte PDF`; el prototipo entrega reporte en pantalla y consulta por API.
- **Figura 20 (Inventario):** Muestra un ítem medido por metros (`Cable coaxial RG-6 (m)`), mientras el modelo `equipos` controla unidades físicas con número de serie único (`serie`).
- **Figura 21 (Catálogo):** Muestra estados `"Revisar duplicado"` y `"Validar precio"` en tabla; en el sistema el campo en BD es booleano (`activo`) y los posibles duplicados se entregan en CSV en `storage/`.
- **Menú superior (Figuras 18–23):** La aplicación unificará las 6 pestañas principales (`Dashboard`, `Ventas y clientes`, `Cobranzas`, `Inventario`, `Catálogo`, `Reclamos`) en todas las pantallas.

---

## 4. Cronograma, evidencias y resultados de pruebas (Capítulo III y Anexo 1)

### 4.1 Incongruencia de fechas en Tabla 8 vs. Tabla 6 y Anexo 1
- **En Tabla 6 (3.3) y Tabla 10 (Anexo 1):** los sprints van del **19 de octubre al 15 de noviembre de 2026**.
- **Incongruencia en Tabla 8 (3.8):** figuran fechas del `02/10/2026` al `14/10/2026` (anteriores al inicio del Sprint 1) y el marcador global sigue en blanco (`___ de los 22 casos de prueba...`).
- **Actualización requerida:** reemplazar por las fechas reales de ejecución de pruebas en noviembre de 2026 (y alinear la Tabla 6 / Tabla 10 si se ajusta el calendario), completar la Tabla 9 y llenar el resultado global tras ejecutar los 4 sprints.

### 4.2 Evidencias gráficas por reemplazar (Sección 3.7)
- Reemplazar los marcadores de las **Figuras 1 a 16** (y quitar el texto `"Colocar imagen aquí..."` de **3.7.6**) con las capturas reales generadas al cierre de cada sprint y del despliegue.

---

## 5. Checklist de aprobación antes de ejecutar el Sprint 1

- [x] Entorno WSL verificado (`Ubuntu 24.04.4 LTS`, `PHP 8.3.6`, `Apache 2.4.58`, `MariaDB 10.11.14`, `Node v20.20.2 / npm 10.8.2`, `Composer 2.7.1`, `Git 2.43.0`).
- [ ] Aprobar la estructura corregida de la BD (**14 tablas** con `conteos_inventario`, `contratos.catalogo_id`, `pagos.periodo`, `timestamps`, sin `ñ` ni tildes, y `ON DELETE RESTRICT`).
- [ ] Indicar usuario/contraseña de MariaDB en WSL para `.env` y `.env.testing` (BD `cablegest` y `cablegest_test`).
- [ ] Autorizar la ejecución de los comandos iniciales del **Sprint 1** (`composer create-project`, `jwt-auth`, `maatwebsite/excel`, Vite/Vue 3, migraciones y pruebas).
