#!/bin/bash
# @ liyvhg
# 
# @method: http GET / http POST
# @param: test4	: test isc-dhcp-server's ipv4 config file
# @param: test6	: test isc-dhcp-server's ipv6 config file
# @param: restart4 : restart isc-dhcp-server
# @param: restart6 : restart isc-dhcp-server6
# 
# maybe this script need root and chmod +s
# 
PATH="/usr/local/sbin:/usr/local/bin:/usr/sbin:/usr/bin:/sbin:/bin:/usr/games"

DHCPD_BIN="/usr/sbin/dhcpd"
DHCPD_PARAM4="-4 -t -cf"
DHCPD_PARAM6="-6 -t -cf"
DHCPD_CONFIG4="/etc/dhcp3/dhcpd.conf"
#DHCPD_CONFIG4="/opt/software/isc-dhcp-test/isc-dhcp-configurator/dhcpd-include-ipv4.conf"
DHCPD_CONFIG6="/etc/dhcp3/dhcpdv6.conf"

DHCPDV4_SERVICENAME="dhcp3-server"
DHCPDV6_SERVICENAME="dhcpv6-server"


SCRIPT_DHCPD_CHECK_IPV4="${DHCPD_BIN} ${DHCPD_PARAM4} ${DHCPD_CONFIG4}"
SCRIPT_DHCPD_CHECK_IPV6="${DHCPD_BIN} ${DHCPD_PARAM6} ${DHCPD_CONFIG6}"

SCRIPT_DHCPD_RESTART_IPV4="/usr/sbin/service ${DHCPDV4_SERVICENAME} restart"
SCRIPT_DHCPD_RESTART_IPV6="/usr/sbin/service ${DHCPDV6_SERVICENAME} restart"

SCRIPT_DHCPD_STATUS_IPV4="/usr/sbin/service $DHCPDV4_SERVICENAME status"
SCRIPT_DHCPD_STATUS_IPV6="/usr/sbin/service $DHCPDV6_SERVICENAME status"

function CheckConfigDhcpServerIpv4()
{
	${SCRIPT_DHCPD_CHECK_IPV4} 2>&1;
}

function CheckConfigDhcpServerIpv6()
{
	${SCRIPT_DHCPD_CHECK_IPV6} 2>&1;
}

function RestartDhcpServerIpv4()
{
	#echo -e "${SCRIPT_DHCPD_RESTART_IPV4}<br>\n";
	echo `${SCRIPT_DHCPD_RESTART_IPV4} 2>&1`;
	#echo `df -h / | grep -v Filesystem`
	#echo `./run-root \"${SCRIPT_DHCPD_RESTART_IPV4}\"` 2>&1;
	#echo `./run-root /usr/sbin/service.sys\ isc-dhcp-server\ restart 2>&1`;
	#echo `./run-root /etc/init.d/isc-dhcp-server\ restart 2>&1`;
	#echo `./run-root 2>&1`;
}

function RestartDhcpServerIpv6()
{
	${SCRIPT_DHCPD_RESTART_IPV6} 2>&1;
}

function CheckStatusDhcpServerIpv4()
{
	${SCRIPT_DHCPD_STATUS_IPV4} 2>&1;
}

function CheckStatusDhcpServerIpv6()
{
	${SCRIPT_DHCPD_STATUS_IPV6} 2>&1;
}

echo -e "Content-type: text/html\n\n";
echo "<html>"
echo "<title>"
echo -e "cgi test\n"
echo "</title>"
echo "<body>"
echo -e "<h1> hello , any body ..</h1>"

echo -e "Method: $REQUEST_METHOD<br>"

if [ "$REQUEST_METHOD" = "POST" ]; then
	#echo "POST"
	_F_QUERY_STRING=`dd count=$CONTENT_LENGTH bs=1 2> /dev/null`"&"
	if [ "$QUERY_STRING" != "" ] ; then
			_F_QUERY_STRING="$_F_QUERY_STRING""$QUERY_STRING""&"
	fi
	echo  "QueryString: \"${QUERY_STRING}\"<br>"
	echo  "QueryString: \"${_F_QUERY_STRING}\"<br>"
elif [ "$REQUEST_METHOD" == "GET" ];then
	echo  "QueryString: \"${QUERY_STRING}\"<br>"
	
	# switch 
	case "${QUERY_STRING}" in
		"test4" | "TEST4" )
			echo "Will test ipv4 config file<br>"
			echo "Do something for test ipv4 ...<br>"
			CheckConfigDhcpServerIpv4;
			;;
		"test6" | "TEST6" )
			echo "Will test ipv6 config file<br>"
			echo "Do something for test ipv6 ...<br>"
			CheckConfigDhcpServerIpv6;
			;;
		"restart4" | "RESTART4" )
			echo "Will restart isc-dhcpd3-server<br>"
			echo "Do something for restart dhcpv4 server ...<br>"
			RestartDhcpServerIpv4;
			;;
		"restart6" | "RESTART6" )
			echo "Will restart isc-dhcpdv6-server<br>"
			echo "Do something for dhcpv6 server ...<br>"
			RestartDhcpServerIpv6;
			;;
		"status4" | "STATUS4" )
			echo "will check isc-dhcpd3-server status<br>"
			CheckStatusDhcpServerIpv4;
			;;
		"status6" | "STATUS6" )
			echo "will check isc-dhcpdv6-server status<br>"
			CheckStatusDhcpServerIpv6;
			;;
		* )	
			echo "What can I do for you?<br>"
			;;
	esac
	
else
	echo "some err <br>"
fi

echo "<br>"
echo "<br>"
#/usr/sbin/dhcpd -4 -t -cf /opt/software/isc-dhcp-test/isc-dhcp-configurator/dhcpd-include-ipv4.conf 2>&1

echo "<br><br>"
#echo "===this is the end===<br>"
echo "</body>"
echo "</html>"

