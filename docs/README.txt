$Id: README.txt,v 1.2 2005-11-18 23:28:56 adicvs Exp $

Requirements
============

PHP5 is required. The following additional considerations and components are
required.

Tftpd
-----
The tftpd daemon requires the php POSIX Functions and Process Control 
Functions. This requires the following ./configure switches during the 
php build.

    --enable-pcntl
    --enable-sigchild
    --enable-posix

Revision control
----------------
The revision control code requires the xdiff Functions. The xdiff code
is currently available through PECL <http://pecl.php.net/package/xdiff>.

The build requires the Debian autoconf package.

    apt-get install autoconf

The build also required the php development files to be installed. During 
the php build run:

    make install-build install-headers

First libxdiff is required:

    cd /usr/local/src/php
    wget http://www.xmailserver.org/libxdiff-0.14.tar.gz
    tar xzf libxdiff-0.14.tar.gz
    cd libxdiff-0.14
    ./configure --prefix=/usr/local && make && make install
    
Ready to install xdiff:

    cd /usr/local/src/php
    wget http://pecl.php.net/get/xdiff-1.3.tgz
    tar xzf xdiff-1.3.tgz
    cd xdiff-1.3
    phpize
    ./configure && make && make install

This installs the xdiff.so extension in 

    /usr/local/lib/php/extensions/no-debug-non-zts-20041030/xdiff.so
    
To have php load the extension edit php.ini, set the following:

    extension_dir = "/usr/local/lib/php/extensions/no-debug-non-zts-20041030"
    extension=xdiff.so

Usage
=====

On newer devices (2960's, 3560's, 3750's, not 2950's, 3550's) the following can
be used to automatically backup configs weekly on Sundays.

	kron occurrence SaveConfigSchedule at 23:00 Sun recurring
	 policy-list SaveConfig
	!
	kron occurrence Backup at 23:10 Sun recurring
	 policy-list Backup
	!
	kron policy-list SaveConfig
	 cli write
	!
	kron policy-list Backup
	 cli save


