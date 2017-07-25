#!/bin/bash
#
# This program is free software: you can redistribute it and/or modify
# it under the terms of the GNU General Public License as published by
# the Free Software Foundation, either version 3 of the License, or
# (at your option) any later version. See LICENSE for more details.
#	 
# This program is distributed in the hope that it will be useful,
# but WITHOUT ANY WARRANTY; without even the implied warranty of
# MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
# See LICENSE for more details.
#	
# The author can't BE LIABLE TO YOU FOR ANY DAMAGE, INCLUDING ANY
# GENERAL, SPECIAL, INCIDENTAL OR CONSEQUENTIAL DAMAGES ARISING OUT OF THE
# USE OR INABILITY TO USE THE PROGRAM. See LICENSE for more details.
#

if [ "$1" == "restart" ] || [ "$1" == "stop" ]
then
	pkill -ef '/home/ethos/custom-fanbytemp-daemon.php'
fi

proc=$(ps -aux | grep php | grep custom-fanbytemp-daemon)

if [ "$1" != "stop" ]
then
	if [ "$proc" == "" ]
	then
		/usr/bin/php /home/ethos/custom-fanbytemp-daemon.php &
		echo "Success!"
	else
		echo "Already launched!"
	fi
fi
