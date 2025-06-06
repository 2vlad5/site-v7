#  Добро пожаловать на единый портал mwj-2v5.ru

#  Система учета рабочего времени

Система для учета рабочего времени сотрудников с возможностью ведения рабочих журналов, назначения смен и генерации отчетов.

## Основные функции

- Аутентификация пользователей с проверкой ключей доступа
- Три роли: Создатель, Администратор, Пользователь
- Ведение рабочих журналов по месяцам и дням
- Назначение смен
- Создание отчетов в форматах Excel, HTML и TXT
- Запросы на восстановление данных
- Резервное копирование и восстановление журналов

## Автоматическая очистка старых уведомлений

Система автоматически удаляет уведомления о назначенных сменах, которые старше 3 дней. Это происходит:

1. При входе пользователя в систему
2. При посещении страницы уведомлений
3. При запуске cron-задачи (если настроена)

## Установка и настройка

Для запуска системы необходимо настроить PHP-сервер с поддержкой:
- PHP 7.0 или выше
- Расширение JSON
- Права на запись в директорию data/

## Структура каталогов

- `data/`: Каталог для хранения всех данных системы
  - `entries/`: Записи журналов
  - `backups/`: Резервные копии
  - `reports/`: Сгенерированные отчеты
- `css/`: Стили системы
- `lib/`: Библиотеки (например, SimpleXLSXGen для экспорта в Excel)

## Периодические задачи

Для автоматического выполнения периодических задач (например, очистки старых уведомлений) можно настроить запуск скрипта `cron_tasks.php` через планировщик задач.
 
