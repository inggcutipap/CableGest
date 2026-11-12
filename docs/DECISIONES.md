# Decisiones técnicas — CableGest

Registro breve de las decisiones arquitectónicas y de diseño tomadas durante el desarrollo.

## Sprint 1 — Base, catálogo y clientes

1. **Paridad de entorno en WSL2 Ubuntu 24.04:** Se reemplaza la referencia a Laragon por WSL2 Ubuntu 24.04 con PHP 8.3.6, Apache 2.4.58, MariaDB 10.11.14 y Node.js 20 LTS para igualar exactamente el servidor de producción.
2. **Nomenclatura ASCII sin `ñ` ni tildes en esquema y código:** Columnas y estados se definen en `snake_case` sin caracteres especiales (`password`, `direccion`, `telefono`, `fecha_instalacion`, `en_reparacion`, `en_proceso`) para evitar errores de codificación o mapeo en Eloquent.
3. **Estructura corregida de 14 tablas y claves `RESTRICT`:** Se añade `catalogo_id` en `contratos` (con `venta_detalle_id` nullable) para permitir la migración desde VB6 y cobros mensuales, `periodo` único en `pagos` para evitar doble cobro, `conteos_inventario` para persistir RF09/RF10, `timestamps` estándar y `ON DELETE RESTRICT` en todas las FKs.
4. **Eliminación de tablas boilerplate de Laravel (`users`, `cache`, `jobs`):** Se usa `CACHE_STORE=file` y `SESSION_DRIVER=file` para mantener el esquema de MariaDB limpio con exactamente las 14 tablas del modelo de CableGest.
5. **JWT con `php-open-source-saver/jwt-auth` v2.10:** Verificado compatible con Laravel 12; configurado en el guard `api` con `Usuario` (`JWTSubject`), lista negra habilitada para `/logout` y TTL configurable (`480` min por turno).
6. **Limitación de intentos de login y manejo JSON global:** El endpoint `POST /api/login` usa `throttle:login` (5 intentos/minuto por correo+IP) y `bootstrap/app.php` devuelve respuestas JSON uniformes (`401`, `404`, `422`, `429`) sin redirecciones ni volcado de PII en logs (Ley N.° 29733).
7. **Importador idempotente `catalogo:importar`:** Usa `maatwebsite/excel` v4.0 manteniendo ceros a la izquierda, clasifica por palabras clave normalizadas (fallback `cargo_unico`), conserva desactivaciones manuales en re-importaciones y exporta los grupos con igual nombre normalizado a `storage/catalogo_duplicados.csv`.
8. **Observer reutilizable de auditoría (`AuditoriaObserver`):** Permite `usuario_id = null` en consola/seeders, excluye `password` y timestamps de ruido, y registra `antes`/`despues` en JSON sobre `auditoria`.
9. **Frontend SPA en `frontend/` con Vue 3 + Vite 7 + Tailwind CSS v4:** Configurado sin librerías de interfaz externas, usando proxy `/api` en desarrollo y respetando la paleta `#1e3a5f` y `#f5f4ee` de las maquetas; CORS restringido a `FRONTEND_URL`.
10. **Exclusión de binarios y datos sensibles en `.gitignore`:** Se excluyen `.env`, `.env.testing`, `*.xlsx`, `*.xls`, `*.docx`, `*.sql`, `*.csv` en `storage/` y `frontend/dist` para no versionar datos reales ni credenciales.

## Sprint 2 — Ventas, contratos y migración de cartera

11. **Transacción atómica en ventas (`VentaService`):** Toda venta valida ítems activos, congela `precio_unitario` del catálogo, calcula `subtotal` y `monto_total`, y crea automáticamente un `Contrato` (`CG-NNNNNN`, `estado = activo`) por cada línea de tipo plan (`plan_internet`, `plan_tv`, `plan_combo`) dentro de `DB::transaction`.
12. **Regla de anulación lógica alineada al sistema VB6 (`frmContratos.frm`):** Al anular una venta (`estado = anulada`), sus contratos asociados pasan a `baja`; sin embargo, si alguno ya tiene pagos no anulados registrados (equivalente a `regrecibosdet` en VB6), la anulación se bloquea con HTTP 422 y tampoco se permite reactivar contratos de ventas anuladas.
13. **Auditoría en ventas y contratos:** Se registra `AuditoriaObserver` sobre `Venta` y `Contrato`, guardando acción `crear`, `actualizar` y `anular` (cuando `ventas.estado` pasa a `anulada`) con detalle `antes`/`despues`.
14. **Importador Excel de cartera (`cartera:importar`):** Valida DNI numérico de 8 dígitos, rechaza duplicados dentro del mismo archivo (y filas con plan inexistente), opera de forma repetible (`updateOrCreate`) y genera el reporte `storage/cartera_rechazados.csv` sin exponer datos personales en consola.
15. **Extractor temporal desde `vision2026` (`vision2026:migrar`) y mejora del clasificador:** Lee en modo solo lectura desde variables `LEGACY_DB_*`, amplía `CatalogoClasificador` para reconocer velocidades en `MG`/`MB` y planes `DUO`, migra en lotes idempotentes sin eventos masivos de auditoría (respetando Ley N.° 29733), y registra en `storage/vision2026_rechazados.csv` los registros con RUC o códigos distintos de 8 dígitos.


