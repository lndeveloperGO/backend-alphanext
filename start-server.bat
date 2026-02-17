@echo off
cd /d D:\portofolio\backend-alphanext

start cmd /k php artisan serve --port=8000
start cmd /k php artisan queue:work
start cmd /k php artisan schedule:work
start cmd /k cloudflared tunnel run alphanext-api
