#!/bin/bash

WARNING_PERCENT="98"
CRITICAL_PERCENT="50"
HOST="127.0.0.1"
SNMP=""

while getopts "w:c:h:s:" OPT; do
	case "${OPT}" in
		w)
			WARNING_PERCENT=${OPTARG}
			;;
		c)
			CRITICAL_PERCENT=${OPTARG}
			;;
		h)
			HOST=${OPTARG}
			;;
		s)
			SNMP=${OPTARG}
			;;
		*)
			echo "Usage: $0 [ -w WARNING ] [ -c CRITICAL ] [ -h HOST ] [ -s SNMP ]" 1>&2
			exit 3
			;;
	esac
done

if [ "${SNMP}" != "" ];then
	DATA=`snmpwalk -v2c -c ${SNMP} ${HOST} .1.3.6.1.2.1.33.1`
	if [ $? -ne 0 ];then
		exit 3
	fi

	STATUS=`echo "${DATA}" | grep 3.6.1.2.1.33.1.4.1.0 | sed -e 's/^.*INTEGER: //'`
	BATTERY=`echo "${DATA}" | grep 3.6.1.2.1.33.1.2.4.0 | sed -e 's/^.*INTEGER: //'`
	TIMELEFT=`echo "${DATA}" | grep 3.6.1.2.1.33.1.2.3.0 | sed -e 's/^.*INTEGER: //'`

	STATES=(0 "Other" "None" "Normal" "Bypass" "Battery" "Booster" "Reducer")
	STATUS=${STATES[${STATUS}]}

	if [ ${TIMELEFT} -ge 60 ];then
		((HOUR=${TIMELEFT} / 60))
		((MIN=${TIMELEFT} - ${HOUR} * 60))
		TIMELEFT=${HOUR}h${MIN}m
	else
		TIMELEFT=${TIMELEFT}m
	fi
else
	DATA=`curl -s -k https://${HOST}:8888/0/json`
	if [ $? -ne 0 ];then
		echo "UNKNOWN - No data"
		exit 3
	fi

	STATUS=`echo "${DATA}" | jq -r '.status'`
	BATTERY=`echo "${DATA}" | jq -r '.batCapacity' | sed 's/%//'`
	TIMELEFT=`echo "${DATA}" | jq -r '.batTimeRemain'`
fi

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
