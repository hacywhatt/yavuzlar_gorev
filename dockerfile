
FROM php:8.2-apache

WORKDIR /var/www/html


RUN apt-get update && apt-get install -y \
    libsqlite3-dev \
    libzip-dev \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libicu-dev \
    zip \
    unzip \
  # Kurulumdan sonra APT önbelleğini temizleyerek imaj boyutunu küçültüyoruz
  && rm -rf /var/lib/apt/lists/*


RUN docker-php-ext-configure gd --with-freetype --with-jpeg \
  && docker-php-ext-install -j$(nproc) gd pdo pdo_sqlite zip intl

RUN a2enmod rewrite

COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

COPY composer.json composer.lock ./

RUN composer install --no-interaction --no-dev --optimize-autoloader

COPY . /var/www/html/

RUN chown -R www-data:www-data /var/www/html/var


CMD ["apache2-foreground"]
