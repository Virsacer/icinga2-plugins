#!/bin/bash

WARN="98"
CRIT="50"
HOST="127.0.0.1"
SNMP=""

while getopts "w:c:h:s:" OPT; do
	case "${OPT}" in
		w)
			WARN=${OPTARG}
			;;
		c)
			CRIT=${OPTARG}
			;;
		h)
			HOST=${OPTARG}
			;;
		s)
			SNMP=${OPTARG}
			;;
		*)
			echo "USAGE: $0 [ -w Warning ] [ -c Critical ] [ -h Host ] [ -s SNMP ]" 1>&2
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
		echo "UNKNOWN: No data"
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

EXIT=0
ECHO="${STATUS} - ${BATTERY}% - ${TIMELEFT}|Battery=${BATTERY}%;${WARN};${CRIT} Time=${MINUTES}m"

if [ "${STATUS}" != "Normal" -a "${STATUS}" != "Reducer" ];then
	if [ "${STATUS}" == "CAL" ];then
		EXIT=1
	else
		EXIT=2
	fi
fi
if [ ${BATTERY} -lt ${CRIT} ];then
	EXIT=2
elif [ ${BATTERY} -lt ${WARN} -a ${EXIT} -eq 0 ];then
	EXIT=1
fi

case ${EXIT} in
	2) echo -en "CRITICAL: ${ECHO}";;
	1) echo -en "WARNING: ${ECHO}";;
	0) echo -en "OK: ${ECHO}";;
esac

exit ${EXIT}
