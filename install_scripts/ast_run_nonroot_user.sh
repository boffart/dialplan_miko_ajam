#!/bin/sh
if  ( cat /etc/passwd | grep -q "asterisk" );  then 
	echo '';
else
	adduser asterisk       ; 
fi

if  ( cat /etc/group | grep -q "asterisk" );  then 
	echo '';
else
	addgroup asterisk       ; 
fi

part1='[directories]
astetcdir => /etc/asterisk
astvarlibdir => /var/lib/asterisk
astdbdir => /var/lib/asterisk
astkeydir => /var/lib/asterisk
astdatadir => /var/lib/asterisk
astagidir => /var/lib/asterisk/agi-bin
astspooldir => /var/spool/asterisk
astrundir => /var/run/asterisk
astlogdir => /var/log/asterisk
astsbindir => /usr/sbin';


if  ( uname -a | grep -q "x86_64" );  then 
	part2='astmoddir => /usr/lib64/asterisk/modules'; 
else 
	part2='astmoddir => /usr/lib/asterisk/modules'; 
fi

part3='
[options]
runuser  = asterisk
rungroup = asterisk

documentation_language = en_US';

echo "${part1}" > /etc/asterisk/asterisk.conf
echo "${part2}" >> /etc/asterisk/asterisk.conf
echo "${part3}" >> /etc/asterisk/asterisk.conf

	
chown -R asterisk:asterisk /etc/asterisk; chmod +x -R /etc/asterisk/* #*/
chown -R asterisk:asterisk /var/lib/asterisk; chmod +x -R /var/lib/asterisk/* #*/
chown -R asterisk:asterisk /var/spool/asterisk; chmod +x -R /var/spool/asterisk/* #*/
chown -R asterisk:asterisk /var/log/asterisk; chmod +x -R /var/log/asterisk/* #*/
chown -R asterisk:asterisk /etc/asterisk/

if  ( uname -a | grep -q "x86_64" ); then 
	chown -R asterisk:asterisk /usr/lib64/asterisk; chmod +x /usr/lib64/asterisk/*; #*/
else 
	chown -R asterisk:asterisk /usr/lib/asterisk; chmod +x /usr/lib/asterisk/*; #*/
fi

cd /usr/src/dialplan_miko_ajam;
cp -R agi-bin/* /var/lib/asterisk/agi-bin;

chmod u+x -R /var/lib/asterisk/agi-bin/* ; #*/
chown -R asterisk:asterisk /var/run/asterisk; chmod +x /var/run/asterisk/* #*
	