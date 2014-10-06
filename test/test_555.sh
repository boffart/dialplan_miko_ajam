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
Setvar: v2=2013-11-01
Setvar: v3=2013-12-01
Setvar: v4=1001-1000
";

echo "$call_text" > /tmp/file.call;
mv '/tmp/file.call' "$astspooldir/outgoing/";

asterisk -rvvv;