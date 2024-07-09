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
class CommentFunc extends Base
{
	/**
	 * 댓글 랜덤 추첨
	 */
	public function procYeokboxPickComment()
	{
		// 현재 설정 상태 불러오기
		$config = ConfigModel::getConfig();
        $output = new BaseObject();
        $output->setMessage('test');
        $output->add('test', 'test_str');
        $this->add('test2', $output->get('test'));

		return $output;
	}
}
