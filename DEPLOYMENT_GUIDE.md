# 🚀 Инструкция по деплою ReproCRM на сервер

## 📋 Подготовка к деплою

### ✅ Что уже сделано:
1. ✅ Сохранены все изменения в git
2. ✅ Удален тестовый файл
3. ✅ Создан архив `reprocrm_deploy.tar.gz` для деплоя

### 📦 Архив готов к загрузке
Файл `reprocrm_deploy.tar.gz` содержит все необходимые файлы проекта, исключая:
- `.git/` - git репозиторий
- `vendor/` - зависимости Composer (установятся на сервере)
- `uploads/`, `logs/`, `backups/` - временные файлы
- `database/*.db` - база данных (создастся на сервере)
- `config.env` - конфигурация (создастся на сервере)

## 🖥️ Инструкция по установке на сервер

### Шаг 1: Загрузка файлов


```bash
# Загрузите архив на сервер
scp reprocrm_deploy.tar.gz user@your-server:/var/www/html/

# Или через FTP/SFTP загрузите файл reprocrm_deploy.tar.gz
```

### Шаг 2: Распаковка на сервере

```bash
# Подключитесь к серверу
ssh user@your-server

# Перейдите в директорию веб-сервера
cd /var/www/html/

# Распакуйте архив
tar -xzf reprocrm_deploy.tar.gz

# Создайте директорию для проекта
mkdir -p reprocrm
mv * reprocrm/ 2>/dev/null || true

# Перейдите в директорию проекта
cd reprocrm
```

### Шаг 3: Установка зависимостей

```bash
# Установите Composer (если не установлен)
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# Установите зависимости проекта
composer install --no-dev --optimize-autoloader

# Или используйте локальный composer.phar
php composer.phar install --no-dev --optimize-autoloader
```

### Шаг 4: Настройка прав доступа

```bash
# Создайте необходимые директории
mkdir -p database logs uploads backups config

# Установите права доступа
chmod 755 database logs uploads backups
chmod 644 *.php *.md

# Права для записи
chmod 775 database logs uploads backups
```

### Шаг 5: Настройка конфигурации

```bash
# Скопируйте пример конфигурации
cp config.env.example config.env

# Отредактируйте конфигурацию
nano config.env
```

**Настройте config.env:**
```env
# Основные настройки
APP_NAME="Система учета продаж"
APP_URL=https://your-domain.com
APP_ENV=production
APP_DEBUG=false

# База данных SQLite
DB_TYPE=sqlite
DB_PATH=database/reprocrm.db

# JWT секретный ключ (ОБЯЗАТЕЛЬНО измените!)
JWT_SECRET=your_very_secure_random_string_here_change_this_in_production

# Email настройки (для восстановления пароля)
MAIL_MAILER=smtp
MAIL_HOST=smtp.beget.com
MAIL_PORT=2525
MAIL_USERNAME=repro@dev-asgart.ru
MAIL_PASSWORD=e!ctgRVwdG7!
MAIL_ENCRYPTION=null
MAIL_FROM_ADDRESS=repro@dev-asgart.ru
MAIL_FROM_NAME="Система учета продаж"

# Старые настройки (для совместимости)
SMTP_HOST=smtp.beget.com
SMTP_PORT=2525
SMTP_USER=repro@dev-asgart.ru
SMTP_PASS=e!ctgRVwdG7!
SMTP_FROM=repro@dev-asgart.ru

# Безопасность
RATE_LIMIT_REQUESTS=100
RATE_LIMIT_WINDOW=3600
```

### Шаг 6: Инициализация базы данных

**База данных создается автоматически!** При первом обращении к системе:

1. ✅ Автоматически создается файл `database/reprocrm.db`
2. ✅ Выполняется схема из `database/schema.sqlite.sql`
3. ✅ Создается первый администратор
4. ✅ Добавляются тестовые данные

```bash
# Установите права на директорию базы данных
chmod 775 database/
```

### Шаг 7: Первый администратор

**Администратор создается автоматически!** 

- **Email:** `admin@reprosystem.ru`
- **Пароль:** `admin123`

⚠️ **ВАЖНО:** Обязательно измените пароль после первого входа!

### Шаг 8: Настройка веб-сервера

