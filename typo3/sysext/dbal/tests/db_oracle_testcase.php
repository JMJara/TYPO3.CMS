<?php
/***************************************************************
*  Copyright notice
*
*  (c) 2009 Xavier Perseguers <typo3@perseguers.ch>
*  All rights reserved
*
*  This script is part of the TYPO3 project. The TYPO3 project is
*  free software; you can redistribute it and/or modify
*  it under the terms of the GNU General Public License as published by
*  the Free Software Foundation; either version 2 of the License, or
*  (at your option) any later version.
*
*  The GNU General Public License can be found at
*  http://www.gnu.org/copyleft/gpl.html.
*
*  This script is distributed in the hope that it will be useful,
*  but WITHOUT ANY WARRANTY; without even the implied warranty of
*  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
*  GNU General Public License for more details.
*
*  This copyright notice MUST APPEAR in all copies of the script!
***************************************************************/


require_once('BaseTestCase.php');
require_once('FakeDbConnection.php');

/**
 * Testcase for class ux_t3lib_db. Testing Oracle database handling.
 * 
 * $Id$
 *
 * @author Xavier Perseguers <typo3@perseguers.ch>
 *
 * @package TYPO3
 * @subpackage dbal
 */
class db_oracle_testcase extends BaseTestCase {

	/**
	 * @var t3lib_db
	 */
	protected $db;

	/**
	 * @var array
	 */
	protected $dbalConfig;

	/**
	 * Prepares the environment before running a test.
	 */
	public function setUp() {
			// Backup DBAL configuration
		$this->dbalConfig = $GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dbal'];
			// Backup database connection
		$this->db = $GLOBALS['TYPO3_DB'];
			// Reconfigure DBAL to use Oracle
		require('fixtures/oci8.config.php');

		$className =  self::buildAccessibleProxy('ux_t3lib_db');
		$GLOBALS['TYPO3_DB'] = new $className;
		$parserClassName = self::buildAccessibleProxy('ux_t3lib_sqlparser');
		$GLOBALS['TYPO3_DB']->SQLparser = new $parserClassName;

			// Initialize a fake Oracle connection
		FakeDbConnection::connect($GLOBALS['TYPO3_DB'], 'oci8');

		$this->assertTrue($GLOBALS['TYPO3_DB']->handlerInstance['_DEFAULT']->isConnected());
	}

	/**
	 * Cleans up the environment after running a test.
	 */
	public function tearDown() {
			// Clear DBAL-generated cache files
		$GLOBALS['TYPO3_DB']->clearCachedFieldInfo();
			// Restore DBAL configuration
		$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dbal'] = $this->dbalConfig;
			// Restore DB connection
		$GLOBALS['TYPO3_DB'] = $this->db;
	}

	/**
	 * Cleans a SQL query.
	 *  
	 * @param mixed $sql
	 * @return mixed (string or array)
	 */
	private function cleanSql($sql) {
		if (!is_string($sql)) {
			return $sql;
		}

		$sql = str_replace("\n", ' ', $sql);
		$sql = preg_replace('/\s+/', ' ', $sql);
		return trim($sql);
	}

	/**
	 * @test 
	 */
	public function configurationIsUsingAdodbAndDriverOci8() {
		$configuration = $GLOBALS['TYPO3_DB']->conf['handlerCfg'];
		$this->assertTrue(is_array($configuration) && count($configuration) > 0, 'No configuration found');
		$this->assertEquals('adodb', $configuration['_DEFAULT']['type']);
		$this->assertTrue($GLOBALS['TYPO3_DB']->runningADOdbDriver('oci8') !== FALSE, 'Not using oci8 driver');
	}

	/** 
	 * @test
	 */
	public function tablesWithMappingAreDetected() {
		$tablesWithMapping = array_keys($GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['dbal']['mapping']);

		foreach ($GLOBALS['TYPO3_DB']->cache_fieldType as $table => $fieldTypes) {
			$tableDef = $GLOBALS['TYPO3_DB']->_call('map_needMapping', $table);

			if (in_array($table, $tablesWithMapping)) {
				self::assertTrue(is_array($tableDef), 'Table ' . $table . ' was expected to need mapping');
			} else {
				self::assertFalse($tableDef, 'Table ' . $table . ' was not expected to need mapping');
			}
		}
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12897
	 */
	public function sqlHintIsRemoved() {
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery(
			'/*! SQL_NO_CACHE */ content',
			'tx_realurl_urlencodecache',
			'1=1'
		));
		$expected = 'SELECT "content" FROM "tx_realurl_urlencodecache" WHERE 1 = 1';
		$this->assertEquals($expected, $query);
	}

