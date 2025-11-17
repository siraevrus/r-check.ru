# Чеклист для развертывания на сервере

## Файлы для загрузки на сервер

### 1. PHP файлы (уже в git, нужно обновить через git pull):
- ✅ `export_excel.php` - добавлена поддержка промокодов без пользователей и фильтрация
- ✅ `file_upload.php` - исправлена логика суммирования дубликатов в CSV
- ✅ `preview_upload.php` - исправлен SQL запрос просмотра загрузок
- ✅ `users_report.php` - добавлена поддержка промокодов без пользователей и фильтрация

### 2. Схемы базы данных (нужно закоммитить и загрузить):
- ⚠️ `database/schema.sql` - обновлена схема (разрешены NULL в promo_code_id)
- ⚠️ `database/schema.sqlite.sql` - обновлена схема (разрешены NULL в promo_code_id)

### 3. Файлы миграции (новые файлы, нужно закоммитить и загрузить):
- ⚠️ `database/migrate_mysql.sql` - миграция для MySQL
- ⚠️ `database/migrate_sqlite.sh` - скрипт миграции для SQLite
- ⚠️ `database/migration_allow_null_promo_code_id.sql` - SQL скрипт миграции

## Порядок действий для развертывания:

### Шаг 1: Закоммитить изменения схемы и миграции
```bash
git add database/schema.sql database/schema.sqlite.sql
git add database/migrate_mysql.sql database/migrate_sqlite.sh database/migration_allow_null_promo_code_id.sql
git commit -m "Обновлена схема БД: разрешены NULL в promo_code_id, добавлены скрипты миграции"
git push
```

### Шаг 2: На сервере - обновить код
```bash
cd /path/to/project
git pull origin main
```

### Шаг 3: На сервере - применить миграцию базы данных

**Для MySQL:**
```bash
mysql -u your_user -p your_database < database/migrate_mysql.sql
```

**Для SQLite:**
```bash
./database/migrate_sqlite.sh
```

**Или вручную через SQL:**
```sql
ALTER TABLE users MODIFY COLUMN promo_code_id INT NULL;
```

### Шаг 4: Проверить работу
1. Проверить отвязку пользователя от промокода
2. Проверить выгрузку отчетов с фильтрами
3. Проверить просмотр данных загрузок

## Важно:
- ⚠️ **Обязательно сделайте резервную копию базы данных перед применением миграции!**
- ⚠️ Файлы `otchet.csv` и `otchet.xlsx` - временные файлы, их НЕ нужно загружать на сервер

