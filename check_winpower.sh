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
	MINUTES=`echo "${DATA}" | grep 3.6.1.2.1.33.1.2.3.0 | sed -e 's/^.*INTEGER: //'`

	STATES=(0 "Other" "None" "Normal" "Bypass" "Battery" "Booster" "Reducer")
	STATUS=${STATES[${STATUS}]}

	if [ ${MINUTES} -ge 60 ];then
		((HOUR=${MINUTES} / 60))
		((MIN=${MINUTES} - ${HOUR} * 60))
		TIMELEFT=${HOUR}h${MIN}m
	else
		TIMELEFT=${MINUTES}m
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

	MINUTES=0
	if [[ $TIMELEFT =~ ([0-9]+)h ]]; then
		((MINUTES=${BASH_REMATCH[1]} * 60))
	fi
	if [[ $TIMELEFT =~ ([0-9]+)m ]]; then
		((MINUTES=${MINUTES} + ${BASH_REMATCH[1]}))
	fi
fi

OUTPUT="${STATUS} - ${BATTERY}% - ${TIMELEFT}|Battery=${BATTERY}%;${WARNING_PERCENT};${CRITICAL_PERCENT} Time=${MINUTES}m"

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
