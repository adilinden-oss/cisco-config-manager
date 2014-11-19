<?php
/* Copyright (C) 2005-2014 Adi Linden <adi@adis.ca>
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

/* tftpd-file.php   This file is included by tftpd.php. I contains the 
 *                  functions that are specific to accessing the local 
 *                  file system. 
 */

/* 
 *  Do NOT run this script through a browser. 
 *  Needs to be accessed from a shell.
 */
if ($_SERVER["SHELL"] != '/bin/bash' && $_SERVER["SHELL"] != '/bin/sh') {
    die("<br><strong>This script is cannot be run through browser!</strong>");
}

/* Include configuration */
require_once('../config.php');

/* Handle file requests */
function tftpd_handle_file ($c_sock, $sock, $opcode, $mode, $path, $file)
{
    /* Accessing file in TFTP_FILE_ROOT */
    if (!preg_match('/\/$/', $path) && $path != '') {
        $path .= '/';
    }
    $file = TFTP_FILE_ROOT . '/' . $path . $file;
    tftpd_log('1', 'access file: '.$file);

    /* Deny any accesses to paths containing ../ */
    if (preg_match('/\.\.\//', $file)) {
        tftpd_send_nak($c_sock, $sock, TFTP_EACCESS, 'path contains ../');
        return false;
    }
    
    /* Check general file permissions, we can't stat() a non existing file
     * See stat.h for details on the stat() bit masks.
     *
     * Need to use lstat() to check symlink, stat() to check file the
     * symlink points to. !!! To allow symlinks change to sast() !!!
     */
    if (! $stat = @lstat($file)) {
        tftpd_send_nak($c_sock, $sock, TFTP_ENOTFOUND, 'cannot stat');
        return false;
    }

    /* Don't allow symlinks 
     * #define S_IFMT  00170000
     * #define S_IFLNK  0120000
     * #define S_ISLNK(m)      (((m) & S_IFMT) == S_IFLNK)
     */
    if (($stat['mode'] & 00170000) === 0120000) {
        tftpd_send_nak($c_sock, $sock, TFTP_EACCESS, 'file is symlink');
        return false;
    }
    /* Handle read request */
    if ($opcode == TFTP_RRQ) {
        /* Is file publically readable?
         * #define S_IROTH 00004
         */
        if (($stat['mode'] & 00004) === 00 ) {
            tftpd_send_nak($c_sock,$sock,TFTP_EACCESS,'no public read access');
            return false;
        }
        /* Open file for reading */
        $fp = fopen($file, 'r', false);
        if (! $fp) {
            tftpd_send_nak($c_sock, $sock, TFTP_UNDEF, 'fopen() failed');
            return false;
        }
        /* Send file to peer */
        tftpd_send_file($c_sock, $sock, $fp);
        fclose($fp);
        return true;
    }

    /* Handle write request */
    if ($opcode == TFTP_WRQ) {
        /* Is file publically writable?
         * #define S_IWOTH 00002
         */
        if (($stat['mode'] & 00002) === 00) {
            tftpd_send_nak($c_sock,$sock,TFTP_EACCESS,'no public write access');
            return false;
        }
        /* Check read-only flag */
        if (TFTP_FILE_RO) {
            tftpd_send_nak($c_sock,$sock,TFTP_EACCESS,'write access disabled');
            return false;
        }
        /* Open file for writing */
        $fp = fopen($file, 'w', false);
        if (! $fp) {
            tftpd_send_nak($c_sock, $sock, TFTP_UNDEF,'fopen() failed');
            return false;
        }
        /* Receive file from peer */
        tftpd_recv_file($c_sock, $sock, $fp);
        fclose($fp);
        return true;
    }

    /* We should never get here */
    fclose($fp);
    tftpd_send_nak($c_sock, $sock, TFTP_EBADOP, 'ouch! not good...');
    return false;
}

/* Receive file */
function tftpd_recv_file($s, $sock, $fp)
{
    $block = 0;
    $xfer_byte = 0;
    $xfer_time = tftpd_microtime();
    do {
        if (! tftpd_receive_data($s, $sock, $block, $data)) {
            return false;
        }
        $r = fwrite($fp, $data);
        if ($r != strlen($data)) {
            tftpd_send_nak($c_sock, $sock, TFTP_ENOSPACE, 'disk full?');
            return false;
        }
        $block++;
        if ($block > 65535) {
            $block = 0;
        }
        $xfer_byte += strlen($data);
    } while (strlen($data) == 512);

    /* Be a good citizen and churn out one last ACK */
    $s_buf = pack('nn', TFTP_ACK, $block);
    tftpd_send_packet($s, $sock, $s_buf);

    /* Log our success */
    $xfer_time = round(tftpd_microtime() - $xfer_time, 3);
    tftpd_log('1', 'received '.$xfer_byte.' bytes in '.$xfer_time.' seconds');
}

/* Send file */
function tftpd_send_file($s, $sock, $fp)
{
    $block = 1;
    $xfer_byte = 0;
    $xfer_time = tftpd_microtime();
    while (!feof($fp)) {
        $data = fread($fp, TFTP_SEGSIZE);
        if (! tftpd_send_data($s, $sock, $block, $data)) {
            return false;
        }
        $block++;
        if ($block > 65535) {
            $block = 0;
        }
        $xfer_byte += strlen($data);
    }

    /* Log our success */
    $xfer_time = round(tftpd_microtime() - $xfer_time,3);
    tftpd_log('1', 'sent '.$xfer_byte.' bytes in '.$xfer_time.' seconds');
}

?>
