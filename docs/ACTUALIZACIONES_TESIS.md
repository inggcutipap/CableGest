# Actualizaciones y alineación de la tesis (`PA2_TDI_CutipaPerez.docx`) con CableGest

Este documento consolida todas las actualizaciones, correcciones técnicas y datos empíricos necesarios para alinear el informe de tesis de pregrado (**`PA2_TDI_CutipaPerez.docx`**) con la implementación real y verificada de **CableGest**, desarrollada para **Corporación Global de Telecomunicaciones S.A.C. (Cable Visión)** en Mollendo y Congata (Arequipa, Perú).

---

## 1. Resumen ejecutivo de la implementación (Estado al cierre de los 4 Sprints)

El desarrollo del software se ejecutó al 100% cubriendo todos los requerimientos funcionales (`RF01` a `RF14`), requerimientos no funcionales (`RNF01` a `RNF10`) y los 22 casos de prueba automatizados (`CP01` a `CP22`).

| Parámetro | Estado en el Word inicial | Estado real implementado y verificado |
|---|---|---|
| **Arquitectura** | Monolito proyectado | Desacoplada: Backend API REST (Laravel 12 / PHP 8.3) + Frontend SPA (Vue 3 / Vite / Tailwind CSS) |
| **Tablas en Base de Datos** | 13 tablas (con inconsistencias) | **14 tablas optimizadas** en MariaDB 10.11.14 (incorporada `conteos_inventario`) |
| **Casos de prueba automatizados** | Marcadores en blanco (`___ de 22`) | **35 pruebas automatizadas pasando (235 aserciones, 0 fallos)** cubriendo `CP01`–`CP22` |
| **Datos migrados de `vision2026`** | No cuantificados | **40** ítems catálogo, **13,322** clientes, **11,352** contratos y **11,551** pagos históricos |
| **Entorno de desarrollo** | Laragon / Windows | **WSL2 Ubuntu 24.04.4 LTS** (paridad nativa con Ubuntu Server 24.04 LTS) |
| **Servidor web de producción** | Nginx / PHP-FPM o Apache | **Apache 2.4.58** en local/WSL y guía de despliegue preparada para **Nginx + PHP 8.3-FPM** (`docs/DESPLIEGUE.md`) |
| **Control de versiones** | No documentado | Repositorio Git local en rama `main` con historial trazable en español |

---

## 2. Entorno tecnológico verificado (Capítulo III, Sección 3.2)

### 2.1 Versiones exactas del entorno de desarrollo y construcción
Actualizar la tabla de la **Sección 3.2 (*Entorno de construcción*)** con las siguientes especificaciones verificadas en el entorno WSL Ubuntu 24.04:

- **Sistema Operativo:** Ubuntu 24.04.4 LTS (GNU/Linux x86_64 bajo WSL2)
- **Lenguaje Backend:** PHP 8.3.6 (cli) (NTS) con extensiones `pdo_mysql`, `mbstring`, `xml`, `zip`, `gd`, `intl`, `curl`, `openssl`
- **Gestor de dependencias PHP:** Composer 2.7.1
- **Framework Backend:** Laravel Framework 12.73.0 (modo API REST)
- **Base de Datos:** MariaDB 10.11.14 (`10.11.14-MariaDB-0ubuntu0.24.04.1`)
- **Entorno de ejecución Frontend:** Node.js v20.20.2 y npm 10.8.2
- **Framework Frontend:** Vue.js 3.5.13 (Composition API) con Vite 7.3.8
- **Diseño y estilos:** Tailwind CSS v4.2.1
- **Control de versiones:** Git 2.43.0

