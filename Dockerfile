FROM php:8.2-apache

# Instalar extensiones para conectar con MySQL
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Activar mod_rewrite de Apache
RUN a2enmod rewrite

# Configurar el puerto de Render
RUN sed -i 's/Listen 80/Listen 10000/' /etc/apache2/ports.conf \
    && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:10000>/' /etc/apache2/sites-available/000-default.conf

# Copiar los archivos del proyecto
COPY . /var/www/html/

# Permisos de Apache
RUN chown -R www-data:www-data /var/www/html

EXPOSE 10000

CMD ["apache2-foreground"]