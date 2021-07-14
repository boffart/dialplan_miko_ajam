
# Разместить исходники в каталоге:
mkdir -p /usr/src/dialplan-miko-ajam

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