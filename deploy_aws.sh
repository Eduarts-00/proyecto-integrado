#!/usr/bin/env bash
set -e

echo "============================================================"
echo "  Instalador Automático FarmaNet Pro — AWS Academy (EC2)    "
echo "============================================================"

export DEBIAN_FRONTEND=noninteractive
export HOME=/root
export COMPOSER_HOME=/root/.composer
export COMPOSER_ALLOW_SUPERUSER=1

# 1. Detectar sistema operativo (Ubuntu / Debian o Amazon Linux 2023)
if command -v apt-get >/dev/null 2>&1; then
    echo "[1/7] Actualizando paquetes e instalando Apache, MariaDB y PHP en Ubuntu/Debian..."
    apt-get update -y
    apt-get install -y software-properties-common curl git unzip zip acl mariadb-server apache2

    # Verificar versión de PHP disponible en apt (Laravel 11 requiere PHP >= 8.2)
    PHP_VER=$(apt-cache policy php | grep Candidate | grep -oE '[0-9]+\.[0-9]+' | head -n1 || echo "8.1")
    if [ "$PHP_VER" = "8.1" ] || [ "$PHP_VER" = "8.0" ] || [ "$PHP_VER" = "7.4" ]; then
        add-apt-repository ppa:ondrej/php -y
        apt-get update -y
        apt-get install -y php8.2 libapache2-mod-php8.2 php8.2-mysql php8.2-xml php8.2-mbstring php8.2-curl php8.2-zip php8.2-bcmath php8.2-intl
    else
        apt-get install -y php libapache2-mod-php php-mysql php-xml php-mbstring php-curl php-zip php-bcmath php-intl
    fi

    WEB_USER="www-data"
    APACHE_SERVICE="apache2"
elif command -v dnf >/dev/null 2>&1; then
    echo "[1/7] Instalando Apache, MariaDB y PHP 8.2 en Amazon Linux 2023..."
    dnf install -y git unzip zip mariadb105-server httpd php8.2 php8.2-mysqlnd php8.2-xml php8.2-mbstring php8.2-pecl-zip php8.2-bcmath php8.2-intl
    WEB_USER="apache"
    APACHE_SERVICE="httpd"
fi

# 2. Instalar Composer
if ! command -v composer >/dev/null 2>&1; then
    echo "[2/7] Instalando Composer..."
    curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer
fi

# 3. Iniciar y configurar MariaDB
echo "[3/7] Iniciando MariaDB y creando base de datos 'farmanet_pro'..."
systemctl enable mariadb
systemctl start mariadb

mysql -u root <<EOF
CREATE DATABASE IF NOT EXISTS farmanet_pro CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER IF NOT EXISTS 'farmanet'@'127.0.0.1' IDENTIFIED BY 'farmanet123';
CREATE USER IF NOT EXISTS 'farmanet'@'localhost' IDENTIFIED BY 'farmanet123';
GRANT ALL PRIVILEGES ON farmanet_pro.* TO 'farmanet'@'127.0.0.1';
GRANT ALL PRIVILEGES ON farmanet_pro.* TO 'farmanet'@'localhost';
FLUSH PRIVILEGES;
EOF

# 4. Clonar o actualizar el repositorio desde GitHub
APP_DIR="/var/www/farmanet"
echo "[4/7] Descargando proyecto desde GitHub en ${APP_DIR}..."
if [ -d "$APP_DIR/.git" ]; then
    cd "$APP_DIR"
    git fetch origin main
    git reset --hard origin/main
else
    rm -rf "$APP_DIR"
    git clone https://github.com/Eduarts-00/proyecto-integrado.git "$APP_DIR"
    cd "$APP_DIR"
fi

# 5. Configurar variables de entorno (.env)
echo "[5/7] Configurando archivo .env y dependencias de Laravel..."
PUBLIC_IP=$(curl -s --max-time 4 https://checkip.amazonaws.com | tr -d '[:space:]' || echo "localhost")
if [ -z "$PUBLIC_IP" ]; then
    PUBLIC_IP="localhost"
fi

cp .env.example .env
sed -i "s|^APP_NAME=.*|APP_NAME=\"FarmaNet Pro\"|g" .env
sed -i "s|^APP_ENV=.*|APP_ENV=production|g" .env
sed -i "s|^APP_DEBUG=.*|APP_DEBUG=false|g" .env
sed -i "s|^APP_TIMEZONE=.*|APP_TIMEZONE=America/Santiago|g" .env
sed -i "s|^APP_URL=.*|APP_URL=http://${PUBLIC_IP}|g" .env
sed -i "s|^DB_CONNECTION=.*|DB_CONNECTION=mysql|g" .env
sed -i "s|^#* *DB_HOST=.*|DB_HOST=127.0.0.1|g" .env
sed -i "s|^#* *DB_PORT=.*|DB_PORT=3306|g" .env
sed -i "s|^#* *DB_DATABASE=.*|DB_DATABASE=farmanet_pro|g" .env
sed -i "s|^#* *DB_USERNAME=.*|DB_USERNAME=farmanet|g" .env
sed -i "s|^#* *DB_PASSWORD=.*|DB_PASSWORD=farmanet123|g" .env
sed -i "s|^SESSION_DRIVER=.*|SESSION_DRIVER=file|g" .env
sed -i "s|^CACHE_STORE=.*|CACHE_STORE=file|g" .env

export COMPOSER_ALLOW_SUPERUSER=1
composer install --no-dev --optimize-autoloader --no-interaction

php artisan key:generate --force

# 6. Ejecutar migraciones y seeders (77 medicamentos, 145 lotes, 11 convenios, usuarios e ISP)
echo "[6/7] Ejecutando migraciones y cargando catálogo de farmacia privada..."
php artisan migrate:fresh --seed --force
php artisan config:clear
php artisan view:clear
php artisan route:clear

chown -R ${WEB_USER}:${WEB_USER} "$APP_DIR"
chmod -R 775 "$APP_DIR/storage" "$APP_DIR/bootstrap/cache"

# 7. Configurar VirtualHost en Apache (Puerto 80)
echo "[7/7] Configurando servidor web Apache en Puerto 80..."
if [ "$APACHE_SERVICE" = "apache2" ]; then
    cat >/etc/apache2/sites-available/farmanet.conf <<EOF
<VirtualHost *:80>
    DocumentRoot ${APP_DIR}/public
    <Directory ${APP_DIR}/public>
        AllowOverride All
        Require all granted
    </Directory>
    ErrorLog \${APACHE_LOG_DIR}/farmanet_error.log
    CustomLog \${APACHE_LOG_DIR}/farmanet_access.log combined
</VirtualHost>
EOF
    a2enmod rewrite
    a2dissite 000-default.conf || true
    a2ensite farmanet.conf
    systemctl enable apache2
    systemctl restart apache2
else
    cat >/etc/httpd/conf.d/farmanet.conf <<EOF
<VirtualHost *:80>
    DocumentRoot ${APP_DIR}/public
    <Directory ${APP_DIR}/public>
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
EOF
    systemctl enable httpd
    systemctl restart httpd
fi

echo "============================================================"
echo "  ✅ ¡FarmaNet Pro desplegado con éxito en AWS Academy!     "
echo "  🌐 Abre en tu navegador: http://${PUBLIC_IP}              "
echo "============================================================"
