<?php

/*
+---------------------------------------------------------------------------+
| Openads v2.3                                                              |
| ============                                                              |
|                                                                           |
| Copyright (c) 2003-2007 Openads Limited                                   |
| For contact details, see: http://www.openads.org/                         |
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


require_once MAX_PATH.'/lib/OA/DB.php';
require_once MAX_PATH.'/lib/OA/DB/Table.php';
require_once(MAX_PATH.'/lib/OA/Upgrade/DB_UpgradeAuditor.php');
require_once MAX_PATH.'/lib/OA/Upgrade/DB_Upgrade.php';
require_once MAX_PATH.'/lib/OA/Upgrade/Migration.php';


/**
 * A class for testing the Openads_DB_Upgrade class.
 *
 * @package    Openads Upgrade
 * @subpackage TestSuite
 * @author     Monique Szpak <monique.szpak@openads.org>
 */
class Test_DB_Upgrade extends UnitTestCase
{

    var $path;

    var $aChangesVars;
    var $aOptions;
    var $prefix;

    /**
     * The constructor method.
     */
    function Test_DB_Upgrade()
    {
        $this->UnitTestCase();

        $this->aChangesVars['version']       = '2';
        $this->aChangesVars['name']          = 'changes_test';
        $this->aChangesVars['comments']      = '';
        $this->aOptions['split']             = true;
        $this->aOptions['output']            = MAX_PATH.'/var/changes_test.xml';
        $this->aOptions['xsl_file']          = "";
        $this->aOptions['output_mode']       = 'file';
        $this->prefix                        = $GLOBALS['_MAX']['CONF']['table']['prefix'];
    }

    function test_constructor()
    {
        $this->path = MAX_PATH.'/lib/OA/Upgrade/tests/data/';
        $oDB_Upgrade = & new OA_DB_Upgrade();
        $this->assertIsA($oDB_Upgrade, 'OA_DB_Upgrade', 'OA_DB_Upgrade not instantiated');
    }

    function test_initMDB2Schema()
    {
        $this->path = MAX_PATH.'/lib/OA/Upgrade/tests/data/';
        $oDB_Upgrade = & new OA_DB_Upgrade();
        $oDB_Upgrade->initMDB2Schema();
        $this->assertIsA($oDB_Upgrade->oSchema, 'MDB2_Schema', 'MDB2 Schema not instantiated');
        $this->assertIsA($oDB_Upgrade->oSchema->db, 'MDB2_Driver_Common', 'MDB2 Driver not instantiated');
    }

    function test_listTables()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();
        $this->_dropTestTables($oDB_Upgrade->oSchema->db);
        $prefixOld = $this->prefix;
        $GLOBALS['_MAX']['CONF']['table']['prefix'] = 'xyz_';
        $this->prefix = $GLOBALS['_MAX']['CONF']['table']['prefix'];
        $oDB_Upgrade->prefix = $GLOBALS['_MAX']['CONF']['table']['prefix'];
        $this->_createTestTables($oDB_Upgrade->oSchema->db);
        $aDbTables = $oDB_Upgrade->_listTables();

//        $query = "SHOW /*!50002 FULL*/ TABLES/*!50002  WHERE Table_type = 'BASE TABLE'*/ LIKE 'xyz\_%'";
//        $query = "SHOW TABLES LIKE 'xyz\_%'";
//        $host = $GLOBALS['_MAX']['CONF']['database']['host'];
//        $name = $GLOBALS['_MAX']['CONF']['database']['username'];
//        $dbase = $GLOBALS['_MAX']['CONF']['database']['name'];
//
//        $db = mysql_connect($host,$name);
//        if (is_resource($db))
//        {
//            if (mysql_selectdb($dbase))
//            {
//                $res = mysql_query($query);
//                if (is_resource($res))
//                {
//                    while (list($key, $value) = each(mysql_fetch_assoc($res)))
//                    {
//                        $aDbTables[] = $value;
//                    }
//                }
//            }
//        }
        $this->assertEqual(count($aDbTables),2,'');
        $this->assertEqual($aDbTables[0],'xyz_table1','');
        $this->assertEqual($aDbTables[1],'xyz_table2','');
        $this->_dropTestTables($oDB_Upgrade->oSchema->db);
        $GLOBALS['_MAX']['CONF']['table']['prefix'] = $prefixOld;
        $this->prefix = $GLOBALS['_MAX']['CONF']['table']['prefix'];
    }

    function test_stripPrefixesFromDatabaseDefinition()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();
        $aDefinition['tables'][$this->prefix.'table1'] = array();
        $aDefinition['tables'][$this->prefix.'table1']['indexes'][$this->prefix.'table1_pkey'] = array();
        $aDefinition['tables'][$this->prefix.'table1']['indexes'][$this->prefix.'table1_pkey']['primary'] = true;

        $aDefStripped = $oDB_Upgrade->_stripPrefixesFromDatabaseDefinition($aDefinition);

        $this->assertFalse(isset($aDefStripped['tables'][$this->prefix.'table1']), 'unstripped tablename found in definition');
        $this->assertTrue(isset($aDefStripped['tables']['table1']), 'stripped tablename not found in definition');

        $this->assertFalse(isset($aDefStripped['tables']['table1']['indexes'][$this->prefix.'table1_pkey']), 'unstripped indexname found in definition');
        $this->assertTrue(isset($aDefStripped['tables']['table1']['indexes']['table1_pkey']), 'stripped indexname not found in definition');
    }

    function test_getDefinitionFromDatabase()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();
        $this->_dropTestTables($oDB_Upgrade->oSchema->db);
        $prefixOld = $this->prefix;
        $GLOBALS['_MAX']['CONF']['table']['prefix'] = 'xyz_';
        $this->prefix = $GLOBALS['_MAX']['CONF']['table']['prefix'];
        $oDB_Upgrade->prefix = $GLOBALS['_MAX']['CONF']['table']['prefix'];
        $aDefOrig = $this->_createTestTables($oDB_Upgrade->oSchema->db);
        $aDefNew = $oDB_Upgrade->_getDefinitionFromDatabase();
        $aDiff = $oDB_Upgrade->oSchema->compareDefinitions($this->aDefNew, $aDefOrig);
        $this->assertEqual(count($aDiff),0,'definitions don\'t match');
        $this->_dropTestTables($oDB_Upgrade->oSchema->db);
        $GLOBALS['_MAX']['CONF']['table']['prefix'] = $prefixOld;
        $this->prefix = $GLOBALS['_MAX']['CONF']['table']['prefix'];
    }

    function test_checkSchemaIntegrity()
    {
        $this->path = MAX_PATH.'/lib/OA/Upgrade/tests/data/';

        $oDB_Upgrade = $this->_newDBUpgradeObject();
//        $this->_dropTestTables($oDB_Upgrade->oSchema->db);
        // new tables table1 and table2

        $oTable = new OA_DB_Table();
        $oTable->init(MAX_PATH.'/etc/tables_core.xml');
        $oTable->dropAllTables();

        // get the current definition
        $oTable->init($this->path.'schema_test_tables_core2.xml');
        $oDB_Upgrade->aDefinitionNew = $oTable->aDefinition;
        //$oTable->dropAllTables();

        // get a changed definition and implement it
        $oTable->init($this->path.'schema_test_tables_core1.xml');
        $oTable->createAllTables();

        // now the following have changed
        // to *imitate* a broken/tweaked database
        // missing password_recovery table
        // new column preference.user_custom_field1
        // missing column preference.updates_enabled
        // changed column zones.cost from dec 10,4 to dec 12,2
        $this->assertTrue($oDB_Upgrade->checkSchemaIntegrity(MAX_PATH.'/var/changes_tables_core2'),'');

        $aConstructive = $oDB_Upgrade->aChanges['constructive']['tables'];
        $aDestructive = $oDB_Upgrade->aChanges['destructive']['tables'];

        $this->assertTrue(isset($aConstructive['add']),'array of add tables not found');
        $this->assertTrue(isset($aConstructive['add']['password_recovery']),'password_recovery not found in array of add tables');

        $this->assertTrue(isset($aConstructive['change']),'change tables not found');
        $this->assertTrue(isset($aConstructive['change']['zones']),'zones table not found in change array');
        $this->assertTrue(isset($aConstructive['change']['zones']['change']['fields']['cost']['length']),'zones field changes not found in change array');
        $this->assertTrue(isset($aConstructive['change']['preference']),'preference table not found in change array');
        $this->assertTrue(isset($aConstructive['change']['preference']['add']['fields']['updates_enabled']),'preference field changes not found in change array');

        $this->assertTrue(isset($aDestructive['remove']),'remove tables not found');
        $this->assertTrue(isset($aDestructive['remove']['table1']),'table1 not found in remove tables array');
        $this->assertTrue(isset($aDestructive['remove']['table2']),'table2 not found in remove tables array');

        $this->assertTrue(isset($aDestructive['change']),'change tables not found');
        $this->assertTrue(isset($aDestructive['change']['preference']),'preference table not found in change array');
        $this->assertTrue(isset($aDestructive['change']['preference']['remove']['user_custom_field1']),'preference field changes not found in change array');
        if (file_exists(MAX_PATH.'/var/changes_tables_core2'))
        {
            unlink(MAX_PATH.'/var/changes_tables_core2');
        }
    }

    function test_UpgradeWithNoBackups()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();
        $oDB_Upgrade->doBackups = false;
        $oDB_Upgrade->aChanges['affected_tables']['constructive'] = array('table1');

        $this->assertTrue($oDB_Upgrade->_backup(),'_backup failed');

        $aAuditRec = $oDB_Upgrade->oAuditor->queryAuditByDBUpgradeId(1);

        $this->assertIsA($aAuditRec, 'array', 'aAuditRec not an array');
        $this->assertEqual(count($aAuditRec),1,'wrong number of audit records');
        $this->assertEqual($aAuditRec[0]['action'],DB_UPGRADE_ACTION_BACKUP_IGNORED,'wrong audit code');

        $this->assertIsA($oDB_Upgrade->aRestoreTables, 'array', 'aRestoreTables not an array');
        $this->assertEqual(count($oDB_Upgrade->aRestoreTables),0, 'aRestoreTables should be empty');
    }

    /**
     * this test calls backup method then immediately rollsback
     * emulating an upgrade error without interrupt (can recover in same session)
     *
     */
    function test_BackupAndRollbackRestoreTables()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();
        $oDB_Upgrade->aChanges['affected_tables']['constructive'] = array('table1');

        $aTbl_def_orig = $oDB_Upgrade->oSchema->getDefinitionFromDatabase(array($this->prefix.'table1'));

        $this->assertTrue($oDB_Upgrade->_backup(),'_backup failed');
        $this->assertIsA($oDB_Upgrade->aRestoreTables, 'array', 'aRestoreTables not an array');
        // the aRestoreTables array holds the tablenames without a prefix
        $this->assertTrue(array_key_exists('table1', $oDB_Upgrade->aRestoreTables), 'table not found in aRestoreTables');
        $this->assertTrue(array_key_exists('bak', $oDB_Upgrade->aRestoreTables['table1']), 'backup table name not found for table table1');
        $this->assertTrue(array_key_exists('def', $oDB_Upgrade->aRestoreTables['table1']), 'definition array not found for table table1');

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();

        $table_bak = $oDB_Upgrade->aRestoreTables['table1']['bak'];
        $this->assertTrue($this->_tableExists($table_bak, $oDB_Upgrade->aDBTables), 'backup table not found in database');

        OA_DB::setQuoteIdentifier();
        $aTbl_def_bak = $oDB_Upgrade->oSchema->getDefinitionFromDatabase(array($this->prefix.$table_bak));
        OA_DB::disabledQuoteIdentifier();

        $aTbl_def_orig = $aTbl_def_orig['tables'][$this->prefix.'table1'];
        $aTbl_def_bak  = $aTbl_def_bak['tables'][$this->prefix.$table_bak];

        foreach ($aTbl_def_orig['fields'] AS $name=>$aType)
        {
            $this->assertTrue(array_key_exists($name, $aTbl_def_bak['fields']), 'field missing from backup table');
        }

        $oDB_Upgrade->oSchema->db->manager->dropTable($this->prefix.'table1');

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();
        $this->assertFalse($this->_tableExists('table1', $oDB_Upgrade->aDBTables), 'could not drop test table');

        $this->assertTrue($oDB_Upgrade->rollback(), 'rollback failed');

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();
        $this->assertTrue($this->_tableExists('table1',$oDB_Upgrade->aDBTables), 'test table was not restored');

        $aTbl_def_rest = $oDB_Upgrade->oSchema->getDefinitionFromDatabase(array($this->prefix.'table1'));
        $aTbl_def_rest = $aTbl_def_rest['tables'][$this->prefix.'table1'];

        // also test field definition properties?
        foreach ($aTbl_def_orig['fields'] AS $field=>$aDef)
        {
            $this->assertTrue(array_key_exists($field, $aTbl_def_rest['fields']), 'field missing from restored table');
        }

        // test field order?  (tho the field sort method is tested above so should be covered)
        foreach ($aTbl_def_orig['indexes'] AS $index=>$aDef)
        {
            $this->assertTrue(array_key_exists($index, $aTbl_def_rest['indexes']), 'index missing from restored table');
            if (array_key_exists('primary', $aDef))
            {
                $this->assertTrue(array_key_exists('primary', $aTbl_def_rest['indexes'][$index]), 'primary flag missing from restored index');
            }
            if (array_key_exists('unique', $aDef))
            {
                $this->assertTrue(array_key_exists('unique', $aTbl_def_rest['indexes'][$index]), 'unique flag missing from restored index');
            }
            foreach ($aDef['fields'] AS $field=>$aField)
            {
                $this->assertTrue(array_key_exists($field, $aTbl_def_rest['indexes'][$index]['fields']), 'index field missing from restored table');
            }
        }
      $this->assertFalse($this->_tableExists($oDB_Upgrade->aRestoreTables['table1']['bak'],$oDB_Upgrade->aDBTables), 'test table was not restored');
