#!/bin/sh

# AGI
cat /etc/asterisk/asterisk.conf | grep agi;
ls -l /var/lib/asterisk/agi-bin | grep 1C;
asterisk -rx'dialplan show miko_ajam' | grep AGI;


# AJAM
asterisk -rx'http show status';
asterisk -rx'manager show settings';

#
asterisk -rx'manager show users';
asterisk -rx'manager show user 1cami';

# CDR / CEL
asterisk -rx'cdr show status';
asterisk -rx'cel show status';

asterisk -rx'module show like odbc';
asterisk -rx'odbc show all';

# FAX 
asterisk -rx'fax show settings';

