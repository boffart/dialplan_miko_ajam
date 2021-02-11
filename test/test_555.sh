#!/bin/sh
dir_script='/tmp/';
# каталог из asterisk.conf
astspooldir='/var/spool/asterisk';
#
call_text="Channel: SIP/104
Context: miko_ajam
Extension: 10000555
Callerid: Alexey<1001>
Setvar: v1=SIP/1001
Setvar: v2=2016-01-01
Setvar: v3=2016-12-31
Setvar: v4=1002-1000
";

echo "$call_text" > /tmp/file.call;
mv '/tmp/file.call' "$astspooldir/outgoing/";

asterisk -rvvv;

###
## Тест средствами AMI:
#
# Action: login
# Username: 1cami
# Secret: PASSWORD1cami
#
#
# Action: originate
# Channel: Local/10000555@miko_ajam_10000555
# WaitTime: 10
# Application: NoCDR
# Variable: v1=SIP/511,v2=2017-01-25,v3=2017-01-26,v4=511
#
# Action: logoff

