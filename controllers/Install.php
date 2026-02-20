<?php

namespace Rhymix\Modules\Yeokbox\Controllers;

use DB;

/**
 * 역박스 커스텀
 * 
 * Copyright (c) Lastorder-DC
 * 
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class Install extends Base
{
	/**
	 * 모듈 설치 콜백 함수.
	 * 
	 * @return object
	 */
	public function moduleInstall()
	{

	}
	
	/**
	 * 모듈 업데이트 확인 콜백 함수.
	 * 
	 * @return bool
	 */
	public function checkUpdate()
	{
		$oDB = DB::getInstance();
		if (!$oDB->isColumnExists('yeokbox_pick_log', 'comment_member_srl')) return true;
		if (!$oDB->isColumnExists('yeokbox_pick_log', 'comment_nick_name')) return true;
		if (!$oDB->isColumnExists('yeokbox_pick_log', 'comment_content')) return true;
	}
	
	/**
	 * 모듈 업데이트 콜백 함수.
	 * 
	 * @return object
	 */
	public function moduleUpdate()
	{
		$oDB = DB::getInstance();
		if (!$oDB->isColumnExists('yeokbox_pick_log', 'comment_member_srl'))
		{
			$oDB->addColumn('yeokbox_pick_log', 'comment_member_srl', 'number', 11, null, true);
		}
		if (!$oDB->isColumnExists('yeokbox_pick_log', 'comment_nick_name'))
		{
			$oDB->addColumn('yeokbox_pick_log', 'comment_nick_name', 'varchar', 80, null, true);
		}
		if (!$oDB->isColumnExists('yeokbox_pick_log', 'comment_content'))
		{
			$oDB->addColumn('yeokbox_pick_log', 'comment_content', 'text', null, null, true);
		}
	}
	
	/**
	 * 캐시파일 재생성 콜백 함수.
	 * 
	 * @return void
	 */
	public function recompileCache()
	{
		
	}
}
