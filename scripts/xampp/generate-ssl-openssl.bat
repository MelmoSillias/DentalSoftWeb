@echo off
REM Genere un certificat auto-signé pour dentalsoft.local + apidentalsoft.local (offline, MAF)
set SSL_DIR=C:\xampp\apache\conf
set OPENSSL=C:\xampp\apache\bin\openssl.exe

if not exist "%SSL_DIR%\ssl.crt" mkdir "%SSL_DIR%\ssl.crt"
if not exist "%SSL_DIR%\ssl.key" mkdir "%SSL_DIR%\ssl.key"

"%OPENSSL%" req -x509 -nodes -days 825 -newkey rsa:2048 ^
  -keyout "%SSL_DIR%\ssl.key\dentalsoft.local-key.pem" ^
  -out "%SSL_DIR%\ssl.crt\dentalsoft.local.pem" ^
  -subj "/CN=dentalsoft.local" ^
  -addext "subjectAltName=DNS:dentalsoft.local,DNS:apidentalsoft.local"

echo Certificat cree :
echo   %SSL_DIR%\ssl.crt\dentalsoft.local.pem
echo   %SSL_DIR%\ssl.key\dentalsoft.local-key.pem
echo Importer le .pem sur chaque poste client ou accepter l'avertissement navigateur.
