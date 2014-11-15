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

/* tftpd.php    A tftp daemon written in php. This implementation aims to be 
 *              RFC 1350 compliant. This is a the daemonized version. It 
 *              forks child processes to handle up to TFTP_MAXCHILD concurrent 
 *              tftp requests.
 *
 *              The daemon is primarily meant to store and retrieve Cisco
 *              device configuration files in a SQL database. However, it
 *              also supports access to files stored in the files system.
 *              The TFTP_FILE_ constants control this behaviour.
 *
 *              To access a config file authentication is required. The
 *              password is provided in the path information. A path
 *              beginning and ending with $ indicated a config file request.
 */

/* From RFC 1350 (see rfc1350.txt for complete details)
 * 
 * TFTP supports five types of packets, all of which have been mentioned
 * above:
 *
 *        opcode  operation
 *          1     Read request (RRQ)
 *          2     Write request (WRQ)
 *          3     Data (DATA)
 *          4     Acknowledgment (ACK)
 *          5     Error (ERROR)
 *
 * The TFTP header of a packet contains the  opcode  associated  with
 * that packet. 
 *
 *          2 bytes     string    1 byte     string   1 byte
 *          ------------------------------------------------
 * RRQ/WRQ | 01/02  |  Filename  |   0  |    Mode    |   0  |
 *          ------------------------------------------------
 *
 *          2 bytes     2 bytes      n bytes
 *          ----------------------------------
 * DATA    |   03   |   Block #  |   Data     |
 *          ----------------------------------
 *
 *           2 bytes     2 bytes
 *           ---------------------
 * ACK     |   04   |   Block #  |
 *           ---------------------
 *
 *          2 bytes     2 bytes      string    1 byte
 *          -----------------------------------------
 * ERROR   |   05   |  ErrorCode |   ErrMsg   |   0  |
 *          -----------------------------------------
 *
 *  Error Codes
 *
 *      Value     Meaning
 *
 *      0         Not defined, see error message (if any).
 *      1         File not found.
 *      2         Access violation.
 *      3         Disk full or allocation exceeded.
 *      4         Illegal TFTP operation.
 *      5         Unknown transfer ID.
 *      6         File already exists.
 *      7         No such user.
 */

/* Opcodes */
define('TFTP_RRQ',          '1');
define('TFTP_WRQ',          '2');
define('TFTP_DATA',         '3');
define('TFTP_ACK',          '4');
define('TFTP_ERROR',        '5');

/* Error codes, see arpa/tftp.h */
define ('TFTP_EUNDEF',      '0');   /* not defined */
define ('TFTP_ENOTFOUND',   '1');   /* file not found */
define ('TFTP_EACCESS',     '2');   /* access violation */
define ('TFTP_ENOSPACE',    '3');   /* disk full or allocation exceeded */
define ('TFTP_EBADOP',      '4');   /* illegal TFTP operation */
define ('TFTP_EBADID',      '5');   /* unknown transfer ID */
define ('TFTP_EEXISTS',     '6');   /* file already exists */
define ('TFTP_ENOUSER',     '7');   /* no such user */

/* Connections */
define ('TFTP_SEGSIZE',     '512'); /* data bytes per packet */
define ('TFTP_RETRY',       '4');   /* max retries per packet */
define ('TFTP_TIMEOUT',     '4');   /* timout before retry per packet */

/* Daemon */
define ('TFTP_MAX_CHILD',   '20');  /* max number of concurrent children */
define ('TFTP_WAIT_LISTEN', '9');   /* seconds the listen socket blocks */
define ('TFTP_PID_FILE',    '/var/run/tftpd.pid');

/* Logging and debugging */

/* TFTP_LOG_LEVEL
 *    0  - No logging at all, except errors
 *    1  - Basic connection and error reporting to defined log location
 * >=10  - Do not daemonize parent, log to stdout
 * >=20  - Do not spawn children, log to stdout
 */
define ('TFTP_LOG_LEVEL',   '1');
define ('TFTP_USE_SYSLOG',  TRUE);

/* Include functions we need */
require_once('./tftpd-file.php');   /* Handles file requests */
require_once('./tftpd-config.php'); /* Handles configuration requests */

/*  Do NOT run this script through a browser. 
 *  Needs to be accessed from a shell.
 */