//        $oTable = new OA_DB_Table();
        // backup tables should now have been removed
        // drop the backup tables
//        OA_DB::setQuoteIdentifier();
//        $this->assertTrue($oTable->dropTable($this->prefix.$oDB_Upgrade->aRestoreTables['table1']['bak']),'error dropping test backup for table1');
//        OA_DB::disabledQuoteIdentifier();
    }

    /**
     * this test emulates an addTable event then executes rollback
     * added table should be dropped during rollback
     *
     */
    function test_BackupAndRollbackAddedTables()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();

        $oDB_Upgrade->aAddedTables['table2'] = true;

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();

        $this->assertTrue($oDB_Upgrade->rollback(), 'rollback failed');

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();
        $this->assertFalse($this->_tableExists('table2',$oDB_Upgrade->aDBTables), 'table2 was not dropped');

    }

    /**
     * this test emulates a removeTable event then executes rollback
     * *dropped* table should be restored during rollback
     *
     */
    function test_BackupAndRollbackDroppedTables()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject('destructive');

        $oDB_Upgrade->aChanges['affected_tables']['destructive'] = array('table1');

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();

        $this->assertTrue($oDB_Upgrade->_backup(), 'backup failed');

        $this->_dropTestTables($oDB_Upgrade->oSchema->db);

        $this->assertTrue($oDB_Upgrade->rollback(), 'rollback failed');

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();
        $this->assertTrue($this->_tableExists('table1',$oDB_Upgrade->aDBTables), 'table1 was not restored');

      $this->assertFalse($this->_tableExists($oDB_Upgrade->aRestoreTables['table1']['bak'],$oDB_Upgrade->aDBTables), 'test table was not restored');
//        $oTable = new OA_DB_Table();
        // backup tables should now have been removed
        // drop the backup tables
