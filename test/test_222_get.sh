#!/bin/sh
dir_script='/tmp/';
# каталог из asterisk.conf
astspooldir='/var/spool/asterisk';
#
call_text="Channel: SIP/1001
Context: miko_ajam
Extension: 10000222
Callerid: Alexey<1001>
Setvar: command=get
Setvar: dbFamily=CF
Setvar: key=104
Setvar: val=79257184222
Setvar: chan=SIP/1001";

echo "$call_text" > /tmp/file.call;
mv '/tmp/file.call' "$astspooldir/outgoing/";

asterisk -rvvv;