#### Apache (.htaccess)
Файл `.htaccess` уже включен в проект и содержит:

```apache
RewriteEngine On

# Скрытие расширения .php
RewriteCond %{REQUEST_FILENAME} !-d
RewriteCond %{REQUEST_FILENAME} !-f
RewriteRule ^([^\.]+)$ $1.php [NC,L]

# Перенаправление с .php на URL без расширения
RewriteCond %{THE_REQUEST} /([^.]+)\.php [NC]
RewriteRule ^ /%1? [NC,L,R=301]

# Скрыть служебные файлы
<FilesMatch "\.(md|json|lock|env|sql|log)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Защита папок
<Directory vendor>
    Order allow,deny
    Deny from all
</Directory>

<Directory database>
    Order allow,deny
    Deny from all
</Directory>

<Directory logs>
    Order allow,deny
    Deny from all
</Directory>
```

#### Nginx
Используйте файл `nginx.conf` из проекта или настройте:

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html/reprocrm;
    index index.php;

    # Скрытие расширения .php
    location ~ ^/([^/]+)/?$ {
        try_files $uri $uri.php $uri/ =404;
    }

    # PHP обработка
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php8.1-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }

    # Скрыть служебные файлы
    location ~ \.(md|json|lock|env|sql|log)$ {
        deny all;
    }

    # Защита папок
    location ~ /(vendor|database|logs|\.git|backups)/ {
        deny all;
    }

    # Основные правила
    location / {
        try_files $uri $uri.php $uri/ /index.php?$query_string;
    }
}
```

### Шаг 9: Настройка HTTPS (рекомендуется)

```bash
# Установите Certbot
apt-get update
apt-get install certbot python3-certbot-apache
# или для nginx:
apt-get install certbot python3-certbot-nginx

# Получите SSL сертификат
certbot --apache -d your-domain.com
# или для nginx:
certbot --nginx -d your-domain.com
```

### Шаг 9: Финальная настройка

```bash
# Установите владельца файлов
chown -R www-data:www-data /var/www/html/reprocrm

# Установите права
find /var/www/html/reprocrm -type d -exec chmod 755 {} \;
find /var/www/html/reprocrm -type f -exec chmod 644 {} \;

# Дополнительные права для записи
chmod 775 database logs uploads backups
```

## 🔒 Безопасность

### Обязательные действия:
1. ✅ **Измените JWT_SECRET** в config.env
2. ✅ **Создайте сильный пароль** для администратора
3. ✅ **Настройте HTTPS**
4. ✅ **Удалите install.php** после установки (если есть)
5. ✅ **Ограничьте доступ** к служебным файлам

### Дополнительные меры:
- Регулярно делайте backup базы данных
- Настройте мониторинг логов
- Обновляйте систему
- Используйте firewall

## 📊 Проверка установки

После установки проверьте:

1. ✅ **Главная страница:** `https://your-domain.com/`
2. ✅ **Вход администратора:** `https://your-domain.com/admin.php`
3. ✅ **Регистрация врача:** `https://your-domain.com/doctor_register.php`
4. ✅ **Панель администратора** доступна
5. ✅ **Загрузка файлов** работает

## 🛠️ Управление системой

### Backup базы данных:
```bash
cp database/reprocrm.db backups/reprocrm_$(date +%Y%m%d_%H%M%S).db
```

### Восстановление из backup:
```bash
cp backups/reprocrm_20250108_120000.db database/reprocrm.db
```

### Очистка логов:
```bash
find logs/ -name "*.log" -mtime +30 -delete
```

## 📞 Поддержка

При возникновении проблем проверьте:
1. Логи веб-сервера: `/var/log/apache2/error.log` или `/var/log/nginx/error.log`
2. Логи PHP: `php -i | grep error_log`
3. Логи приложения: `logs/file_upload.log`
4. Права доступа к файлам и папкам

## 🎉 Готово!

Ваша система ReproCRM установлена и готова к работе!

**Данные для входа:**
- **URL:** `https://your-domain.com/admin.php`
- **Email:** `admin@yourdomain.com`
- **Пароль:** `your_secure_password`

---

**Версия:** 1.0  
**Дата:** Январь 2025
