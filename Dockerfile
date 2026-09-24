FROM php:8.3-apache

# Extensão SQLite (PDO) para o banco embutido — sem dependências externas
RUN docker-php-ext-install pdo_sqlite

ENV APACHE_DOCUMENT_ROOT=/var/www/html/public

RUN sed -ri -e 's!/var/www/html!${APACHE_DOCUMENT_ROOT}!g' /etc/apache2/sites-available/*.conf \
    && sed -ri -e 's!/var/www/!${APACHE_DOCUMENT_ROOT}/!g' /etc/apache2/apache2.conf /etc/apache2/conf-available/*.conf \
    && a2enmod rewrite headers

# Código da aplicação em /var/www/html (public/ é o document root)
COPY app/ /var/www/html/
RUN chown -R www-data:www-data /var/www/html/var

EXPOSE 80
