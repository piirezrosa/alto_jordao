# Alto Jordão — PHP 8.3 + Apache
FROM php:8.3-apache

# Extensões usadas pelo projeto (curl, mbstring, openssl e iconv já vêm na imagem)
RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers remoteip \
 && sed -ri 's#AllowOverride None#AllowOverride All#' /etc/apache2/apache2.conf \
 && cp "$PHP_INI_DIR/php.ini-production" "$PHP_INI_DIR/php.ini"

# Ajustes de PHP (fuso, limites de upload, erros no log) e do Apache (IP real atrás do Caddy, bloqueio de .sql/.log etc.)
COPY docker/php/alto.ini /usr/local/etc/php/conf.d/zz-alto.ini
COPY docker/php/apache-alto.conf /etc/apache2/conf-available/alto.conf
RUN a2enconf alto

COPY code/ /var/www/html/

# Pastas em que o site grava (fotos de produtos/marcas/devoluções e relatórios criptografados)
RUN mkdir -p img/produtos img/marcas img/devolucoes relatorios_seguros \
 && chown -R www-data:www-data /var/www/html/img /var/www/html/relatorios_seguros

WORKDIR /var/www/html
EXPOSE 80
HEALTHCHECK --interval=30s --timeout=5s --start-period=20s --retries=3 \
  CMD php -r 'exit(@fsockopen("127.0.0.1",80)?0:1);'
