#!/bin/bash

#command[check_disk]=sudo /usr/lib/nagios/plugins/check_disk.sh -w $ARG1$ -c $ARG2$ -d $ARG3$

#ZFS confusion -> see https://oshogbo.vexillium.org/blog/65/

WARNING_PERCENT="85"
CRITICAL_PERCENT="90"
WARNING_LARGE="95"
CRITICAL_LARGE="98"
DISK="-l"
IGNORE=""

while getopts "w:c:d:r:i:" OPT; do
	case "${OPT}" in
		w)
			WARNING_PERCENT=${OPTARG}
			WARNING_LARGE=${OPTARG}
			;;
		c)
			CRITICAL_PERCENT=${OPTARG}
			CRITICAL_LARGE=${OPTARG}
			;;
		d)
			DISK=${OPTARG}
			;;
		i)
			IGNORE=${OPTARG}
			;;
		:)
			echo "Error: -${OPTARG} requires an argument."
			exit 3
			;;
		\?)
			echo "Usage: $0 [ -w WARNING ] [ -c CRITICAL ] [ -d DISK ] [ -i IGNORE ]" 1>&2
			exit 3
			;;
	esac
done

DATA=`timeout 10 df -PT ${DISK} 2> /dev/null`
if [ $? -ne 0 ];then
	`timeout -v 10 df ${DISK}`
	exit 3
fi
if [ "${DISK}" != "-l" ];then
	COUNT=`echo "${DATA}" | egrep "${DISK}" | wc -l`
	if [ "${COUNT}" -eq 0 ];then
		echo "CRITICAL - DISK '${DISK}' not present"
		exit 2
	fi
fi
if [ ! -z "${IGNORE}" ];then
	DATA=`echo "${DATA}" | egrep -v "${IGNORE}"`
fi

STATUS=0
OUTPUT=""
PERFORMANCE="|"

while read -r FS; do
	FS=(${FS})
	case ${FS[1]} in
		Type|devtmpfs|overlay|tmpfs)
			continue
			;;
		zfs)
			SIZE=`zfs list -Hpo quota ${FS[6]}`
			USED=`zfs list -Hpo used ${FS[6]}`
			AVAI=`zfs list -Hpo avail ${FS[6]}`
			if [ ${SIZE} -le 0 ];then
				SIZE=$((${AVAI}+${USED}))
			fi
			;;
		*)
			SIZE=$((${FS[2]}*1024))
			USED=$((${FS[3]}*1024))
			AVAI=$((${FS[4]}*1024))
			;;
	esac
	if [ "${DISK}" == "-l" ];then
		OUTPUT="${OUTPUT}\n"
	else
		OUTPUT="${OUTPUT} -"
	fi
	PERCENT=$((100-${AVAI}*100/${SIZE}))
	OUTPUT="${OUTPUT} ${FS[6]} (${FS[1]}) ${PERCENT}% "`echo "scale=3;${USED}/1024/1024/1024" | bc -l`"GB/"`echo "scale=3;${SIZE}/1024/1024/1024" | bc -l`"GB"
	if [ ${SIZE} -ge 1099511627776 ];then
		WARNING_PERCENT=${WARNING_LARGE}
		CRITICAL_PERCENT=${CRITICAL_LARGE}
	fi
	if [ ${PERCENT} -ge ${CRITICAL_PERCENT} ];then
		STATUS=2
		OUTPUT="${OUTPUT} (CRITICAL)"
	else
		if [ ${PERCENT} -ge ${WARNING_PERCENT} ];then
			if [ ${STATUS} -eq 0 ];then
				STATUS=1
			fi
			OUTPUT="${OUTPUT} (WARNING)"
		fi
	fi
	PERFORMANCE="${PERFORMANCE} ${FS[6]}=${USED}B;$(((${AVAI}+${USED})*${WARNING_PERCENT}/100 | bc -l));$(((${AVAI}+${USED})*${CRITICAL_PERCENT}/100 | bc -l));0;${SIZE}"
done <<< "$DATA"

case ${STATUS} in
	2) echo "CRITICAL${OUTPUT}";;
	1) echo "WARNING${OUTPUT}";;
	0) echo "OK${OUTPUT}";;
esac

echo ${PERFORMANCE} | sed -e 's/| /|/'
exit ${STATUS}
