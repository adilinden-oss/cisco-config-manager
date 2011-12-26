<?php
/* $Id: db.php,v 1.3 2005-11-18 23:28:56 adicvs Exp $
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

/* db.php   A simple class to access data in a mysql database. It provides
 *          some simple functions that process query results in easier to
 *          handle formats (associative arrays) then native php mysql 
 *          functions.
 *
 *          This class inspired and based on ezSQL by Justin Vincent. For
 *          a more featureful solution see ezSQL.
 */

class db {

    /* Class constructor
     *
     * Opens database connection and selects the database we want to work
     * with.
     */
    function __construct ($host, $user, $pass, $dbname)
    {
        if (! $this->open($host, $user, $pass)) {
            return false;
        }
        if (! $this->select($dbname)) {
            return false;
        }
        return true;
    }

    /* Class destructor
     *
     * Closes the database connection.
     */
    function __destruct ()
    {
        $this->close();
    }

    /* The database query
     *
     * This sends $query to the database connection. Returns the number 
     * of rows affected by the operation. The $this->result object can be 
     * used to obtain results outside this class.
     */
    function query ($query)
    {
        /* Run query */
        $this->result = @mysql_query($query, $this->dbh);
        if (! $this->result) {
            echo "Error sending query: ".mysql_error();
            return false;
        }

        /* Determine number of rows affected */
        if ( preg_match("/^(insert|delete|update|replace)\s+/i",$query) ) {
            $n = mysql_affected_rows($this->dbh);
        } else {
            $n = mysql_num_rows($this->result);
        }
        return $n;
    }

    /* Get a single row
     *
     * Sends the (optional) $query to the database connection. If $query is 
     * not specified it operates on $this->result of a previous operation.
     * Returns an associative array containing the result of the first row. 
     */
    function get_row ($query = NULL)
    {
        /* Send the query to the db */
        if ($query) {
            $this->query($query);
        }
        
        /* Process the result */
        $r = mysql_fetch_assoc($this->result);

        return $r;
    }

    /* Get multiple rows
     *
     * Sends the (optional) $query to the database connection. If $query is 
     * not specified it operates on $this->result of a previous operation.
     * Returns an multi dimensional array containing the resulting rows as 
     * numeric indices and fields as associative nested array.
     */
    function get_rows ($query = NULL)
    {
        /* Send the query to the db */
        if ($query) {
            $this->query($query);
        }
        
        /* Process the result */
        $i = 0;
        while ($r = mysql_fetch_assoc($this->result)) {
            $a[$i] = $r;
            $i++;
        }
        return $a;
    }

    /* Open database connection */
    function open ($host, $user, $pass)
    {
        $this->dbh = @mysql_connect($host, $user, $pass);
        if (! $this->dbh) {
            echo "Error connecting to db: ".mysql_error();
            return false;
        }
        return true;
    }

    /* Select database */
    function select($dbname)
    {
        if ( !@mysql_select_db($dbname, $this->dbh)) {
            echo "Error selecting db: ".$dbname;
            return false;
        }
        return true;
    }

    /* Close database connection */
    function close ()
    {
        @mysql_close($this->dbh);
    }

}
?>
