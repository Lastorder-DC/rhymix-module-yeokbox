<?php

namespace Rhymix\Modules\Yeokbox\Controllers;

use Rhymix\Modules\Yeokbox\Models\Config as ConfigModel;
use BaseObject;
use Context;

/**
 * 역박스 커스텀
 * 
 * Copyright (c) Lastorder-DC
 * 
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class Admin extends Base
{
	/**
	 * 초기화
	 */
	public function init()
	{
		// 관리자 화면 템플릿 경로 지정
		$this->setTemplatePath($this->module_path . 'views/admin/');
	}
	
	/**
	 * 관리자 설정 화면 예제
	 */
	public function dispYeokboxAdminConfig()
	{
		// 현재 설정 상태 불러오기
		$config = ConfigModel::getConfig();
		
		// Context에 세팅
		Context::set('yeokbox_config', $config);

		$args = new \stdClass();
		$args->member_srl = $config->yeokka_member_srl;
		$output = executeQuery('yeokbox.getFriendList', $args, ['member_srl']);
		debugPrint($output);
		
		// 스킨 파일 지정
		$this->setTemplateFile('config');
	}
	
	/**
	 * 관리자 설정 저장 액션 예제
	 */
	public function procYeokboxAdminInsertConfig()
	{
		// 현재 설정 상태 불러오기
		$config = ConfigModel::getConfig();
		
		// 제출받은 데이터 불러오기
		$vars = Context::getRequestVars();
		$yeokka_member_srl = intval($vars->yeokka_member_srl);
		
		// 제출받은 데이터를 각각 적절히 필터링하여 설정 변경
		if (!\MemberModel::getMemberInfoByMemberSrl($yeokka_member_srl))
		{
			return new BaseObject(-1, 'msg_yeokbox_invalid_member_srl');
		}
		else
		{
			$config->yeokka_member_srl = $yeokka_member_srl;
		}
		
		// 변경된 설정을 저장
		$output = ConfigModel::setConfig($config);
		if (!$output->toBool())
		{
			return $output;
		}
		
		// 설정 화면으로 리다이렉트
		$this->setMessage('success_registed');
		$this->setRedirectUrl(Context::get('success_return_url'));
	}
}
