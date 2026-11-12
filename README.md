# CableGest

Aplicación web para la gestión de ventas, clientes, cobranzas, inventario y reclamos de **Corporación Global de Telecomunicaciones S.A.C. (Cable Visión)** — Mollendo y Congata, Arequipa.

---

## Requisitos del entorno (WSL Ubuntu 24.04 / Servidor Ubuntu 24.04 LTS)

- **PHP:** 8.3 (`cli`, `mysql`, `mbstring`, `xml`, `zip`, `gd`, `intl`, `curl`, `openssl`)
- **Composer:** 2.7+
- **Base de datos:** MariaDB 10.11 (`10.11.14`)
- **Servidor web (producción):** Apache 2.4.58 (`rewrite`, `headers`)
- **Node.js / npm (via NVM):** Node 20 LTS (`v20.20.2`) y npm 10+

Si aún no preparaste tu entorno WSL Ubuntu 24.04, revisa [docs/CONFIGURACION_WSL.md](docs/CONFIGURACION_WSL.md).

---

## Instalación en desarrollo (Ubuntu 24.04 / WSL2)

### 1. Backend (Laravel 12)
```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Edita `.env` con tus credenciales locales de MariaDB (`DB_DATABASE=cablegest`, `DB_USERNAME=cablegest`, `DB_PASSWORD=...`) y el origen del frontend (`FRONTEND_URL=http://localhost:5173`).

### 2. Migraciones y usuario inicial
```bash
php artisan migrate --seed
```

Credenciales del administrador semilla (`UsuarioSeeder`):
- **Correo:** `admin@cablevision.pe`
- **Contraseña temporal:** `CableGest2026!` *(configurable antes del seed mediante `ADMIN_SEED_PASSWORD` en `.env`; cámbiala en producción)*.

### 3. Carga del catálogo desde Excel
El archivo `.xlsx` debe tener las columnas `internal_id`, `description` y `sale_unit_price` (no lo subas a Git):
```bash
php artisan catalogo:importar /ruta/al/catalogo.xlsx
```
- Conserva ceros a la izquierda en `codigo_interno` y es repetible sin duplicar registros.
- No desactiva automáticamente posibles duplicados: genera un informe en `storage/catalogo_duplicados.csv` para revisión manual.

### 3.1 Carga de clientes y contratos (Excel o migración `vision2026`)
- **Desde Excel/CSV depurado:**
  ```bash
  php artisan cartera:importar /ruta/al/cartera.xlsx
  ```
  Valida DNI numérico de 8 dígitos sin duplicados, vincula contratos con `catalogo_servicios` y exporta filas rechazadas a `storage/cartera_rechazados.csv`.
- **Migración temporal desde `vision2026` (solo lectura):** mediante variables de entorno `LEGACY_DB_HOST`, `LEGACY_DB_USER`, `LEGACY_DB_PASS` y `LEGACY_DB_NAME`, ejecuta `php artisan vision2026:migrar` para extraer `mitm`, `ccte` y `regcontratos` dejando constancia de rechazados en `storage/vision2026_rechazados.csv`.

### 4. Frontend (Vue.js 3 + Vite + Tailwind CSS)
```bash
cd frontend
cp .env.example .env
npm install
```

---

## Cómo correr el sistema en desarrollo

En una **única terminal WSL** (dejando el prompt libre para otros comandos):

```bash
cd /var/www/html/CableGest
php artisan serve > /tmp/laravel.log 2>&1 &
npm --prefix frontend run dev > /tmp/vite.log 2>&1 &
```

Para apagar ambos servidores:
```bash
pkill -f "php artisan serve"; pkill -f "127.0.0.1:8000"; pkill -f "vite"
```

Accede desde el navegador a `http://localhost:5173`.

---

## Pruebas automatizadas

Las pruebas usan una base de datos MariaDB separada (`cablegest_test`, configurada en `phpunit.xml`). Antes de ejecutarlas por primera vez, copia `.env.testing.example` a `.env.testing` y coloca tu `DB_PASSWORD`:

```bash
cp .env.testing.example .env.testing
php artisan test
```

Para ejecutar únicamente las pruebas del Sprint 1, Sprint 2 o Sprint 3:
```bash
php artisan test tests/Feature/Sprint1Test.php
php artisan test tests/Feature/Sprint2Test.php
php artisan test tests/Feature/Sprint3Test.php
```

Comandos operativos de cobranzas (Sprint 3):
```bash
php artisan pagos:generar-mes
php artisan pagos:marcar-vencidos
php artisan recibos:importar ruta/al/archivo.xlsx
```
