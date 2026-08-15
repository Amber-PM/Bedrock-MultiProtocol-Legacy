@echo off
setlocal enabledelayedexpansion
cd /d "%~dp0"

set PHP_BINARY=
if exist "bin\php\php.exe" (
    set "PHP_BINARY=bin\php\php.exe"
) else if exist "..\bin\php\php.exe" (
    set "PHP_BINARY=..\bin\php\php.exe"
) else (
    where /q php.exe && set "PHP_BINARY=php"
)

if "%PHP_BINARY%"=="" (
    echo PHP not found.
    exit /b 1
)

set COMPOSER_CMD=
if exist "composer.phar" (
    set COMPOSER_CMD="%PHP_BINARY%" "composer.phar"
) else if exist "..\composer.phar" (
    set COMPOSER_CMD="%PHP_BINARY%" "..\composer.phar"
) else (
    where /q composer && (
        set COMPOSER_CMD=composer
    ) || (
        curl -L -sS https://getcomposer.org/composer.phar -o composer.phar
        if exist "composer.phar" (
            set COMPOSER_CMD="%PHP_BINARY%" "composer.phar"
        ) else (
            echo Composer not found.
            exit /b 1
        )
    )
)

rem composer.json wires pocketmine/bedrock-protocol and pocketmine/bedrock-data as "path"
rem repositories (../BedrockProtocol, ../BedrockData/BedrockData) with symlink disabled, so
rem "composer install" re-copies them into vendor\vapebw\bedrock-protocol and
rem vendor\vapebw\bedrock-data on every run. Protocol-223 work has been edited directly under
rem vendor\, so a plain install would silently discard it. Push the current vendor copy back
rem into the sibling source folders first (so the re-copy is a no-op), and additionally keep a
rem backup to restore from in case those sibling folders aren't present in this checkout.
set BP_VENDOR=vendor\vapebw\bedrock-protocol
set BP_SRC=..\BedrockProtocol
set BP_BACKUP=%TEMP%\pmv-bedrock-protocol-backup
set BD_VENDOR=vendor\vapebw\bedrock-data
set BD_SRC=..\BedrockData\BedrockData
set BD_BACKUP=%TEMP%\pmv-bedrock-data-backup

if exist "%BP_VENDOR%" (
    if exist "%BP_SRC%" robocopy "%BP_VENDOR%" "%BP_SRC%" /MIR /NFL /NDL /NJH /NJS >nul
    if exist "%BP_BACKUP%" rmdir /s /q "%BP_BACKUP%"
    robocopy "%BP_VENDOR%" "%BP_BACKUP%" /MIR /NFL /NDL /NJH /NJS >nul
)
if exist "%BD_VENDOR%" (
    if exist "%BD_SRC%" robocopy "%BD_VENDOR%" "%BD_SRC%" /MIR /NFL /NDL /NJH /NJS >nul
    if exist "%BD_BACKUP%" rmdir /s /q "%BD_BACKUP%"
    robocopy "%BD_VENDOR%" "%BD_BACKUP%" /MIR /NFL /NDL /NJH /NJS >nul
)

call %COMPOSER_CMD% install --no-dev --classmap-authoritative --ignore-platform-reqs
if !errorlevel! neq 0 exit /b !errorlevel!

if exist "%BP_BACKUP%" robocopy "%BP_BACKUP%" "%BP_VENDOR%" /MIR /NFL /NDL /NJH /NJS >nul
if exist "%BD_BACKUP%" robocopy "%BD_BACKUP%" "%BD_VENDOR%" /MIR /NFL /NDL /NJH /NJS >nul

"%PHP_BINARY%" -dphar.readonly=0 build\server-phar.php
