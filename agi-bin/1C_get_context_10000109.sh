#!/bin/sh
# Consume all variables sent by Asterisk
while read VAR && [ "$VAR" != '' ] ; do : ; done

# get var chan
echo 'GET VARIABLE "number"'; 
read exten;
exten=`echo "$exten" | awk -F'[(]|[)]' ' { print $2} '`;

echo 'GET VARIABLE "tehnology"'; 
read tehnology;
tehnology=`echo "$tehnology" | awk -F'[(]|[)]' ' { print $2} '`;

if [ "$tehnology" = 'SIP' ]; then
	result=`asterisk -rx"sip show peer $exten" | grep Context | awk -F'[:]+[ ]+' ' { print $2  } '`;
elif [ "$tehnology" = 'DAHDI' ]; then 
	result=`asterisk -rx"dahdi show channel $exten" | grep Context | awk -F'[:]+[ ]+' ' { print $2  } '`;
elif [ "$tehnology" = 'IAX' ]; then 
	result=`asterisk -rx"iax2 show peer $exten" | grep Context | awk -F'[:]+[ ]+' ' { print $2  } '`;
fi

#echo "EXEC UserEvent GetContest|\"Channel:$tehnology/$exten\"|\"context:$result\"";
echo "EXEC UserEvent GetContest,\"Channel:$tehnology/$exten\",\"context:$result\"";
read RESPONSE;