### 2.2 Entorno de despliegue en servidor (Sección 3.2 y Anexo 1, Fase 3)
- **Servidor:** Ubuntu Server 24.04 LTS
- **Servidor Web y Proxy:** Nginx 1.24+ configurado como proxy inverso y servidor de estáticos del frontend (`/dist`) y enrutador a PHP-FPM (`/api`), con certificado SSL/TLS con SAN para IP pública (`RNF08`).
- **Procesador de aplicaciones:** PHP 8.3-FPM.
- **Base de Datos:** MariaDB 10.11 con enlace exclusivo a `127.0.0.1` (`bind-address = 127.0.0.1`, `RNF10`).
- **Seguridad perimetral:** UFW con solo puertos 22 (SSH), 80 (HTTP) y 443 (HTTPS) abiertos.
- **Respaldos:** Script automatizado `scripts/respaldo_cablegest.sh` con retención de 14 días y transferencia externa vía `rsync` (`RNF09`).

---

## 3. Estructura final y análisis crítico de la base de datos (Anexo 2, B.2)

### 3.1 Las 7 correcciones estructurales aplicadas al modelo original

1. **Desacoplamiento de contratos y ventas (`contratos`):**
   - *Problema original:* `venta_detalle_id` era `NOT NULL` y no existía `catalogo_id`. Imposibilitaba migrar contratos históricos desde VB6 sin inventar ventas ficticias y no permitía facturar cobros mensuales en contratos migrados.
   - *Solución implementada:* `venta_detalle_id` es `NULLABLE` y único; se agregó `catalogo_id` (`FK -> catalogo_servicios.id`, `NOT NULL`) y `codigo_contrato` (`VARCHAR(20)`, `UNIQUE`) para preservar el código heredado (`Z01-...`).
2. **Persistencia de conteos físicos de inventario (`conteos_inventario` - Tabla 14):**
   - *Problema original:* `RF09` y `RF10` exigían *"exactitud del inventario (último cálculo)"*, pero ninguna tabla almacenaba los conteos. El porcentaje se hubiera perdido en memoria.
   - *Solución implementada:* Creación de la 14.ª tabla `conteos_inventario` (`total_sistema`, `total_fisico`, `coincidentes`, `verificados`, `exactitud`, `discrepancias` en JSON, `fecha`).
3. **Control estricto de ciclo de cobro y prevención de doble facturación (`pagos`):**
   - *Problema original:* No existía columna `periodo` ni restricción única contra doble facturación mensual; faltaba el estado `anulado` y trazabilidad del medio de pago.
   - *Solución implementada:* Se añadió `periodo` (`CHAR(7)`, formato `AAAA-MM`) con restricción `UNIQUE(contrato_id, periodo)`, estado `anulado`, y los campos `usuario_cobro_id`, `metodo_pago` y `observaciones`.
4. **Trazabilidad de auditoría para procesos en consola (`auditoria`):**
   - *Problema original:* `usuario_id` era `NOT NULL`. Comandos en segundo plano (migración, cron de vencimientos `pagos:marcar-vencidos`) rompían la integridad referencial.
   - *Solución implementada:* `usuario_id` es `NULLABLE` (`ON DELETE RESTRICT`), `detalle` almacena `antes` y `despues` en formato JSON (`LONGTEXT`), e índices en `(tabla_afectada, registro_id)` y `fecha`.
5. **Historial y estado de equipos en campo (`movimientos_inventario`, `instalaciones`):**
   - *Problema original:* La fecha de movimiento era `DATE` (sin hora, impidiendo ordenar entradas/salidas del mismo día) y no había motivo; `instalaciones` no distinguía si un equipo seguía en el domicilio o fue retirado por cambio técnico.
   - *Solución implementada:* `movimientos_inventario.fecha` pasó a `TIMESTAMP` con columna `motivo`. En `instalaciones` se agregaron `fecha_retiro`, `activo` (`BOOLEAN`) y `usuario_id`.
6. **Integridad de alertas y anulación transaccional (`notificaciones`, `ventas`):**
   - *Problema original:* `notificaciones` permitía duplicar alertas para un mismo pago vencido; anular una venta no definía el destino del contrato.
   - *Solución implementada:* Se agregó `tipo` con restricción `UNIQUE(pago_id, tipo)`. En `ventas`, la anulación pasa automáticamente sus contratos a `baja`, bloqueando la anulación con HTTP 422 si el contrato ya registra pagos cobrados.
