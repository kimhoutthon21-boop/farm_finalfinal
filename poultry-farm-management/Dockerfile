# PHP + Apache image for hosting on Render (Render has no native PHP runtime).
FROM php:8.2-apache

# MySQL driver for PDO
RUN docker-php-ext-install pdo_mysql

# Production PHP settings, Khmer/English text needs mbstring (built in), set upload/memory sane defaults
RUN mv "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Let the project's .htaccess files work (they block config/, includes/, database/),
# and make Apache listen on the port Render provides in $PORT (Apache expands ${PORT} at startup).
RUN sed -i 's/AllowOverride None/AllowOverride All/g' /etc/apache2/apache2.conf \
 && sed -i 's/Listen 80/Listen ${PORT}/' /etc/apache2/ports.conf \
 && sed -i 's/<VirtualHost \*:80>/<VirtualHost *:${PORT}>/' /etc/apache2/sites-available/000-default.conf \
 && a2enmod rewrite headers

ENV PORT=10000

COPY . /var/www/html/
RUN chown -R www-data:www-data /var/www/html

EXPOSE 10000
