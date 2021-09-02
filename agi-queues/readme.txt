
# Установить beanstalk
curl -O -L 'https://github.com/beanstalkd/beanstalkd/archive/refs/tags/v1.12.tar.gz'
tar xzf v1.12.tar.gz
rm -rf v1.12.tar.gz
cd beanstalkd-1.12/
make
cp beanstalkd /usr/bin/beanstalkd

# Скачать архив https://codeload.github.com/boffart/dialplan_miko_ajam/zip/refs/heads/master
cd /usr/src
curl -L -o 'dialplan_miko_ajam-master.zip' 'https://codeload.github.com/boffart/dialplan_miko_ajam/zip/refs/heads/master'
unzip dialplan_miko_ajam-master.zip
# Разместить исходники в каталоге:
mv /usr/src/dialplan_miko_ajam-master /usr/src/dialplan-miko-ajam
rm -rf dialplan_miko_ajam-master.zip
mkdir /usr/src/dialplan-miko-ajam/agi-queues/tmp
# Сменить владельца каталога
chown -R asterisk:asterisk /usr/src/dialplan-miko-ajam
# Предоставим права на исполонение:
chmod +x /usr/src/dialplan-miko-ajam/agi-queues/src/Core/agi-bin/*.php

ln -s /usr/src/dialplan-miko-ajam/agi-queues/miko-queues /var/www/html/miko-queues;
ln -s /usr/src/dialplan-miko-ajam/agi-queues/miko-change-state /var/www/html/miko-change-state;
rm -rf /usr/src/dialplan-miko-ajam/agi-queues/miko-queues/index.php;
ln -s /usr/src/dialplan-miko-ajam/agi-queues/agents.dump /usr/src/dialplan-miko-ajam/agi-queues/miko-queues/index.php


# ln -s /usr/src/dialplan-miko-ajam/agi-queues/agents.dump.test /usr/src/dialplan-miko-ajam/agi-queues/miko-queues/index.php
chown asterisk:asterisk /var/www/html/miko-queues;

# Проверка
curl -L 'http://127.0.0.1/miko-queues/'
# должна вернуть JSON

# Задача для cron (пользорватель asterisk)
crontab -u asterisk -e;
# (проверить пути к исполняемым файлам)
*/1 * * * * /usr/bin/nohup /usr/bin/php -f /usr/src/dialplan-miko-ajam/agi-queues/src/Core/Bin/miko-queue-router.php check 2>&1 > /dev/null

/etc/init.d/crond reload

# Добавить в /etc/asterisk/extensions_custom.conf
[miko-custom-call-routing]
exten => _[0-9*#+a-zA-Z][0-9*#+a-zA-Z]!,1,Dial(Local/${EXTEN}@miko-custom-queue/n,,${TRUNK_OPTIONS})
exten => _[0-9*#+a-zA-Z][0-9*#+a-zA-Z]!,2,hangup();

exten => h,1,AGI(/usr/src/dialplan-miko-ajam/agi-queues/src/Core/agi-bin/miko-agi-call-hangup.php)

[miko-custom-queue]
exten => _[0-9*#+a-zA-Z][0-9*#+a-zA-Z]!,1,AGI(/usr/src/dialplan-miko-ajam/agi-queues/src/Core/agi-bin/miko-agi-call-routing.php)

[from-internal-custom]
; Вместо 333 указать УНИКАЛЬНЫЙ номер, для более удобной переадресации на очередь.
exten => 333,1,Goto(miko-custom-call-routing,${EXTEN},1)

# Создать новую Queues в интерфейсе FreePBX.

# В настройках очереди учитываются только поля:
# -- Agent Timeout - как долго пытаться звонить агенту
# -- Max Wait Time - как долго пытаться дозваниваться через очередь
# -- Fail Over Destination - Резервный номер телефона.
# -- Music on Hold Class - Класс музыки на удержании
# -- MOH Only / Agent Ringing - Что будет слышать клиент
# -- Retry - Через сколько секунд направить вызов на сотрудника повторно
# -- Static Agents - состав очереди

# Описать настройки в файле на АТС:
/usr/src/dialplan-miko-ajam/agi-queues/settings.php
В $settingsQueue["QUEUE_NUMBER"]  = "90000999"; // вместо 90000999 указать номер очереди.


# Создать Custom Destination
Target: miko-custom-call-routing,${EXTEN},1
Description: MikoCustomCallrouting

# Перенаправить Inbound Rout на созданный ранее Custom Destination (MikoCustomCallrouting).
# Протестировать работу очереди.