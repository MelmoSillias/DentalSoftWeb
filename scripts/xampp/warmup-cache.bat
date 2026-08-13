@echo off
cd /d C:\xampp\htdocs\dentalsoft-api
php bin/console cache:clear --env=prod --no-warmup
php bin/console cache:warmup --env=prod
