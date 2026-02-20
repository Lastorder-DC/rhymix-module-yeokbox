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
	 * 댓글 랜덤 추첨 (서버사이드)
	 */
	public function procYeokboxPickComment()
	{
		$logged_info = Context::get('logged_info');
		if (!$logged_info || !$logged_info->member_srl)
		{
			return new BaseObject(-1, '로그인이 필요합니다.');
		}

		$target_srl = intval(Context::get('target_srl'));
		if (!$target_srl)
		{
			return new BaseObject(-1, '대상 문서가 지정되지 않았습니다.');
		}

		$comment_list = \CommentModel::getCommentList($target_srl, 0, 0, 1000);
		$comment_list = $comment_list->data;

		if (empty($comment_list))
		{
			return new BaseObject(-1, '댓글이 없습니다.');
		}

		// 서버사이드에서 랜덤 추첨
		$random_index = random_int(0, count($comment_list) - 1);
		$picked = $comment_list[$random_index];

		// 추첨 기록 저장
		$pick_srl = getNextSequence();
		$log = new \stdClass();
		$log->pick_srl = $pick_srl;
		$log->document_srl = $target_srl;
		$log->comment_srl = intval($picked->comment_srl);
		$log->member_srl = intval($logged_info->member_srl);
		$log->regdate = date('YmdHis');
		executeQuery('yeokbox.insertPickLog', $log);

		// 당첨 결과 반환
		$result = new \stdClass();
		$result->comment_srl = $picked->comment_srl;
		$result->nick_name = $picked->nick_name;
		$result->content = $picked->content;
		$result->regdate = $picked->regdate;
		$result->total_comments = count($comment_list);
		$result->pick_srl = $pick_srl;

		$this->add('picked_comment', $result);
	}

	/**
	 * 내 추첨 기록 조회 페이지
	 */
	public function dispYeokboxPickLog()
	{
		$logged_info = Context::get('logged_info');
		if (!$logged_info || !$logged_info->member_srl)
		{
			return new BaseObject(-1, '로그인이 필요합니다.');
		}

		$args = new \stdClass();
		$args->member_srl = intval($logged_info->member_srl);
		$args->page = intval(Context::get('page')) ?: 1;
		$output = executeQueryArray('yeokbox.getPickLogByMember', $args);

		Context::set('pick_logs', $output->data);
		Context::set('total_count', $output->total_count);
		Context::set('total_page', $output->total_page);
		Context::set('page', $output->page);
		Context::set('page_navigation', $output->page_navigation);

		$this->setTemplateFile('pick_log');
	}
}
