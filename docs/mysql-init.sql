# $Id: mysql-init.sql,v 1.4 2005-11-23 17:34:23 adicvs Exp $
#
# Database structure for Cisco Config Manager
#

# The user
GRANT select,insert,update,delete ON ccm.*
  TO ccm_user@localhost IDENTIFIED BY 'schmack';

# Create database from scratch, wiping old data
#DROP DATABASE ccm;
CREATE DATABASE IF NOT EXISTS ccm;
USE ccm;

# Create tables
CREATE TABLE devices (
        id bigint(20) UNSIGNED NOT NULL AUTO_INCREMENT,
        devicename tinytext NOT NULL,
        description tinytext NOT NULL,
        filename tinytext NOT NULL,
        password tinytext NOT NULL,
        UNIQUE KEY id (id)
        );

CREATE TABLE address (
        device bigint(20) UNSIGNED NOT NULL,
        ip int UNSIGNED NOT NULL,
        hostname tinytext NOT NULL,
        UNIQUE KEY ip (ip)
        );

CREATE TABLE configurations (
        device bigint(20) UNSIGNED NOT NULL,
        content longtext NOT NULL,
        UNIQUE KEY device (device)
        );

CREATE TABLE patches (
        id bigint UNSIGNED NOT NULL AUTO_INCREMENT,
        device bigint(20) UNSIGNED NOT NULL,
        ts timestamp,
        content longtext NOT NULL,
        UNIQUE KEY id (id)
        );

CREATE TABLE updated (
        device bigint(20) UNSIGNED NOT NULL,
        ts timestamp,
        UNIQUE KEY device (device)
        );

CREATE TABLE logs (
        ts timestamp,
        content longtext NOT NULL
        );

# Populate with sample data
INSERT INTO devices
        (id, devicename, description, filename, password) VALUES
        (1, 'rtr-toronto', 'Toronto core router', 'rtr-toronto.knet.ca-confg', 'testing0'),
        (2, 'rtr-sl', 'Sioux Lookout core router', 'rtr-sl.knet.ca-confg', 'testing1'),
        (3, 'rtr-dl', 'Deer Lake cable headend', 'rtr-dl.knet.ca-confg', 'testing2')
        ;

