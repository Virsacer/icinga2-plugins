#!/bin/bash

#command[check_zfs]=sudo /usr/lib/nagios/plugins/check_zfs.sh -w $ARG1$ -c $ARG2$

WARNING_PERCENT="70"
CRITICAL_PERCENT="85"

while getopts "w:c:" OPT; do
	case "${OPT}" in
		w)
			WARNING_PERCENT=${OPTARG}
			;;
		c)
			CRITICAL_PERCENT=${OPTARG}
			;;
		:)
			echo "Error: -${OPTARG} requires an argument."
			exit 3
			;;
		\?)
			echo "Usage: $0 [ -w WARNING ] [ -c CRITICAL ]" 1>&2
			exit 3
			;;
	esac
done

DATA=`zpool list -Hpo name,health,cap,alloc,size`
if [ $? -ne 0 ];then
	exit 3
fi

STATUS=0
OUTPUT=""
PERFORMANCE="|"

while read -r POOL; do
	POOL=(${POOL})
	OUTPUT="${OUTPUT}\n${POOL[0]} ${POOL[1]} ${POOL[2]}%"
	if [ "${POOL[1]}" != "ONLINE" -o ${POOL[2]} -ge ${CRITICAL_PERCENT} ];then
		STATE="`zpool status ${POOL[0]} | sed -e '/^$/d' -e '/action:/d' -e '/config:/d' -e '/pool:/d' -e '/see:/d' -e '/state:/d' -e '/status:/,/\.$/d' -e 's/ \+was \/dev.*//'`\n"
		echo ${STATE} | grep "resilver in progress" > /dev/null
		if [ $? -ne 0 ];then
			STATUS=2
			OUTPUT="${OUTPUT} (CRITICAL)\n${STATE}"
		else
			STATUS=1
			OUTPUT="${OUTPUT} (WARNING)\n${STATE}"
		fi
	else
		if [ ${POOL[2]} -ge ${WARNING_PERCENT} ];then
			if [ ${STATUS} -eq 0 ];then
				STATUS=1
			fi
			OUTPUT="${OUTPUT} (WARNING)"
		fi
	fi
	PERFORMANCE="${PERFORMANCE} '${POOL[0]}'=${POOL[3]}B;$((${POOL[4]}*${WARNING_PERCENT}/100));$((${POOL[4]}*${CRITICAL_PERCENT}/100));0;${POOL[4]}"
done <<< "$DATA"

case ${STATUS} in
	2) echo "CRITICAL";;
	1) echo "WARNING";;
	0) echo "OK";;
esac

echo -e "${OUTPUT}" | sed -z '$ s/\n\+$//'
echo ${PERFORMANCE} | sed -e 's/| /|/'
exit ${STATUS}
