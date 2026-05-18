@echo off
cd /d "%~dp0"
php -d upload_max_filesize=12M -d post_max_size=16M artisan serve --host=127.0.0.1 --port=8000 %*
