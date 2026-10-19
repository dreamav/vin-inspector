# VIN Inspector

Веб-приложение для проверки автомобилей по VIN-коду и подбора оригинальных автозапчастей (OEM) с поддержкой нескольких автомобильных брендов (Infiniti, Mercedes-Benz).

## 🚀 Стек технологий

- **Frontend:**
  - [Vue 2](https://vuejs.org/) (Options API)
  - [Vite](https://vitejs.dev/) + `vite-plugin-vue2`
  - Чистый Scoped CSS (Glassmorphism, тёмная тема, адаптивный дизайн)
- **Backend:**
  - PHP 7.1+ (встроенный HTTP-клиент cURL с поддержкой gzip и кастомных заголовков)
  - Без внешних зависимостей Composer

## 📁 Структура проекта

```text
├── api/                  # PHP API эндпоинты
│   ├── check-vin.php     # Поиск автомобиля по VIN-коду
│   ├── check-oem.php     # Проверка применимости запчасти по OEM-номеру
│   ├── config.php        # Загрузка и валидация конфигурации марок
│   └── get-brands.php    # Список доступных брендов для фронтенда
├── config/
│   └── brands.json       # Конфигурация сайтов и селекторов брендов
├── frontend/             # Vue 2 SPA приложение
│   ├── src/
│   │   ├── components/   # Vue компоненты (BrandSelector, VinSearch, OemCheckResult и др.)
│   │   ├── assets/       # Глобальные стили
│   │   ├── App.vue       # Главный компонент
│   │   └── main.js       # Точка входа
│   ├── package.json
│   └── vite.config.js    # Конфигурация Vite с проксированием /api -> http://localhost:8000
├── .gitignore
├── GEMINI.md             # Правила разработки и стандарты проекта
└── README.md             # Описание проекта
```

## 🛠️ Запуск проекта локально

### 1. Запуск бэкенда (PHP)
В корневой папке проекта:
```bash
php -S localhost:8000
```

### 2. Запуск фронтенда (Vite Dev Server)
В папке `frontend`:
```bash
cd frontend
npm install
npm run dev
```

После этого откройте в браузере: **http://localhost:3000**
API запросы автоматически проксируются на `http://localhost:8000`.

## ⚙️ Добавление новых марок
Новые марки автомобилей и их целевые сайты добавляются в конфигурационный файл `config/brands.json`.
