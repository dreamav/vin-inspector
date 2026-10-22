---
name: run-vin-inspector
description: >-
  Use this skill to launch, restart, or check the status of the VIN Inspector project
  servers (PHP 7.1 backend on port 8000 and Vite Vue 2 frontend on port 3000)
  autonomously without extra user confirmation or searching for PHP paths.
---

# Run VIN Inspector

Runbook for starting, checking, and managing development servers for the VIN Inspector project.

## Environment & Paths
- **PHP 7.1 Command:** `php` (Интерпретатор PHP 7.1 уже в системном PATH: `D:\OpenServer\modules\php\PHP-7.1\php.exe`).
- **Backend Directory:** `e:\vibecoding\vincode` (Project root)
- **Frontend Directory:** `e:\vibecoding\vincode\frontend`
- **Backend Port:** `8000` (API endpoint: `http://localhost:8000`)
- **Frontend Port:** `3000` (Dev server: `http://localhost:3000`)

## Startup Procedure

### 1. Check Existing Processes
Before launching new tasks, check if servers are already active:
- Run `manage_task` with action `list` to see active background tasks.
- Or check ports in PowerShell:
  ```powershell
  Get-NetTCPConnection -LocalPort 8000, 3000 -ErrorAction SilentlyContinue | Select-Object LocalPort, State, OwningProcess
  ```
If both are active and responding, report the ready status to the user immediately.

### 2. Launch Backend (PHP 7.1)
If port 8000 is not running:
- Run via `run_command`:
  - **CommandLine:** `php -S localhost:8000`
  - **Cwd:** `e:\vibecoding\vincode`
  - **IsDaemon:** `true`
  - **WaitMsBeforeAsync:** `1500`

### 3. Launch Frontend (Vite)
If port 3000 is not running:
- Run via `run_command`:
  - **CommandLine:** `npm run dev`
  - **Cwd:** `d:\vibecoding\vincode\frontend`
  - **IsDaemon:** `true`
  - **WaitMsBeforeAsync:** `3000`

### 4. Verification
Verify server availability using `curl.exe`:
- **Backend check:**
  ```powershell
  curl.exe -s http://localhost:8000/api/get-brands.php
  ```
  Expected: JSON object containing `"success": true`.
- **Frontend check:**
  ```powershell
  curl.exe -i http://localhost:3000/
  ```
  Expected: `HTTP/1.1 200 OK`.
- **API Proxy check:**
  ```powershell
  curl.exe -s http://localhost:3000/api/get-brands.php
  ```
  Expected: JSON response proxied through Vite.

### 5. Summary Output
Provide the user with clickable links:
- Frontend: [http://localhost:3000](http://localhost:3000)
- Backend API: [http://localhost:8000/api/get-brands.php](http://localhost:8000/api/get-brands.php)