	///////////////////////////////////////
	// Tests concerning quoting
	///////////////////////////////////////

	/**
	 * @test
	 */
	public function selectQueryIsProperlyQuoted() {
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery(
			'uid',					// select fields
			'tt_content',			// from table
			'pid=1',				// where clause
			'cruser_id',			// group by
			'tstamp'				// order by
		));
		$expected = 'SELECT "uid" FROM "tt_content" WHERE "pid" = 1 GROUP BY "cruser_id" ORDER BY "tstamp"';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=2438
	 */
	public function distinctFieldIsProperlyQuoted() {
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery(
			'COUNT(DISTINCT pid)',	// select fields
			'tt_content',			// from table
			'1=1'					// where clause
		));
		$expected = 'SELECT COUNT(DISTINCT "pid") FROM "tt_content" WHERE 1 = 1';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=10411
	 * @remark Remapping is not expected here
	 */
	public function multipleInnerJoinsAreProperlyQuoted() {
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery(
			'*',
			'tt_news_cat INNER JOIN tt_news_cat_mm ON tt_news_cat.uid = tt_news_cat_mm.uid_foreign INNER JOIN tt_news ON tt_news.uid = tt_news_cat_mm.uid_local',
			'1=1'
		));
		$expected = 'SELECT * FROM "tt_news_cat"';
		$expected .= ' INNER JOIN "tt_news_cat_mm" ON "tt_news_cat"."uid"="tt_news_cat_mm"."uid_foreign"';
		$expected .= ' INNER JOIN "tt_news" ON "tt_news"."uid"="tt_news_cat_mm"."uid_local"';
		$expected .= ' WHERE 1 = 1';
		$this->assertEquals($expected, $query);
	}

	/** 
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=6198
	 */
	public function stringsWithinInClauseAreProperlyQuoted() {
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery(
			'COUNT(DISTINCT tx_dam.uid) AS count',
			'tx_dam',
			'tx_dam.pid IN (1) AND tx_dam.file_type IN (\'gif\',\'png\',\'jpg\',\'jpeg\') AND tx_dam.deleted = 0'
		));
		$expected = 'SELECT COUNT(DISTINCT "tx_dam"."uid") AS "count" FROM "tx_dam"';
		$expected .= ' WHERE "tx_dam"."pid" IN (1) AND "tx_dam"."file_type" IN (\'gif\',\'png\',\'jpg\',\'jpeg\') AND "tx_dam"."deleted" = 0';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12515
	 * @remark Remapping is not expected here
	 */
	public function concatAfterLikeOperatorIsProperlyQuoted() {
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery(
			'*',
			'sys_refindex, tx_dam_file_tracking',
			'sys_refindex.tablename = \'tx_dam_file_tracking\''
			. ' AND sys_refindex.ref_string LIKE CONCAT(tx_dam_file_tracking.file_path, tx_dam_file_tracking.file_name)'
		));
		$expected = 'SELECT * FROM "sys_refindex", "tx_dam_file_tracking" WHERE "sys_refindex"."tablename" = \'tx_dam_file_tracking\'';
		$expected .= ' AND (dbms_lob.instr("sys_refindex"."ref_string", CONCAT("tx_dam_file_tracking"."file_path","tx_dam_file_tracking"."file_name"),1,1) > 0)';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12231
	 */
	public function cachingFrameworkQueryIsProperlyQuoted() {
		$currentTime = time();
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery(
			'content',
			'cache_hash',
			'identifier = ' . $GLOBALS['TYPO3_DB']->fullQuoteStr('abbbabaf2d4b3f9a63e8dde781f1c106', 'cache_hash') .
				' AND (crdate + lifetime >= ' . $currentTime . ' OR lifetime = 0)'
		));
		$expected = 'SELECT "content" FROM "cache_hash" WHERE "identifier" = \'abbbabaf2d4b3f9a63e8dde781f1c106\' AND ("crdate"+"lifetime" >= ' . $currentTime . ' OR "lifetime" = 0)';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12231
	 */
	public function calculatedFieldsAreProperlyQuoted() {
		$currentTime = time();
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery(
			'identifier',
			'cachingframework_cache_pages',
			'crdate + lifetime < ' . $currentTime . ' AND lifetime > 0'
		));
		$expected = 'SELECT "identifier" FROM "cachingframework_cache_pages" WHERE "crdate"+"lifetime" < ' . $currentTime . ' AND "lifetime" > 0';
		$this->assertEquals($expected, $query);
	}

	///////////////////////////////////////
	// Tests concerning remapping
	///////////////////////////////////////

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=10411
	 * @remark Remapping is expected here
	 */
	public function tablesAndFieldsAreRemappedInMultipleJoins() {
		$selectFields = '*';
		$fromTables   = 'tt_news_cat INNER JOIN tt_news_cat_mm ON tt_news_cat.uid = tt_news_cat_mm.uid_foreign INNER JOIN tt_news ON tt_news.uid = tt_news_cat_mm.uid_local';
		$whereClause  = '1=1';
		$groupBy      = '';
		$orderBy      = '';

		$GLOBALS['TYPO3_DB']->_callRef('map_remapSELECTQueryParts', $selectFields, $fromTables, $whereClause, $groupBy, $orderBy);
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery($selectFields, $fromTables, $whereClause, $groupBy, $orderBy));

		$expected = 'SELECT * FROM "ext_tt_news_cat"';
		$expected .= ' INNER JOIN "ext_tt_news_cat_mm" ON "ext_tt_news_cat"."cat_uid"="ext_tt_news_cat_mm"."uid_foreign"';
		$expected .= ' INNER JOIN "ext_tt_news" ON "ext_tt_news"."news_uid"="ext_tt_news_cat_mm"."local_uid"';
		$expected .= ' WHERE 1 = 1';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=6953
	 */
	public function fieldWithinSqlFunctionIsRemapped() {
		$selectFields = 'tstamp, script, SUM(exec_time) AS calc_sum, COUNT(*) AS qrycount, MAX(errorFlag) AS error';
		$fromTables   = 'tx_dbal_debuglog';
		$whereClause  = '1=1';
		$groupBy      = '';
		$orderBy      = '';

		$GLOBALS['TYPO3_DB']->_callRef('map_remapSELECTQueryParts', $selectFields, $fromTables, $whereClause, $groupBy, $orderBy);
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery($selectFields, $fromTables, $whereClause, $groupBy, $orderBy));

		$expected = 'SELECT "tstamp", "script", SUM("exec_time") AS "calc_sum", COUNT(*) AS "qrycount", MAX("errorflag") AS "error" FROM "tx_dbal_debuglog" WHERE 1 = 1';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=6953
	 */
	public function tableAndFieldWithinSqlFunctionIsRemapped() {
		$selectFields = 'MAX(tt_news_cat.uid) AS biggest_id';
		$fromTables   = 'tt_news_cat INNER JOIN tt_news_cat_mm ON tt_news_cat.uid = tt_news_cat_mm.uid_foreign';
		$whereClause  = 'tt_news_cat_mm.uid_local > 50';
		$groupBy      = '';
		$orderBy      = '';

		$GLOBALS['TYPO3_DB']->_callRef('map_remapSELECTQueryParts', $selectFields, $fromTables, $whereClause, $groupBy, $orderBy);
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery($selectFields, $fromTables, $whereClause, $groupBy, $orderBy));

		$expected = 'SELECT MAX("ext_tt_news_cat"."cat_uid") AS "biggest_id" FROM "ext_tt_news_cat"';
		$expected .= ' INNER JOIN "ext_tt_news_cat_mm" ON "ext_tt_news_cat"."cat_uid"="ext_tt_news_cat_mm"."uid_foreign"';
		$expected .= ' WHERE "ext_tt_news_cat_mm"."local_uid" > 50';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12515
	 * @remark Remapping is expected here
	 */
	public function concatAfterLikeOperatorIsRemapped() {
		$selectFields = '*';
		$fromTables   = 'sys_refindex, tx_dam_file_tracking';
		$whereClause  = 'sys_refindex.tablename = \'tx_dam_file_tracking\''
							. ' AND sys_refindex.ref_string LIKE CONCAT(tx_dam_file_tracking.file_path, tx_dam_file_tracking.file_name)';
		$groupBy      = '';
		$orderBy      = '';

		$GLOBALS['TYPO3_DB']->_callRef('map_remapSELECTQueryParts', $selectFields, $fromTables, $whereClause, $groupBy, $orderBy);
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery($selectFields, $fromTables, $whereClause, $groupBy, $orderBy));

		$expected = 'SELECT * FROM "sys_refindex", "tx_dam_file_tracking" WHERE "sys_refindex"."tablename" = \'tx_dam_file_tracking\'';
		$expected .= ' AND (dbms_lob.instr("sys_refindex"."ref_string", CONCAT("tx_dam_file_tracking"."path","tx_dam_file_tracking"."filename"),1,1) > 0)';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=5708
	 */
	public function fieldIsMappedOnRightSideOfAJoinCondition() {
		$selectFields = 'cpg_categories.uid, cpg_categories.name';
		$fromTables   = 'cpg_categories, pages';
		$whereClause  = 'pages.uid = cpg_categories.pid AND pages.deleted = 0 AND 1 = 1';
		$groupBy      = '';
		$orderBy      = 'cpg_categories.pos';

		$GLOBALS['TYPO3_DB']->_callRef('map_remapSELECTQueryParts', $selectFields, $fromTables, $whereClause, $groupBy, $orderBy);
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery($selectFields, $fromTables, $whereClause, $groupBy, $orderBy));

		$expected = 'SELECT "cpg_categories"."uid", "cpg_categories"."name" FROM "cpg_categories", "pages" WHERE "pages"."uid" = "cpg_categories"."page_id"';
		$expected .= ' AND "pages"."deleted" = 0 AND 1 = 1 ORDER BY "cpg_categories"."pos"';
		$this->assertEquals($expected, $query);
	}

	///////////////////////////////////////
	// Tests concerning DB management
	///////////////////////////////////////

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12670
	 */
	public function notNullableColumnsWithDefaultEmptyStringAreCreatedAsNullable() {
		$parseString = '
			CREATE TABLE tx_realurl_uniqalias (
				uid int(11) NOT NULL auto_increment,
				tstamp int(11) DEFAULT \'0\' NOT NULL,
				tablename varchar(60) DEFAULT \'\' NOT NULL,
				field_alias varchar(255) DEFAULT \'\' NOT NULL,
				field_id varchar(60) DEFAULT \'\' NOT NULL,
				value_alias varchar(255) DEFAULT \'\' NOT NULL,
				value_id int(11) DEFAULT \'0\' NOT NULL,
				lang int(11) DEFAULT \'0\' NOT NULL,
				expire int(11) DEFAULT \'0\' NOT NULL,

				PRIMARY KEY (uid),
				KEY tablename (tablename),
				KEY bk_realurl01 (field_alias,field_id,value_id,lang,expire),
				KEY bk_realurl02 (tablename,field_alias,field_id,value_alias(220),expire)
			);
		';

		$components = $GLOBALS['TYPO3_DB']->SQLparser->_callRef('parseCREATETABLE', $parseString);
		$this->assertTrue(is_array($components), 'Not an array: ' . $components);

		$sqlCommands = $GLOBALS['TYPO3_DB']->SQLparser->_call('compileCREATETABLE', $components);
		$this->assertTrue(is_array($sqlCommands), 'Not an array: ' . $sqlCommands);
		$this->assertEquals(4, count($sqlCommands));

		$expected = $this->cleanSql('
			CREATE TABLE "tx_realurl_uniqalias" (
				"uid" NUMBER(20) NOT NULL,
				"tstamp" NUMBER(20) DEFAULT 0,
				"tablename" VARCHAR(60) DEFAULT \'\',
				"field_alias" VARCHAR(255) DEFAULT \'\',
				"field_id" VARCHAR(60) DEFAULT \'\',
				"value_alias" VARCHAR(255) DEFAULT \'\',
				"value_id" NUMBER(20) DEFAULT 0,
				"lang" NUMBER(20) DEFAULT 0,
				"expire" NUMBER(20) DEFAULT 0,
				PRIMARY KEY ("uid")
			)
		');
		$this->assertEquals($expected, $this->cleanSql($sqlCommands[0]));
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=11142
	 * @see http://bugs.typo3.org/view.php?id=12670
	 */
	public function defaultValueIsProperlyQuotedInCreateTable() {
		$parseString = '
			CREATE TABLE tx_test (
				uid int(11) NOT NULL auto_increment,
				lastname varchar(60) DEFAULT \'unknown\' NOT NULL,
				firstname varchar(60) DEFAULT \'\' NOT NULL,
				language varchar(2) NOT NULL,
				tstamp int(11) DEFAULT \'0\' NOT NULL,
				
				PRIMARY KEY (uid),
				KEY name (name)
			);
		';

		$components = $GLOBALS['TYPO3_DB']->SQLparser->_callRef('parseCREATETABLE', $parseString);
		$this->assertTrue(is_array($components), 'Not an array: ' . $components);
	
		$sqlCommands = $GLOBALS['TYPO3_DB']->SQLparser->_call('compileCREATETABLE', $components);
		$this->assertTrue(is_array($sqlCommands), 'Not an array: ' . $sqlCommands);
		$this->assertEquals(2, count($sqlCommands));

		$expected = $this->cleanSql('
			CREATE TABLE "tx_test" (
				"uid" NUMBER(20) NOT NULL,
				"lastname" VARCHAR(60) DEFAULT \'unknown\',
				"firstname" VARCHAR(60) DEFAULT \'\',
				"language" VARCHAR(2) DEFAULT \'\',
				"tstamp" NUMBER(20) DEFAULT 0,
				PRIMARY KEY ("uid")
			)
		');
		$this->assertEquals($expected, $this->cleanSql($sqlCommands[0]));
	}

	///////////////////////////////////////
	// Tests concerning subqueries
	///////////////////////////////////////

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12758
	 */
	public function inWhereClauseWithSubqueryIsProperlyQuoted() {
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery(
			'*',
			'tx_crawler_queue',
			'process_id IN (SELECT process_id FROM tx_crawler_process WHERE active=0 AND deleted=0)'
		));
		$expected = 'SELECT * FROM "tx_crawler_queue" WHERE "process_id" IN (SELECT "process_id" FROM "tx_crawler_process" WHERE "active" = 0 AND "deleted" = 0)';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12758
	 */
	public function subqueryIsRemappedForInWhereClause() {
		$selectFields = '*';
		$fromTables   = 'tx_crawler_queue';
		$whereClause  = 'process_id IN (SELECT process_id FROM tx_crawler_process WHERE active=0 AND deleted=0)';
		$groupBy      = '';
		$orderBy      = '';

		$GLOBALS['TYPO3_DB']->_callRef('map_remapSELECTQueryParts', $selectFields, $fromTables, $whereClause, $groupBy, $orderBy);
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery($selectFields, $fromTables, $whereClause, $groupBy, $orderBy));

		$expected = 'SELECT * FROM "tx_crawler_queue" WHERE "process_id" IN (SELECT "ps_id" FROM "tx_crawler_ps" WHERE "is_active" = 0 AND "deleted" = 0)';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12800
	 */
	public function cachingFrameworkQueryIsSupported() {
		$currentTime = time();
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->DELETEquery(
			'cachingframework_cache_hash_tags',
			'identifier IN (' .
				$GLOBALS['TYPO3_DB']->SELECTsubquery(
					'identifier',
					'cachingframework_cache_pages',
					'crdate + lifetime < ' . $currentTime . ' AND lifetime > 0'
				) .
			')'
		));
		$expected = 'DELETE FROM "cachingframework_cache_hash_tags" WHERE "identifier" IN (';
		$expected .= 'SELECT "identifier" FROM "cachingframework_cache_pages" WHERE "crdate"+"lifetime" < ' . $currentTime . ' AND "lifetime" > 0';
		$expected .= ')';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12800
	 */
	public function cachingFrameworkQueryIsRemapped() {
		$currentTime = time();
		$table = 'cachingframework_cache_hash_tags';
		$where = 'identifier IN (' .
				$GLOBALS['TYPO3_DB']->SELECTsubquery(
					'identifier',
					'cachingframework_cache_pages',
					'crdate + lifetime < ' . $currentTime . ' AND lifetime > 0'
				) .
			')';

			// Perform remapping (as in method exec_DELETEquery)
		if ($tableArray = $GLOBALS['TYPO3_DB']->_call('map_needMapping', $table)) {
				// Where clause:
			$whereParts = $GLOBALS['TYPO3_DB']->SQLparser->parseWhereClause($where);
			$GLOBALS['TYPO3_DB']->_callRef('map_sqlParts', $whereParts, $tableArray[0]['table']);
			$where = $GLOBALS['TYPO3_DB']->SQLparser->compileWhereClause($whereParts, FALSE);

				// Table name:
			if ($GLOBALS['TYPO3_DB']->mapping[$table]['mapTableName']) {
				$table = $GLOBALS['TYPO3_DB']->mapping[$table]['mapTableName'];
			}
		}
		
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->DELETEquery($table, $where));
		$expected = 'DELETE FROM "cf_cache_hash_tags" WHERE "identifier" IN (';
		$expected .= 'SELECT "identifier" FROM "cf_cache_pages" WHERE "crdate"+"lifetime" < ' . $currentTime . ' AND "lifetime" > 0';
		$expected .= ')';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12758
	 */
	public function existsWhereClauseIsProperlyQuoted() {
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery(
			'*',
			'tx_crawler_process',
			'active = 0 AND NOT EXISTS (' .
				$GLOBALS['TYPO3_DB']->SELECTsubquery(
					'*',
					'tx_crawler_queue',
					'tx_crawler_queue.process_id = tx_crawler_process.process_id AND tx_crawler_queue.exec_time = 0)'
				) .
			')'
		));
		$expected = 'SELECT * FROM "tx_crawler_process" WHERE "active" = 0 AND NOT EXISTS (';
		$expected .= 'SELECT * FROM "tx_crawler_queue" WHERE "tx_crawler_queue"."process_id" = "tx_crawler_process"."process_id" AND "tx_crawler_queue"."exec_time" = 0';
		$expected .= ')';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=12758
	 */
	public function subqueryIsRemappedForExistsWhereClause() {
		$selectFields = '*';
		$fromTables   = 'tx_crawler_process';
		$whereClause  = 'active = 0 AND NOT EXISTS (' .
			$GLOBALS['TYPO3_DB']->SELECTsubquery(
				'*',
				'tx_crawler_queue',
				'tx_crawler_queue.process_id = tx_crawler_process.process_id AND tx_crawler_queue.exec_time = 0'
			) .
		')';
		$groupBy      = '';
		$orderBy      = '';

		$GLOBALS['TYPO3_DB']->_callRef('map_remapSELECTQueryParts', $selectFields, $fromTables, $whereClause, $groupBy, $orderBy);
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery($selectFields, $fromTables, $whereClause, $groupBy, $orderBy));

		$expected = 'SELECT * FROM "tx_crawler_ps" WHERE "is_active" = 0 AND NOT EXISTS (';
		$expected .= 'SELECT * FROM "tx_crawler_queue" WHERE "tx_crawler_queue"."process_id" = "tx_crawler_ps"."ps_id" AND "tx_crawler_queue"."exec_time" = 0';
		$expected .= ')';
		$this->assertEquals($expected, $query);
	}

	///////////////////////////////////////
	// Tests concerning advanced operators
	///////////////////////////////////////

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=13135
	 */
	public function caseStatementIsProperlyQuoted() {
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery(
			'process_id, CASE active' .
				' WHEN 1 THEN ' . $GLOBALS['TYPO3_DB']->fullQuoteStr('one', 'tx_crawler_process') .
				' WHEN 2 THEN ' . $GLOBALS['TYPO3_DB']->fullQuoteStr('two', 'tx_crawler_process') .
				' ELSE ' . $GLOBALS['TYPO3_DB']->fullQuoteStr('out of range', 'tx_crawler_process') . 
			' END AS number',
			'tx_crawler_process',
			'1=1'
		));
		$expected = 'SELECT "process_id", CASE "active" WHEN 1 THEN \'one\' WHEN 2 THEN \'two\' ELSE \'out of range\' END AS "number" FROM "tx_crawler_process" WHERE 1 = 1';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=13135
	 */
	public function caseStatementIsProperlyRemapped() {
		$selectFields = 'process_id, CASE active' .
				' WHEN 1 THEN ' . $GLOBALS['TYPO3_DB']->fullQuoteStr('one', 'tx_crawler_process') .
				' WHEN 2 THEN ' . $GLOBALS['TYPO3_DB']->fullQuoteStr('two', 'tx_crawler_process') .
				' ELSE ' . $GLOBALS['TYPO3_DB']->fullQuoteStr('out of range', 'tx_crawler_process') . 
			' END AS number';
		$fromTables   = 'tx_crawler_process';
		$whereClause  = '1=1';
		$groupBy      = '';
		$orderBy      = '';

		$GLOBALS['TYPO3_DB']->_callRef('map_remapSELECTQueryParts', $selectFields, $fromTables, $whereClause, $groupBy, $orderBy);
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery($selectFields, $fromTables, $whereClause, $groupBy, $orderBy));

		$expected = 'SELECT "ps_id", CASE "is_active" WHEN 1 THEN \'one\' WHEN 2 THEN \'two\' ELSE \'out of range\' END AS "number" ';
		$expected .= 'FROM "tx_crawler_ps" WHERE 1 = 1';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=13135
	 */
	public function caseStatementWithExternalTableIsProperlyRemapped() {
		$selectFields = 'process_id, CASE tt_news.uid' .
				' WHEN 1 THEN ' . $GLOBALS['TYPO3_DB']->fullQuoteStr('one', 'tt_news') .
				' WHEN 2 THEN ' . $GLOBALS['TYPO3_DB']->fullQuoteStr('two', 'tt_news') .
				' ELSE ' . $GLOBALS['TYPO3_DB']->fullQuoteStr('out of range', 'tt_news') . 
			' END AS number';
		$fromTables   = 'tx_crawler_process, tt_news';
		$whereClause  = '1=1';
		$groupBy      = '';
		$orderBy      = '';

		$GLOBALS['TYPO3_DB']->_callRef('map_remapSELECTQueryParts', $selectFields, $fromTables, $whereClause, $groupBy, $orderBy);
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery($selectFields, $fromTables, $whereClause, $groupBy, $orderBy));

		$expected = 'SELECT "ps_id", CASE "ext_tt_news"."news_uid" WHEN 1 THEN \'one\' WHEN 2 THEN \'two\' ELSE \'out of range\' END AS "number" ';
		$expected .= 'FROM "tx_crawler_ps", "ext_tt_news" WHERE 1 = 1';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=13134
	 */
	public function locateStatementIsProperlyQuoted() {
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery(
			'*, CASE WHEN' .
				' LOCATE(' . $GLOBALS['TYPO3_DB']->fullQuoteStr('(fce)', 'tx_templavoila_tmplobj') . ', datastructure)>0 THEN 2' .
				' ELSE 1' . 
			' END AS scope',
			'tx_templavoila_tmplobj',
			'1=1'
		));
		$expected = 'SELECT *, CASE WHEN INSTR("datastructure", \'(fce)\') > 0 THEN 2 ELSE 1 END AS "scope" FROM "tx_templavoila_tmplobj" WHERE 1 = 1';
		$this->assertEquals($expected, $query);
	}

	/**
	 * @test
	 * @see http://bugs.typo3.org/view.php?id=13134
	 */
	public function locateStatementWithPositionIsProperlyQuoted() {
		$query = $this->cleanSql($GLOBALS['TYPO3_DB']->SELECTquery(
			'*, CASE WHEN' .
				' LOCATE(' . $GLOBALS['TYPO3_DB']->fullQuoteStr('(fce)', 'tx_templavoila_tmplobj') . ', datastructure, 4)>0 THEN 2' .
				' ELSE 1' . 
			' END AS scope',
			'tx_templavoila_tmplobj',
			'1=1'
		));
		$expected = 'SELECT *, CASE WHEN INSTR("datastructure", \'(fce)\', 4) > 0 THEN 2 ELSE 1 END AS "scope" FROM "tx_templavoila_tmplobj" WHERE 1 = 1';
		$this->assertEquals($expected, $query);
	}
}
?>