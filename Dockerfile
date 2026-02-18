FROM php:8.2-apache

# Установка системных зависимостей
RUN apt-get update && apt-get install -y \
    libzip-dev \
    zip \
    unzip \
    git \
    curl \
    libpng-dev \
    libonig-dev \
    libxml2-dev \
    default-mysql-client \
    && docker-php-ext-install pdo_mysql mbstring exif pcntl bcmath gd zip

# Установка Composer
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Включение mod_rewrite для Apache
RUN a2enmod rewrite

# Настройка Apache
RUN echo '<VirtualHost *:80>\n\
    DocumentRoot /var/www/html\n\
    <Directory /var/www/html>\n\
        AllowOverride All\n\
        Require all granted\n\
    </Directory>\n\
</VirtualHost>' > /etc/apache2/sites-available/000-default.conf

# Рабочая директория
WORKDIR /var/www/html

# Копирование файлов проекта
COPY . /var/www/html/

# Установка зависимостей
RUN composer install --no-dev --optimize-autoloader

# Настройка прав доступа
RUN chown -R www-data:www-data /var/www/html \
    && chmod -R 755 /var/www/html

# Открытие порта
EXPOSE 80

# Запуск Apache
CMD ["apache2-foreground"]
