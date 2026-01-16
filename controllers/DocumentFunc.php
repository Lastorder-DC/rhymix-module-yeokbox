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
class DocumentFunc extends Base
{
	private function checkBumpable($dateString) {
		$timezone = new \DateTimeZone('Asia/Seoul');
		$targetDate = \DateTime::createFromFormat('YmdHis', $dateString, $timezone);

		if (!$targetDate) {
			return false;
		}

		$currentDate = new \DateTime('now', $timezone);
		$diffSeconds = abs($currentDate->getTimestamp() - $targetDate->getTimestamp());
		$twoDaysInSeconds = 1 * 24 * 60 * 60;

		if ($diffSeconds >= $twoDaysInSeconds) {
			return true;
		} else {
			return false;
		}
	}

	/**
	 * 댓글 랜덤 추첨
	 */
	public function procYeokboxBumpDocument()
	{
		$logged_info = Context::get('logged_info');

		// 현재 설정 상태 불러오기
		$config = ConfigModel::getConfig();
        $target_srl = intval(Context::get('target_srl'));
		$oDocumentController = getController('document');
        $oDocument = \DocumentModel::getDocument($target_srl);

		// 수정 불가능한 글은 끌올도 안되게 함
		if(!$oDocument->isEditable() || !$oDocument->isAccessible()) {
			return new baseObject(-1, 'msg_not_permitted');
		}

		// 관리자가 아니라면 1일 이상 지난 글만 끌올 허용
        $regdate = $oDocument->get('regdate');
		if(!$this->checkBumpable($regdate) && $logged_info->is_admin !== 'Y') {
			return new baseObject(-1, '작성한지 1일 이상 경과하지 않은 글은 끌어올릴 수 없습니다.');
		}

		// 관리자가 아니라면 구인탭 글만 끌올 허용
		if($oDocument->get('category_srl') != 1351253 && $logged_info->is_admin !== 'Y') {
			return new baseObject(-1, '구인 카테고리 이외 글은 끌어올릴 수 없습니다.');
		}

		$obj = new \stdClass;
		$obj->document_srl = $oDocument->get('document_srl');
		$obj->regdate = date('YmdHis');
		$obj->list_order = getNextSequence() * -1;
		$obj->update_order = $obj->list_order;
		$output = executeQuery('yeokbox.bumpDocument', $obj);
		if(!$output->toBool()) {
			return $output;
		}
		\DocumentController::clearDocumentCache($obj->document_srl);
		
		// 1000포인트 차감
		\PointController::setPoint($logged_info->member_srl, 1000, 'minus');

		return new baseObject(0, '글을 끌어올렸습니다.');
	}

	/**
	 * 카테고리 일괄 이동
	 */
	public function procYeokboxMoveCategoryBulk()
	{
		return new baseObject(-1, 'msg_not_permitted');
		/*
		$logged_info = Context::get('logged_info');
		$oDocumentController = getController('document');

		if($logged_info->is_admin !== 'Y') {
			return new baseObject(-1, 'msg_not_permitted');
		}

		$vars = Context::getRequestVars();
		$category_srl = intval($vars->category_srl);
		$target_srl = intval($vars->target_srl);
		$module_srl = 252;

		$args = new \stdClass();
		$args->list_count = 1000;
		$args->category_srl = $category_srl;
		$document_list = \DocumentModel::getDocumentList($args, false, false);
		if(!$document_list->toBool()) {
			return $document_list;
		}
		$document_list = $document_list->data;

		foreach($document_list as $document) {
			$args = new \stdClass();
			$args->document_srl = $document->document_srl;
			$args->category_srl = $target_srl;
			debugPrint("Category move for " . $args->document_srl . " from " . intval($vars->category_srl) . " to " . $args->category_srl);
			$output = executeQuery('yeokbox.updateDocumentCategorySrl', $args);
			\DocumentController::clearDocumentCache($args->document_srl);
			if(!$output->toBool()) {
				return $output;
			}
		}

		$oDocumentController->updateCategoryCount($module_srl, $category_srl);
		$oDocumentController->updateCategoryCount($module_srl, $target_srl);

		return new baseObject(0, '최대 1000개의 글의 카테고리 일괄 이전을 완료했습니다. 완료되지 않았다면 다시 실행해주세요.');
		*/
	}
}
