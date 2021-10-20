#!/bin/bash

#command[check_apcupsd]=/usr/lib/nagios/plugins/check_apcupsd.sh -w $ARG1$ -c $ARG2$

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

DATA=`/sbin/apcaccess`
if [ $? -ne 0 ];then
	exit 3
fi

STATUS=`echo "${DATA}" | grep STATUS | sed 's/.*:  *\([A-Z][ A-Z]*[A-Z]\).*/\1/'`
BATTERY=`echo "${DATA}" | grep BCHARGE | sed 's/.*:  *\([0-9][0-9]*\).*/\1/'`
TIMELEFT=`echo "${DATA}" | grep TIMELEFT | sed 's/.*:  *\([0-9][0-9.]*\).*/\1/'`

OUTPUT="${STATUS} - ${BATTERY}% - ${TIMELEFT}m|'Battery'=${BATTERY}%;${WARNING_PERCENT};${CRITICAL_PERCENT}"

if [ "${STATUS}" != "ONLINE" -o ${BATTERY} -lt ${CRITICAL_PERCENT} ];then
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
