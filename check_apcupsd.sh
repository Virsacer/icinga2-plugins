#!/bin/bash

WARN="98"
CRIT="50"

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

DATA=`/sbin/apcaccess`
if [ $? -ne 0 ];then
	exit 3
fi

STATUS=`echo "${DATA}" | grep STATUS | sed 's/.*:  *\([A-Z][ A-Z]*[A-Z]\).*/\1/'`
BATTERY=`echo "${DATA}" | grep BCHARGE | sed 's/.*:  *\([0-9][0-9]*\).*/\1/'`
TIMELEFT=`echo "${DATA}" | grep TIMELEFT | sed 's/.*:  *\([0-9][0-9.]*\).*/\1/'`

ECHO="${STATUS} - ${BATTERY}% - ${TIMELEFT}m|Battery=${BATTERY}%;${WARN};${CRIT} Time=${TIMELEFT}m"

if [ "${STATUS}" != "ONLINE" -o ${BATTERY} -lt ${CRIT} ];then
	if [ "${STATUS}" == "CAL" ];then
		echo "WARNING: ${ECHO}"
		exit 1
	else
		echo "CRITICAL: ${ECHO}"
		exit 2
	fi
elif [ ${BATTERY} -lt ${WARN} ];then
	echo "WARNING: ${ECHO}"
	exit 1
elif [ ${BATTERY} -ge ${WARN} -a ${BATTERY} -le 100 ];then
	echo "OK: ${ECHO}"
	exit 0
fi

echo "UNKNOWN: ${ECHO}"
exit 3
