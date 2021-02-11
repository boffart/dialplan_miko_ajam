#!/bin/sh

apt-get update;
apt-get upgrade;

# Installation of Basic Dependencies
apt-get install build-essential subversion libncurses5-dev libssl-dev libxml2-dev libsqlite3-dev uuid-dev unixodbc libltdl-dev linux-headers-$(uname -r) libjansson-dev;

apt-get install libmyodbc unixODBC-dev;
# Для ubuntu 16:
# https://www.dataarmor.ru/how-to-install-mysql-odbc-ubuntu-16-04/
curl 'https://cdn.mysql.com//Downloads/Connector-ODBC/5.3/mysql-connector-odbc-5.3.9-linux-ubuntu16.04-x86-64bit.tar.gz' -o mysql-connector-odbc-5.3.9-linux-ubuntu16.04-x86-64bit.tar.gz;
tar -xvf mysql-connector-odbc-5.3.9-linux-ubuntu16.04-x86-64bit.tar.gz;
cp mysql-connector-odbc-5.3.9-linux-ubuntu16.04-x86-64bit/lib/libmyodbc5a.so  /usr/lib/x86_64-linux-gnu/odbc/

mysql-connector-odbc-5.3.9-linux-ubuntu16.04-x86-64bit/bin/myodbc-installer -d -a -n 'MySQL' -t 'DRIVER=/usr/lib/x86_64-linux-gnu/odbc/libmyodbc5a.so;'
# тест подключения к базе данных
mysql-connector-odbc-5.3.9-linux-ubuntu16.04-x86-64bit/bin/myodbc-installer -s -a -c2 -n 'test' -t 'DRIVER=MySQL;SERVER=127.0.0.1;DATABASE=mysql;UID=root;PWD=123456'


# Downloading Your Asterisk Source Code
cd /usr/src/;

wget http://downloads.asterisk.org/pub/telephony/dahdi-linux-complete/dahdi-linux-complete-current.tar.gz;
wget http://downloads.asterisk.org/pub/telephony/libpri/libpri-current.tar.gz;

wget http://downloads.asterisk.org/pub/telephony/asterisk/asterisk-16-current.tar.gz;

# Extraction of Downloaded Files
tar zxvf dahdi-linux-complete*;
tar zxvf libpri*;
tar zxvf asterisk*;

# DAHDI Installation
cd /usr/src/dahdi-linux-complete-*/;
make && make install && make config;

# LibPRI Installation. In order to enable your BRI, PRI and QSIG based hardware
cd /usr/src/libpri-*/
make && make install

# install ODBC driver
odbcinst -i -d -f /usr/share/libmyodbc/odbcinst.ini

cd /usr/src/asterisk-*/
# Asterisk Installation
if  ( uname -a | grep -q "x86_64");  then 
	./configure --libdir=/usr/lib64; 
else 
	./configure; 
fi

# сборка и инсталяция Asterisk
make && make install

# make config - важная команда, формирует файл «/etc/rc.d/init.d/asterisk» 
# без этого файла при начале работы системы не будет запускаться скрипт  /usr/sbin/safe_asterisk  
make config

# 
cp /usr/lib64/libasteriskssl.so.1 /usr/lib/libasteriskssl.so.1

# Проверим, запущен ли сервис.
/etc/init.d/asterisk status
# Запуск сервиса. 
# /etc/init.d/asterisk start


mkdir -p /var/spool/asterisk/fax/;
chmod 777 /var/spool/asterisk/fax/;

# Для скачивания записи разговров необходима конвертация. Установим SOX.
apt-get install sox