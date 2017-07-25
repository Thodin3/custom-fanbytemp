<?php
/*
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version. See LICENSE for more details.
	 
 	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
	See LICENSE for more details.
	
	The author can't BE LIABLE TO YOU FOR ANY DAMAGE, INCLUDING ANY
	GENERAL, SPECIAL, INCIDENTAL OR CONSEQUENTIAL DAMAGES ARISING OUT OF THE
	USE OR INABILITY TO USE THE PROGRAM. See LICENSE for more details.
 */

// tick use required as of PHP 4.3.0
declare(ticks=1);

// Init constants
const FAN_COMMAND = 'fan';
const GLOBAL_FAN_COMMAND = 'globalfan';
const CUR_WORKER = 'c94e13';
const CR = "\n";
const LOCAL_CONF = '/home/ethos/local.conf';
#const LOCAL_CONF = 'test/sample_local.conf';
const LOG_FILE = '/home/ethos/custom-fanbytemp.log';
#const LOG_FILE = 'test/custom-fanbytemp.log';
const OVERCLOCK_LOG = '/var/log/ethos-overclock.log';
#const OVERCLOCK_LOG = 'test/ethos-overclock.log';
const RELOAD = 0;
const READ_TEMP = 1;
const READ_FAN = 2;
const HYSTERESIS = 1;
const MIN_STABLE_TIME = 3;
const MAX_DIFF = 3;

// Init global variables
$REF_TEMPS_ARRAY = NULL;
$REF_CONF_ARRAY = NULL;
$DIFF_CONF_ARRAY = NULL;
$READ_FAN = NULL;
$STABLE_TIME = -6;
$LOG_ROTATE_DATE = '';
$STANDBY = FALSE;

/**
 * execute a command ID
 * @param $command_id
 * @return mixed command output
 */
function execute($command_id)
{
    switch ($command_id) {
        case RELOAD:
            $command = '/opt/ethos/sbin/ethos-overclock';
            #$command = "echo 'Conf Reloaded !'";
            break;
        case READ_TEMP:
            $command = '/opt/ethos/sbin/ethos-readdata temps';
            #$command = 'cat test/sample_temps.txt';
            break;
        case READ_FAN:
            $command = '/opt/ethos/sbin/ethos-readdata fan';
            #$command = 'cat test/sample_fan.txt';
            break;
        default:
            $command = '';
            break;
    }
    return `$command`;
}

/**
 * fan percent for conf file according to old temperatures
 * @param $old_temps_array
 * @return array
 */
function fan_percent_conf(&$old_temps_array)
{
    $temps_array = read_temp();
    $array_percent = fan_percent_array($temps_array, $old_temps_array);
    $old_temps_array = $temps_array;
    return $array_percent;
}

/**
 * read the temperature
 * @return array
 */
function read_temp()
{
    $temps = trim(execute(READ_TEMP));
    $temps_array = explode(' ', $temps);
    return $temps_array;
}

/**
 * read the fan speed
 * @return array
 */
function read_fan()
{
    $fans = trim(execute(READ_FAN));
    $fans_array = explode(' ', $fans);
    return $fans_array;
}

/**
 * calculate fan percent speed according to temperature value
 * @param $temp
 * @return int
 */
function fan_percent($temp)
{
    if ($temp < 30) {
        return 40;
    }
    if ($temp >= 30 && $temp < 50) {
        return round($temp / 2 + 25);
    }
    if ($temp >= 50 && $temp < 80) {
        return round($temp);
    }
    if ($temp >= 80 && $temp < 90) {
        return round($temp * 2 - 80);
    }
    //$temp>=90
    return 100;
}

/**
 * calculate hysteresis temperature with old one
 * if the temperature is going up nothing is changed
 * @param float $temp
 * @param float $temp_old
 * @return mixed
 */
function hysteresis($temp, $temp_old)
{
    $diff = abs($temp - $temp_old);

    if ($temp > $temp_old || $diff > HYSTERESIS) {
        return $temp;
    } else {
        return $temp_old;
    }
}

/**
 * loop on every temps to get fan percent speed
 * @param array $temp_array
 * @param array $old_temp_array
 * @return array
 */