7. **Normalización técnica y eliminación de caracteres especiales:**
   - *Problema original:* Nombres y valores de enum con `ñ`, tildes y espacios (`contraseña_hash`, `"en reparación"`).
   - *Solución implementada:* Strict ASCII en identificadores de BD, modelos y enums (`password`, `disponible`, `instalado`, `en_reparacion`, `baja`). Claves foráneas con `ON DELETE RESTRICT` y `ON UPDATE CASCADE`.

---

### 3.2 Diccionario de datos final (14 tablas normalizadas)

```
1.  usuarios (id, nombre, correo, password, rol, activo, created_at, updated_at)
2.  clientes (id, nombres, dni [CHAR(8) UNIQUE], direccion, telefono, fecha_registro, created_at, updated_at)
3.  catalogo_servicios (id, codigo_interno [UNIQUE], nombre, tipo, segmento, velocidad_mbps, precio, activo, created_at, updated_at)
4.  ventas (id, cliente_id, usuario_id, fecha_venta, monto_total, estado, motivo_anulacion, created_at, updated_at)
5.  venta_detalle (id, venta_id, catalogo_id, cantidad, precio_unitario, subtotal, created_at, updated_at)
6.  contratos (id, codigo_contrato [UNIQUE], cliente_id, catalogo_id, venta_detalle_id [UNIQUE NULLABLE], fecha_inicio, estado, created_at, updated_at)
7.  pagos (id, contrato_id, periodo [AAAA-MM], monto, fecha_vencimiento, fecha_pago, estado, usuario_cobro_id, metodo_pago, observaciones, created_at, updated_at) [UNIQUE(contrato_id, periodo)]
8.  equipos (id, catalogo_id, marca, serie [UNIQUE], estado, created_at, updated_at)
9.  movimientos_inventario (id, equipo_id, usuario_id, tipo_movimiento, motivo, fecha, created_at, updated_at)
10. instalaciones (id, contrato_id, equipo_id, usuario_id, fecha_instalacion, fecha_retiro, activo, created_at, updated_at)
11. reclamos (id, cliente_id, contrato_id, usuario_registro_id, tecnico_id, motivo, diagnostico, estado, fecha_registro, fecha_atencion, observaciones, created_at, updated_at)
12. auditoria (id, usuario_id [NULLABLE], accion, tabla_afectada, registro_id, detalle, fecha)
13. notificaciones (id, pago_id, tipo, mensaje, estado, fecha_generacion) [UNIQUE(pago_id, tipo)]
14. conteos_inventario (id, usuario_id, total_sistema, total_fisico, coincidentes, verificados, exactitud, discrepancias, fecha, created_at, updated_at)
```

---

## 4. Datos reales migrados desde la base de datos heredada (`vision2026`)

Para sustentar el volumen de datos en la tesis y demostrar la viabilidad de la solución con información de producción de Cable Visión, se ejecutó la migración mediante el comando `php artisan vision2026:migrar`:

| Entidad CableGest | Tabla origen `vision2026` | Registros migrados exitosamente | Registros omitidos / rechazados | Criterio de validación y depuración |
|---|---|---|---|---|
| **Catálogo de servicios** | `mitm` | **40 ítems** (39 activos, 1 inactivo) | 0 | Clasificación automática por palabras clave (`plan_internet`, `plan_tv`, `plan_combo`, `cargo_unico`, `equipo`). Detección de duplicados en `storage/catalogo_duplicados.csv`. |
| **Clientes** | `ccte` | **13,322 clientes** | 310 rechazados | Validación estricta de DNI numérico de 8 dígitos (conservando ceros a la izquierda). Rechazo de códigos antiguos o RUCs en `storage/vision2026_rechazados.csv`. Cumplimiento de la Ley N.° 29733. |
| **Contratos** | `regcontratos` | **11,352 contratos** (2,185 activos, 9,167 suspendidos) | 155 rechazados | Vinculación por DNI y código interno de plan (`S0000001` a `S0000032`). Asignación de código correlativo `tnumcontrato`. |
| **Recibos / Cobranzas** | `regrecibos` | **11,551 recibos** (7,199 pagados, 4,139 vencidos, 213 pendientes) | 217 rechazados | Extracción de recibos pendientes (`PEN`/`AMR`) con saldo real (`tsaldo`) e historial pagado (`CAN`) desde 2026. Importe total pagado migrado: S/ 264,532.00; importe vencido: S/ 219,996.00. |
| **Usuarios iniciales** | Semilla (`UsuarioSeeder`) | **3 usuarios** (Admin, Operador, Técnico) | — | Contraseñas con hash bcrypt; base para la asignación de roles. |

