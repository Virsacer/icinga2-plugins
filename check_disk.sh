#!/bin/bash

#ZFS confusion -> see https://oshogbo.vexillium.org/blog/65/

WARN="85"
CRIT="90"
WARN_LARGE="95"
CRIT_LARGE="98"
DISK="-l"
IGNORE=""

while getopts "w:c:d:i:" OPT; do
	case "${OPT}" in
		w)
			WARN=${OPTARG}
			WARN_LARGE=${OPTARG}
			;;
		c)
			CRIT=${OPTARG}
			CRIT_LARGE=${OPTARG}
			;;
		d)
			DISK=${OPTARG}
			;;
		i)
			IGNORE=${OPTARG}
			;;
		*)
			echo "USAGE: $0 [ -w Warning ] [ -c Critical ] [ -d Disk ] [ -i Ignore ]" 1>&2
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
		echo "CRITICAL: DISK '${DISK}' not present"
		exit 2
	fi
fi
if [ ! -z "${IGNORE}" ];then
	DATA=`echo "${DATA}" | egrep -v "${IGNORE}"`
fi

EXIT=0
ECHO=""
PERF="|"

while read -r FS; do
	FS=(${FS})
	if [ ${FS[1]} == "zfs" ];then
		[ -x "$(which zfs)" ] || FS[1]="ZFS"
	fi
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
	PERCENT=$((100-${AVAI}*100/${SIZE}))
	if [ ${SIZE} -ge 1099511627776 ];then
		WARN=${WARN_LARGE}
		CRIT=${CRIT_LARGE}
	fi
	if [ ${USED} -ge $(((${AVAI}+${USED})*${CRIT}/100 | bc -l)) ];then
		if [ "${DISK}" == "-l" ];then
			ECHO="${ECHO}\n[CRITICAL] "
		fi
		EXIT=2
	else
		if [ ${USED} -ge $(((${AVAI}+${USED})*${WARN}/100 | bc -l)) ];then
			if [ "${DISK}" == "-l" ];then
				ECHO="${ECHO}\n[WARNING] "
			fi
			if [ ${EXIT} -eq 0 ];then
				EXIT=1
			fi
		else
			if [ "${DISK}" == "-l" ];then
				ECHO="${ECHO}\n[OK] "
			fi
		fi
	fi
	ECHO="${ECHO}${FS[6]} (${FS[1]}) ${PERCENT}% "`echo "scale=3;${USED}/1024/1024/1024" | bc -l`"GB/"`echo "scale=3;${SIZE}/1024/1024/1024" | bc -l`"GB"
	PERF="${PERF} ${FS[6]}=${USED}B;$(((${AVAI}+${USED})*${WARN}/100 | bc -l));$(((${AVAI}+${USED})*${CRIT}/100 | bc -l));0;${SIZE}"
	if [ "${DISK}" != "-l" ];then
		ECHO=" ${ECHO}"
		PERF="${PERF} percent=${PERCENT}%;${WARN};${CRIT} allocation=${SIZE}B"
	fi
done <<< "$DATA"

case ${EXIT} in
	2) echo -en "CRITICAL:${ECHO}";;
	1) echo -en "WARNING:${ECHO}";;
	0) echo -en "OK:${ECHO}";;
esac

echo ${PERF} | sed -e 's/| /|/'
exit ${EXIT}
