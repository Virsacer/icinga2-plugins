#!/bin/bash

WARN="70"
CRIT="85"

while getopts "w:c:" OPT; do
	case "${OPT}" in
		w)
			WARN=${OPTARG}
			;;
		c)
			CRIT=${OPTARG}
			;;
		*)
			echo "USAGE: $0 [ -w Warning ] [ -c Critical ]" 1>&2
			exit 3
			;;
	esac
done

DATA=`zpool list -Hpo name,health,cap,alloc,size`
if [ $? -ne 0 ];then
	exit 3
fi

EXIT=0
ECHO=""
PERF="|"

while read -r POOL; do
	STATE=""
	POOL=(${POOL})
	ECHO="${ECHO}\n"
	if [ "${POOL[1]}" != "ONLINE" -o ${POOL[2]} -ge ${CRIT} ];then
		if [ "${POOL[1]}" != "ONLINE" ];then
			STATE="\n`zpool status ${POOL[0]} | sed -e '/^$/d' -e '/action:/d' -e '/config:/d' -e '/pool:/d' -e '/see:/d' -e '/state:/d' -e '/status:/,/\.$/d' -e 's/ \+was \/dev.*//'`\n"
		fi
		echo ${STATE} | grep "resilver in progress" > /dev/null
		if [ $? -ne 0 -o ${POOL[2]} -ge ${CRIT} ];then
			ECHO="${ECHO}[CRITICAL] "
			EXIT=2
		else
			ECHO="${ECHO}[WARNING] "
			if [ ${EXIT} -eq 0 ];then
				EXIT=1
			fi
		fi
	else
		if [ ${POOL[2]} -ge ${WARN} ];then
			ECHO="${ECHO}[WARNING] "
			if [ ${EXIT} -eq 0 ];then
				EXIT=1
			fi
		else
			ECHO="${ECHO}[OK] "
		fi
	fi
	ECHO="${ECHO}${POOL[0]} ${POOL[1]} ${POOL[2]}%${STATE}"
	PERF="${PERF} '${POOL[0]}'=${POOL[3]}B;$((${POOL[4]}*${WARN}/100));$((${POOL[4]}*${CRIT}/100));0;${POOL[4]}"
done <<< "$DATA"

case ${EXIT} in
	2) echo -en "CRITICAL:${ECHO}";;
	1) echo -en "WARNING:${ECHO}";;
	0) echo -en "OK:${ECHO}";;
esac

echo ${PERF} | sed -e 's/| /|/'
exit ${EXIT}