---

## 5. Resultados de pruebas automatizadas para la Tabla 8 del Word (Sección 3.8)

En la **Tabla 8 (*Resultados de las pruebas funcionales del software*)** de la tesis, se deben reemplazar los marcadores vacíos con la ejecución automatizada ejecutada con `php artisan test`:

- **Comando oficial de ejecución:** `php artisan test`
- **Total de pruebas ejecutadas:** **35 pruebas (100% pasando)**
- **Total de aserciones validadas:** **235 aserciones**
- **Pruebas fallidas o con error:** **0**
- **Tiempo de ejecución suite completa:** **3.77 segundos**

### Detalle de los 22 Casos de Prueba Oficiales (CP01 a CP22)

| ID | Módulo | RF | Caso de prueba ejecutado | Método de prueba en backend | Resultado |
|---|---|---|---|---|---|
| **CP01** | Autenticación | RF01 | Iniciar sesión con credenciales válidas | `Sprint1Test::test_cp01_iniciar_sesion_con_credenciales_validas` | **Conforme (Token emitido)** |
| **CP02** | Autenticación | RF01 | Iniciar sesión con credenciales inválidas | `Sprint1Test::test_cp02_iniciar_sesion_con_credenciales_invalidas` | **Conforme (401 Error)** |
| **CP03** | Autenticación | RF01 | Pedir un recurso protegido sin token | `Sprint1Test::test_cp03_pedir_recurso_protegido_sin_token` | **Conforme (401 No autorizado)** |
| **CP04** | Clientes | RF02 | Registrar cliente con DNI nuevo | `Sprint1Test::test_cp04_registrar_cliente_con_dni_nuevo` | **Conforme (201 Creado)** |
| **CP05** | Clientes | RF02 | Registrar cliente con DNI existente | `Sprint1Test::test_cp05_registrar_cliente_con_dni_existente` | **Conforme (422 Duplicado)** |
| **CP06** | Catálogo | RF13 | Desactivar un ítem del catálogo | `Sprint1Test::test_cp06_desactivar_item_del_catalogo` | **Conforme (Baja lógica)** |
| **CP07** | Ventas | RF03 | Venta con plan, instalación y equipo | `Sprint2Test::test_cp07_venta_con_plan_instalacion_y_equipo_calcula_tres_lineas_y_total` | **Conforme (Total exacto)** |
| **CP08** | Ventas | RF03 | Venta de un plan genera contrato | `Sprint2Test::test_cp08_venta_de_un_plan_genera_contrato_asociado` | **Conforme (Contrato activo)** |
| **CP09** | Cobranzas | RF04 | Registrar pago de un contrato | `Sprint3Test::test_cp09_registrar_pago_de_contrato_marca_pagado_con_fecha` | **Conforme (Estado pagado)** |
| **CP10** | Cobranzas | RF05 | Dejar vencer pago sin registrarlo | `Sprint3Test::test_cp10_dejar_vencer_pago_genera_vencido_y_notificacion_sin_duplicar` | **Conforme (Alerta generada)** |
| **CP11** | Cobranzas | RF04 | Estado de cuenta de un cliente | `Sprint3Test::test_cp11_estado_cuenta_cliente_calcula_pagos_y_saldo_pendiente` | **Conforme (Saldo consolidado)** |
| **CP12** | Cobranzas | RF06 | Reporte de morosidad | `Sprint3Test::test_cp12_reporte_morosidad_lista_solo_contratos_con_pagos_vencidos` | **Conforme (Solo morosos)** |
| **CP13** | Inventario | RF07 | Entrada de equipo con serie nueva | `Sprint4Test::test_cp13_entrada_equipo_con_serie_nueva_crea_disponible_y_movimiento` | **Conforme (Disponible + mov)** |
| **CP14** | Inventario | RF07 | Entrada de equipo con serie repetida | `Sprint4Test::test_cp14_equipo_con_serie_repetida_es_rechazado` | **Conforme (422 Serie única)** |
| **CP15** | Inventario | RF11 | Instalar equipo en un contrato | `Sprint4Test::test_cp15_instalar_equipo_en_contrato_pasa_a_instalado_y_descuenta_stock` | **Conforme (Instalado, stock −1)** |
| **CP16** | Inventario | RF08 | Consultar stock por tipo de equipo | `Sprint4Test::test_cp16_consultar_stock_por_tipo_cuenta_solo_equipos_disponibles` | **Conforme (Calculado dinámico)** |
| **CP17** | Inventario | RF09 | Exactitud con conteo físico | `Sprint4Test::test_cp17_exactitud_conteo_fisico_calcula_coincidentes_sobre_verificados` | **Conforme (Fórmula exacta)** |
| **CP18** | Reclamos | RF12 | Registrar reclamo y asignar técnico | `Sprint4Test::test_cp18_registrar_reclamo_y_asignar_tecnico` | **Conforme (Abierto → En proceso)** |
| **CP19** | Reclamos | RF12 | Registrar diagnóstico y cerrar reclamo | `Sprint4Test::test_cp19_registrar_diagnostico_y_cerrar_reclamo` | **Conforme (Resuelto + atención)** |
| **CP20** | Auditoría | RF14 | Crear/modificar venta o pago genera log | `Sprint2Test/Sprint3Test/Sprint4Test (test_cp20)` | **Conforme (Bitácora JSON)** |
| **CP21** | Dashboard | RF10 | Indicadores clave del dashboard | `Sprint4Test::test_cp21_dashboard_indicadores_coinciden_con_consultas_directas` | **Conforme (100% coincidencia)** |
| **CP22** | Migración | — | Cargar clientes depurados desde Excel | `Sprint2Test::test_cp22_cargar_clientes_depurados_desde_excel_sin_duplicados` | **Conforme (Sin duplicados)** |

