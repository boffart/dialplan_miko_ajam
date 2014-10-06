#!/bin/sh
dir_script='/tmp/';
# каталог из asterisk.conf
astspooldir='/var/spool/asterisk';

#SIPADDHEADER='Call-Info:\;answer-after=0';
#
call_text="Channel: SIP/1001
Context: miko_ajam
Extension: 10000222
Callerid: Alexey<1001>
Setvar: command=show
Setvar: dbFamily=UserBuddyStatus
Setvar: chan=SIP/1001
Setvar: SIPADDHEADER=$SIPADDHEADER";

echo "$call_text" > /tmp/file.call;
mv '/tmp/file.call' "$astspooldir/outgoing/";

asterisk -rvvv;