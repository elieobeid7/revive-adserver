<?php

/*
+---------------------------------------------------------------------------+
| OpenX v${RELEASE_MAJOR_MINOR}                                                                |
| =======${RELEASE_MAJOR_MINOR_DOUBLE_UNDERLINE}                                                                |
|                                                                           |
| Copyright (c) 2003-2009 OpenX Limited                                     |
| For contact details, see: http://www.openx.org/                           |
|                                                                           |
| This program is free software; you can redistribute it and/or modify      |
| it under the terms of the GNU General Public License as published by      |
| the Free Software Foundation; either version 2 of the License, or         |
| (at your option) any later version.                                       |
|                                                                           |
| This program is distributed in the hope that it will be useful,           |
| but WITHOUT ANY WARRANTY; without even the implied warranty of            |
| MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the             |
| GNU General Public License for more details.                              |
|                                                                           |
| You should have received a copy of the GNU General Public License         |
| along with this program; if not, write to the Free Software               |
| Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA |
+---------------------------------------------------------------------------+
$Id$
*/

require_once MAX_PATH . '/lib/OA/DB.php';
require_once 'DB/DataObject.php';

/**
 * The common Data Abstraction Layer (DAL) class.
 *
 * @package    OpenXDal
 * @author     Radek Maciaszek <radek.maciaszek@openx.org>
 * @author     Andrew Hill <andrew.hill@openx.org>
 */
class OA_Dal
{

    /**
     * A local instance of the OA_DB created database handler.
     *
     * @var MDB2_Driver_Common
     */
    var $oDbh;

    /**
     * The constructor method.
     */
    function OA_Dal()
    {
        $this->oDbh =& $this->_getDbh();
    }

    /**
     * A private method to return the database handler from
     * OA_DB.
     *
     * This private method allows the database handler to be
     * mocked during unit tests.
     *
     * @return MDB2_Driver_Common An MDB2 connection resource, or PEAR_Error
     *                            on failure to connect.
     */
    function &_getDbh()
    {
        return OA_DB::singleton();
    }

    /**
     * A factory method to obtain the appropriate DB_DataObject for a given
     * table name.
     *
     * @static
     * @param  string $table The name of the table for which a DB_DataObject is required.
     * @return DB_DataObjectCommon The appropriate DB_DataObjectCommon implementaion,
     *                             or false on error.
     */
    function factoryDO($table)
    {
        OA_Dal::_setupDataObjectOptions();
        $do = DB_DataObject::factory($table);
        if (is_a($do, 'DB_DataObjectCommon')) {
            $do->init();
            return $do;
        }
        return false;
    }

    function checkIfDoExists($table)
    {
        OA_Dal::_setupDataObjectOptions();
        global $_DB_DATAOBJECT;
        if (!is_array($_DB_DATAOBJECT['CONFIG']['class_location']))
        {
            $location = $_DB_DATAOBJECT['CONFIG']['class_location'];
            $fileExists = DB_DataObject::findTableFile($location, $table);
        }
        else
        {
            foreach ($_DB_DATAOBJECT['CONFIG']['class_location'] as $k => $location)
            {
                $fileExists = DB_DataObject::findTableFile($location, $table);
                if ($fileExists)
                {
                    break;
                }
            }
        }

        return $fileExists;
    }

    /**
     * A method to obtain an appropriate DB_DataObject for a given table name, pre-loaded
     * with the desired data, when possible.
     *
     * Example use:
     *   $doBanners = OA_Dal::staticGetDO("banners", 12);
     * Return the oject pre-loaded with the banner ID 12.
     *
     * Example use:
     *   $doClients = OA_Dal::staticGetDO("clients", "name", "fred");
     * Return the object pre-loaded with all clients where the "name" column is
     * equal to "fred".
     *
     * @static
     * @param string $table The name of the table for which a DB_DataObject is required.
     * @param string $k     Either the column name, if $v is supplied, otherwise the
     *                      value of the table's primary key.
     * @param string $v     An optional value when $k is a column name of the table.
     * @return DB_DataObjectCommon The appropriate DB_DataObjectCommon implementaion,
     *                             or false on error.
     */
    function staticGetDO($table, $k, $v = null)
    {
        OA_DAL::_setupDataObjectOptions();
        $do = OA_Dal::factoryDO($table);
        if (PEAR::isError($do) || !$do) {
            return false;
        }
        if (!$do->get($k, $v)) {
            return false;
        }
        return $do;
    }

    /**
     * A method to duplicate an existing row
     *
     * Example use:
     *     $doBanners = OA_Dal::staticDuplicate('banners', 12, 1);
     *
     * @static
     * @param string $table The name of the table for which a DB_DataObject is required.
     * @param int $origId   The id of the row to copy
     * @param int $newId    The id to be assigned as the id of the row copied (optional)
     * @return mixed        Returns the restults returned from the duplicate method
     */
    function staticDuplicate($table, $origId, $newId = null)
    {
        OA_DAL::_setupDataObjectOptions();
        $do = OA_Dal::factoryDO($table);
        if (PEAR::isError($do)) {
            return false;
        }
        if (!$result = $do->duplicate($origId, $newId)) {
            return false;
        }
        return $result;
    }

