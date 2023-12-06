#!/bin/bash

WARNING_PERCENT="98"
CRITICAL_PERCENT="50"

while getopts "w:c:" OPT; do
	case "${OPT}" in
		w)
			WARNING_PERCENT=${OPTARG}
			;;
		c)
			CRITICAL_PERCENT=${OPTARG}
			;;
		*)
			echo "Usage: $0 [ -w WARNING ] [ -c CRITICAL ]" 1>&2
			exit 3
			;;
	esac
done

DATA=`curl -s -k https://127.0.0.1:8888/0/json`
if [ $? -ne 0 ];then
	echo "UNKNOWN - No data"
	exit 3
fi

STATUS=`echo "${DATA}" | jq -r '.status'`
BATTERY=`echo "${DATA}" | jq -r '.batCapacity' | sed 's/%//'`
TIMELEFT=`echo "${DATA}" | jq -r '.batTimeRemain'`

OUTPUT="${STATUS} - ${BATTERY}% - ${TIMELEFT}|Battery=${BATTERY}%;${WARNING_PERCENT};${CRITICAL_PERCENT}"

if [ "${STATUS}" != "Normal" -o ${BATTERY} -lt ${CRITICAL_PERCENT} ];then
	if [ "${STATUS}" == "CAL" ];then
		echo "WARNING - ${OUTPUT}"
		exit 1
	else
		echo "CRITICAL - ${OUTPUT}"
		exit 2
	fi
elif [ ${BATTERY} -lt ${WARNING_PERCENT} ];then
	echo "WARNING - ${OUTPUT}"
	exit 1
elif [ ${BATTERY} -ge ${WARNING_PERCENT} -a ${BATTERY} -le 100 ];then
	echo "OK - ${OUTPUT}"
	exit 0
fi

echo "UNKNOWN - ${OUTPUT}"
exit 3
