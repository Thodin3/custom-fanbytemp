# custom-fanbytemp
This script is complementary to ethOS and provide a custom behavior for fan speed based on GPU temperatures

## Features
+ Every 5s, the custom script (as a deamon) will check GPU temperatures and modify */home/ethos/local.conf* if necessary.
+ In the *local.conf*, a line is modified (each other lines are copied as is): `fan c94e13 62 64 62 66 53 64` it controls fan speed of each GPU of your RIG worker.
+ Only one worker is currently supported.
+ It will also trigger *overclock* command to take the new fan speed into account.
+ After this, the script will check the real fan speed and ajust the conf accordingly.
+ After your RIG booted, the ajustment with real fan speed is not done until the temperatures are stable.
+ Then it will standby until an new change of GPU temperatures.
+ An hysteresis of 1 is applied for temperature going down.

### Installation
- go to your RIG and copy *custom-fanbytemp-daemon.php* and *custom-fanbytemp.sh* in */home/ethos*
- modify `const CUR_WORKER = 'c94e13';` with your worker id in *custom-fanbytemp-daemon.php*
**NOTICE:** `c94e13` is a SAMPLE rig/[worker]/hostname, change it to the rig/[worker]/hostname of YOUR RIG
- modify */home/ethos/custom.sh* like *custom.sh*
- restart your ethOS RIG
- check your logs in */home/ethos/custom-fanbytemp.log*
- enjoy your new silenced RIG!

### Usage
- You can manage the daemon with command *custom-fanbytemp.sh* `[start*|stop|restart]` *=default
    - when stopped, the daemon will revert your fans to `globalfan` value in *local.conf*

### Tests
- remove `#` in *custom-fanbytemp-daemon.php*
- modify the files in test/ folder :
	- *sample_fan.txt* is the list of Fans % speed
	- *sample_local.conf* is your local.conf that is modified by the script
	- *sample_temps.txt* is the list of GPU temperatures