    /**
     * A factory method to load the appropriate MAX_Dal_Admin class for a
     * given table name.
     *
     * @static
     * @param string $table The name of the table for which a MAX_Dal_Admin class is
     *                      required.
     * @return MAX_Dal_Common The appropriate MAX_Dal_Common implementaion,
     *                        or false on error.
     */
    function factoryDAL($table)
    {
        include_once MAX_PATH . '/lib/max/Dal/Common.php';
        return MAX_Dal_Common::factory($table);
    }

    /**
     * Returns table prefix (see config [table][prefix]
     *
     * @return string  Table prefix
     */
    function getTablePrefix()
    {
        return $GLOBALS['_MAX']['CONF']['table']['prefix'];
    }


    /**
     * Set up the required DB_DataObject options.
     * this method will add one location for all data-aware plugins
     *
     * @static
     * @access private
     */
    function _setupDataObjectOptions()
    {
        static $needsSetup;
        if (isset($needsSetup)) {
            return;
        }
        $needsSetup = false;

        $aConf = $GLOBALS['_MAX']['CONF'];

        // Set DB_DataObject options

        // core dataobjects and schema
        $pathDataObjectsCore   = MAX_PATH . '/lib/max/Dal/DataObjects';
        $aIniLocations[0] = $pathDataObjectsCore.'/db_schema.ini';
        $aLnkLocations[0] = $pathDataObjectsCore.'/db_schema.links.ini';
        $aDboLocations[0] = $pathDataObjectsCore;

        // plugin dataobjects and schemas
        $pathDataObjectsPlugin = MAX_PATH . $aConf['pluginPaths']['var'] . 'DataObjects';
        $aIniLocations[1] = $pathDataObjectsPlugin.'/db_schema.ini';
        $aLnkLocations[1] = $pathDataObjectsPlugin.'/db_schema.links.ini';
        $aDboLocations[1] = $pathDataObjectsPlugin;

        $dbname = $GLOBALS['_MAX']['CONF']['database']['name'];
        $options =& PEAR::getStaticProperty('DB_DataObject', 'options');
        $options = array(
            'database'              => OA_DB::getDsn(),
            'ini_'.$dbname          => $aIniLocations,
            'links_'.$dbname        => $aLnkLocations,
            'schema_location'       => $pathDataObjectsCore, // only used by generator?
            'class_location'        => $aDboLocations,
            'require_prefix'        => $pathDataObjectsCore . '/', // only used by generator?
            'class_prefix'          => 'DataObjects_',
            'debug'                 => 0,
            'production'            => 0,
        );
    }

    function _setupDataObjectOptionsOLD()
    {
        static $needsSetup;
        if (isset($needsSetup)) {
            return;
        }
        $needsSetup = false;

        // Set DB_DataObject options
        $MAX_ENT_DIR = MAX_PATH . '/lib/max/Dal/DataObjects';
        $options =& PEAR::getStaticProperty('DB_DataObject', 'options');
        $options = array(
            'database'              => OA_DB::getDsn(),
            'schema_location'       => $MAX_ENT_DIR,
            'class_location'        => $MAX_ENT_DIR,
            'require_prefix'        => $MAX_ENT_DIR . '/',
            'class_prefix'          => 'DataObjects_',
            'debug'                 => 0,
            'production'            => 0,
        );
    }

    /**
     * A method to return the SQL required to create a temporary
     * table when prepended to a SELECT statement, depending on
     * the database type in use.
     *
     * @static
     * @param string $table The name of the temporary table to create.
     * @return string The SQL code to prepend to a SELECT statement to
     *                create the temporary table.
     */
    function createTemporaryTableFromSelect($table)
    {
        $aConf = $GLOBALS['_MAX']['CONF'];
        $oDbh =& OA_DB::singleton();
        if ($oDbh->dsn['phptype'] == 'pgsql') {
            $sql = "
                CREATE TEMPORARY TABLE
                    $table
                AS";
        } else {
            $sql = "
                CREATE TEMPORARY TABLE
                    $table
                TYPE={$aConf['table']['type']}";
        }
        return $sql;
    }

    /**
     * A method to return the SQL required to obtain an INTERVAL
     * value, depending on the datbase type in use.
     *
     * For example, in MySQL:
     *  INTERVAL 30 DAY;
     *
     * For example, in PostgreSQL:
     *  (30 DAY)::interval
     *
     * @static
     * @param string $interval The INTERVAL field or integer value. For example,
     *                         "30", or "table.column".
     * @param string $type     The INTERVAL length. For example, "DAY".
     * @return string The SQL code required to obtain the INTERVAL value.
     */
    function quoteInterval($interval, $type)
    {
        $oDbh =& OA_DB::singleton();
        if ($oDbh->dsn['phptype'] == 'pgsql') {
            return "($interval || ' $type')::interval";
        }
        return "INTERVAL $interval $type";
    }

    /**
     * Returns true if $sqlDate is not an 'empty' date, false otherwise.
     *
     * @static
     * @param string $sqlDate
     */
    function isValidDate($sqlDate)
    {
        $dbh = OA_DB::singleton();
        return !empty($sqlDate) && preg_match('#^\d\d\d\d-\d\d-\d\d$#D', $sqlDate);
    }

    /**
     * Returns true if the $sqlDate represents 'empty' OpenX date,
     * false otherwise.
     *
     * @static
     * @param string $sqlDate
     */
    function isNullDate($sqlDate)
    {
        return !OA_Dal::isValidDate($sqlDate);
    }

}

?>