if ($_SERVER["SHELL"] != '/bin/bash' && $_SERVER["SHELL"] != '/bin/sh') {
    die("<br><strong>This script is cannot be run through browser!</strong>");
}

/* Do it */
tftpd_daemon();

/* Daemonize */
function tftpd_daemon()
{
    /* Debug, do not fork */
    if (TFTP_LOG_LEVEL >= 10) {
            tftpd_log('10', 'debug, not forking parent');
        tftpd_listen();
        exit(0);
    }

    /* Fork */
    $pid = pcntl_fork();
    switch($pid) {

        /* Child, continues detached */
        case 0:
            posix_setsid();
            tftpd_listen();
            exit(0);

        /* Error, exit with error */
        case -1:
            tftpd_log('0', 'cannot fork');
            exit(1);

        /* Parent, exits without error */
        default:
            if (TFTP_PID_FILE) {
                file_put_contents(TFTP_PID_FILE, $pid . "\n");
            }
            exit(0);
    }
}

/* Listen on the service port */
function tftpd_listen()
{
    /* daemon port and address */
    $sock['d_port'] = 3232;
    $sock['d_port'] = 69;
    $sock['d_addr'] = '0.0.0.0';
    /* child (connect) port and address */
    $sock['c_port'] = '';
    $sock['c_addr'] = '0.0.0.0';
    /* peer (remote) port and address */
    $sock['p_port'] = '';
    $sock['p_addr'] = '';

    /* trach children */
    $children = array();

    /* Create receive socket for the service */
    $d_sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if (! $d_sock) {
        tftpd_log('0', 'could not create daemon socket');
        return;
    }
    $r = socket_bind($d_sock, $sock['d_addr'], $sock['d_port']);
    if (! $r) {
        tftpd_log('0', 'could not bind daemon socket');
        return;
    }
    tftpd_log('1', 'listen: '.$sock['d_addr'].':'.$sock['d_port']);

    /* Set timeout, this allows us to come alive and service child 
     * processes even if no packet has been received
     */
    tftpd_recv_time_out($d_sock, TFTP_WAIT_LISTEN);

    /* Listener loop */
    while(true) {

        /* Receive packet from peer (new connection) */
        $r_buf = '';
        $r = tftpd_recv_packet($d_sock, $sock, $r_buf);

        /* Cleanup child processes */
        foreach ($children as $pid => $v) {
            $w = pcntl_waitpid($pid, $status, WNOHANG);
            /* Child exited */
            if ($w < 0) {
                unset($children[$pid]);
            }
        }

        /* Ignore request if max concurrent connections reached */
        if (count($children) > TFTP_MAX_CHILD) {
            tftpd_log('0', 'max connection limit ('.TFTP_MAX_CHILD.') reached');
            continue;
        }

        /* Ignore request unless we actually received a packet > 0 */
        if ($r < 1) {
            continue;
        }

        /* Log the connection attempt */
        tftpd_log('1', 'connect: '.$sock['p_addr'].':'.$sock['p_port']);

        /* Debug, do not fork */
        if (TFTP_LOG_LEVEL >= 20) {
            tftpd_log('20', 'debug, not forking child');
            tftpd_connect($sock, $r_buf);
            continue;
        }

        /* Fork to service connection request */
        $pid = pcntl_fork();
        switch($pid) {

            /* Child, handles connection request, exits when done */
            case 0:
                tftpd_connect($sock, $r_buf);
                exit(0);

            /* Error, unable to fork */
            case -1:
                tftpd_log('0', 'failed to fork, unable to handle request');
                exit(1);

            /* Parent continues and tracks children */
            default:
                $children[$pid] = $pid;
                break;
        }
    }

    socket_close($d_sock);
}

