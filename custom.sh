#!/bin/bash
# /home/ethos/custom.sh
# This file is where you should put any custom scripting you would like to run. 
# It will run once, after Xorg (Graphical interface) starts up, any commands which you absolutely have to run before xorg should be located in rc.local before the "exit 0"
# All scripting in this file should be before the "exit 0" as well.  Preface any commands which require 'root' privileges with the "sudo" command
# Examples script running as user ethos: 
# my_command --my flags
# Example of a php script running as user root:
# sudo php /path/to/my/script.php

/home/ethos/custom-fanbytemp.sh

exit 0
