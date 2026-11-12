# Guía de despliegue en producción — CableGest

Procedimiento de referencia para instalar y operar **CableGest** en **Ubuntu Server 24.04 LTS** con **Nginx**, **PHP 8.3-FPM**, **MariaDB 10.11**, **HTTPS por dirección IP pública**, **UFW** y **respaldos diarios fuera del servidor** (`RNF08`, `RNF09`, `RNF10`).

---

## 1. Preparación base del servidor y cortafuegos (`RNF10`)

```bash
sudo apt update && sudo apt upgrade -y
sudo apt install -y nginx mariadb-server ufw curl unzip git \
  php8.3-fpm php8.3-cli php8.3-mysql php8.3-mbstring \
  php8.3-xml php8.3-zip php8.3-gd php8.3-intl php8.3-curl
```

Habilitar solo SSH, HTTP y HTTPS en el cortafuegos:

```bash
sudo ufw default deny incoming
sudo ufw default allow outgoing
sudo ufw allow OpenSSH
sudo ufw allow 80/tcp
sudo ufw allow 443/tcp
sudo ufw enable
sudo ufw status
```

---

## 2. Base de datos MariaDB solo local (`RNF10`)

Verifica en `/etc/mysql/mariadb.conf.d/50-server.cnf` que MariaDB escuche únicamente en `127.0.0.1`:

```ini
bind-address = 127.0.0.1
```

Reinicia MariaDB y crea el esquema y usuario dedicados (reemplaza la clave por una segura):

```bash
sudo systemctl restart mariadb
sudo mariadb <<'SQL'
CREATE DATABASE cablegest CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'cablegest'@'127.0.0.1' IDENTIFIED BY 'REEMPLAZAR_CLAVE_FUERTE';
GRANT ALL PRIVILEGES ON cablegest.* TO 'cablegest'@'127.0.0.1';
FLUSH PRIVILEGES;
SQL
```

---

## 3. Código fuente, dependencias y compilación de frontend

Clona o despliega el repositorio en `/var/www/cablegest`:

```bash
cd /var/www/cablegest
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
php artisan jwt:secret
```

Configura `.env` para producción:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://TU_IP_PUBLICA

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=cablegest
DB_USERNAME=cablegest
DB_PASSWORD=REEMPLAZAR_CLAVE_FUERTE

FRONTEND_URL=https://TU_IP_PUBLICA
```

Ejecuta migraciones, siembra inicial y caché de configuración:

```bash
php artisan migrate --force --seed
php artisan config:cache
php artisan route:cache
```

Cambia de inmediato las contraseñas iniciales de `admin@cablevision.pe`, `operador@cablevision.pe` y `tecnico@cablevision.pe`.

Compila el frontend Vue 3 (`frontend/.env.production` o `VITE_API_URL=https://TU_IP_PUBLICA/api`):

```bash
cd /var/www/cablegest/frontend
npm ci
VITE_API_URL=https://TU_IP_PUBLICA/api npm run build
```

Asigna permisos al usuario web (`www-data`):

```bash
sudo chown -R www-data:www-data /var/www/cablegest/storage /var/www/cablegest/bootstrap/cache
```

---

## 4. Certificado HTTPS por IP pública y Nginx (`RNF08`)

Si utilizas certificado emitido para IP (o autofirmado con SAN para la IP mientras la autoridad certificadora emite el definitivo):

```bash
sudo mkdir -p /etc/ssl/cablegest
sudo openssl req -x509 -nodes -days 365 -newkey rsa:2048 \
  -keyout /etc/ssl/cablegest/cablegest.key \
  -out /etc/ssl/cablegest/cablegest.crt \
  -subj "/CN=TU_IP_PUBLICA" \
  -addext "subjectAltName=IP:TU_IP_PUBLICA"
```

Crea `/etc/nginx/sites-available/cablegest`:

```nginx
server {
    listen 80;
    server_name TU_IP_PUBLICA;
    return 301 https://$host$request_uri;
}

server {
    listen 443 ssl http2;
    server_name TU_IP_PUBLICA;

    ssl_certificate     /etc/ssl/cablegest/cablegest.crt;
    ssl_certificate_key /etc/ssl/cablegest/cablegest.key;
    ssl_protocols       TLSv1.2 TLSv1.3;

    add_header X-Frame-Options "SAMEORIGIN" always;
    add_header X-Content-Type-Options "nosniff" always;

    # Frontend SPA compilado
    root /var/www/cablegest/frontend/dist;
    index index.html;

    location / {
        try_files $uri $uri/ /index.html;
    }

    # Backend API Laravel
    location ^~ /api {
        alias /var/www/cablegest/public;
        try_files $uri $uri/ @laravel_api;
    }

    location @laravel_api {
        fastcgi_pass unix:/run/php/php8.3-fpm.sock;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME /var/www/cablegest/public/index.php;
        fastcgi_param SCRIPT_NAME /index.php;
        fastcgi_param REQUEST_URI $request_uri;
    }
}
```

Habilita el sitio y valida Nginx:

```bash
sudo ln -sf /etc/nginx/sites-available/cablegest /etc/nginx/sites-enabled/cablegest
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
```

---

## 5. Programador de tareas de Laravel (vencimientos y cobros)

Agrega al crontab de `www-data` (`sudo crontab -u www-data -e`):

```cron
* * * * * cd /var/www/cablegest && php artisan schedule:run >> /dev/null 2>&1
```

Esto ejecutará automáticamente:
- `pagos:marcar-vencidos` todos los días a las `00:30`.
- `pagos:generar-mes` el día `1` de cada mes a las `01:00`.

---

## 6. Respaldo diario fuera del servidor (`RNF09`)

1. Copia `scripts/respaldo_cablegest.sh` a `/usr/local/sbin/respaldo_cablegest.sh` y otorga permisos `0750`.
2. Configura las variables de respaldo en `/etc/cablegest-backup.env` (con permisos `0600` solo lectura para `root`):

```env
APP_DIR=/var/www/cablegest
BACKUP_DIR=/var/backups/cablegest
RETENCION_DIAS=14
DESTINO_REMOTO=usuario@IP_SERVIDOR_RESPALDOS:/respaldos/cablegest/
```

3. Programa el cron diario como `root` (`sudo crontab -e`) a las `02:00`:

```cron
0 2 * * * set -a && . /etc/cablegest-backup.env && set +a && /usr/local/sbin/respaldo_cablegest.sh >> /var/log/cablegest-backup.log 2>&1
```
