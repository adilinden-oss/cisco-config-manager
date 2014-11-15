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

/* browse.php   Browse the /tftpboot directory structure.
 */

/* Include configuration */
require_once('./config.php');

/* Get path */
if (isset($_GET['path'])) {
    $dir = sanitize($_GET['path']);
} else {
    $dir = TFTP_FILE_ROOT;
}

/* Download or show files */
if (isset($_GET['download'])) {
    /* Download files */
    $file = sanitize($_GET['download']);
    get_file($file);
} else {
    /* Show files */
    send_header($dir);
    show_files($dir);
    send_footer();
}

function sanitize($path)
{
    /* Don't get back up the directory structure */
    if (preg_match('/\.\./', $path) > 0) {
        echo "Illegal path specified";
        exit;
    }
    /* Make sure we are within DIRROOT */
    if (preg_match('!^'.TFTP_FILE_ROOT.'!', $path) == 0) {
        echo "Illegal access";
        exit;
    }
    return $path;
}

function get_file($file)
{
    $fp = @fopen($file, 'r');
    if (! $fp) {
        echo "Permission denied";
        return false;
    }

    /* Download all files except .txt */
    header("Expires: Mon, 26 Jul 1997 05:00:00 GMT"); // some day in the past
    header("Last-Modified: " . gmdate("D, d M Y H:i:s") . " GMT");
    if (preg_match('/\.txt$/', $file) > 0) {
        header("Content-type: text/plain");
    } else {
        header("Content-type: application/x-download");
        header("Content-Disposition: attachment; filename=" . basename($file));
        header("Content-Transfer-Encoding: binary");
    }
    while (! feof($fp)) {
        print(fread($fp,1024));
        flush();
    }
    fclose($fp);
}

function do_ls($dir) {
    $dh = @opendir($dir);
    if ($dh) {
        while(false !== ($files[] = readdir($dh))) {
        }
        return $files;
    }
    return false;
}

function show_files($dir)
{
    if (! $files = do_ls($dir)) {
        echo "Failed to access location\n";
        return false;
    }

    asort($files);
    foreach ($files as $file) {
        /* Ignore dots and empti files */
        if ($file == '' || $file == '.' || $file == '..') {
            continue;
        }
        /* Prepend dir */
        $path = $dir . '/' . $file;
        /* Show file */
        if (is_file($path)) {
            echo '<img src="./icons/generic.png" alt="file">&nbsp;&nbsp;';
            echo '<a href="'.$_SERVER['PHP_SELF'].'?download='.$path.'">'.$file."</a><br>\n";
        }
        /* Show dir */
        if (is_dir($path)) {
            echo '<img src="./icons/dir.png" alt="dir">&nbsp;&nbsp;';
            echo '<a href="'.$_SERVER['PHP_SELF'].'?path='.$path.'">'.$file."</a><br>\n";
        }
    }
}

function send_header($dir)
{
?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
  <title>Browse: <?php echo $dir; ?></title>
</head>
<body>
<h2>Browse: <?php echo $dir; ?></h2>
<p>
<?php 
}   
      
function send_footer()
{   
?>  
</body>
</html>
<?php 
}     

?>
