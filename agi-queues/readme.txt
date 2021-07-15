
# Разместить исходники в каталоге:
mkdir -p /usr/src/dialplan-miko-ajam

# https://codeload.github.com/boffart/dialplan_miko_ajam/zip/refs/heads/master;

# Сменить владельца каталога
chown -R asterisk:asterisk /usr/src/dialplan-miko-ajam

# Установить beanstalk
yum install beanstalkd;
systemctl enable beanstalkd;
systemctl start beanstalkd;

# Задача для cron (пользорватель asterisk)
crontab -u asterisk -e;
# (проверить пути к исполняемым файлам)
*/1 * * * * /usr/bin/nohup /usr/bin/php -f /usr/src/dialplan-miko-ajam/agi-queues/src/Core/Bin/miko-queue-router.php start 2>&1 > /dev/null

# Добавить в extensions_custom.conf
[miko-custom-call-routing]
exten => _[0-9*#+a-zA-Z][0-9*#+a-zA-Z]!,1,Dial(Local/${EXTEN}@miko-custom-queue/n,,${TRUNK_OPTIONS})
exten => _[0-9*#+a-zA-Z][0-9*#+a-zA-Z]!,2,hangup();

exten => h,1,AGI(/usr/src/dialplan-miko-ajam/agi-queues/src/Core/agi-bin/miko-agi-call-hangup.php)

[miko-custom-queue]
exten => _[0-9*#+a-zA-Z][0-9*#+a-zA-Z]!,1,AGI(/usr/src/dialplan-miko-ajam/agi-queues/src/Core/agi-bin/miko-agi-call-routing.php)

# Создать Custom Destination
Target: miko-custom-call-routing,${EXTEN},1
Description: MikoCustomCallrouting

# В настройках очереди учитываются поля:
# -- Agent Timeout - как долго пытаться звонить агенту
# -- Max Wait Time - как долго пытаться дозваниваться через очередь
# -- Fail Over Destination - Резервный номер телефона.
# -- Music on Hold Class - Класс музыки на удержании
# -- MOH Only / Agent Ringing - Что будет слышать клиент
# -- Retry - Через сколько секунд направить вызов на сотрудника повторно
# -- Static Agents