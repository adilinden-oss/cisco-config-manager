#Building a cisco-config-manager server#
##Foreword##
This server is dedicated to hosting the Cisco configuration repository and provides access to Cisco IOS image files.

##Base System##
###Installation###
This server is a vSphere VM. The VM is created with 2vCPU, 1024M RAM. Since this is a 64bit installation choose the Debian Jesse ISO for installation.

    debian-jessie-DI-b2-amd64-netinst.iso

Partitioning is as follows:

    disc 1 (8GB)
        /                remaining space (primary)
        swap             1 GB
    disc 2 (12G)
        /home            remaining space

During install select “SSH Server” and nothing else.

###Basic System Setup###
Install additional packages.

    apt-get install open-vm-tools vim lsof screen

Condigure sshd to allow key based authentication only for root. Edit /etc/ssh/sshd_config. Note that I found Debian Jesse already had this by default.
 
    PermitRootLogin without-password

###ntp###
Install ntp.

    apt-get install ntp

The /etc/ntp.conf file was the only configuration file that needed editing
for ntp. It was changed to contain:

    # /etc/ntp.conf, configuration for ntpd

    # Default behaviour
    tinker panic 0
    restrict 127.0.0.1
    restrict ::1

    # be only a NTP  client
    restrict -4 default nomodify nopeer noquery notrap
    restrict -6 default nomodify nopeer noquery notrap

    # Time servers
    server 0.vmware.pool.ntp.org
    server 1.vmware.pool.ntp.org
    server 2.vmware.pool.ntp.org
    server 66.165.220.4

    # File locations
    driftfile /var/lib/ntp/ntp.drift

Finally ntp is restarted for the new configuration to take effect.
   
    /etc/init.d/ntp restart
    
###postfix###
Install postfix.

    apt-get install postfix

The /etc/postfix/main.cf file contains all significant postfix configuration
information. Just one line needs to be added to have postfix limited to only
localhost and not listen on public interfaces:

    inet_interfaces = localhost

##Applications##
###git###
Install git

    apt-get install git

###mysql###
The mysql database application is installed from Debian packages.

    apt-get install mysql-server libmysqlclient18

###apache2###
Install apache.

    apt-get install apache2

###php###
PHP needs to be built from source to support the additional features needed for the cisco-config-manager.

Build prerequisites for php5.

    apt-get install build-essential bison autoconf curl \
        apache2-dev libmysqlclient-dev libxml2-dev 

Build prerequisites for libxdiff.

    apt-get install gawk re2c 

Install php5 from sources using php_build.sh.

    mkdir /usr/local/src/php
    cd /usr/local/src/php
    curl -L -O http://museum.php.net/php5/php-5.3.29.tar.xz
    ./php_build.sh

Install libxdiff from sources.

    mkdir /usr/local/src/libxdiff
    cd /usr/local/src/libxdiff
    curl -L -O http://www.xmailserver.org/libxdiff-0.23.tar.gz
    tar xzf libxdiff-0.23.tar.gz
    cd libxdiff-0.23
    ./configure && make && make install

    cd /usr/local/src/libxdiff
    curl -L -O  http://pecl.php.net/get/xdiff-1.5.2.tgz
    tar xzf xdiff-1.5.2.tgz
    cd xdiff-1.5.2
    phpize
    ./configure && make && make install

This installs the xdiff.so extension in 

      /usr/local/lib/php/extensions/no-debug-non-zts-20090626/
    
To have php load the extension edit php.ini, set the following:

      extension_dir = "/usr/local/lib/php/extensions/no-debug-non-zts-20090626"
      extension=xdiff.so

###cisco-config-manager###
Get the sources

    cd /usr/local/src
    git clone https://github.com/adilinden/cisco-config-manager.git

Install a “Welcome page” as /var/www/html/index.html or install an /var/www/html/index.php page to redirect to the ./cisco-config-manager directory.

Install the config manager by checking out the files from git into the webroot.

    mkdir /var/www/html/cisco-config-manager
    cd /usr/local/src/cisco-config-manager
    GIT_WORK_TREE=/var/www/html/cisco-config-manager git checkout -f

It would be best to alter some details such as database password. These files need the database password updated:

      /var/www/html/confgmgr/docs/mysql-init.sql
      /var/www/html/confgmgr/devices.php
      /var/www/html/confgmgr/tftpd/tftpd-config.php

Install the mysql database.

    mysql -u root -p
    mysql> source /var/www/html/confgmgr/docs/mysql-init.sql

Install the init script and start the daemon.

    cp /var/www/html/confgmgr/docs/tftpd.init /etc/init.d/tftpd
    insserv -v tftpd
    /etc/init.d/tftpd start

For the file browser to work, files are expected to be in /tftpboot. Since we have plenty of files we can place them into /home/tftpboot and symlink.

    mkdir /home/tftpboot
    ln -s /home/tftpboot /tftpboot

Now access the cisco-config-manager at these URL:

    http://<server IP>/cisco-config-manager/browse.php
    http://<server IP>/cisco-config-manager/devices.php
