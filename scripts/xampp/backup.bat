@echo off
set BACKUP=C:\DentalSoft\backups\%date:~-4,4%%date:~-7,2%%date:~-10,2%
mkdir "%BACKUP%" 2>nul
C:\xampp\mysql\bin\mysqldump -u dentalsoft -p dentalsoft > "%BACKUP%\db.sql"
xcopy /E /I /Y C:\xampp\htdocs\dentalsoft-api\public\upload_files "%BACKUP%\upload_files\"
