# Alto Jordão — PHP 8.3 + Apache
FROM php:8.3-apache

# Extensões usadas pelo projeto (curl, mbstring, openssl e iconv já vêm na imagem)
RUN docker-php-ext-install pdo_mysql \
 && a2enmod rewrite headers \
 && sed -ri 's#AllowOverride None#AllowOverride All#' /etc/apache2/apache2.conf

COPY code/ /var/www/html/

# Pastas em que o site grava (fotos de produtos/marcas/devoluções e relatórios criptografados)
RUN mkdir -p img/produtos img/marcas img/devolucoes relatorios_seguros \
 && chown -R www-data:www-data /var/www/html/img /var/www/html/relatorios_seguros

WORKDIR /var/www/html
EXPOSE 80