//        OA_DB::setQuoteIdentifier();
//        $this->assertTrue($oTable->dropTable($this->prefix.$oDB_Upgrade->aRestoreTables['table1']['bak']),'error dropping test backup for table1');
//        OA_DB::disabledQuoteIdentifier();

    }

    /**
     * this test calls backup method then immediately rollsback
     * emulating an upgrade error without interrupt (can recover in same session)
     *
     */
    function test_BackupAndRollback_restoreAutoIncrement()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();

        $this->_createTestTableAutoInc($oDB_Upgrade->oSchema->db);

        $oDB_Upgrade->aChanges['affected_tables']['constructive'] = array('table1_autoinc');

        $aTbl_def_orig = $oDB_Upgrade->oSchema->getDefinitionFromDatabase(array($this->prefix.'table1_autoinc'));
        $this->assertTrue($oDB_Upgrade->_backup(),'_backup failed');
        $this->assertIsA($oDB_Upgrade->aRestoreTables, 'array', 'aRestoreTables not an array');
        $this->assertTrue(array_key_exists('table1_autoinc', $oDB_Upgrade->aRestoreTables), 'table not found in aRestoreTables');
        $this->assertTrue(array_key_exists('bak', $oDB_Upgrade->aRestoreTables['table1_autoinc']), 'backup table name not found for table table1');
        $this->assertTrue(array_key_exists('def', $oDB_Upgrade->aRestoreTables['table1_autoinc']), 'definition array not found for table table1');

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();

        $table_bak = $oDB_Upgrade->aRestoreTables['table1_autoinc']['bak'];
        $this->assertTrue($this->_tableExists($table_bak, $oDB_Upgrade->aDBTables), 'backup table not found in database');

        OA_DB::setQuoteIdentifier();
        $aTbl_def_bak = $oDB_Upgrade->oSchema->getDefinitionFromDatabase(array($this->prefix.$table_bak));
        OA_DB::disabledQuoteIdentifier();

        $aTbl_def_orig = $aTbl_def_orig['tables'][$this->prefix.'table1_autoinc'];
        $aTbl_def_bak  = $aTbl_def_bak['tables'][$this->prefix.$table_bak];

        foreach ($aTbl_def_orig['fields'] AS $name=>$aType)
        {
            $this->assertTrue(array_key_exists($name, $aTbl_def_bak['fields']), 'field missing from backup table');
        }

        $oDB_Upgrade->oSchema->db->manager->dropTable($this->prefix.'table1_autoinc');

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();
        $this->assertFalse($this->_tableExists('table1_autoinc', $oDB_Upgrade->aDBTables), 'could not drop test table');

        $this->assertTrue($oDB_Upgrade->rollback(), 'rollback failed');

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();
        $this->assertTrue($this->_tableExists('table1_autoinc',$oDB_Upgrade->aDBTables), 'test table was not restored');

        $aTbl_def_rest = $oDB_Upgrade->oSchema->getDefinitionFromDatabase(array($this->prefix.'table1_autoinc'));
        $aTbl_def_rest = $aTbl_def_rest['tables']['table1_autoinc'];

        // also test field definition properties?

        $aDiffs       = $oDB_Upgrade->oSchema->compareDefinitions($aTbl_def_orig, $aTbl_def_rest);
        $this->assertEqual(count($aDiffs)==0,'differences found in restored table');

      $this->assertFalse($this->_tableExists($oDB_Upgrade->aRestoreTables['table1_autoinc']['bak'],$oDB_Upgrade->aDBTables), 'test table was not restored');
//        $oTable = new OA_DB_Table();
        // backup tables should now have been removed
        // drop the backup tables
