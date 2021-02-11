########################################################################
# v2.2
# Пример интеграции:
# http://wiki.miko.ru/astpanel:dialplan_miko_ajam
########################################################################
# зависимости
PHP 5.3.10 / 7.0.22
Для PHP должен быть доступен модуль mysqli / mysql

MySQL mysql  Ver 15.1 Distrib 10.0.31-MariaDB
Apache 2.4.18
unixODBC

SoX v14.4.0
GPL Ghostscript 9.10
spandsp
Asterisk 1.8+

########################################################################
# telnet ip_adres 5038
action: login
username: 1cami
secret: PASSWORD1cami

v2.5 // 23 июля 2018г.
- исправлен скрипт "1c/download/index.php"

v2.2 // 12 октября 2017г.
- исправлен скрипт "1c/cdr_xml/index.php"

v2.1 // 12 октября 2017г.
- Переработаны скрипты для PHP7
- Исправлены скрипты AGI "1C_Download.php", "1C_CDR.php", "1C_Playback.php", "1C_HistoryFax.php"
- Обновлены скрипты синхронизации истории звонков для 1С "1c/cdr_xml/index.php", "1c/cel_xml/index.php"

#
v1.6 // 9 марта 2016г.
- Добавлен файл features.conf
- Исправлен файл extensions.conf, доработан пример включения контекста парковки "parkedcalls"
- Доработан скрипт установки Asterisk "debian_asterisk_install.sh"
- Исправлены скрипты AGI "1C_Download.php", "1C_CDR.php", "1C_Playback.php". Добавлена возможность выбора файла записи, если на один звонок более одного файла
- Добавлен скрипт "1c/getvar/index.php" для получения переменных канала. Скрипт позволяет сократить число запросов к АТС, снизить нагрузку на станцию
- Обновлены скрипты синхронизации истории звонков для 1С "1c/cdr_xml/index.php", "1c/cel_xml/index.php"

v1.5 // 13 июля 2015г.
- Поправлен файл extensions.conf, в exten 10000111 добавлена информация по autoanswernumber 

v1.3 // 2 июня 2015г. 
- Исправлена ошибка в скрипте "agi-bin/1C_CDR.php". Обращение к таблице "cdr" вместо таблицы "PT1C_cdr".
- Исправлена ошибка в скрипте "agi-bin/1C_Playback.php". Получение имени базы данных.