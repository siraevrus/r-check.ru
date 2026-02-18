# Автоматический бэкап базы в ZIP по cron

Скрипт `scripts/backup_db_zip.php` создаёт резервную копию базы данных в ZIP и кладёт её в папку `backups/`.

- **SQLite**: копирует `database/reprocrm.db` в архив `reprocrm_backup_YYYY-MM-DD_HH-mm-ss.zip`.
- **MySQL**: делает дамп через `mysqldump` и упаковывает его в такой же ZIP.

## Настройка cron

1. Убедитесь, что папки существуют и доступны для записи:
   ```bash
   mkdir -p /path/to/project/logs /path/to/project/backups
   chmod 755 /path/to/project/backups /path/to/project/logs
   ```

2. Откройте crontab:
   ```bash
   crontab -e
   ```

3. Добавьте строку (подставьте свой путь к проекту):

   **Ежедневно в 3:00:**
   ```cron
   0 3 * * * php /path/to/r-check.ru/scripts/backup_db_zip.php >> /path/to/r-check.ru/logs/backup_cron.log 2>&1
   ```

   **Каждые 6 часов:**
   ```cron
   0 */6 * * * php /path/to/r-check.ru/scripts/backup_db_zip.php >> /path/to/r-check.ru/logs/backup_cron.log 2>&1
   ```

   **Еженедельно (воскресенье, 4:00):**
   ```cron
   0 4 * * 0 php /path/to/r-check.ru/scripts/backup_db_zip.php >> /path/to/r-check.ru/logs/backup_cron.log 2>&1
   ```

4. Для MySQL на сервере должны быть установлены и доступны в `PATH` клиент и `mysqldump`. Конфигурация берётся из `config.env` (DB_HOST, DB_NAME, DB_USER, DB_PASS).

## Где смотреть бэкапы

- Файлы: каталог `backups/` в корне проекта.
- В админке: раздел «Резервное копирование» — там отображаются и `.db`, и `.zip`; ZIP можно скачать или удалить.

## Очистка старых бэкапов

В настройках системы (Настройки → очистка) можно включить удаление резервных копий старше N дней; при этом удаляются и `.db`, и `.zip`.
