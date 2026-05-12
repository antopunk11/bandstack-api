# Usamos una imagen oficial de PHP con Apache
FROM php:8.2-apache

# Instalamos las extensiones de MySQL necesarias
RUN docker-php-ext-install pdo pdo_mysql

# Copiamos el código de tu API a la carpeta del servidor
COPY . /var/www/html/

# Damos permisos para que Apache pueda leer los archivos
RUN chown -R www-data:www-data /var/www/html

# Exponemos el puerto 80
EXPOSE 80
