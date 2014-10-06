#!/bin/sh
dir_script='/tmp/';
# каталог из asterisk.conf
astspooldir='/var/spool/asterisk';
#
call_text="Channel: SIP/1001
Context: miko_ajam
Extension: 10000666
Callerid: Alexey<1001>
Setvar: v2=1412614937.0
Setvar: v1=SIP/1001
Setvar: v6=Records
";

echo "$call_text" > /tmp/file.call;
mv '/tmp/file.call' "$astspooldir/outgoing/";

asterisk -rvvv;