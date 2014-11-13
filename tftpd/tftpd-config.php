<?php
/* $Id: tftpd-config.php,v 1.4 2005-11-22 07:07:11 adicvs Exp $
 * 
 * Copyright (C) 2005 Adi Linden <adi@adis.on.ca>
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

/* tftpd-config.php This file is included by tftpd.php. I contains the 
 *                  functions that are specific to accessing configuration
 *                  files located in a mysql database.
 */

/* Database access */
define ('TFTP_DB',              'ccm');
define ('TFTP_DB_HOST',         'localhost');
define ('TFTP_DB_USER',         'ccm_user');
define ('TFTP_DB_PASS',         'schmack');

/* Include functions we need */
require_once('../db/db.php');       /* Database functions */

/* 
 *  Do NOT run this script through a browser. 
 *  Needs to be accessed from a shell.
 */
if ($_SERVER["SHELL"] != '/bin/bash' && $_SERVER["SHELL"] != '/bin/sh') {
    die("<br><strong>This script is cannot be run through browser!</strong>");
}

/* Handle configuration request */
function tftpd_handle_config ($c_sock, $sock, $opcode, $mode, $path, $file)
{
    tftpd_log('1', 'access config: '.$file);

    /* Establish database connection */
    $db = new db(TFTP_DB_HOST, TFTP_DB_USER, TFTP_DB_PASS, TFTP_DB);

    /* Authorize access */
    if (!tftpd_authorize_config ($c_sock, $sock, $path, $file, $db)) {
        return false;
    }

    /* Handle read request */
    if ($opcode == TFTP_RRQ) {
        tftpd_send_config($c_sock, $sock, $db);
        unset($db);
        return true;
    }

    /* Handle write request */
    if ($opcode == TFTP_WRQ) {
        tftpd_recv_config($c_sock, $sock, $db);
        unset($db);
        return true;
    }

    /* We should never get here */
    unset($db);
    tftpd_send_nak($c_sock, $sock, TFTP_EBADOP, 'ouch! not good...');
    return false;
}

/* Authorize access */
function tftpd_authorize_config ($s, $sock, $path, $file, &$db)
{
    /* Sanitize path and filename */
    $path = preg_replace('/^\$(.*)\$$/', '$1', $path);
    $pass = mysql_real_escape_string($path);
    $file = mysql_real_escape_string($file);

    /* Authorize access access 
     * Note that the $db object will keep the query result for use later in
     * the script. In particular we will retrieve 'device' later.
     */
    /* Check for filename first */
    $sql = "SELECT id,filename,password FROM devices WHERE filename='$file'";
    tftpd_log('25', 'sql query string: "'.$sql.'"');
    if ($db->query($sql) != 1) {
        tftpd_send_nak($s,$sock,TFTP_ENOTFOUND,'config not found');
        return false;
    }
    /* Check for filename password match next */
    $sql = "SELECT id,filename,password FROM devices WHERE filename='$file' AND password='$path'";
    tftpd_log('25', 'sql query string: "'.$sql.'"');
    if ($db->query($sql) != 1) {
        tftpd_send_nak($s,$sock,TFTP_EACCESS,'config not found');
        return false;
    }
    return true;
}

/* Receive configuration */
function tftpd_recv_config ($s, $sock, &$db)
{
    $recv_data = '';
    $block = 0;
    $xfer_byte = 0;
    $xfer_time = tftpd_microtime();
    do {
        if (! tftpd_receive_data($s, $sock, $block, $data)) {
            return false;
        }
        $recv_data .= $data;
        $block++;
        $xfer_byte += strlen($data);
    } while (strlen($data) == 512);

    /* Be a good citizen and churn out one last ACK */
    $s_buf = pack('nn', TFTP_ACK, $block);
    tftpd_send_packet($s, $sock, $s_buf);

    /* Log our success */
    $xfer_time = round(tftpd_microtime() - $xfer_time, 3);
    tftpd_log('1', 'received '.$xfer_byte.' bytes in '.$xfer_time.' seconds');

    /* Write config to database */
    tftpd_db_write_config($db, $recv_data);
}

/* Send configuration */
function tftpd_send_config ($s, $sock, &$db)
{
    /* Read config from database */
    tftpd_db_read_config ($db, $data);

    /* Turn read data from a single string into an array of string with
     * TFTP_SEGSIZE length for each array element
     */
    $data = str_split($data, TFTP_SEGSIZE);

    /* Write packets */
    $block = 1;
    $xfer_byte = 0;
    $xfer_time = tftpd_microtime();
    foreach ($data as $chunk) {
        if (! tftpd_send_data($s, $sock, $block, $chunk)) {
            return false;
        }
        $block++;
        $xfer_byte += strlen($chunk);
    }

    /* Log our success */
    $xfer_time = round(tftpd_microtime() - $xfer_time,3);
    tftpd_log('1', 'sent '.$xfer_byte.' bytes in '.$xfer_time.' seconds');
}

/* Read configuration from database */
function tftpd_db_read_config (&$db, &$data)
{
    /* Our $db object still contains the query results from the authentication
     * query earlier. Lets retrieve the device id so we read the proper
     * configuration file
     */
    $r = $db->get_row();
    $device = $r['id'];

    /* Retrieve configuration data */
    $sql = "SELECT content FROM configurations WHERE device='".$device."'";
    tftpd_log('25', 'sql query string: "'.$sql.'"');
    $r = $db->get_row($sql);
    $data = $r['content'];
}

/* Write configuration to database */
function tftpd_db_write_config (&$db, &$data)
{
    /* Our $db object still contains the query results from the authentication
     * query earlier. Lets retrieve the device id so we update the proper
     * configuration file
     */
    $r = $db->get_row();
    $device = $r['id'];
    $filename = $r['filename'];

    /* Retrieve old configuration data
     * Only create patch if we have a previous config
     */
    $sql = "SELECT content FROM configurations WHERE device='".$device."'";
    tftpd_log('25', 'sql query string: "'.$sql.'"');
    if ($db->query($sql) > 0) {

        /* Create patch */
        $r = $db->get_row($sql);
        $old_data = $r['content'];
        $patch = xdiff_string_diff($data, $old_data);

        /* Don't save an empty patch */
        if ($patch != '') {

            /* Escape the data before accessing db */
            $patch = mysql_real_escape_string($patch);

            /* Insert the patch into the database */
            $sql = "INSERT INTO patches (id, device, ts, content) VALUES ('0','$device',NOW(),'$patch')";
            tftpd_log('25', 'sql query string: "'.$sql.'"');
            if ($db->query($sql) > 0) {
                tftpd_log('1', 'db: patch table updated');
            } else {
                tftpd_log('0', 'db: patch table update failed');
            }
        }

        /* Prepare query to update the configuration */
        $data = mysql_real_escape_string($data);
        $sql = "UPDATE configurations SET content='$data' WHERE device='$device'";
    } else {

        /* Prepare query to insert the configuration */
        $data = mysql_real_escape_string($data);
        $sql = "INSERT INTO configurations (device, content) VALUES ('$device', '$data')";
    }

    tftpd_log('25', 'sql query string: "'.$sql.'"');
    $db->query($sql);
    tftpd_log('1', 'db: configurations table updated');

    /* REPLACE the timestamp */
    $sql = "REPLACE INTO updated (device, ts) "
         . "VALUES ('$device',NOW())";
    tftpd_log('25', 'sql query string: "'.$sql.'"');
    $db->query($sql);
}

?>
