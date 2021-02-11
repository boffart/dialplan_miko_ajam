#!/bin/sh

# Откроем порт 80
firewall-cmd --zone=public --add-port=80/tcp
firewall-cmd --permanent --add-service=http

# Открываем порт 5060
tmpXML='<?xml version="1.0" encoding="utf-8"?>
<service>
 <short>SIP5060</short>
 <description>SIP signal port</description>
 <port protocol="udp" port="5060"/>
</service>';

echo "${tmpXML}" > /etc/firewalld/services/SIP5060.xml

cd /etc/firewalld/services

restorecon SIP5060.xml
restorecon
chmod 640 SIP5060.xml
firewall-cmd --permanent --add-service=SIP5060

# Открываем порты RDP
tmpXML='<service>
 <short>RTPAsterisk</short>
 <description>RTP ports for Asterisk</description>
 <port protocol="udp" port="5060"/>
</service>';

echo "${tmpXML}" > /etc/firewalld/services/RTPAsterisk.xml

cd /etc/firewalld/services
restorecon RTPAsterisk.xml
restorecon
chmod 640 RTPAsterisk.xml
firewall-cmd --permanent --add-service=RTPAsterisk
firewall-cmd --reload


firewall-cmd --zone=public --list-all