/* Connect to client */
function tftpd_connect($sock, $r_buf)
{
    /* Create child socket to communicate with peer */
    $c_sock = socket_create(AF_INET, SOCK_DGRAM, SOL_UDP);
    if (! $c_sock) {
        tftpd_log('0', 'could not create child socket');
        return;
    }
    $r = socket_bind($c_sock, $sock['c_addr']);
    if (! $r) {
        tftpd_log('0', 'could not bind child socket');
        return;
    }
    socket_getsockname($c_sock, $sock['c_addr'], $sock['c_port']);
    tftpd_log('11', 'listen: '.$sock['c_addr'].':'.$sock['c_port']);

    /* Parse the request */
    if (! tftpd_recv_request($r_buf, $opcode, $request, $mode)) {
        tftpd_log('1', 'disconnect: invalid request');
        socket_close($c_sock);
        return;
    }
    tftpd_log('11', 'request: "'.$request.'", mode: "'.$mode.'"');

    /* Check mode */
    if (    strcasecmp($mode, 'netascii') != 0 && 
            strcasecmp($mode, 'binary') != 0 &&
            strcasecmp($mode, 'octet') != 0 ) {
        tftpd_send_nak($c_sock, $sock, TFTP_EBADOP, 'unknown mode');
        socket_close($c_sock);
        return;
    }

    /* Sanitize request */
    tftpd_sanitize_request($request, $path, $file);

    /* Handle config requests as indicated by a path delimited with $.
     * Example: $password$
     */
    if (preg_match('/^\$.+\$$/', $path)) {
        tftpd_handle_config ($c_sock, $sock, $opcode, $mode, $path, $file);
    }

    /* Handle file requests */
    else {
        tftpd_handle_file($c_sock, $sock, $opcode, $mode, $path, $file);
    }

    socket_close($c_sock);
}

/* Receive data */
function tftpd_receive_data($s, $sock, $s_block, &$data)
{
    for ($retry = 0; $retry < TFTP_RETRY; $retry++) {

        /* Assemble and send ack packet */
        $s_buf = pack('nn', TFTP_ACK, $s_block);
        tftpd_send_packet($s, $sock, $s_buf);

        /* Set timeout, receive packet, reset timeout */
        tftpd_recv_time_out($s, TFTP_TIMEOUT);
        $r = tftpd_recv_packet($s, $sock, $r_buf);
        tftpd_recv_time_out($s, 0);

        /* Check received packet, should be DATA with incremented block # */
        if ($r > 0) {
            if (tftpd_recv_data($r_buf, &$opcode, &$r_block, &$data)) {
                if ($r_block == $s_block + 1) {
                    return true;
                }
            }
            /* If we didn't get DATA, did we get an error? */
            if (tftpd_recv_nak($r_buf, &$opcode, &$err, &$msg)) {
                tftpd_log('1', 'disconnect: client sent: '.$msg);
                return false;
            }
        }
    }
    /* We timed out */
    tftpd_log('1', 'disconnect: timeout');
    return false;
}

/* Send data */
function tftpd_send_data($s, $sock, $s_block, &$data)
{
    /* Send packet up to TFTP_RETRY number of times */
    for ($retry = 0; $retry < TFTP_RETRY; $retry++) {
        
        /* Assemble and send data packet */
        $s_buf = pack('nna*', TFTP_DATA, $s_block, $data);
        tftpd_send_packet($s, $sock, $s_buf);
        
        /* Set timeout, receive packet, reset timeout */
        tftpd_recv_time_out($s, TFTP_TIMEOUT);
        $r = tftpd_recv_packet($s, $sock, $r_buf);
        tftpd_recv_time_out($s, 0);

        /* Check received packet for ACK with current block # */
        if ($r > 0) {
            if (tftpd_recv_ack($r_buf, &$opcode, &$r_block)) {
                if ($s_block == $r_block) {
                    return true;
                }
            }
            /* If we didn't get ACK, did we get an error? */
            if (tftpd_recv_nak($r_buf, &$opcode, &$err, &$msg)) {
                tftpd_log('1', 'disconnect: client sent: '.$msg);
                return false;
            }
        }
    }
    /* We timed out */
    tftpd_log('1', 'disconnect: timeout');
    return false;
}

/* Sanitize request */
function tftpd_sanitize_request($request, &$path, &$file)
{
    /* Split the request into path and file information. Strip leading
     * '.' and/or '/'. Valid characters are $-_.0-9a-zA-Z 
     */
    $path = '';
    $file = '';
    preg_match('!^\.*/*(?:([$\-_/\.0-9a-zA-Z]*)/)*([$\-_\.0-9a-zA-Z]+)$!', 
                       $request, $matches);
    $path = $matches[1];
    $file = $matches[2];
}

/* Parse received data packet */
function tftpd_recv_data($packet, &$opcode, &$block, &$data)
{
    preg_match('/^(..)(..)(.*)\x00/', $packet, $matches);
    $opcode = hexdec(bin2hex(substr($packet, 0, 2)));
    $block = hexdec(bin2hex(substr($packet, 2, 2)));
    $data = substr($packet, 4);

    if ($opcode == TFTP_DATA) {
        return true;
    }
    return false;
}

