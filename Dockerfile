# Dockerfile untuk project gudang (Laravel 12 + MySQL + dompdf)
# Referensi struktur: 01.API-UJIKOM
FROM php:8.3-cli

# Extension yang dibutuhkan:
#  - pdo_mysql : koneksi MySQL
#  - mbstring  : wajib Laravel
#  - gd        : dompdf (render gambar/QR di PDF)
#  - zip       : composer
#  - bcmath    : perhitungan presisi
RUN apt-get update && apt-get install -y \
        libzip-dev libpng-dev libjpeg-dev libfreetype6-dev libonig-dev \
        zip unzip git curl \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install pdo pdo_mysql mbstring zip gd bcmath

# Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Direktori kerja
WORKDIR /var/www