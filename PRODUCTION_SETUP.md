# 🚀 Инструкция по установке на production сервер

## Система учета продаж по промокодам

### 📋 Требования к серверу

- **PHP**: 7.4 или выше
- **SQLite**: 3.x
- **Веб-сервер**: Apache/Nginx
- **Расширения PHP**:
  - pdo_sqlite
  - mbstring
  - zip
  - xml

### 📦 Установка

#### 1. Загрузка файлов

Загрузите все файлы проекта на сервер через FTP/SFTP.

```bash
# Структура проекта
/var/www/html/reprocrm/
├── database/
│   ├── reprocrm.db
│   └── schema.sqlite.sql
├── src/
├── vendor/
├── uploads/
├── logs/
├── *.php
└── composer.json
```

#### 2. Установка зависимостей

```bash
cd /var/www/html/reprocrm
php composer.phar install --no-dev --optimize-autoloader
```

Или если composer установлен глобально:

```bash
composer install --no-dev --optimize-autoloader
```

#### 3. Настройка прав доступа

```bash
# Права на директории
chmod 755 database/
chmod 755 uploads/
chmod 755 logs/

# Права на базу данных
chmod 664 database/reprocrm.db

# Права на папки для записи
chmod 775 uploads/
chmod 775 logs/
```

#### 4. Настройка веб-сервера

**Apache (.htaccess):**

Создайте файл `.htaccess` в корне проекта:

```apache
RewriteEngine On

# Перенаправление на HTTPS (опционально)
# RewriteCond %{HTTPS} off
# RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]

# Скрыть служебные файлы
<FilesMatch "\.(md|json|lock|env|sql|log)$">
    Order allow,deny
    Deny from all
</FilesMatch>

# Защита папки vendor
<Directory vendor>
    Order allow,deny
    Deny from all
</Directory>

# Защита папки database
<Directory database>
    Order allow,deny
    Deny from all
</Directory>

# Защита папки logs
<Directory logs>
    Order allow,deny
    Deny from all
</Directory>
```

**Nginx:**

```nginx
server {
    listen 80;
    server_name your-domain.com;
    root /var/www/html/reprocrm;
    index index.php;

    # Скрыть служебные файлы
    location ~ \.(md|json|lock|env|sql|log)$ {
        deny all;
    }

    # Защита папок
    location ~ ^/(vendor|database|logs)/ {
        deny all;
    }

    # PHP обработка
    location ~ \.php$ {
        fastcgi_pass unix:/var/run/php/php7.4-fpm.sock;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
    }
}
```

#### 5. Настройка конфигурации

Скопируйте `.env.example` в `config.env` и настройте:

```bash
cp config.env.example config.env
```

Отредактируйте `config.env`:

```env
APP_NAME="Система учета продаж"
DB_TYPE=sqlite
DB_PATH=database/reprocrm.db
JWT_SECRET=your_secure_random_string_here
```

#### 6. Инициализация базы данных

Если база данных пустая или не создана:

```bash
sqlite3 database/reprocrm.db < database/schema.sqlite.sql
```

#### 7. Создание администратора

Выполните SQL для создания первого администратора:

```sql
INSERT INTO admins (email, password_hash, created_at, updated_at)
VALUES (
    'admin@yourdomain.com',
    '$2y$10$YourPasswordHashHere',
    datetime('now'),
    datetime('now')
);
```

Для генерации пароля:

```php
<?php
echo password_hash('your_secure_password', PASSWORD_DEFAULT);
?>
```

### 🔒 Безопасность

1. **Измените JWT_SECRET** в `config.env` на случайную строку
2. **Создайте сильный пароль** для администратора
3. **Настройте HTTPS** на production
4. **Ограничьте доступ** к служебным файлам
5. **Регулярно делайте backup** базы данных

### 📊 Структура доступов

#### Администратор

- ✅ Управление промокодами
- ✅ Загрузка файлов с продажами
- ✅ Просмотр отчетов врачей
- ✅ Выдача призов
- ✅ Управление сотрудниками

#### Врач (медработник)

- ✅ Регистрация по промокоду
- ✅ Просмотр своих продаж
- ✅ Редактирование профиля
- ✅ История выдачи призов

### 🔄 Workflow

1. **Администратор создает промокоды** (массово)
2. **Загружает CSV файлы** с продажами
3. **Врачи регистрируются** по промокодам
4. **Врачи видят** свою статистику
5. **Администратор выдает призы** врачам

### 📁 Формат CSV файла

```csv
Промокод,Продукт,Дата,Продажи
TEST0001,Продукт А,2025-10-08,5
TEST0002,Продукт Б,2025-10-08,3
```

**Колонки:**
- **Промокод** (8 символов) - обязательно
- **Продукт** - название продукта - обязательно
- **Дата** - дата продажи (YYYY-MM-DD) - по умолчанию сегодня
- **Продажи** (или Количество) - количество продаж (суммируется)

### 🛠️ Обслуживание

#### Backup базы данных

```bash
# Создание backup
cp database/reprocrm.db backups/reprocrm_$(date +%Y%m%d_%H%M%S).db

# Восстановление
cp backups/reprocrm_20251008_120000.db database/reprocrm.db
```

#### Очистка логов

```bash
# Очистка старых логов (старше 30 дней)
find logs/ -name "*.log" -mtime +30 -delete
```

#### Очистка временных файлов

```bash
# Удаление старых загруженных файлов
find uploads/ -type f -mtime +7 -delete
```

### 📝 Важные файлы

- `database/reprocrm.db` - база данных SQLite
- `config.env` - конфигурация приложения
- `logs/file_upload.log` - логи загрузки файлов
- `uploads/` - временные загруженные файлы

### 🔗 Основные URL

- `/` - главная страница
- `/admin.php` - вход администратора
- `/doctor.php` - вход врача
- `/doctor_register.php` - регистрация врача
- `/promo_codes.php` - управление промокодами (админ)
- `/file_upload.php` - загрузка файлов (админ)
- `/users_report.php` - отчет пользователей (админ)

### ⚡ Production оптимизации

В `php.ini`:

```ini
upload_max_filesize = 10M
post_max_size = 10M
max_execution_time = 300
memory_limit = 256M
```

### 📞 Поддержка

При возникновении проблем проверьте:
1. Логи веб-сервера
2. Логи PHP (error_log)
3. Файл `logs/file_upload.log`
4. Права доступа к файлам и папкам

---

**Версия:** 1.0  
**Дата:** Октябрь 2025