//        OA_DB::setQuoteIdentifier();
//        $this->assertTrue($oTable->dropTable($this->prefix.$oDB_Upgrade->aRestoreTables['table1_autoinc']['bak']),'error dropping test backup for table1_autoinc');
//        OA_DB::disabledQuoteIdentifier();

    }

    /**
     * _verify methods look at the changeset and compile a tasklist
     * some verification of the tasks take place, eg, checking for existence of objects to be changed
     *
     */

    function test_verifyTasksIndexesRemove()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();

        $aPrev_definition               = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_original.xml');
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_indexRemove.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);

        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_indexRemove.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $this->assertTrue($oDB_Upgrade->_verifyTasksIndexesRemove(),'failed _verifyTasksIndexesRemove');
        $aTaskList = $oDB_Upgrade->aTaskList;
        $this->assertTrue(isset($aTaskList['indexes']['remove']),'failed creating task list: indexes remove');
        $this->assertEqual(count($aTaskList['indexes']['remove']),1, 'incorrect elements in task list: indexes remove');
        $this->assertEqual($aTaskList['indexes']['remove'][0]['name'], 'index2', 'wrong index name');
        $this->assertEqual($aTaskList['indexes']['remove'][0]['table'], 'table1', 'wrong table name');
    }

    /**
     * tests verification of adding an index as well as a primary and unique constraint
     *
     */
    function test_verifyTasksIndexesAdd()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();

        $aPrev_definition               = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_original.xml');
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_indexAdd.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);

        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_indexAdd.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $this->assertTrue($oDB_Upgrade->_verifyTasksIndexesAdd(),'failed _verifyTasksIndexesAdd');
        $aTaskList = $oDB_Upgrade->aTaskList;
        $this->assertTrue(isset($aTaskList['indexes']['add']),'failed creating task list: indexes add');
        $this->assertEqual(count($aTaskList['indexes']['add']),3, 'incorrect elements in task list: indexes add');

        $this->assertEqual($aTaskList['indexes']['add'][0]['name'], 'table2_pkey', 'wrong index name');
        $this->assertEqual($aTaskList['indexes']['add'][0]['table'], 'table2', 'wrong table name');
        $this->assertEqual(count($aTaskList['indexes']['add'][0]['cargo']),1, 'incorrect number of add index tasks in task list');
        $this->assertTrue(isset($aTaskList['indexes']['add'][0]['cargo']['indexes']),'indexes cargo array not found in task add array');
        $this->assertTrue(isset($aTaskList['indexes']['add'][0]['cargo']['indexes']['table2_pkey']),'index definition not found in task add array');
        $this->assertTrue(isset($aTaskList['indexes']['add'][0]['cargo']['indexes']['table2_pkey']['primary']),'index primary not found in task add array');
        $this->assertFalse(isset($aTaskList['indexes']['add'][0]['cargo']['indexes']['table2_pkey']['unique']),'index unique found in task add array');
        $this->assertTrue(isset($aTaskList['indexes']['add'][0]['cargo']['indexes']['table2_pkey']['fields']),'index fields not found in task add array');
        $this->assertTrue(isset($aTaskList['indexes']['add'][0]['cargo']['indexes']['table2_pkey']['fields']['b_id_field2']),'index field not found in task add array');
        $this->assertTrue(isset($aTaskList['indexes']['add'][0]['cargo']['indexes']['table2_pkey']['fields']['b_id_field2']['sorting']),'sorting not defined for field in task add array');

        $this->assertEqual($aTaskList['indexes']['add'][1]['name'], 'index_unique', 'wrong index name');
        $this->assertEqual($aTaskList['indexes']['add'][1]['table'], 'table2', 'wrong table name');
        $this->assertEqual(count($aTaskList['indexes']['add'][1]['cargo']),1, 'incorrect number of add index tasks in task list');
        $this->assertTrue(isset($aTaskList['indexes']['add'][1]['cargo']['indexes']),'indexes cargo array not found in task add array');
        $this->assertTrue(isset($aTaskList['indexes']['add'][1]['cargo']['indexes']['index_unique']),'index definition not found in task add array');
        $this->assertTrue(isset($aTaskList['indexes']['add'][1]['cargo']['indexes']['index_unique']['unique']),'index unique not found in task add array');
        $this->assertFalse(isset($aTaskList['indexes']['add'][1]['cargo']['indexes']['index_unique']['primary']),'index primary found in task add array');
        $this->assertTrue(isset($aTaskList['indexes']['add'][1]['cargo']['indexes']['index_unique']['fields']),'index fields not found in task add array');
        $this->assertTrue(isset($aTaskList['indexes']['add'][1]['cargo']['indexes']['index_unique']['fields']['b_id_field2']),'index field not found in task add array');
        $this->assertTrue(isset($aTaskList['indexes']['add'][1]['cargo']['indexes']['index_unique']['fields']['b_id_field2']['sorting']),'sorting not defined for field in task add array');

        $this->assertEqual($aTaskList['indexes']['add'][2]['name'], 'index_new', 'wrong index name');
        $this->assertEqual($aTaskList['indexes']['add'][2]['table'], 'table2', 'wrong table name');
        $this->assertEqual(count($aTaskList['indexes']['add'][2]['cargo']),1, 'incorrect number of add index tasks in task list');
        $this->assertTrue(isset($aTaskList['indexes']['add'][2]['cargo']['indexes']),'indexes cargo array not found in task add array');
        $this->assertTrue(isset($aTaskList['indexes']['add'][2]['cargo']['indexes']['index_new']),'index definition not found in task add array');
        $this->assertFalse(isset($aTaskList['indexes']['add'][2]['cargo']['indexes']['index_new']['unique']),'index unique found in task add array');
        $this->assertFalse(isset($aTaskList['indexes']['add'][2]['cargo']['indexes']['index_new']['primary']),'index primary found in task add array');
        $this->assertTrue(isset($aTaskList['indexes']['add'][2]['cargo']['indexes']['index_new']['fields']),'index fields not found in task add array');
        $this->assertTrue(isset($aTaskList['indexes']['add'][2]['cargo']['indexes']['index_new']['fields']['b_id_field2']),'index field not found in task add array');
        $this->assertTrue(isset($aTaskList['indexes']['add'][2]['cargo']['indexes']['index_new']['fields']['b_id_field2']['sorting']),'sorting not defined for field in task add array');
    }

    function test_verifyTasksTablesAdd()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();

        $aPrev_definition               = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_original.xml');
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_tableAdd.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);

        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_tableAdd.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $this->assertTrue($oDB_Upgrade->_verifyTasksTablesAdd(),'failed _verifyTasksTablesAdd');
        $aTaskList = $oDB_Upgrade->aTaskList;
        $this->assertTrue(isset($aTaskList['tables']['add']),'failed creating task list: tables add');
        $this->assertEqual(count($aTaskList['tables']['add']),1, 'incorrect elements in task list: tables add');
        $this->assertEqual($aTaskList['tables']['add'][0]['name'], 'table_new', 'wrong table name');
        $this->assertEqual(count($aTaskList['tables']['add'][0]['cargo']),2, 'incorrect number of add table tasks in task list');
        $this->assertTrue(isset($aTaskList['tables']['add'][0]['cargo']['a_text_field_new']),'a_text_field_new field not found in task add array');
        $this->assertTrue(isset($aTaskList['tables']['add'][0]['cargo']['b_id_field_new']),'b_id_field_new field not found in task add array');
        $this->assertEqual(count($aTaskList['tables']['add'][0]['indexes']),2, 'incorrect number of add table indexes in task list');
        $this->assertEqual($aTaskList['tables']['add'][0]['indexes'][0]['name'],'table_new_pkey','index1_new not found in task index array');
        $this->assertEqual($aTaskList['tables']['add'][0]['indexes'][0]['table'],'table_new','wrong table in task index array');
        $this->assertEqual($aTaskList['tables']['add'][0]['indexes'][1]['name'],'index2_new','index2_new not found in task index array');
        $this->assertEqual($aTaskList['tables']['add'][0]['indexes'][1]['table'],'table_new','wrong table in task index array');

    }

    function test_verifyTasksTablesRemove()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject('destructive');

        $aPrev_definition               = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_original.xml');
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_tableRemove.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);

        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_tableRemove.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $this->assertTrue($oDB_Upgrade->_verifyTasksTablesRemove(),'failed _verifyTasksTablesRemove');
        $aTaskList                      = $oDB_Upgrade->aTaskList;
        $this->assertTrue(isset($aTaskList['tables']['remove']),'failed creating task list: tables remove');
        $this->assertEqual(count($aTaskList['tables']['remove']),1, 'incorrect elements in task list: tables remove');
        $this->assertEqual($aTaskList['tables']['remove'][0]['name'], 'table2', 'wrong table name');
    }

    function test_verifyTasksTablesRename()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();

        $aPrev_definition               = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_original.xml');
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_tableRename.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);

        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_tableRename.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);

        $this->_editChangesetTableRename($oDB_Upgrade, 'table1_rename', 'table1');

        $this->aOptions['split']        = false; // this is a rewrite of a previously split changeset, don't split it again
        $this->aOptions['rewrite']      = true; // this is a rewrite of a previously split changeset, don't split it again
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($oDB_Upgrade->aChanges, $this->aOptions);
        $this->aOptions['rewrite']      = false; // reset this var
        $this->aOptions['split']        = true; // reset this var
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $oDB_Upgrade->aTaskList         = $aTaskList;
        $this->assertTrue($oDB_Upgrade->_verifyTasksTablesRename(),'failed test_verifyTasksTablesRename');
        $aTaskList = $oDB_Upgrade->aTaskList;
        $this->assertTrue(isset($aTaskList['tables']['rename']),'failed creating task list: tables rename');
        $this->assertEqual(count($aTaskList['tables']['rename']),1, 'incorrect elements in task list: tables rename');
        $this->assertEqual($aTaskList['tables']['rename'][0]['name'], 'table1_rename', 'wrong new table name');
        $this->assertEqual($aTaskList['tables']['rename'][0]['cargo']['was'], 'table1', 'wrong old table name');
    }

    /**
     * tests the verification of a variety of possible table alterations
     *
     */
    function test_verifyTasksTablesAlter()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();

        $aPrev_definition               = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_original.xml');

        // Test 1 : add field
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_tableAlter1.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);
        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_tableAlter1.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $oDB_Upgrade->aTaskList = array();
        $this->assertTrue($oDB_Upgrade->_verifyTasksTablesAlter(),'failed _verifyTasksTablesAlter: add field');
        $aTaskList = $oDB_Upgrade->aTaskList;
        $this->assertTrue(isset($aTaskList['fields']['add']),'failed creating task list: fields add');
        $this->assertEqual(count($aTaskList['fields']['add']),1, 'incorrect elements in task list: fields add');
        $this->assertEqual($aTaskList['fields']['add'][0]['name'], 'table1', 'wrong table name');
        $this->assertEqual($aTaskList['fields']['add'][0]['field'], 'c_date_field_new', 'wrong field name');
        $this->assertEqual(count($aTaskList['fields']['add'][0]['cargo']),1, 'incorrect number of add fields tasks in task list');
        $this->assertTrue(isset($aTaskList['fields']['add'][0]['cargo']['add']['c_date_field_new']),'c_date_field_new field not found in task add array');

        // Test 2 : change field
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_tableAlter2.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);
        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_tableAlter2.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $oDB_Upgrade->aTaskList = array();
        $this->assertTrue($oDB_Upgrade->_verifyTasksTablesAlter(),'failed _verifyTasksTablesAlter: change field');
        $aTaskList = $oDB_Upgrade->aTaskList;
        $this->assertTrue(isset($aTaskList['fields']['change']),'failed creating task list: fields change');
        $this->assertEqual(count($aTaskList['fields']['change']),1, 'incorrect elements in task list: fields change');
        $this->assertEqual($aTaskList['fields']['change'][0]['name'], 'table1', 'wrong table name');
        $this->assertEqual($aTaskList['fields']['change'][0]['field'], 'a_text_field', 'wrong field name');
        $this->assertEqual(count($aTaskList['fields']['change'][0]['cargo']),1, 'incorrect number of change fields tasks in task list');
        $this->assertTrue(isset($aTaskList['fields']['change'][0]['cargo']['change']['a_text_field']),'a_text_field field not found in task change array');
        $this->assertEqual($aTaskList['fields']['change'][0]['cargo']['change']['a_text_field']['definition']['default'],'foo','a_text_field default property not set in task change array');
        $this->assertEqual($aTaskList['fields']['change'][0]['cargo']['change']['a_text_field']['definition']['length'],64,'a_text_field length property not set in task change array');

        // Test 5 : change primary key field
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_tableAlter5.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);
        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_tableAlter5.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $oDB_Upgrade->aTaskList = array();
        $this->assertTrue($oDB_Upgrade->_verifyTasksTablesAlter(),'failed _verifyTasksTablesAlter: change field');
        $aTaskList = $oDB_Upgrade->aTaskList;
        $this->assertTrue(isset($aTaskList['fields']['change']),'failed creating task list: fields change');
        $this->assertEqual(count($aTaskList['fields']['change']),1, 'incorrect elements in task list: fields change');
        $this->assertEqual($aTaskList['fields']['change'][0]['name'], 'table1', 'wrong table name');
        $this->assertEqual($aTaskList['fields']['change'][0]['field'], 'b_id_field', 'wrong field name');
        $this->assertEqual(count($aTaskList['fields']['change'][0]['cargo']),1, 'incorrect number of change fields tasks in task list');
        $this->assertTrue(isset($aTaskList['fields']['change'][0]['cargo']['change']['b_id_field']),'b_id_field field not found in task change array');
        $this->assertTrue($aTaskList['fields']['change'][0]['cargo']['change']['b_id_field']['definition']['autoincrement'],'b_id_field autoincrement property not set in task change array');
        $this->assertEqual($aTaskList['fields']['change'][0]['cargo']['change']['b_id_field']['definition']['length'],11,'b_id_field length property not set in task change array');

        // Test 4 : rename field
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_tableAlter4.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);
        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_tableAlter4.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);

        $this->_editChangesetFieldRename($oDB_Upgrade, 'table1', 'b_id_field_renamed', 'b_id_field');

        $this->aOptions['split']        = false; // this is a rewrite of a previously split changeset, don't split it again
        $this->aOptions['rewrite']      = true; // this is a rewrite of a previously split changeset, don't split it again
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($oDB_Upgrade->aChanges, $this->aOptions);
        $this->aOptions['rewrite']      = false; // reset this var
        $this->aOptions['split']        = true; // reset this var
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $oDB_Upgrade->aTaskList = array();
        $this->assertTrue($oDB_Upgrade->_verifyTasksTablesAlter(),'failed _verifyTasksTablesAlter: rename field');
        $aTaskList = $oDB_Upgrade->aTaskList;
        $this->assertTrue(isset($aTaskList['fields']['rename']),'failed creating task list: fields rename');
        $this->assertEqual(count($aTaskList['fields']['rename']),1, 'incorrect elements in task list: fields rename');
        $this->assertEqual($aTaskList['fields']['rename'][0]['name'], 'table1', 'wrong table name');
        $this->assertEqual($aTaskList['fields']['rename'][0]['field'], 'b_id_field', 'wrong field name');
        $this->assertEqual(count($aTaskList['fields']['rename'][0]['cargo']['rename']),1, 'incorrect number of rename fields tasks in task list');
        $this->assertTrue(isset($aTaskList['fields']['rename'][0]['cargo']['rename']['b_id_field']),'b_id_field field not found in task change array');
        $this->assertEqual($aTaskList['fields']['rename'][0]['cargo']['rename']['b_id_field']['name'],'b_id_field_renamed','b_id_field wrong value in task change array');

         // Test 6 : add primary key field
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_tableAlter6.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);
        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_tableAlter6.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $oDB_Upgrade->aTaskList = array();
        $this->assertTrue($oDB_Upgrade->_verifyTasksTablesAlter(),'failed _verifyTasksTablesAlter: add primary key field');
        $aTaskList = $oDB_Upgrade->aTaskList;
        $this->assertTrue(isset($aTaskList['fields']['add']),'failed creating task list: fields add');
        $this->assertEqual(count($aTaskList['fields']['add']),1, 'incorrect elements in task list: fields add');
        $this->assertEqual($aTaskList['fields']['add'][0]['name'], 'table2', 'wrong table name');
        $this->assertEqual($aTaskList['fields']['add'][0]['field'], 'b_id_field_pk', 'wrong field name');
        $this->assertEqual(count($aTaskList['fields']['add'][0]['cargo']),1, 'incorrect number of add fields tasks in task list');
        $this->assertTrue(isset($aTaskList['fields']['add'][0]['cargo']['add']['b_id_field_pk']),'b_id_field_pk field not found in task add array');

        // Test 3 : remove field
        $oDB_Upgrade = $this->_newDBUpgradeObject('destructive');
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_tableAlter3.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);
        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_tableAlter3.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $oDB_Upgrade->aTaskList = array();
        $this->assertTrue($oDB_Upgrade->_verifyTasksTablesAlter(),'failed _verifyTasksTablesAlter: remove field');
        $aTaskList = $oDB_Upgrade->aTaskList;
        $this->assertTrue(isset($aTaskList['fields']['remove']),'failed creating task list: fields remove');
        $this->assertEqual(count($aTaskList['fields']['remove']),1, 'incorrect elements in task list: fields remove');
        $this->assertEqual($aTaskList['fields']['remove'][0]['name'], 'table1', 'wrong table name');
        $this->assertEqual($aTaskList['fields']['remove'][0]['field'], 'a_text_field', 'wrong field name');
        $this->assertEqual(count($aTaskList['fields']['remove'][0]['cargo']['remove']),1, 'incorrect number of remove fields tasks in task list');
        $this->assertTrue(isset($aTaskList['fields']['remove'][0]['cargo']['remove']['a_text_field']),'a_text_field field not found in task change array');

    }

    /**
     * _execute methods look items in the tasklist and execute them
     * the external migration calls are mocked
     *
     */
    function test_executeTasksIndexesRemove()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();

        $aPrev_definition               = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_original.xml');
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_indexRemove.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);

        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_indexRemove.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $this->assertTrue($oDB_Upgrade->_verifyTasksIndexesRemove(),'failed _verifyTasksIndexesRemove');

        // no migration callbacks on index events

        $aConstraints = $oDB_Upgrade->oSchema->db->manager->listTableConstraints($this->prefix.'table1');
        $this->assertTrue(in_array('index2', $aConstraints),'index2 not found');

        $this->assertTrue($oDB_Upgrade->_executeTasksIndexesRemove(),'failed _executeTasksIndexesRemove');

        $aConstraints = $oDB_Upgrade->oSchema->db->manager->listTableConstraints($this->prefix.'table1');
        $this->assertFalse(in_array('index2', $aConstraints),'index2 found');
    }

    /**
     * tests execution of adding an index as well as a primary and unique constraint
     *
     */
    function test_executeTasksIndexesAdd()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();

        $aPrev_definition               = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_original.xml');
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_indexAdd.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);

        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_indexAdd.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $this->assertTrue($oDB_Upgrade->_verifyTasksIndexesAdd(),'failed _verifyTasksIndexesAdd');

        // no migration callbacks on index events

        $aConstraints   = $oDB_Upgrade->oSchema->db->manager->listTableConstraints($this->prefix.'table2');
        $this->assertFalse(in_array($this->prefix.'table2_pkey', $aConstraints),'table2_pkey found');
        $this->assertFalse(in_array('index_unique', $aConstraints),'index_unique found');
        $aIndexes       = $oDB_Upgrade->oSchema->db->manager->listTableIndexes($this->prefix.'table2');
        $this->assertFalse(in_array('index_new', $aIndexes),'index_new found');

        $this->assertTrue($oDB_Upgrade->_executeTasksIndexesAdd(),'failed _executeTasksIndexesAdd');

        $aConstraints   = $oDB_Upgrade->oSchema->db->manager->listTableConstraints($this->prefix.'table2');
        $this->assertTrue(in_array($this->prefix.'table2_pkey', $aConstraints),'table2_pkey not found');
        $this->assertTrue(in_array('index_unique', $aConstraints),'index_unique not found');
        $aIndexes       = $oDB_Upgrade->oSchema->db->manager->listTableIndexes($this->prefix.'table2');
        $this->assertTrue(in_array('index_new', $aIndexes),'index_new not found');
    }

    function test_executeTasksTablesAdd()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();
        $oDB_Upgrade->aChanges['affected_tables']['constructive'] = array('table2');

        $aPrev_definition               = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_original.xml');
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_tableAdd.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);

        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_tableAdd.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $this->assertTrue($oDB_Upgrade->_verifyTasksTablesAdd(),'failed _verifyTasksTablesAdd');

        Mock::generatePartial(
            'Migration',
            $mockMigrator = 'Migration_'.rand(),
            array('beforeAddTable__table_new', 'afterAddTable__table_new')
        );

        $oDB_Upgrade->oMigrator = new $mockMigrator($this);
        $oDB_Upgrade->oMigrator->setReturnValue('beforeAddTable__table_new', true);
        $oDB_Upgrade->oMigrator->expectOnce('beforeAddTable__table_new');
        $oDB_Upgrade->oMigrator->setReturnValue('afterAddTable__table_new', true);
        $oDB_Upgrade->oMigrator->expectOnce('afterAddTable__table_new');

        $oDB_Upgrade->oTable->init($this->path.'schema_test_tableAdd.xml');
        $this->assertTrue($oDB_Upgrade->_executeTasksTablesAdd(),'failed _executeTasksTablesAdd');
        $oDB_Upgrade->oMigrator->tally();

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();
        $this->assertTrue($this->_tableExists('table_new', $oDB_Upgrade->aDBTables),'table_new not found');
        //$this->assertTrue(in_array($oDB_Upgrade->prefix.'table_new', $oDB_Upgrade->aDBTables),'table_new not found');
        $aDBFields = $oDB_Upgrade->oSchema->db->manager->listTableFields($this->prefix.'table_new');
        $this->assertTrue(in_array('b_id_field_new', $aDBFields),'b_id_field_new not found in table_new');
        $this->assertTrue(in_array('a_text_field_new', $aDBFields),'a_text_field_new not found in table_new');
    }

    function test_executeTasksTablesRemove()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject('destructive');

        $aPrev_definition               = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_original.xml');
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_tableRemove.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);

        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_tableRemove.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $this->assertTrue($oDB_Upgrade->_verifyTasksTablesRemove(),'failed _verifyTasksTablesRemove');

        Mock::generatePartial(
            'Migration',
            $mockMigrator = 'Migration_'.rand(),
            array('beforeRemoveTable__table2', 'afterRemoveTable__table2')
        );

        $oDB_Upgrade->oMigrator = new $mockMigrator($this);
        $oDB_Upgrade->oMigrator->setReturnValue('beforeRemoveTable__table2', true);
        $oDB_Upgrade->oMigrator->expectOnce('beforeRemoveTable__table2');
        $oDB_Upgrade->oMigrator->setReturnValue('afterRemoveTable__table2', true);
        $oDB_Upgrade->oMigrator->expectOnce('afterRemoveTable__table2');

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();
        $this->assertTrue($this->_tableExists('table2', $oDB_Upgrade->aDBTables),'table2 not found');

        $this->assertTrue($oDB_Upgrade->_executeTasksTablesRemove(),'failed _executeTasksTablesRemove');
        $oDB_Upgrade->oMigrator->tally();

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();
        $this->assertFalse($this->_tableExists('table2', $oDB_Upgrade->aDBTables),'table2 found');
    }

    function test_executeTasksTablesRename()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();

        $aPrev_definition               = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_original.xml');
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_tableRename.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);

        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_tableRename.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);

        $this->_editChangesetTableRename($oDB_Upgrade, 'table1_rename', 'table1');

        $this->aOptions['split']        = false; // this is a rewrite of a previously split changeset, don't split it again
        $this->aOptions['rewrite']      = true; // this is a rewrite of a previously split changeset, don't split it again
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($oDB_Upgrade->aChanges, $this->aOptions);
        $this->aOptions['rewrite']      = false; // reset this var
        $this->aOptions['split']        = true; // reset this var
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $oDB_Upgrade->aTaskList         = $aTaskList;
        $this->assertTrue($oDB_Upgrade->_verifyTasksTablesRename(),'failed test_verifyTasksTablesRename');

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();
        $this->assertFalse($this->_tableExists('table1_rename', $oDB_Upgrade->aDBTables),'table1_rename found');
        $this->assertTrue($this->_tableExists('table1', $oDB_Upgrade->aDBTables),'table1 not found');

        Mock::generatePartial(
            'Migration',
            $mockMigrator = 'Migration_'.rand(),
            array('beforeRenameTable__table1_rename', 'afterRenameTable__table1_rename')
        );

        $oDB_Upgrade->oMigrator = new $mockMigrator($this);
        $oDB_Upgrade->oMigrator->setReturnValue('beforeRenameTable__table1_rename', true);
        $oDB_Upgrade->oMigrator->expectOnce('beforeRenameTable__table1_rename');
        $oDB_Upgrade->oMigrator->setReturnValue('afterRenameTable__table1_rename', true);
        $oDB_Upgrade->oMigrator->expectOnce('afterRenameTable__table1_rename');

        $this->assertTrue($oDB_Upgrade->_executeTasksTablesRename(),'failed _executeTasksTablesRename');
        $oDB_Upgrade->oMigrator->tally();

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();
        $this->assertTrue($this->_tableExists('table1_rename', $oDB_Upgrade->aDBTables),'table1_rename not found');
        $this->assertFalse($this->_tableExists('table1', $oDB_Upgrade->aDBTables),'table1 found');

        $oTable = new OA_DB_Table();
        $this->assertTrue($oTable->dropTable($this->prefix.'table1_rename'),'error dropping table1_rename');
    }

    /**
     * test the execution of a variety of table alterations
     *
     */
    function test_executeTasksTablesAlter()
    {
        $oDB_Upgrade = $this->_newDBUpgradeObject();

        $aPrev_definition                = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_original.xml');

        // Test 1 : add field
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_tableAlter1.xml');
        $aChanges_write                  = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);
        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_tableAlter1.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $oDB_Upgrade->aTaskList = array();
        $this->assertTrue($oDB_Upgrade->_verifyTasksTablesAlter(),'failed _verifyTasksTablesAlter: add field');

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();
        $this->assertTrue($this->_tableExists('table1', $oDB_Upgrade->aDBTables),'table1 not found');
        $aDBFields = $oDB_Upgrade->oSchema->db->manager->listTableFields($this->prefix.'table1');
        $this->assertFalse(in_array('c_date_field_new', $aDBFields),'c_date_field_new found in table1');

        Mock::generatePartial(
            'Migration',
            $mockMigrator = 'Migration_'.rand(),
            array('beforeAddField__table1__c_date_field_new', 'afterAddField__table1__c_date_field_new')
        );

        $oDB_Upgrade->oMigrator = new $mockMigrator($this);
        $oDB_Upgrade->oMigrator->setReturnValue('beforeAddField__table1__c_date_field_new', true);
        $oDB_Upgrade->oMigrator->expectOnce('beforeAddField__table1__c_date_field_new');
        $oDB_Upgrade->oMigrator->setReturnValue('afterAddField__table1__c_date_field_new', true);
        $oDB_Upgrade->oMigrator->expectOnce('afterAddField__table1__c_date_field_new');

        $this->assertTrue($oDB_Upgrade->_executeTasksTablesAlter(),'failed _executeTasksTablesAlter: add field');
        $oDB_Upgrade->oMigrator->tally();

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();
        $this->assertTrue($this->_tableExists('table1', $oDB_Upgrade->aDBTables),'table1 not found');
        $aDBFields = $oDB_Upgrade->oSchema->db->manager->listTableFields($this->prefix.'table1');
        $this->assertTrue(in_array('c_date_field_new', $aDBFields),'c_date_field_new not found in table1');

        // Test 2 : change field
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_tableAlter2.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);
        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_tableAlter2.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $oDB_Upgrade->aTaskList = array();
        $this->assertTrue($oDB_Upgrade->_verifyTasksTablesAlter(),'failed _verifyTasksTablesAlter: change field');

        $aDef = $oDB_Upgrade->oSchema->getDefinitionFromDatabase(array($this->prefix.'table1'));
        $this->assertEqual($aDef['tables'][$this->prefix.'table1']['fields']['a_text_field']['default'],'','wrong original default value');
        $this->assertEqual($aDef['tables'][$this->prefix.'table1']['fields']['a_text_field']['length'],32,'wrong original length value');

        Mock::generatePartial(
            'Migration',
            $mockMigrator = 'Migration_'.rand(),
            array('beforeAlterField__table1__a_text_field', 'afterAlterField__table1__a_text_field')
        );

        $oDB_Upgrade->oMigrator = new $mockMigrator($this);
        $oDB_Upgrade->oMigrator->setReturnValue('beforeAlterField__table1__a_text_field', true);
        $oDB_Upgrade->oMigrator->expectOnce('beforeAlterField__table1__a_text_field');
        $oDB_Upgrade->oMigrator->setReturnValue('afterAlterField__table1__a_text_field', true);
        $oDB_Upgrade->oMigrator->expectOnce('afterAlterField__table1__a_text_field');

        $this->assertTrue($oDB_Upgrade->_executeTasksTablesAlter(),'failed _executeTasksTablesAlter: change field');
        $oDB_Upgrade->oMigrator->tally();
        $aDef = $oDB_Upgrade->oSchema->getDefinitionFromDatabase(array($this->prefix.'table1'));
        $this->assertEqual($aDef['tables'][$this->prefix.'table1']['fields']['a_text_field']['default'],'foo','wrong assigned default value');
        $this->assertEqual($aDef['tables'][$this->prefix.'table1']['fields']['a_text_field']['length'],64,'wrong assigned length value');

        // Test 5 : change primary key field
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_tableAlter5.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);
        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_tableAlter5.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aTaskList = array();
        $this->assertTrue($oDB_Upgrade->_verifyTasksTablesAlter(),'failed _verifyTasksTablesAlter: change field');

        $aDef = $oDB_Upgrade->oSchema->getDefinitionFromDatabase(array($this->prefix.'table1'));
        $this->assertFalse($aDef['tables'][$this->prefix.'table1']['fields']['b_id_field']['autoincrement'],'','wrong original autoincrement value');
        $this->assertEqual($aDef['tables'][$this->prefix.'table1']['fields']['b_id_field']['length'],9,'wrong original length value');

        Mock::generatePartial(
            'Migration',
            $mockMigrator = 'Migration_'.rand(),
            array('beforeAlterField__table1__b_id_field', 'afterAlterField__table1__b_id_field')
        );

        $oDB_Upgrade->oMigrator = new $mockMigrator($this);
        $oDB_Upgrade->oMigrator->setReturnValue('beforeAlterField__table1__b_id_field', true);
        $oDB_Upgrade->oMigrator->expectOnce('beforeAlterField__table1__b_id_field');
        $oDB_Upgrade->oMigrator->setReturnValue('afterAlterField__table1__b_id_field', true);
        $oDB_Upgrade->oMigrator->expectOnce('afterAlterField__table1__b_id_field');

        $this->assertTrue($oDB_Upgrade->_executeTasksTablesAlter(),'failed _executeTasksTablesAlter: change field');
        $oDB_Upgrade->oMigrator->tally();
        $aDef = $oDB_Upgrade->oSchema->getDefinitionFromDatabase(array($this->prefix.'table1'));
        $this->assertTrue($aDef['tables'][$this->prefix.'table1']['fields']['b_id_field']['autoincrement'],'wrong assigned autoincrement value');
        $this->assertEqual($aDef['tables'][$this->prefix.'table1']['fields']['b_id_field']['length'],11,'wrong assigned length value');

        // Test 4 : rename field
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_tableAlter4.xml');
        $aChanges_write                  = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);
        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_tableAlter4.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);

        $this->_editChangesetFieldRename($oDB_Upgrade, 'table1', 'b_id_field_renamed', 'b_id_field');

        $this->aOptions['split']        = false; // this is a rewrite of a previously split changeset, don't split it again
        $this->aOptions['rewrite']      = true; // this is a rewrite of a previously split changeset, don't split it again
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($oDB_Upgrade->aChanges, $this->aOptions);
        $this->aOptions['rewrite']      = false; // reset this var
        $this->aOptions['split']        = true; // reset this var
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $oDB_Upgrade->aTaskList = array();
        $this->assertTrue($oDB_Upgrade->_verifyTasksTablesAlter(),'failed _verifyTasksTablesAlter: rename field');

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();
        $this->assertTrue($this->_tableExists('table1', $oDB_Upgrade->aDBTables),'table1 not found');
        $aDBFields = $oDB_Upgrade->oSchema->db->manager->listTableFields($this->prefix.'table1');
        $this->assertTrue(in_array('b_id_field', $aDBFields),'b_id_field not found in table1');
        $this->assertFalse(in_array('b_id_field_renamed', $aDBFields),'b_id_field_renamed found in table1');

        Mock::generatePartial(
            'Migration',
            $mockMigrator = 'Migration_'.rand(),
            array('beforeRenameField__table1__b_id_field_renamed', 'afterRenameField__table1__b_id_field_renamed')
        );

        $oDB_Upgrade->oMigrator = new $mockMigrator($this);
        $oDB_Upgrade->oMigrator->setReturnValue('beforeRenameField__table1__b_id_field_renamed', true);
        $oDB_Upgrade->oMigrator->expectOnce('beforeRenameField__table1__b_id_field_renamed');
        $oDB_Upgrade->oMigrator->setReturnValue('afterRenameField__table1__b_id_field_renamed', true);
        $oDB_Upgrade->oMigrator->expectOnce('afterRenameField__table1__b_id_field_renamed');

        $this->assertTrue($oDB_Upgrade->_executeTasksTablesAlter(),'failed _executeTasksTablesAlter: rename field');
        $oDB_Upgrade->oMigrator->tally();

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();
        $this->assertTrue($this->_tableExists('table1', $oDB_Upgrade->aDBTables),'table1 not found');
        $aDBFields = $oDB_Upgrade->oSchema->db->manager->listTableFields($this->prefix.'table1');
        $this->assertFalse(in_array('b_id_field', $aDBFields),'b_id_field found in table1');
        $this->assertTrue(in_array('b_id_field_renamed', $aDBFields),'b_id_field_renamed not found in table1');

         // Test 6 : add primary key field
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_tableAlter6.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);
        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_tableAlter6.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $oDB_Upgrade->aTaskList = array();
        $this->assertTrue($oDB_Upgrade->_verifyTasksTablesAlter(),'failed _verifyTasksTablesAlter: add primary key field');

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();
        $this->assertTrue($this->_tableExists('table2', $oDB_Upgrade->aDBTables),'table2 not found');
        $aDBFields = $oDB_Upgrade->oSchema->db->manager->listTableFields($this->prefix.'table2');
        $this->assertFalse(in_array('b_id_field_pk', $aDBFields),'b_id_field_pk found in table2');

        Mock::generatePartial(
            'Migration',
            $mockMigrator = 'Migration_'.rand(),
            array('beforeAddField__table2__b_id_field_pk', 'afterAddField__table2__b_id_field_pk')
        );

        $oDB_Upgrade->oMigrator = new $mockMigrator($this);
        $oDB_Upgrade->oMigrator->setReturnValue('beforeAddField__table2__b_id_field_pk', true);
        $oDB_Upgrade->oMigrator->expectOnce('beforeAddField__table2__b_id_field_pk');
        $oDB_Upgrade->oMigrator->setReturnValue('afterAddField__table2__b_id_field_pk', true);
        $oDB_Upgrade->oMigrator->expectOnce('afterAddField__table2__b_id_field_pk');

        $this->assertTrue($oDB_Upgrade->_executeTasksTablesAlter(),'failed _executeTasksTablesAlter: add primary key field');
        $oDB_Upgrade->oMigrator->tally();

        $oDB_Upgrade->aDBTables = $oDB_Upgrade->_listTables();
        $this->assertTrue($this->_tableExists('table2', $oDB_Upgrade->aDBTables),'table2 not found');
        $aDBFields = $oDB_Upgrade->oSchema->db->manager->listTableFields($this->prefix.'table2');
        $this->assertTrue(in_array('b_id_field_pk', $aDBFields),'b_id_field_pk not found in table1');

        // Test 3 : remove field
        $oDB_Upgrade = $this->_newDBUpgradeObject('destructive');
        $oDB_Upgrade->aDefinitionNew    = $oDB_Upgrade->oSchema->parseDatabaseDefinitionFile($this->path.'schema_test_tableAlter3.xml');
        $aChanges_write                 = $oDB_Upgrade->oSchema->compareDefinitions($oDB_Upgrade->aDefinitionNew, $aPrev_definition);
        $this->aOptions['output']       = MAX_PATH.'/var/changes_test_tableAlter3.xml';
        $result                         = $oDB_Upgrade->oSchema->dumpChangeset($aChanges_write, $this->aOptions);
        $oDB_Upgrade->aChanges          = $oDB_Upgrade->oSchema->parseChangesetDefinitionFile($this->aOptions['output']);
        $oDB_Upgrade->aDBTables         = $oDB_Upgrade->_listTables();
        $oDB_Upgrade->aTaskList = array();
        $this->assertTrue($oDB_Upgrade->_verifyTasksTablesAlter(),'failed _verifyTasksTablesAlter: remove field');

        $aDBFields = $oDB_Upgrade->oSchema->db->manager->listTableFields($this->prefix.'table1');
        $this->assertTrue(in_array('a_text_field', $aDBFields),'a_text_field not found in table1');

        Mock::generatePartial(
            'Migration',
            $mockMigrator = 'Migration_'.rand(),
            array('beforeRemoveField__table1__a_text_field', 'afterRemoveField__table1__a_text_field')
        );

        $oDB_Upgrade->oMigrator = new $mockMigrator($this);
        $oDB_Upgrade->oMigrator->setReturnValue('beforeRemoveField__table1__a_text_field', true);
        $oDB_Upgrade->oMigrator->expectOnce('beforeRemoveField__table1__a_text_field');
        $oDB_Upgrade->oMigrator->setReturnValue('afterRemoveField__table1__a_text_field', true);
        $oDB_Upgrade->oMigrator->expectOnce('afterRemoveField__table1__a_text_field');

        $this->assertTrue($oDB_Upgrade->_executeTasksTablesAlter(),'failed _executeTasksTablesAlter: remove field');
        $oDB_Upgrade->oMigrator->tally();

        $aDBFields = $oDB_Upgrade->oSchema->db->manager->listTableFields($this->prefix.'table1');
        $this->assertFalse(in_array('a_text_field', $aDBFields),'a_text_field found in table1');
    }

    function _tableExists($tablename, $aExistingTables)
    {
        return in_array($this->prefix.$tablename, $aExistingTables);
    }

    /**
     * internal function to set up some test tables
     *
     * @param mdb2 connection $oDbh
     */
    function _createTestTables($oDbh)
    {
        $this->_dropTestTables($oDbh);
        $conf = &$GLOBALS['_MAX']['CONF'];
        //$conf['table']['prefix'] = '';
        $conf['table']['split'] = false;
        $oTable = new OA_DB_Table();
        $oTable->init($this->path.'schema_test_original.xml');
        $this->assertTrue($oTable->createTable('table1'),'error creating test table1');
        $this->assertTrue($oTable->createTable('table2'),'error creating test table2');
        $aExistingTables = $oDbh->manager->listTables();
        $this->assertTrue($this->_tableExists('table1', $aExistingTables), '_createTestTables');
        $this->assertTrue($this->_tableExists('table2', $aExistingTables), '_createTestTables');
        return $oTable->aDefinition;
    }

    function _dropTestTables($oDbh)
    {
        $conf = &$GLOBALS['_MAX']['CONF'];
        //$conf['table']['prefix'] = '';
        $conf['table']['split'] = false;
        $oTable = new OA_DB_Table();
        $oTable->init($this->path.'schema_test_original.xml');
        $aExistingTables = $oDbh->manager->listTables();
        if ($this->_tableExists('table1', $aExistingTables))
        {
            $this->assertTrue($oTable->dropTable($this->prefix.'table1'),'error dropping test table1');
        }
        if ($this->_tableExists('table2', $aExistingTables))
        {
            $this->assertTrue($oTable->dropTable($this->prefix.'table2'),'error dropping test table2');
        }
        $aExistingTables = $oDbh->manager->listTables();
        $this->assertFalse($this->_tableExists('table1', $aExistingTables), '_dropTestTables');
        $this->assertFalse($this->_tableExists('table2', $aExistingTables), '_dropTestTables');
    }

    /**
     * internal function to set up some a test table with an autoincrement field
     *
     * @param mdb2 connection $oDbh
     */
    function _createTestTableAutoInc($oDbh)
    {
        $this->_dropTestTables($oDbh);
        $conf = &$GLOBALS['_MAX']['CONF'];
        //$conf['table']['prefix'] = '';
        $conf['table']['split'] = false;
        $oTable = new OA_DB_Table();
        $oTable->init($this->path.'schema_test_autoinc.xml');
        $this->assertTrue($oTable->createTable('table1_autoinc'),'error creating test table1_autoinc');
        $aExistingTables = $oDbh->manager->listTables();
        $this->assertTrue($this->_tableExists('table1_autoinc', $aExistingTables), '_createTestTableAutoInc');
    }

    function _dropTestTableAutoInc($oDbh)
    {
        $conf = &$GLOBALS['_MAX']['CONF'];
        //$conf['table']['prefix'] = 'test_';
        $conf['table']['split'] = false;
        $oTable = new OA_DB_Table();
        $oTable->init($this->path.'schema_test_autoinc.xml');
        $aExistingTables = $oDbh->manager->listTables();
        if ($this->_tableExists('table1_autoinc', $aExistingTables))
        {
            $this->assertTrue($oTable->dropTable($this->prefix.'table1_autoinc'),'error dropping test table1_autoinc');
        }
        $aExistingTables = $oDbh->manager->listTables();
        $this->assertFalse($this->_tableExists('table1_autoinc', $aExistingTables), '_dropTestTableAutoInc');
    }
    /**
     * internal function to return an initialised db_upgrade object for testing
     *
     * @param string $timing
     * @return object
     */
    function _newDBUpgradeObject($timing='constructive')
    {
        $oDB_Upgrade = & new OA_DB_Upgrade();
        $oDB_Upgrade->initMDB2Schema();
        $oDB_Upgrade->timingStr = $timing;
        $oDB_Upgrade->timingInt = ($timing ? 0 : 1);
        $oDB_Upgrade->prefix = $this->prefix;
        $oDB_Upgrade->schema = 'tables_core';
        $oDB_Upgrade->versionFrom = 1;
        $oDB_Upgrade->versionTo = 2;
        $this->_createTestTables($oDB_Upgrade->oSchema->db);
        $oDB_Upgrade->logFile = MAX_PATH . "/var/DB_Upgrade.dev.test.log";

        $oDBAuditor   = new OA_DB_UpgradeAuditor();
        $this->assertTrue($oDBAuditor->init($oDB_Upgrade->oSchema->db), 'error initialising upgrade auditor, probable error creating database action table');
        $oDBAuditor->setKeyParams(array('schema_name'=>$oDB_Upgrade->schema,
                                        'version'=>$oDB_Upgrade->versionTo,
                                        'timing'=>$oDB_Upgrade->timingInt
                                        ));

//        Mock::generatePartial(
//            'OA_DB_UpgradeAuditor',
//            $mockAuditor = 'OA_DB_UpgradeAuditor'.rand(),
//            array('beforeAddTable__table_new', 'afterAddTable__table_new')
//        );
//
//        $oDBAuditor = new $mockAuditor($this);
//        $oDBAuditor->setReturnValue('logDatabaseAction', true);
//        $oDBAuditor->expectOnce('logDatabaseAction');

        $oDB_Upgrade->oAuditor = &$oDBAuditor;

        return $oDB_Upgrade;
    }

    /**
     * internal
     * emulates work done by oSchema when dev edits a changest
     * this modifies a changeset array for renaming tables
     */
    function _editChangesetTableRename(&$oDB_Upgrade, $table_name, $table_name_was)
    {
        if (isset($oDB_Upgrade->aChanges['constructive']['tables']['add'][$table_name]))
        {
            unset($oDB_Upgrade->aChanges['constructive']['tables']['add'][$table_name]);
            if (empty($oDB_Upgrade->aChanges['constructive']['tables']['add']))
            {
                unset($oDB_Upgrade->aChanges['constructive']['tables']['add']);
            }
            $oDB_Upgrade->aChanges['constructive']['tables']['rename'][$table_name]['was'] = $table_name_was;
            if (isset($oDB_Upgrade->aChanges['destructive']['tables']['remove'][$table_name_was]))
            {
                unset($oDB_Upgrade->aChanges['destructive']['tables']['remove'][$table_name_was]);
                if (empty($oDB_Upgrade->aChanges['destructive']['tables']['remove']))
                {
                    unset($oDB_Upgrade->aChanges['destructive']['tables']['remove']);
                }
            }
        }
    }

    /**
     * internal
     * emulates work done by oSchema when dev edits a changest
     * this modifies a changeset array for renaming fields
     */
    function _editChangesetFieldRename(&$oDB_Upgrade, $table_name, $field_name, $field_name_was)
    {
        $oDB_Upgrade->aChanges['constructive']['tables']['change'][$table_name]['rename']['fields'][$field_name]['was'] = $field_name_was;
        if (isset($oDB_Upgrade->aChanges['destructive']['tables']['change'][$table_name]['remove'][$field_name_was]))
        {
            unset($oDB_Upgrade->aChanges['destructive']['tables']['change'][$table_name]['remove'][$field_name_was]);
            if (empty($oDB_Upgrade->aChanges['destructive']['tables']['change'][$table_name]['remove']))
            {
                unset($oDB_Upgrade->aChanges['destructive']['tables']['change'][$table_name]['remove']);
            }
            if (empty($oDB_Upgrade->aChanges['destructive']['tables']['change'][$table_name]))
            {
                unset($oDB_Upgrade->aChanges['destructive']['tables']['change'][$table_name]);
            }
            if (empty($oDB_Upgrade->aChanges['destructive']['tables']['change']))
            {
                unset($oDB_Upgrade->aChanges['destructive']['tables']['change']);
            }
        }
        if (isset($oDB_Upgrade->aChanges['constructive']['tables']['change'][$table_name]['add']['fields'][$field_name]))
        {
            unset($oDB_Upgrade->aChanges['constructive']['tables']['change'][$table_name]['add']['fields'][$field_name]);
            if (empty($oDB_Upgrade->aChanges['constructive']['tables']['change'][$table_name]['add']['fields']))
            {
                unset($oDB_Upgrade->aChanges['constructive']['tables']['change'][$table_name]['add']['fields']);
            }
            if (empty($oDB_Upgrade->aChanges['constructive']['tables']['change'][$table_name]['add']))
            {
                unset($oDB_Upgrade->aChanges['constructive']['tables']['change'][$table_name]['add']);
            }
            if (empty($oDB_Upgrade->aChanges['constructive']['tables']['change'][$table_name]))
            {
                unset($oDB_Upgrade->aChanges['constructive']['tables']['change'][$table_name]);
            }
            if (empty($oDB_Upgrade->aChanges['constructive']['tables']['change']))
            {
                unset($oDB_Upgrade->aChanges['constructive']['tables']['change']);
            }
        }

    }
}

?>