function fan_percent_array(&$temp_array, $old_temp_array = [0, 0, 0, 0, 0, 0, 0, 0])
{
    $i = 0;
    $percent_array = array();
    foreach ($temp_array as &$value) {
        $value = hysteresis($value, $old_temp_array[$i]);
        array_push($percent_array, fan_percent($value));
        $i++;
    }
    return $percent_array;
}

/**
 * convert the array to string for display
 * @param $percent_array
 * @return string
 */
function display_string($percent_array)
{
    $display = '';
    if ($percent_array != NULL) {
        foreach ($percent_array as $value) {
            $display = "$display $value";
        }
    }
    return $display;
}

/**
 * put the new conf to ethOS in local.conf file
 * @param array $fan_values
 * @param string $filename
 * @return bool
 */
function put_conf($fan_values, $filename = LOCAL_CONF)
{
    $fh = fopen($filename, 'r+');

    $new_conf = '';
    $changed = FALSE;

    if ($fh != NULL) {
        while (!feof($fh)) {

            $line = fgets($fh);
            $conf_line = explode(' ', $line);
            $command = '';
            $worker = '';

            if (count($conf_line) >= 2) {
                $command = trim($conf_line[0]);
                $worker = trim($conf_line[1]);
            }

            // check for empty indexes
            if ($command == FAN_COMMAND AND $worker == CUR_WORKER) {
                $display = display_string($fan_values);
                $new_line = FAN_COMMAND . ' ' . CUR_WORKER . $display . CR;
                if ($new_line != $line) {
                    daemon_log(CR . "Final Percents: " . $display);
                    $changed = TRUE;
                }
            } else {
                $new_line = $line;
            }
            $new_conf .= $new_line;
        }

        if ($changed) {
            // using file_put_contents() instead of fwrite()
            file_put_contents($filename, $new_conf);
        }

        fclose($fh);
    } else {
        daemon_log("File not found!");
    }

    return $changed;
}

/**
 * get the old value from globalfan in local.conf file
 * @param string $filename
 * @return string $fan_value
 */
function read_conf($filename = LOCAL_CONF)
{
    $fh = fopen($filename, 'r');

    $fan_value = '';

    if ($fh != NULL) {
        while (!feof($fh)) {

            $line = fgets($fh);
            $conf_line = explode(' ', $line);
            $fan_value = '';

            if (count($conf_line) == 2 && (trim($conf_line[0]) == GLOBAL_FAN_COMMAND)) {
                $fan_value = trim($conf_line[1]);
                break;
            }
        }

        fclose($fh);
    } else {
        daemon_log("File not found!");
    }

    return $fan_value;
}

/**
 * logger for this daemon
 * @param $raw_text
 * @param bool $carriage_return
 * @internal param bool $clean
 */
function daemon_log($raw_text, $carriage_return = TRUE)
{
    global $LOG_ROTATE_DATE;

    $current_date = date("Y-m-d");

    if ($current_date != $LOG_ROTATE_DATE) {
        $fh = fopen(LOG_FILE, 'w');
        $LOG_ROTATE_DATE = $current_date;
    } else {
        $fh = fopen(LOG_FILE, 'a');
    }

    if ($fh != NULL) {
        $text = $raw_text;
        if ($carriage_return) {
            $text .= CR;
        }
        fwrite($fh, $text);
        fclose($fh);
    }
}

/**
 * check if overclock has finished reading log
 * @return bool
 */
function reload_finished()
{
    $content = file_get_contents(OVERCLOCK_LOG);

    if ($content != FALSE) {
        return (strstr($content, "overclock finished") != FALSE);
    }

    daemon_log(OVERCLOCK_LOG . " not found!");
    return TRUE;
}

/**
 * reload conf with overclock ethOS command
 * @param $reload
 */
function reload_conf($reload)
{
    global $STANDBY;

    $finished = reload_finished();
    if (!$finished) {
        daemon_log('Overclock not finished!');
    } else if ($reload) {
        log_stats();
        $STANDBY = FALSE;
        daemon_log(execute(RELOAD));
    } else if ($STANDBY) {
        daemon_log(".", FALSE);
    } else {
        log_stats();
        $STANDBY = TRUE;
    }
}

/**
 * log all stats variables
 */
