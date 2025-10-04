FROM php:8.2-apache

# Installa le estensioni PHP necessarie
RUN apt-get update && apt-get install -y \
    libfreetype6-dev \
    libjpeg62-turbo-dev \
    libpng-dev \
    libzip-dev \
    libonig-dev \
    unzip \
    git \
    curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd \
    && docker-php-ext-install pdo pdo_mysql mysqli zip mbstring \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*

# Installa Composer
RUN curl -sS https://getcomposer.org/installer | php -- --install-dir=/usr/local/bin --filename=composer

# Installa FPDF e altre librerie PHP
RUN composer global require setasign/fpdf

# Abilita mod_rewrite per Apache
RUN a2enmod rewrite

# Installa Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Imposta la working directory
WORKDIR /var/www/html

# Copia i file dell'applicazione
COPY . .

# Installa le dipendenze PHP (commentato per evitare conflitti con Laravel)
# RUN composer install --no-dev --optimize-autoloader

# Configura Apache per servire da public/
RUN sed -i 's|/var/www/html|/var/www/html/public|g' /etc/apache2/sites-available/000-default.conf

# Crea le directory necessarie con i permessi corretti
RUN mkdir -p public/uploads public/qrcodes public/generated_images public/generated_pdfs \
    && chown -R www-data:www-data public/ \
    && chmod -R 755 public/

# Configura PHP per lo sviluppo
RUN echo "display_errors = On" >> /usr/local/etc/php/conf.d/docker-php-dev.ini \
    && echo "error_reporting = E_ALL" >> /usr/local/etc/php/conf.d/docker-php-dev.ini \
    && echo "upload_max_filesize = 50M" >> /usr/local/etc/php/conf.d/docker-php-dev.ini \
    && echo "post_max_size = 50M" >> /usr/local/etc/php/conf.d/docker-php-dev.ini

EXPOSE 80

CMD ["apache2-foreground"]