/* Parse received nak (error) packet */
function tftpd_recv_nak($packet, &$opcode, &$err, &$msg)
{
    preg_match('/^(..)(..)(.*)\x00/', $packet, $matches);
    //$opcode = hexdec(bin2hex($matches[1]));
    //$err = hexdec(bin2hex($matches[2]));
    $opcode = hexdec(bin2hex(substr($packet, 0, 2)));
    $err = hexdec(bin2hex(substr($packet, 2, 2)));
    $msg = $matches[3];

    if ($opcode == TFTP_ERROR) {
        return true;
    }
    return false;
}

/* Parse received ack packet */
function tftpd_recv_ack($packet, &$opcode, &$block)
{
    preg_match('/^(..)(..)/', $packet, $matches);
    //$opcode = hexdec(bin2hex($matches[1]));
    //$block = hexdec(bin2hex($matches[2]));
    $opcode = hexdec(bin2hex(substr($packet, 0, 2)));
    $block = hexdec(bin2hex(substr($packet, 2, 2)));

    if ($opcode == TFTP_ACK) {
        return true;
    }
    return false;
}

/* Parse received request packet */
function tftpd_recv_request($packet, &$opcode, &$request, &$mode)
{
    /* Split packet into relevant parts, tried using unpack, explode
     * and PCRE function without success. Cannot seem to match the
     * work.
     */
    preg_match('/^(..)(.*)\x00(.*)\x00/', $packet, $matches);
    $opcode = hexdec(bin2hex(substr($packet, 0, 2)));
    $request = $matches[2];
    $mode = $matches[3];

    if ($opcode == TFTP_RRQ || $opcode == TFTP_WRQ) {
        return true;
    }
    return false;
}

/* Create ERROR packet */
function tftpd_send_nak($s, &$sock, $err, $msg = '')
{
    $tftpd_error = array(
            0   => 'Undefined error',
            1   => 'File not found',
            2   => 'Access violation',
            3   => 'Disk full or allocation exceeded',
            4   => 'Illegal TFTP operation',
            5   => 'Unknown transfer ID',
            6   => 'File already exists',
            7   => 'No such user' );

    $buf = pack('nna*', TFTP_ERROR, $err, $tftpd_error[$err]);
    tftpd_send_packet($s, &$sock, &$buf);
    tftpd_log('1', 'abort: ('.$err.') '.$tftpd_error[$err].' - '.$msg);
}

/* Set receive timeout */
function tftpd_recv_time_out($s, $t)
{
    socket_set_option($s,SOL_SOCKET, SO_RCVTIMEO, array("sec"=>$t, "usec"=>0));
    tftpd_log('23', 'recv timout set to '.$t.' secs');
}

/* Receive packet */
function tftpd_recv_packet($s, &$sock, &$buf)
{
    $r = @socket_recvfrom($s, $buf, 516, 0, $sock['p_addr'], $sock['p_port']);
    tftpd_log('22', 'recv packet: '.bin2hex(substr($buf, 0, 16)));
    return $r;
}

/* Send packet */
function tftpd_send_packet($s, &$sock, &$buf)
{
    $r = @socket_sendto($s, $buf, strlen($buf), 0x100, 
            $sock['p_addr'], $sock['p_port']);
    tftpd_log('22', 'send packet: '.bin2hex(substr($buf, 0, 16)));
    return $r;
}

/* Handle messages */
function tftpd_log($ll, $msg)
{
    $id = 'tftpd/'.posix_getpid();

    /* Log to SYSLOG */
    if (TFTP_USE_SYSLOG && TFTP_LOG_LEVEL < 10) {
        define_syslog_variables();
        openlog('tftpd.php', LOG_CONS | LOG_PID, LOG_DAEMON);
        if ($ll == 0) {
            syslog(LOG_ERR, $msg);
        } else if ($ll < 2) {
            syslog(LOG_INFO, $msg);
        }

    /* To stdout if not logging to SYSLOG */
    } else {
        /* Logging according to level */
        if ($ll <= TFTP_LOG_LEVEL) {
            echo $id.' '.$msg."\n";
        }
    }
}

/* Generate time stamp */
function tftpd_microtime()
{
    list($usec, $sec) = explode(" ", microtime());
    return ((float)$usec + (float)$sec);
}

?>
