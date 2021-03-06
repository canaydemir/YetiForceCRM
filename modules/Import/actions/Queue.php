<?php
/* +***********************************************************************************
 * The contents of this file are subject to the vtiger CRM Public License Version 1.0
 * ("License"); You may not use this file except in compliance with the License
 * The Original Code is:  vtiger CRM Open Source
 * The Initial Developer of the Original Code is vtiger.
 * Portions created by vtiger are Copyright (C) vtiger.
 * All Rights Reserved.
 * Contributor(s): YetiForce.com
 * *********************************************************************************** */

class Import_Queue_Action extends \App\Controller\Action
{
	public static $IMPORT_STATUS_NONE = 0;
	public static $IMPORT_STATUS_SCHEDULED = 1;
	public static $IMPORT_STATUS_RUNNING = 2;
	public static $IMPORT_STATUS_HALTED = 3;
	public static $IMPORT_STATUS_COMPLETED = 4;

	public function __construct()
	{
	}

	/**
	 * {@inheritdoc}
	 */
	public function checkPermission(\App\Request $request)
	{
		$currentUserPrivilegesModel = Users_Privileges_Model::getCurrentUserPrivilegesModel();
		if (!$currentUserPrivilegesModel->hasModulePermission($request->getModule())) {
			throw new \App\Exceptions\NoPermitted('LBL_PERMISSION_DENIED', 406);
		}
	}

	/**
	 * {@inheritdoc}
	 */
	public function process(\App\Request $request)
	{
	}

	/**
	 * Adds status to the database.
	 *
	 * @param \App\Request $request
	 * @param string       $user
	 */
	public static function add(\App\Request $request, $user)
	{
		if ($request->get('is_scheduled')) {
			$temp_status = self::$IMPORT_STATUS_SCHEDULED;
		} else {
			$temp_status = self::$IMPORT_STATUS_NONE;
		}
		\App\Db::getInstance()->createCommand()->insert('vtiger_import_queue', [
			'userid' => $user->id,
			'tabid' => \App\Module::getModuleId($request->getModule()),
			'field_mapping' => \App\Json::encode($request->get('field_mapping')),
			'default_values' => \App\Json::encode($request->get('default_values')),
			'merge_type' => $request->get('merge_type'),
			'merge_fields' => \App\Json::encode($request->get('merge_fields')),
			'temp_status' => $temp_status,
		])->execute();
	}

	public static function remove($importId)
	{
		if (vtlib\Utils::checkTable('vtiger_import_queue')) {
			App\Db::getInstance()->createCommand()->delete('vtiger_import_queue', ['importid' => $importId])->execute();
		}
	}

	public static function removeForUser($user)
	{
		if (vtlib\Utils::checkTable('vtiger_import_queue')) {
			App\Db::getInstance()->createCommand()->delete('vtiger_import_queue', ['userid' => $user->id])->execute();
		}
	}

	public static function getUserCurrentImportInfo($user)
	{
		if (vtlib\Utils::checkTable('vtiger_import_queue')) {
			$rowData = (new App\Db\Query())->from('vtiger_import_queue')->where(['userid' => $user->id])->one();
			if ($rowData) {
				return self::getImportInfoFromResult($rowData);
			}
		}

		return null;
	}

	/**
	 * Import info.
	 *
	 * @param string             $module
	 * @param Users_Record_Model $user
	 *
	 * @return null|array
	 */
	public static function getImportInfo($module, $user)
	{
		$rowData = (new \App\Db\Query())->from('vtiger_import_queue')->where(['tabid' => \App\Module::getModuleId($module), 'userid' => $user->id])->one();
		if ($rowData) {
			return self::getImportInfoFromResult($rowData);
		}

		return null;
	}

	public static function getImportInfoById($importId)
	{
		if (vtlib\Utils::checkTable('vtiger_import_queue')) {
			$rowData = (new App\Db\Query())->from('vtiger_import_queue')->where(['importid' => $importId])->one();
			if ($rowData) {
				return self::getImportInfoFromResult($rowData);
			}
		}

		return null;
	}

	public static function getAll($tempStatus = false)
	{
		$query = (new App\Db\Query())->from('vtiger_import_queue');
		if ($tempStatus !== false) {
			$query->where(['temp_status' => $tempStatus]);
		}
		$dataReader = $query->createCommand()->query();
		$scheduledImports = [];
		while ($row = $dataReader->read()) {
			$scheduledImports[$row['importid']] = self::getImportInfoFromResult($row);
		}
		$dataReader->close();

		return $scheduledImports;
	}

	/**
	 * Import info.
	 *
	 * @param array $rowData
	 *
	 * @return array
	 */
	public static function getImportInfoFromResult($rowData)
	{
		return [
			'id' => $rowData['importid'],
			'module' => \App\Module::getModuleName($rowData['tabid']),
			'field_mapping' => \App\Json::decode($rowData['field_mapping']),
			'default_values' => \App\Json::decode($rowData['default_values']),
			'merge_type' => $rowData['merge_type'],
			'merge_fields' => \App\Json::decode($rowData['merge_fields']),
			'user_id' => $rowData['userid'],
			'temp_status' => $rowData['temp_status'],
		];
	}

	public static function updateStatus($importId, $tempStatus)
	{
		App\Db::getInstance()->createCommand()->update('vtiger_import_queue', ['temp_status' => $tempStatus], ['importid' => $importId])->execute();
	}
}
