<?php

namespace Rhymix\Modules\Yeokbox\Controllers;

/**
 * 역박스 커스텀
 * 
 * Copyright (c) Lastorder-DC
 * 
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class Index extends Base
{
	/**
	 * 초기화
	 */
	public function init()
	{
		$this->setTemplatePath($this->module_path . 'views/');
	}
	
	/**
	 * 메인 화면 예제
	 */
	public function dispYeokboxIndex()
	{
		// 뷰 파일명 지정
		$this->setTemplateFile('index');
	}
}
