# 1. Usar la imagen oficial de PHP con Apache
FROM php:8.2-apache

# 2. Habilitar mod_rewrite y mod_headers (este último es clave para los CORS)
RUN a2enmod rewrite headers

# 3. Instalar las extensiones de MySQL para PDO
RUN docker-php-ext-install pdo pdo_mysql

# 4. Configurar Apache para que permita el uso de .htaccess (AllowOverride All)
RUN sed -i '/<Directory \/var\/www\/>/,/<\/Directory>/ s/AllowOverride None/AllowOverride All/' /etc/apache2/apache2.conf

# 5. Copiar tu código al contenedor
COPY . /var/www/html/

# 6. Ajustar permisos para que Apache pueda trabajar
RUN chown -R www-data:www-data /var/www/html/

EXPOSE 80