function log_stats()
{
    global $DIFF_CONF_ARRAY;
    global $REF_CONF_ARRAY;
    global $STABLE_TIME;
    global $READ_FAN;

    daemon_log("Ref Percents: " . display_string($REF_CONF_ARRAY));
    daemon_log("Fan Percents: " . display_string($READ_FAN));
    daemon_log("Diff Percents: " . display_string($DIFF_CONF_ARRAY));
    daemon_log("Stable time: " . $STABLE_TIME);
}

/**
 * compare reference fan percent to real fan percent
 * @return array
 */
function ref_conf_compare()
{
    global $REF_CONF_ARRAY;
    global $DIFF_CONF_ARRAY;
    global $READ_FAN;

    $current_fan_percent = read_fan();
    // Store fan value for logs
    $READ_FAN = $current_fan_percent;

    $diff_fan_percent_array = array();
    for ($i = 0; $i < count($REF_CONF_ARRAY); $i++) {
        $diff = $REF_CONF_ARRAY[$i] - $current_fan_percent[$i];
        if ($DIFF_CONF_ARRAY != NULL) {
            // check moy value with previous one
            $diff = ceil(($DIFF_CONF_ARRAY[$i] + $diff) / 2);
        }
        $diff = min($diff, MAX_DIFF);
        $diff = max($diff, -MAX_DIFF);
        array_push($diff_fan_percent_array, $diff);
    }
    return $diff_fan_percent_array;
}

/**
 * apply the previous difference between fan percent and real fan percent to the new change
 * @return array|null
 */
function new_fan_percent_conf()
{
    global $REF_CONF_ARRAY;
    global $DIFF_CONF_ARRAY;

    if ($DIFF_CONF_ARRAY != NULL) {
        $new_reference_fan_percent = array();
        for ($i = 0; $i < count($REF_CONF_ARRAY); $i++) {
            array_push($new_reference_fan_percent, $REF_CONF_ARRAY[$i] + $DIFF_CONF_ARRAY[$i]);
        }
        return $new_reference_fan_percent;
    } else {
        return $REF_CONF_ARRAY;
    }
}

/**
 * put real conf with diff from real fan speed
 * @return bool
 */
function put_real_conf()
{
    $new_fan_percent_conf = new_fan_percent_conf();
    $changed = put_conf($new_fan_percent_conf);
    return $changed;
}

/**
 * Callback in case of Kill signal
 * Revert conf to global conf default and reload
 * @param $signo
 */
function revert_handler($signo)
{
    global $REF_CONF_ARRAY;
    global $DIFF_CONF_ARRAY;

    $global_fan = read_conf();
    daemon_log("Back to default (SIG=" . $signo . "): " . $global_fan);
    $DIFF_CONF_ARRAY = NULL;
    foreach ($REF_CONF_ARRAY as &$value) {
        $value = $global_fan;
    }
    reload_conf(put_real_conf());
    exit(1);
}

if (php_uname('s') == 'Linux') {
    // Install the signal handlers
    pcntl_signal(SIGTERM, "revert_handler"); // kill
    pcntl_signal(SIGHUP, "revert_handler"); // kill -s HUP or kill -1
    pcntl_signal(SIGINT, "revert_handler"); // Ctrl-C
}

/**
 * Infinite loop for daemon
 */
while (TRUE) {
    $changed = FALSE;
    $fan_percent_conf = fan_percent_conf($REF_TEMPS_ARRAY);
    if ($REF_CONF_ARRAY != $fan_percent_conf) {
        $REF_CONF_ARRAY = $fan_percent_conf;
        if ($STABLE_TIME > 0) {
            $STABLE_TIME = 0;
        }
        $changed = put_real_conf();
    } else {
        $diff_fan_percent = ref_conf_compare();
        if ($STABLE_TIME >= MIN_STABLE_TIME && $DIFF_CONF_ARRAY != $diff_fan_percent) {
            $DIFF_CONF_ARRAY = $diff_fan_percent;
            $changed = put_real_conf();
        } else if ($STABLE_TIME < MIN_STABLE_TIME) {
            $STABLE_TIME++;
        }
    }
    reload_conf($changed);
    sleep(5);
}

// Reset the signal handlers
pcntl_signal(SIGHUP, SIG_DFL);
pcntl_signal(SIGINT, SIG_DFL);
pcntl_signal(SIGTERM, SIG_DFL);
