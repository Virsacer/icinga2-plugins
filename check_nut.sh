#!/bin/sh

DEVICE="empty"
WARN="98"
CRIT="50"

while getopts "d:w:c:" OPT; do
	case "${OPT}" in
		d)
			DEVICE=${OPTARG}
			;;
		w)
			WARN=${OPTARG}
			;;
		c)
			CRIT=${OPTARG}
			;;
		*)
			echo "USAGE: $0 [ -d Device ] [ -w Warning ] [ -c Critical ]" 1>&2
			exit 3
			;;
	esac
done

DATA=`upsc ${DEVICE} 2>&1`
if [ $? -ne 0 ];then
	echo "${DATA}" | grep -v "Init SSL"
	exit 3
fi

STATUS=`echo "${DATA}" | grep ups.status: | sed 's/.*:  *\([A-Z][ A-Z]*[A-Z]\).*/\1/'`
BATTERY=`echo "${DATA}" | grep battery.charge: | sed 's/.*:  *\([0-9][0-9]*\).*/\1/'`
TIMELEFT=`echo "${DATA}" | grep battery.runtime: | sed 's/.*:  *\([0-9][0-9.]*\).*/\1/'`

MINUTES=$((${TIMELEFT} / 60))

ECHO="${STATUS} - ${BATTERY}% - ${MINUTES}m|Battery=${BATTERY}%;${WARN};${CRIT} Time=${MINUTES}m"

STATUS=`echo "${STATUS}" | sed 's/OL CHRG/OL/'`

if [ "${STATUS}" != "OL" -o ${BATTERY} -lt ${CRIT} ];then
	echo "CRITICAL: ${ECHO}"
	exit 2
elif [ ${BATTERY} -lt ${WARN} ];then
	echo "WARNING: ${ECHO}"
	exit 1
elif [ ${BATTERY} -ge ${WARN} -a ${BATTERY} -le 100 ];then
	echo "OK: ${ECHO}"
	exit 0
fi

echo "UNKNOWN: ${ECHO}"
exit 3
