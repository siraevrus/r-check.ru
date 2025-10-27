# ⚡ Быстрый старт ReproCRM

## 🚀 За 3 шага

### Шаг 1: Проверьте систему
```bash
php check_system.php
```

### Шаг 2: Установите (выберите один способ)

**Способ А - Веб-установка (проще всего):**
```bash
php -S localhost:8000
# Откройте: http://localhost:8000/install.php
```

**Способ Б - Командная строка:**
```bash
composer install
cp config.env.example config.env
sqlite3 database/reprocrm.db < database/schema.sqlite.sql
```

### Шаг 3: Запустите
```bash
php -S localhost:8000
# Откройте: http://localhost:8000
```

## 🔑 Вход по умолчанию

- **Email:** `admin@reprosystem.ru`
- **Пароль:** `admin123`

⚠️ Измените пароль после первого входа!

## 📋 Минимальные требования

- PHP >= 7.4
- Расширения: PDO, SQLite, mbstring, OpenSSL, JSON
- Composer

## 🔧 Полезные команды

```bash
# Проверка системы
php check_system.php

# Запуск локально
php -S localhost:8000

# Запуск через готовый скрипт
bash run_local.sh

# Резервная копия
cp database/reprocrm.db backups/backup_$(date +%Y%m%d).db
```

## 📚 Подробная документация

- **INSTALL.md** - Полная инструкция по установке
- **USER_GUIDE.md** - Руководство пользователя
- **ADMIN_GUIDE.md** - Руководство администратора

## ❓ Проблемы?

1. Запустите `php check_system.php` для диагностики
2. Проверьте логи в `logs/`
3. Читайте INSTALL.md

---

**Готово к работе за 2 минуты!** 🎉