*Párrafo sugerido de cierre de la Sección 3.8 en el Word:*
> "Se ejecutaron los 22 casos de prueba funcionales programados mediante pruebas automatizadas de integración en Laravel (`tests/Feature`), obteniendo un resultado de **22 de los 22 casos conformes (100% de éxito)**. Adicionalmente, se incluyeron 13 pruebas complementarias de lógica unitaria, control de tasa de intentos (rate limiting), cierre de sesión JWT e importadores masivos, totalizando 35 pruebas automatizadas y 235 aserciones sin ningún fallo."

---

## 6. Guía para la actualización de Figuras y Evidencias (Sección 3.7 y Anexo 2)

Las 13 capturas obligatorias definidas en la especificación se corresponden exactamente con las pantallas construidas en el frontend (`http://localhost:5173`):

| N.° Captura | Módulo | Ruta en el navegador | Qué debe mostrar la captura | Figura correspondiente en el Word |
|---|---|---|---|---|
| **(1)** | Backend | Terminal WSL | Salida de `php artisan serve` y `php artisan test` pasando al 100% | Figura 1 (Sección 3.7.1) |
| **(2)** | Base de datos | Terminal / DBeaver | Listado de las 14 tablas en MariaDB (`SHOW TABLES;`) | Figura 2 (Sección 3.7.1) |
| **(3)** | Catálogo | `/catalogo` | Listado con los 40 ítems (Planes Solo Internet, TV, Dúo y Equipos) | Figura 3 (Sección 3.7.2) / Figura 21 |
| **(4)** | Autenticación | Postman / `/login` | Petición `POST /api/login` devolviendo token Bearer JWT | Figura 4 (Sección 3.7.2) |
| **(5)** | Pruebas | Terminal WSL | Resumen de ejecución verde: `Tests: 35 passed (235 assertions)` | Figura 5 (Sección 3.7.3) |
| **(6)** | Ventas | `/ventas` | Formulario con 3 líneas (Plan + Instalación + Equipo) y contrato generado | Figura 6 (Sección 3.7.3) / Figura 18 |
| **(7)** | Clientes | `/clientes` y `/contratos` | Clientes y contratos migrados desde `vision2026` | Figura 7 (Sección 3.7.3) |
| **(8)** | Cobranzas | `/cobranzas` | Pestaña *Estado de cuenta por cliente* y pestaña *Reporte de morosidad* | Figura 8 (Sección 3.7.4) / Figura 19 |
| **(9)** | Alertas | `/alertas` | Bandeja de notificaciones de vencimiento generadas automáticamente | Figura 9 (Sección 3.7.4) |
| **(10)** | Inventario | `/inventario` | Pestaña *Stock por tipo* y cálculo de *Conteo físico y exactitud* | Figura 10 (Sección 3.7.5) / Figura 20 |
| **(11)** | Reclamos | `/reclamos` | Reclamo en proceso con técnico asignado y modal de diagnóstico técnico | Figura 11 (Sección 3.7.5) / Figura 22 |
| **(12)** | Auditoría | `/auditoria` | Bitácora de eventos con visor modal de cambios en JSON (`antes`/`despues`) | Figura 12 (Sección 3.7.6) |
| **(13)** | Dashboard | `/dashboard` | Tarjetas de indicadores gerenciales y ranking Top 5 ítems más vendidos | Figura 13 (Sección 3.7.6) / Figura 17 |

