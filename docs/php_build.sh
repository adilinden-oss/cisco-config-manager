#!/bin/bash -x

PHPVER=5.3.29
[ -d php-${PHPVER} ] && rm -rf php-${PHPVER}

if [ -f php-${PHPVER}.tar.xz ]; then
    xz -d -c php-${PHPVER}.tar.xz | tar -x
elif [ -f php-${PHPVER}.tar.bz2 ]; then
    bzip2 -d -c php-${PHPVER}.tar.bz2 | tar -x
elif [ -f php-${PHPVER}.tar.gz ]; then
    gzip -d -c php-${PHPVER}.tar.gz | tar -x
else
    echo "No php sources found!"
fi

cd php-${PHPVER}

./configure \
        --with-libdir=/usr/lib/x86_64-linux-gnu \
        --disable-all \
        --enable-cli \
        --enable-ctype \
        --enable-ftp \
        --enable-gd-native-ttf \
        --enable-libxml \
        --enable-magic-quotes \
        --enable-safe-mode \
        --enable-session \
        --enable-sockets \
        --enable-tokenizer \
        --enable-xml \
        --enable-pcntl \
        --enable-sigchild \
        --enable-posix \
        --with-apxs2=/usr/bin/apxs2 \
        --with-gettext \
        --with-mysql \
        --with-mysqli=/usr/bin/mysql_config \
        --with-mysql-sock=/var/run/mysqld/mysqld.sock \
        --with-pcre-regex \
        --with-pear \
        --with-zlib \
        --with-zlib-dir

make && \
make install-cli && \
make install-pear && \
make install-programs && \
make install-build && \
make install-headers && \
install -m 644 libs/libphp5.so /usr/lib/apache2/modules

# Config file only if installed from scratch!
if [ ! -f /usr/local/lib/php.ini ]; then
    if [ ! -f php.ini-production ]; then
        install -m 644 php.ini-dist /usr/local/lib/php.ini
    else
        install -m 644 php.ini-production /usr/local/lib/php.ini
    fi
else
    if [ ! -f php.ini-production ]; then
        install -m 644 php.ini-dist /usr/local/lib/php.ini.new
    else
        install -m 644 php.ini-production /usr/local/lib/php.ini.new
    fi
fi

if [ ! -f /etc/php.ini ]; then
    ln -s /usr/local/lib/php.ini /etc/php.ini
fi

# Apache to load php module
if [ ! -f /etc/apache2/mods-available/php5.conf ]; then
    cat<<EOC>/etc/apache2/mods-available/php5.conf
<IfModule mod_php5.c>
    <FilesMatch "\.ph(p3?|tml)$">
	SetHandler application/x-httpd-php
    </FilesMatch>
    <FilesMatch "\.phps$">
	SetHandler application/x-httpd-php-source
    </FilesMatch>
    # To re-enable php in user directories comment the following lines
    # (from <IfModule ...> to </IfModule>.) Do NOT set it to On as it
    # prevents .htaccess files from disabling it.
    <IfModule mod_userdir.c>
        <Directory /home/*/public_html>
            php_admin_value engine Off
        </Directory>
    </IfModule>
</IfModule>
EOC
fi
if [ ! -f /etc/apache2/mods-available/php5.load ]; then
    echo "LoadModule php5_module /usr/lib/apache2/modules/libphp5.so" > /etc/apache2/mods-available/php5.load
    a2enmod php5
fi

# Apache needs to be prefork
a2dismod mpm_event
a2enmod mpm_prefork

# Restart apache
#service apache2 restart

echo "Restart apache...!"

