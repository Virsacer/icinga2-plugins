#!/bin/sh

WARN="80"
CRIT="90"

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

DATA=`cat /proc/meminfo`

MemTotal=`echo "${DATA}" | grep ^MemTotal: | sed 's/.*:  *\([0-9][0-9]*\).*/\1/'`
MemFree=`echo "${DATA}" | grep ^MemFree: | sed 's/.*:  *\([0-9][0-9]*\).*/\1/'`
MemAvailable=`echo "${DATA}" | grep ^MemAvailable: | sed 's/.*:  *\([0-9][0-9]*\).*/\1/'`
Cached=`echo "${DATA}" | grep ^Cached: | sed 's/.*:  *\([0-9][0-9]*\).*/\1/'`
Buffers=`echo "${DATA}" | grep ^Buffers: | sed 's/.*:  *\([0-9][0-9]*\).*/\1/'`
SReclaimable=`echo "${DATA}" | grep ^SReclaimable: | sed 's/.*:  *\([0-9][0-9]*\).*/\1/'`

MemTotal=$((${MemTotal} * 1024))
MemFree=$((${MemFree} * 1024))
MemAvailable=$((${MemAvailable} * 1024))
Cached=$((${Cached} * 1024))
Buffers=$((${Buffers} * 1024))
SReclaimable=$((${SReclaimable} * 1024))

Cached=$((${Cached} + ${SReclaimable}))
MemUsed=$((${MemTotal} - ${MemAvailable}))
MemUsed2=$((${MemTotal} - ${MemFree} - ${Cached} - ${Buffers}))

ECHO=`awk "BEGIN {printf \"%.0f\",${MemUsed}/${MemTotal}*100}"`"% "`awk "BEGIN {printf \"%.3f\",${MemUsed}/1024/1024/1024}"`"GB/"`awk "BEGIN {printf \"%.3f\",${MemTotal}/1024/1024/1024}"`"GB"
PERF="|used=${MemUsed2}B;"`awk "BEGIN {printf \"%.0f\",${MemTotal}*${WARN}/100}"`";"`awk "BEGIN {printf \"%.0f\",${MemTotal}*${CRIT}/100}"`";0;${MemTotal} cached=${Cached}B;;;0;${MemTotal} buffers=${Buffers}B;;;0;${MemTotal} free=${MemFree}B;;;0;${MemTotal}"

if [ ${MemUsed} -ge `awk "BEGIN {printf \"%.0f\",${MemTotal}*${CRIT}/100}"` ];then
	echo "CRITICAL: ${ECHO}${PERF}"
	exit 2
elif [ ${MemUsed} -ge `awk "BEGIN {printf \"%.0f\",${MemTotal}*${WARN}/100}"` ];then
	echo "WARNING: ${ECHO}${PERF}"
	exit 1
else
	echo "OK: ${ECHO}${PERF}"
	exit 0
fi
