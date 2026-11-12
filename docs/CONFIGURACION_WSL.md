# Configuración del entorno WSL Ubuntu 24.04 (paridad con producción)

Objetivo: dejar tu entorno **WSL2 Ubuntu 24.04** idéntico al servidor de producción:
- **Sistema operativo:** Ubuntu 24.04 LTS
- **Servidor web:** Apache/2.4.58 (ya instalado)
- **PHP:** 8.3.6 + extensiones + Composer (ya instalados)
- **Base de datos:** `10.11.14-MariaDB-0ubuntu0.24.04.1` (reemplaza el `mysql-server-8.0` sin uso)
- **Frontend:** NVM + Node.js 20 LTS dentro de WSL

> Ejecuta estos comandos dentro de la terminal de **Ubuntu 24.04 (WSL)**.

---

## 1. Reemplazar MySQL 8.0 por MariaDB 10.11.14

> **Advertencia:** Este paso elimina `mysql-server-8.0` y sus archivos en `/var/lib/mysql`. Confirma antes que no tienes ninguna base de datos que necesites conservar.

```bash
# 1. Detener MySQL 8.0
sudo systemctl stop mysql

# 2. Desinstalar paquetes de MySQL 8.0 y limpiar residuos
sudo apt-get purge -y mysql-server mysql-server-8.0 mysql-server-core-8.0 mysql-client-8.0 mysql-client-core-8.0 mysql-common
sudo rm -rf /etc/mysql /var/lib/mysql
sudo apt-get autoremove -y

# 3. Actualizar índice de paquetes e instalar MariaDB 10.11
sudo apt-get update
sudo apt-get install -y mariadb-server mariadb-client

# 4. Habilitar e iniciar el servicio MariaDB
sudo systemctl enable --now mariadb

# 5. Verificar versión (debe mostrar 10.11.14-MariaDB-0ubuntu0.24.04.1)
mariadb --version
sudo systemctl status mariadb --no-pager
```

*(Opcional recomendado)* Asegura la instalación básica de MariaDB:
```bash
sudo mariadb-secure-installation
```
- Puedes aceptar acceso por socket unix para `root`, deshabilitar usuarios anónimos, deshabilitar acceso remoto de `root` y eliminar la base de datos `test`.

---

## 2. Crear bases de datos y usuario para CableGest

Crea una base de datos para desarrollo (`cablegest`) y otra separada para las pruebas automatizadas (`cablegest_test`), además de un usuario dedicado con permisos mínimos (solo esas dos bases de datos).

Reemplaza `TU_CLAVE_SEGURA` por la contraseña que elijas para desarrollo:

```bash
sudo mariadb << 'EOF'
CREATE DATABASE IF NOT EXISTS cablegest CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE DATABASE IF NOT EXISTS cablegest_test CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

CREATE USER IF NOT EXISTS 'cablegest'@'localhost' IDENTIFIED BY 'TU_CLAVE_SEGURA';
CREATE USER IF NOT EXISTS 'cablegest'@'127.0.0.1' IDENTIFIED BY 'TU_CLAVE_SEGURA';

GRANT ALL PRIVILEGES ON cablegest.* TO 'cablegest'@'localhost';
GRANT ALL PRIVILEGES ON cablegest.* TO 'cablegest'@'127.0.0.1';
GRANT ALL PRIVILEGES ON cablegest_test.* TO 'cablegest'@'localhost';
GRANT ALL PRIVILEGES ON cablegest_test.* TO 'cablegest'@'127.0.0.1';

FLUSH PRIVILEGES;
EOF
```

Verifica la conexión del usuario creado:
```bash
mariadb -u cablegest -p -h 127.0.0.1 -e "SHOW DATABASES;"
```
Y comprueba que MariaDB solo escucha en local (`127.0.0.1:3306`):
```bash
ss -tlnp | grep 3306
```

---

## 3. Utilidades básicas y verificación de PHP 8.3 + Apache 2.4.58

Ya tienes instalados `PHP 8.3.6`, `Composer 2.7.1`, `Apache/2.4.58`, `libapache2-mod-php8.3` y los módulos `rewrite` y `php`. Instala `unzip` y `curl` (necesarios para Composer y NVM) y habilita `headers` en Apache:

```bash
sudo apt-get install -y unzip curl ca-certificates
sudo a2enmod rewrite headers
sudo systemctl reload apache2
```

Verifica tus extensiones PHP requeridas por Laravel 12 y `maatwebsite/excel`:
```bash
php -m | grep -E "curl|gd|intl|mbstring|pdo_mysql|xml|zip|openssl"
```

---

## 4. Instalar NVM y Node.js 20 LTS en WSL

Instalar Node dentro de WSL evita errores con rutas UNC (`\\wsl.localhost\...`) y acelera la compilación de Vue.js 3 + Vite + Tailwind CSS:

```bash
# 1. Instalar NVM (revisa la última versión estable si deseas)
curl -o- https://raw.githubusercontent.com/nvm-sh/nvm/v0.40.1/install.sh | bash

# 2. Cargar NVM en la sesión actual (o cierra y reabre la terminal)
export NVM_DIR="$HOME/.nvm"
[ -s "$NVM_DIR/nvm.sh" ] && \. "$NVM_DIR/nvm.sh"

# 3. Instalar Node.js 20 LTS y fijarlo por defecto
nvm install 20
nvm alias default 20

# 4. Verificar
node -v
npm -v
```

---

## 5. Configurar identidad de Git (para commits del proyecto)

Si aún no tienes nombre y correo configurados para tus commits en Git dentro de WSL:

```bash
git config --global user.name "Tu Nombre Completo"
git config --global user.email "tu_correo@ejemplo.com"
```

---

## 6. Nota sobre Apache local vs. `php artisan serve` (se aplicará tras crear Laravel)

Durante el desarrollo de los sprints puedes trabajar cómodamente con:
- **Backend API:** `php artisan serve` (puerto `8000`)
- **Frontend Vite:** `npm run dev` dentro de `frontend/` (puerto `5173`)

Cuando quieras probar exactamente igual que en producción con **Apache 2.4.58** (o al finalizar el proyecto), crearemos un VirtualHost dedicado en `/etc/apache2/sites-available/cablegest.conf` (sin interferir con `000-default.conf`), apuntando `DocumentRoot` a `/var/www/html/CableGest/public` con `AllowOverride All`, y asignando permisos al grupo `www-data` sobre `storage/` y `bootstrap/cache/`.

---

## 7. Checklist final de comprobación

Ejecuta este bloque cuando termines los pasos anteriores y compárteme la salida si deseas validar que todo quedó listo:

```bash
echo "OS: $(lsb_release -ds)"
echo "PHP: $(php -v | head -n 1)"
echo "Composer: $(composer --version)"
echo "Apache: $(apache2 -v | head -n 1)"
echo "MariaDB: $(mariadb --version)"
echo "Node: $(node -v) / npm: $(npm -v)"
echo "Git: $(git --version) | $(git config user.name) <$(git config user.email)>"
```

