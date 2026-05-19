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

EXIT=0
ECHO="${STATUS} - ${BATTERY}% - ${MINUTES}m|Battery=${BATTERY}%;${WARN};${CRIT} Time=${MINUTES}m"

if [ "${STATUS}" != "OL" -a "${STATUS}" != "OL CHRG" -a "${STATUS}" != "OL TRIM" ];then
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
