#!/bin/bash

if [ "$1" == "--force" ] || [ "$1" == "--kill" ]
then
	pkill -ef '/home/ethos/custom-fanbytemp-daemon.php'
fi

proc=$(ps -aux | grep php | grep custom-fanbytemp-daemon)

if [ "$1" != "--kill" ]
then
	if [ "$proc" == "" ]
	then
		/usr/bin/php /home/ethos/custom-fanbytemp-daemon.php &
		echo "Success!"
	else
		echo "Already launched!"
	fi
fi
