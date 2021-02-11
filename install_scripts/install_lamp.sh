#!/bin/sh

# Установка Apache
yum -y install httpd
service httpd start

# MySQL
yum -y install mariadb-server mariadb
systemctl start mariadb.service
systemctl enable mariadb.service
mysql_secure_installation

# PHP
yum -y install php php-mysql php-gd php-ldap php-odbc php-pear php-xml php-xmlrpc php-mbstring php-snmp php-soap curl curl-devel
systemctl restart httpd.service

# ODBC connector
yum -y install mysql-connector-odbc;
