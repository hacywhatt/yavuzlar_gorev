# 1. Temel PHP Görüntüsü (Apache web sunucusunu içerir)
# PHP 8.x versiyonu önerilir, burada php:8.2-apache kullanılmıştır.
FROM php:8.2-apache

# 2. Gerekli sistem paketlerini ve PHP uzantılarını yükle
# SQLite desteği için libsqlite3-dev ve PHP uzantısı gereklidir.
RUN apt-get update && \
    apt-get install -y --no-install-recommends \
    libsqlite3-dev \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# 3. PHP'de PDO ve SQLite uzantılarını aktif et
RUN docker-php-ext-install pdo_sqlite

# 4. Apache yeniden yazma (mod_rewrite) modülünü aktif et (Temiz URL'ler için gerekli olabilir)
RUN a2enmod rewrite

# 5. PHP'nin zaman dilimini ayarla (Tarih/Saat fonksiyonlarının doğru çalışması için kritik)
# Bu ayar, PHP içindeki date_default_timezone_set('Europe/Istanbul'); ile birlikte çalışmalıdır.
RUN echo "date.timezone = Europe/Istanbul" > /usr/local/etc/php/conf.d/timezone.ini

# 6. Proje dosyalarını Docker container'ının web kök dizinine kopyala
# Proje kök dizinindeki her şeyi (PHP dosyaları, database.sqlite vb.) kopyalar.
COPY . /var/www/html/

# 7. Dosya izinlerini ayarla
# Web sunucusu kullanıcısına (www-data) dosya sahipliği ver (Olası izin hatalarını önler)
RUN chown -R www-data:www-data /var/www/html

# Container'daki Apache sunucusu varsayılan olarak 80 portunda çalışacaktır.
EXPOSE 80