---

## 7. Cambios en la redacción de Requerimientos Funcionales (Anexo 2, A.2)

1. **RF09 (Cálculo de exactitud del inventario):**
   - *Texto actual:* *"El sistema debe calcular la exactitud del inventario a partir de los movimientos registrados."*
   - *Cambio en Word:* *"El sistema debe calcular la exactitud del inventario a partir de un **conteo físico** de los equipos en almacén, contrastando las series físicas contra los equipos registrados en estado disponible mediante la fórmula $\text{Exactitud (\%)} = \frac{\text{Coincidentes}}{\text{Verificados}} \times 100$."*
2. **RF14 (Bitácora de auditoría):**
   - *Texto actual:* Menciona solo ventas, pagos y contratos.
   - *Cambio en Word:* Ampliar la redacción para indicar: *"El sistema debe registrar en una bitácora inmutable de auditoría las operaciones críticas (creación, actualización y anulación) realizadas sobre clientes, catálogo de servicios, ventas, contratos, cobranzas, equipos e inventario, y reclamos técnicos, guardando usuario, marca de tiempo y estado anterior y nuevo en formato JSON."*

---

## 8. Guía de despliegue y respaldo diario generados (Documentos de soporte)

Se generaron dos artefactos técnicos en el repositorio listos para la fase de implementación final descrita en el Capítulo IV de la tesis:
- **`docs/DESPLIEGUE.md`:** Manual operativo detallado de instalación en Ubuntu Server 24.04 LTS con Apache/Nginx, configuración segura de MariaDB local, certificados HTTPS por dirección IP pública, reglas de cortafuegos UFW y cron del scheduler de Laravel.
- **`scripts/respaldo_cablegest.sh`:** Script en Bash con permisos de ejecución que genera volcados lógicos comprimidos (`mysqldump + gzip -9`) con aislamiento seguro de credenciales, rotación automática de 14 días y soporte de réplica remota vía `rsync` (`RNF09`).
