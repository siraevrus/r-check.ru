# 🚀 Инструкция по установке ReproCRM

## Быстрая установка

### Вариант 1: Веб-установка (рекомендуется)

1. **Загрузите проект** на веб-сервер
2. **Откройте в браузере** `http://ваш-домен/install.php`
3. **Следуйте инструкциям** мастера установки

Мастер установки автоматически:
- ✅ Проверит все системные требования
- ✅ Создаст необходимые директории
- ✅ Настроит конфигурацию
- ✅ Инициализирует базу данных SQLite
- ✅ Создаст учетную запись администратора

### Вариант 2: Консольная установка

```bash
# 1. Клонируйте репозиторий
git clone <repository-url>
cd reprocrm

# 2. Установите зависимости Composer
composer install
# или если composer не установлен глобально:
php composer.phar install

# 3. Создайте необходимые директории
mkdir -p database logs uploads backups config
chmod 755 database logs uploads backups

# 4. Скопируйте конфигурацию
cp config.env.example config.env

# 5. Отредактируйте config.env (установите JWT_SECRET и другие параметры)
nano config.env

# 6. Инициализируйте базу данных
sqlite3 database/reprocrm.db < database/schema.sqlite.sql

# 7. Запустите локальный сервер
php -S localhost:8000
```

## Системные требования

### Обязательные:
- **PHP** >= 7.4
- **Расширения PHP:**
  - PDO
  - PDO SQLite
  - mbstring
  - OpenSSL
  - JSON
- **Composer** для установки зависимостей

### Права доступа:
Следующие директории должны быть доступны для записи:
- `database/` - для SQLite базы данных
- `logs/` - для логов приложения
- `uploads/` - для загружаемых файлов
- `backups/` - для резервных копий
- Корневая директория - для создания `config.env`

## Данные для входа по умолчанию

После установки через `install.php` вы получите данные для входа.

Если вы устанавливали вручную, используйте:
- **Email:** `admin@reprosystem.ru`
- **Пароль:** `admin123`

⚠️ **ВАЖНО:** Обязательно измените пароль после первого входа!

## Настройка для production

### 1. Удалите install.php
```bash
rm install.php
```

### 2. Настройте config.env
```env
APP_ENV=production
APP_DEBUG=false
```

### 3. Настройте веб-сервер

#### Apache (.htaccess уже включен в проект)
```apache
<IfModule mod_rewrite.c>
    RewriteEngine On
    RewriteCond %{REQUEST_FILENAME} !-f
    RewriteCond %{REQUEST_FILENAME} !-d
    RewriteRule ^(.*)$ index.php [QSA,L]
</IfModule>
```

#### Nginx
```nginx
server {
    listen 80;
    server_name ваш-домен.ru;
    root /path/to/reprocrm;
    index index.php;

    location / {
        try_files $uri $uri/ /index.php?$query_string;
    }

    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }

    # Запретить доступ к служебным файлам
    location ~ /\. {
        deny all;
    }
    
    location ~ /config\.env {
        deny all;
    }
    
    location ~ /database/ {
        deny all;
    }
}
```

### 4. Настройте права доступа
```bash
# Установите владельца (замените www-data на вашего пользователя веб-сервера)
chown -R www-data:www-data /path/to/reprocrm

# Установите права
find /path/to/reprocrm -type d -exec chmod 755 {} \;
find /path/to/reprocrm -type f -exec chmod 644 {} \;

# Дополнительные права для записи
chmod 775 database logs uploads backups
```

### 5. Настройте HTTPS (Let's Encrypt)
```bash
# Установите Certbot
apt-get install certbot python3-certbot-apache
# или для nginx:
apt-get install certbot python3-certbot-nginx

# Получите сертификат
certbot --apache -d ваш-домен.ru
# или для nginx:
certbot --nginx -d ваш-домен.ru
```

## Локальная разработка

### Быстрый старт
```bash
# Запуск встроенного PHP сервера
php -S localhost:8000

# Или использование готового скрипта
bash run_local.sh
```

### Использование Docker
```bash
# Запуск с Docker Compose
docker-compose up -d

# Остановка
docker-compose down
```

## Проверка установки

После установки проверьте:

1. ✅ Главная страница загружается: `http://ваш-домен/`
2. ✅ Вход в систему работает
3. ✅ Панель администратора доступна
4. ✅ Можно создавать промокоды
5. ✅ Загрузка файлов работает

## Резервное копирование

### Автоматическое (через веб-интерфейс)
1. Войдите как администратор
2. Перейдите в раздел "Резервное копирование"
3. Нажмите "Создать резервную копию"

### Ручное
```bash
# Создание резервной копии
cp database/reprocrm.db backups/reprocrm_$(date +%Y%m%d_%H%M%S).db

# Восстановление из резервной копии
cp backups/reprocrm_20240101_120000.db database/reprocrm.db
```

## Обновление системы

```bash
# 1. Создайте резервную копию
cp database/reprocrm.db backups/backup_before_update.db

# 2. Получите последние изменения
git pull origin main

# 3. Обновите зависимости
composer install

# 4. Проверьте работоспособность
# Если что-то пошло не так - восстановите из резервной копии
```

## Устранение неполадок

### База данных не создается
```bash
# Проверьте права доступа
ls -la database/
# Должно быть: drwxrwxr-x

# Установите права
chmod 775 database
```

### Ошибки PHP
```bash
# Проверьте установленные расширения
php -m | grep -E 'pdo|sqlite|mbstring|openssl|json'

# Установите недостающие (Ubuntu/Debian)
apt-get install php-sqlite3 php-mbstring php-json
```

### Ошибка "Composer не найден"
```bash
# Используйте локальный composer.phar
php composer.phar install

# Или установите глобально
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer
```

### Проблемы с загрузкой файлов
```bash
# Проверьте права
ls -la uploads/
chmod 775 uploads/

# Проверьте настройки PHP
php -i | grep upload_max_filesize
php -i | grep post_max_size
```

## Поддержка

Если у вас возникли вопросы или проблемы:

1. Проверьте логи: `logs/`
2. Просмотрите документацию: `USER_GUIDE.md`, `ADMIN_GUIDE.md`
3. Проверьте системные требования выше

## Безопасность

⚠️ **ВАЖНО для production:**

1. ✅ Удалите `install.php` после установки
2. ✅ Измените пароль администратора
3. ✅ Установите `APP_DEBUG=false` в `config.env`
4. ✅ Используйте HTTPS
5. ✅ Настройте регулярное резервное копирование
6. ✅ Ограничьте доступ к `config.env` и `database/` через веб-сервер
7. ✅ Используйте сильные пароли
8. ✅ Регулярно обновляйте систему

---

**Готово!** Ваша система ReproCRM установлена и готова к работе! 🎉











