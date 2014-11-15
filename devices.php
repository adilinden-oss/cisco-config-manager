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

/* devices.php  Manage devices, view configurations and configuration
 *              history.
 */

/* Include configuration */
require_once('./config.php');

/* Include database access library */
require_once('./db/db.php');

/* Open database connextion */
$db = new db(TFTP_DB_HOST, TFTP_DB_USER, TFTP_DB_PASS, TFTP_DB);

/* Process form vars */
$vars = array('action','id','devicename','description','filename','password','ts','patch');
foreach($vars as $var) {
    $vv = "db_".$var;
    if (isset($_POST[$var])) {
        $$var = trim($_POST[$var]);
        $$vv = mysql_real_escape_string($$var);
    } else if (isset($_GET[$var])) {
        $$var = trim($_GET[$var]);
        $$vv = mysql_real_escape_string($$var);
    }
    else {
        $$var = '';
        $$vv = '';
    }
}

/* Process request */
switch ($action) {

    /* Single patch */
    case 'patch':
        if ($id == '') {
            $message[] = "No device specified. ";
            devices_header(NULL, $message);
            devices_table();
            devices_footer();
            break;
        }
        devices_header('View Patch');
        devices_view_patch($db_id, $db_patch);
        devices_footer();
        break;

    /* Single historic configuration */
    case 'history':
        if ($id == '') {
            $message[] = "No device specified. ";
            devices_header(NULL, $message);
            devices_table();
            devices_footer();
            break;
        }
        devices_header('View Historic Configuration');
        devices_view_history($db_id, $db_ts);
        devices_footer();
        break;

    /* List of patches for device */
    case 'patches':
        if ($id == '') {
            $message[] = "No device specified. ";
            devices_header(NULL, $message);
            devices_table();
            devices_footer();
            break;
        }
        devices_header('View Device History');
        devices_list_patches($db_id);
        devices_footer();
        break;

    /* Current configuration */
    case 'view':
        if ($id == '') {
            $message[] = "No device specified. ";
            devices_header(NULL, $message);
            devices_table();
            devices_footer();
            break;
        }
        devices_header('View Device Configuration');
        devices_view($db_id);
        devices_footer();
        break;

    /* Add device form */
    case 'add':
        devices_header('Add New Device');
        devices_edit();
        devices_footer();
        break;

    /* Add device */
    case 'added':
        unset($message);
        if ($devicename == '') {
            $message[] = "Device name is required. ";
        }
        if ($filename == '') {
            $message[] = "File name is required. ";
        }
        $n = $db->query("SELECT id FROM devices WHERE devicename='$db_devicename'");
        if ($n > 0) {
            $message[] = "Duplicate device name '$devicename' not allowed. ";
        }
        $n = $db->query("SELECT id FROM devices WHERE filename='$db_filename'");
        if ($n > 0) {
            $message[] = "Duplicate file name '$filename' not allowed. ";
        }
        
        if (! isset($message)) {
            /* INSERT new device */
            $db->query("INSERT INTO devices (devicename,description,filename,password) 
                    VALUES ('$db_devicename','$db_description','$db_filename','$db_password')");
            $message[] = "Device added. ";
        }

        devices_header(NULL,$message);
        devices_table();
        devices_footer();
        break;

    /* Edit device form */
    case 'edit':
        devices_header('Edit Device');
        devices_edit($id);
        devices_footer();
        break;

    /* Edit device */
    case 'edited':
        unset($message);
        if ($id == '') {
            $message[] = "Bandits! This ain't supposed to be! ";
        }
        if ($devicename == '') {
            $message[] = "Device name is required. ";
        }
        if ($filename == '') {
            $message[] = "File name is required. ";
        }
        $r = $db->get_row("SELECT id FROM devices WHERE devicename='$db_devicename'");
        if ($r['id'] != '' && $r['id'] != $id) {
            $message[] = "Duplicate device name '$devicename' not allowed. ";
        }
        $r = $db->get_row("SELECT id FROM devices WHERE filename='$db_filename'");
        if ($r['id'] != '' && $r['id'] != $id) {
            $message[] = "Duplicate file name '$filename' not allowed. ";
        }

        if ($message == '') {
            /* Update database */
            $db->query("UPDATE devices SET devicename='$db_devicename',description='$db_description',
                    filename='$db_filename',password='$db_password' WHERE id='$db_id'");
            $message[] = "Device updated. ";
        }

        devices_header(NULL, $message);
        devices_table();
        devices_footer();
        break;

    /* List devices */
    default:
        devices_header();
        devices_table();
        devices_footer();
        break;
}

unset($db);
/* End of main script, just functions below */

function devices_view_patch($id, $patch)
{
    global $db;

    /* Device information */
    $r = $db->get_row("SELECT devicename,description,filename,password FROM devices WHERE id='$id'");
?>
<p><a href="<?php echo $_SERVER['PHP_SELF']; ?>">&laquo; List Devices</a>
<a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=view&id=<?php echo $id; ?>">&laquo; Configuration</a>
<a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=patches&id=<?php echo $id; ?>">&laquo; History</a>
<p><strong>Device Name:</strong>  <?php echo $r['devicename']; ?>
<br><strong>File Name:</strong>  <?php echo $r['filename']; ?>
<p><strong>Patch:</strong>
<?php
    /* Get revision for the patch by walking the array */
    $rows = $db->get_rows("SELECT id FROM patches WHERE device='$id' ORDER BY ts ASC");
    for ($rev = count($rows) - 1; $rev >= 0; $rev--) {
        $row = $rows[$rev];
        /* Array with patch id as key and revision as value */
        $patches[$row['id']] = $rev;
    }

    /* Retrieve patch */
    $row = $db->get_row("SELECT ts,content FROM patches WHERE id='$patch'");
    if ($row['ts'] == '') {
        echo "<p><strong>No matching revision found!</strong>\n";
    } else {
        echo "<br>revision: ".$patches[$patch].", date: ".devices_ts_to_date($row['ts'],'%Y-%m-%d %H:%i:%s').", ";
        echo "<pre>\n".$row['content']."</pre>\n";
    }
?>
<p><a href="<?php echo $_SERVER['PHP_SELF']; ?>">&laquo; List Devices</a>
<a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=view&id=<?php echo $id; ?>">&laquo; Configuration</a>
<a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=patches&id=<?php echo $id; ?>">&laquo; History</a>
<?php
}

function devices_view_history($id, $ts)
{
    global $db;

    /* Device information */
    $r = $db->get_row("SELECT devicename,description,filename,password FROM devices WHERE id='$id'");
?>
<p><a href="<?php echo $_SERVER['PHP_SELF']; ?>">&laquo; List Devices</a>
<a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=view&id=<?php echo $id; ?>">&laquo; Configuration</a>
<a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=patches&id=<?php echo $id; ?>">&laquo; History</a>
<p><strong>Device Name:</strong>  <?php echo $r['devicename']; ?>
<br><strong>File Name:</strong>  <?php echo $r['filename']; ?>
<p><strong>Historic Configuration:</strong>
<p>
<?php
    /* Get current config */
    $r = $db->get_row("SELECT content FROM configurations WHERE device='$id'");
    $config = $r['content'];
    echo "-&gt; retrieving current configuration<br>\n";

    /* Apply patches */
    $rows = $db->get_rows("SELECT ts,content FROM patches WHERE device='$id' ORDER BY ts ASC");
    $rev = count($rows) - 1;
    for ($rev; $rev >= 0; $rev--) {
        $row = $rows[$rev];
        /* Only apply patches that are newer then the requested date */
        if ($row['ts'] >= $ts) {
            echo "-&gt; applying patch revision ".$rev.", date ".devices_ts_to_date($row['ts'])."<br>\n";
            $config = xdiff_string_patch($config, $row['content']);
        }
    }
?>
<pre>
<?php echo $config; ?>
</pre>
<p><a href="<?php echo $_SERVER['PHP_SELF']; ?>">&laquo; List Devices</a>
<a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=view&id=<?php echo $id; ?>">&laquo; Configuration</a>
<a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=patches&id=<?php echo $id; ?>">&laquo; History</a>
<?php 
}

function devices_list_patches($id)
{
    global $db;

    $r = $db->get_row("SELECT devicename,description,filename,password FROM devices WHERE id='$id'");
?>
<p><a href="<?php echo $_SERVER['PHP_SELF']; ?>">&laquo; List Devices</a>
<a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=view&id=<?php echo $id; ?>">&laquo; Configuration</a>
<p><strong>Device Name:</strong>  <?php echo $r['devicename']; ?>
<br><strong>Description:</strong>  <?php echo $r['description']; ?>
<br><strong>File Name:</strong>  <?php echo $r['filename']; ?>
<p><strong>History:</strong>
<?php
    $rows = $db->get_rows("SELECT id,ts FROM patches WHERE device='$id' ORDER BY ts ASC");
    $rev = count($rows);
    /* Current config */
    $view_config = '<a href="'.$_SERVER['PHP_SELF'].'?action=history&ts=20991122002647&id='.$id.'">view</a>';
    echo "<p>revision: ".$rev.", ";
    echo $view_config." current\n";
    $rev--;
    /* Patches */
    for ($rev; $rev >= 0; $rev--) {
        $row = $rows[$rev];
        $view_config = '<a href="'.$_SERVER['PHP_SELF'].'?action=history&ts='.$row['ts'].'&id='.$id.'">view</a>';
        $view_patch = '<a href="'.$_SERVER['PHP_SELF'].'?action=patch&patch='.$row['id'].'&id='.$id.'">view</a>';
        echo "<p>revision: ".$rev.", date: ".devices_ts_to_date($row['ts'],'%Y-%m-%d %H:%i:%s').", ";
        echo $view_config." configuration";
        echo ", ";
        echo $view_patch." patch";
        echo "\n";
    }
?>
<p><a href="<?php echo $_SERVER['PHP_SELF']; ?>">&laquo; List Devices</a>
<a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=view&id=<?php echo $id; ?>">&laquo; Configuration</a>
<?php
}

function devices_view($id)
{
    global $db;

    $r = $db->get_row("SELECT devicename,description,filename,password FROM devices WHERE id='$id'");
?>
<p><a href="<?php echo $_SERVER['PHP_SELF']; ?>">&laquo; List Devices</a>
<p><strong>Device Name:</strong>  <?php echo $r['devicename']; ?>
<br><strong>Description:</strong>  <?php echo $r['description']; ?>
<br><strong>File Name:</strong>  <?php echo $r['filename']; ?>
<br><strong>Password:</strong>  <?php echo $r['password']; ?>
<p><strong>Cisco IOS command to save config:</strong>
<pre>copy running-config tftp://<?php echo $_SERVER['SERVER_ADDR'] ?>/$<?php echo $r['password']; ?>$/<?php echo $r['filename']; ?></pre>
<p><strong>Older Cisco IOS configuration:</strong>
<pre>alias exec save copy system:/running-config tftp://<?php echo $_SERVER['SERVER_ADDR'] ?>/$<?php echo $r['password']; ?>$/<?php echo $r['filename']; ?></pre>
<p><strong>Recommended Cisco IOS configuration:</strong>
<pre>alias exec save show running-config | redirect tftp://<?php echo $_SERVER['SERVER_ADDR'] ?>/$<?php echo $r['password']; ?>$/<?php echo $r['filename']; ?></pre>
<?php
    $r = $db->get_row("SELECT ts FROM updated WHERE device='$id'");
?>
<p><strong>Configuration:</strong>
<br>Saved on <?php echo devices_ts_to_date($r['ts']); ?>
<?php
    $r = $db->query("SELECT ts FROM patches WHERE device='$id'");
    if ($r > 0) :
?>
 (<a href="<?php echo $_SERVER['PHP_SELF'].'?action=patches&id='.$id; ?>">View history</a>)
<?php
    endif;
?>
<pre>
<?php 
    $r = $db->get_row("SELECT content FROM configurations WHERE device='$id'");
    echo htmlentities($r['content'], ENT_QUOTES); 
?>
</pre>
<p><a href="<?php echo $_SERVER['PHP_SELF']; ?>">&laquo; List Devices</a>
<?php
}

function devices_edit($id = NULL)
{
    global $db;

    if (! $id) {
        $action = 'added';
        $device['password'] = devices_gen_pwd();
    } else {
        $action = 'edited';
        $device = $db->get_row("SELECT devicename,description,filename,password FROM devices WHERE id='".$id."'");
    }

    ?>
<p>
<form action="<?php echo $_SERVER['PHP_SELF']; ?>" method="post">
  <p>Device Name:<br><input type="text" size="60" name="devicename" value="<?php if (isset($device['devicename'])) { echo $device['devicename']; }  ?>">
  <p>Description:<br><input type="text" size="60" name="description" value="<?php if (isset($device['description'])) { echo $device['description']; } ?>">
  <p>File Name:<br><input type="text" size="60" name="filename" value="<?php if (isset($device['filename'])) { echo $device['filename']; } ?>">
  <p>Password:<br><input type="text" size="60" name="password" value="<?php echo $device['password'] ?>">
  <p><input type="hidden" name="action" value="<?php echo $action ?>">
    <input type="hidden" name="id" value="<?php echo $id ?>">
    <a href="<?php echo $_SERVER['PHP_SELF']; ?>">&laquo; Back</a>&nbsp;--&nbsp;<input type="submit" value="Update &raquo;">
</form>
    <?php
}

function devices_table()
{
    global $db;

    $rows = $db->get_rows("SELECT id,devicename,description FROM devices ORDER BY devicename ASC");
?>
<p><a href="<?php echo $_SERVER['PHP_SELF']; ?>?action=add">Add</a> a new device.
<p><table width="70%" border="0" cellpadding="2" cellspacing="2">
<tr>
  <th>Device</th>
  <th>Description</th>
  <th colspan="2">Action</th>
</tr>
<?php

    /* Process each device */
    $alt = '';
    foreach ($rows as $row) {
        if ($alt == '') {
            $alt = 'alt';
        } else {
            $alt = '';
        }
            
        $edit = '<a href="'.$_SERVER['PHP_SELF'].'?action=edit&id='.$row['id'].'" class="edit">Edit</a>';
        $view = '<a href="'.$_SERVER['PHP_SELF'].'?action=view&id='.$row['id'].'" class="view">View</a>';
        echo "<tr class='".$alt."'>\n  <td>".$row['devicename']."</td>\n";
        echo "  <td>".$row['description']."</td>\n";
        echo "  <td class='edit'>".$edit."</td>\n";
        echo "  <td class='view'>".$view."</td>\n<tr>\n";
    }
    
?>
</table>
<?php
}

function devices_header($subtitle = 'List Devices', $message = array())
{
?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.01 Transitional//EN">
<html>
<head>
  <meta http-equiv="Content-Type" content="text/html; charset=iso-8859-1">
  <title>Manage Devices &gt; <?php echo $subtitle; ?></title>
<?php devices_css(); ?>
</head>
<body>
<h1>Manage Devices</h1>
<h2><?php echo $subtitle; ?></h2>
<?php
    foreach ($message as $s) :
?>
<strong><?php echo $s; ?></strong><br>
<?php
    endforeach;
}

function devices_footer()
{
?>
</body>
</html>
<?php
}

function devices_gen_pwd()
{
    /* Not including O, 0, 1, l to prevent ambiguity */
    $chars = "ABCDEFGHJKLMNPQRTYVWXYZabcdefghijkmnopqrstuvwxyz23456789";
    srand((double)microtime()*1000000);
    $i = 0;
    $pass = '' ;

    while ($i <= 17) {
        $num = rand() % strlen($chars);
        $tmp = substr($chars, $num, 1);
        $pass = $pass . $tmp;
        $i++;
    }

    return $pass;
}

function devices_ts_to_date($ts, $ft = '%a %b %e, %Y @ %r')
{
    global $db;

    $ts = mysql_real_escape_string($ts);
    $db->query("SELECT DATE_FORMAT('$ts','$ft')");
    $r = mysql_fetch_array($db->result);
    return $r[0];
}

function devices_css()
{
?>
  <style><!--
    a {
      color: #00f;
      text-decoration: none;
      text-align: center;
    }
    a:hover {
      background: #ccc;
      color: #00f;
    }
    body {
      background: #fff;
      color: #000;
      font: 10pt Georgia, "Times New Roman", Times, serif;
      padding-left: 30px;
      padding-right: 30px;
    }
    td.edit {
      text-align: center;
    }
    td.view {
      text-align: center;
    }
    th {
      background: #ddd;
      text-align: center;
    }
    tr.alt {
      background: #eee;
    }
    pre {
      margin-left: 10px;
      margin-right: 10px;
      background: #ddd;
    } -->
  </style>
<?php
}
?>
