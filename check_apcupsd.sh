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

EXIT=0
ECHO="${STATUS} - ${BATTERY}% - ${TIMELEFT}m|Battery=${BATTERY}%;${WARN};${CRIT} Time=${TIMELEFT}m"

if [ "${STATUS}" != "ONLINE" -a "${STATUS}" != "TRIM" ];then
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
