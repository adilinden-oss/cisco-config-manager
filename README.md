# Cisco Configuration Manager #

This project provides tools to manage configurations for Cisco devices. 
At the heart of the project is a tftp daemon written entirely in PHP.
Configurations are read and written to the tftp server using a file path
that incorporates a password feature for security. The current version of
each device configuration is stored in a mysql database. Subsequent changes
to the configurations are stored in diff format to provide revision history.
A web frontend is provided to manage devices and view configurations and
history.

The docs/README.txt file is essential reading. It outlines the system
requirements including required PHP5 compile options and PECL extensions.

The docs/server_build.txt outlines a server build for cisco-config-manager
from scratch. It assumes a VMware environment although this is by no means
a requirement. The accompanying docs/php_build.sh script is a quick way
to get PHP5 configuration that supports cisco-config-manager.

This project was conceived to address some specific needs at K-Net
<http://www.knet.ca/> where it is used on a daily basis. Be warned, the
documentation and installation instructions are sparse.

This work is released under the GNU General Public License, please consult
LICENSE and <http://www.gnu.org/licenses/old-licenses/gpl-2.0.html>.

