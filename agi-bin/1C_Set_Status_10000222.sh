#!/bin/sh
# v.1.1
# Consume all variables sent by Asterisk
while read VAR && [ "$VAR" != '' ] ; do : ; done

#-----------------------------------------------------------------------
# Получение значений переменных AGI
# # # # get var command
echo 'GET VARIABLE "command"'; 
read command;
command=`echo "$command" | awk -F'[(]|[)]' ' { print $2} '`;

# # # # get var dbFamily
echo 'GET VARIABLE "dbFamily"'; 
read dbFamily;
dbFamily=`echo "$dbFamily" | awk -F'[(]|[)]' ' { print $2} '`;

# # # # get var key
echo 'GET VARIABLE "key"'; 
read key;
key=`echo "$key" | awk -F'[(]|[)]' ' { print $2} '`;

# # # # get var val
echo 'GET VARIABLE "val"'; 
read val;
val=`echo "$val" | awk -F'[(]|[)]' ' { print $2} '`;

# # # # get var chan
echo 'GET VARIABLE "chan"'; 
read chan;
chan=`echo "$chan" | awk -F'[(]|[)]' ' { print $2} '`;
#-----------------------------------------------------------------------
# Обработка

tmp_separator=','; 
# для Asterisk 1.6 раскомментировать:
# tmp_separator='|'; 

if [ "$command" = 'get' ]; then
	# установка статуса
	echo "DATABASE GET $dbFamily $key";
	# анализ результата
	read result;
	result=`echo "$result" | awk -F'[(]|[)]' ' { print $2} '`;
	if [ "result" = '' ]; then
		echo "EXEC UserEvent DB_$dbFamily$tmp_separator\"chan1c:$chan\"$tmp_separator\"key:$key\"$tmp_separator\"val:\"";
	else
		echo "EXEC UserEvent DB_$dbFamily$tmp_separator\"chan1c:$chan\"$tmp_separator\"key:$key\"$tmp_separator\"val:$result\"";
	fi;
elif [ "$command" = 'put' ]; then 
	
	# выполним команду:
	if [ "$val" = '' ]; then
		if [ "$dbFamily" = "DND" ]; then
			val='0';
			echo "DATABASE PUT $dbFamily $key $val";
		else
			echo "DATABASE DEL $dbFamily $key";
		fi;
 
	else
		if [ "$dbFamily" = "DND" ]; then
			val='1';
		fi;
		echo "DATABASE PUT $dbFamily $key $val";
	fi;
	# анализ результата
	
	read result;
	result=`echo "$result" | awk -F' ' ' { print $2} '`;
	if [ "$result" = 'result=1' ]; then
		echo "EXEC UserEvent DB_$dbFamily$tmp_separator\"chan1c:$chan\"$tmp_separator\"key:$key\"$tmp_separator\"val:$val\"";
	else
		echo "EXEC UserEvent Error_data_put_$dbFamily$tmp_separator\"chan1c:$chan\"$tmp_separator\"key:$key\"$tmp_separator\"val:$val\"";
	fi;
	#
elif [ "$command" = 'show' ]; then 
	tmp_file=`date +%s`;
	tmp_file=`echo "/tmp/$tmp_file"`;
	
	asterisk -rx'database show UserBuddyStatus' > "$tmp_file";
	kol=`cat "$tmp_file" | wc -l`;
	
	kolpack=15; i=0;
	while [ $i -le $kol ]; do        
		i=`expr $i + $kolpack`;	        
		result=`cat "$tmp_file" | head -n "$i"| tail -n "$kolpack" | sed 's/$/...../g' | tr "\n" " " | tr " " "+" | sed 's/:/@.@/g' | sed 's/+/''/g' | sed 's/\/UserBuddyStatus\//''/g'`;
		echo "EXEC UserEvent From$dbFamily$tmp_separator\"chan1c:$chan\"$tmp_separator\"Lines:$result\"";
		read RESPONSE;
	done;
	rm "${tmp_file}";
fi;