#!/bin/sh

# For CentOS 7

# CentOS Updates
yum update -y
yum -y install net-tools

# Disabling SELinux
sed -i s/SELINUX=enforcing/SELINUX=disabled/g /etc/selinux/config  

# Installation of Basic Dependencies
yum install -y make wget openssl-devel ncurses-devel newt-devel libxml2-devel kernel-devel gcc gcc-c++ sqlite-devel unixODBC unixODBC-devel libtool-ltdl libtool-ltdl-devel

# Downloading Your Asterisk Source Code
cd /usr/src/

wget http://downloads.asterisk.org/pub/telephony/dahdi-linux-complete/dahdi-linux-complete-current.tar.gz;
wget http://downloads.asterisk.org/pub/telephony/libpri/libpri-1.4-current.tar.gz;
wget http://downloads.asterisk.org/pub/telephony/asterisk/asterisk-11-current.tar.gz;

# Extraction of Downloaded Files
tar zxvf dahdi-linux-complete*
tar zxvf libpri*
tar zxvf asterisk*

# DAHDI Installation
cd /usr/src/dahdi-linux-complete*;
make && make install && make config;


# LibPRI Installation. In order to enable your BRI, PRI and QSIG based hardware
cd /usr/src/libpr*
make && make install

# Asterisk Installation
cd /usr/src/asterisk*

if  ( uname -a | grep -q "x86_64");  then 
	./configure --libdir=/usr/lib64; 
else 
	./configure; 
fi

# сборка и инсталяция
make && make install


# make config - важная команда, формирует файл «/etc/rc.d/init.d/asterisk» 
# без этого файла при начале работы системы не будет запускаться скрипт  /usr/sbin/safe_asterisk  
make config

mkdir /var/spool/asterisk/fax/;
chmod 777 /var/spool/asterisk/fax/;