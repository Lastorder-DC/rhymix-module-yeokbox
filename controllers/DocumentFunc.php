<?php

namespace Rhymix\Modules\Yeokbox\Controllers;

use Rhymix\Modules\Yeokbox\Models\Config as ConfigModel;
use BaseObject;
use Context;

/**
 * 역박스 커스텀
 * * Copyright (c) Lastorder-DC
 * * Generated with https://www.poesis.org/tools/modulegen/
 */
class DocumentFunc extends Base
{
	private function checkBumpable($dateString) {
		if (!$dateString) {
			return false;
		}

		// YmdHis 형식을 timestamp로 변환
		$targetTimestamp = strtotime($dateString);
		if ($targetTimestamp === false) {
			return false;
		}

		// 현재 시간과의 차이 계산 (86400초 = 1일)
		$diffSeconds = time() - $targetTimestamp;
		
		return $diffSeconds >= 86400;
	}

	/**
	 * 글 끌어올리기
	 */
	public function procYeokboxBumpDocument()
	{
		$logged_info = Context::get('logged_info');
		$target_srl = intval(Context::get('target_srl'));

		// DocumentModel을 통해 글 정보를 가져옴
		$oDocument = \DocumentModel::getDocument($target_srl);

		// 수정 불가능한 글은 끌올도 안되게 함 (존재하지 않는 글 체크 포함)
		if(!$oDocument->isExists() || !$oDocument->isEditable() || !$oDocument->isAccessible()) {
			return new BaseObject(-1, 'msg_not_permitted');
		}

		// 관리자가 아니라면 1일 이상 지난 글만 끌올 허용
		if($logged_info->is_admin !== 'Y' && !$this->checkBumpable($oDocument->get('regdate'))) {
			return new BaseObject(-1, '작성한지 1일 이상 경과하지 않은 글은 끌어올릴 수 없습니다.');
		}

		// 관리자가 아니라면 구인탭 글만 끌올 허용
		if($logged_info->is_admin !== 'Y' && $oDocument->get('category_srl') != 1351253) {
			return new BaseObject(-1, '구인 카테고리 이외 글은 끌어올릴 수 없습니다.');
		}

		// 업데이트 실행
		$obj = new \stdClass;
		$obj->document_srl = $oDocument->get('document_srl');
		$obj->list_order = getNextSequence() * -1;
		$obj->update_order = $obj->list_order;
		
		$output = executeQuery('yeokbox.bumpDocument', $obj);
		if(!$output->toBool()) {
			return $output;
		}

		// 캐시 삭제
		\DocumentController::clearDocumentCache($obj->document_srl);
		
		// 1000포인트 차감
		\PointController::setPoint($logged_info->member_srl, 1000, 'minus');

		return new BaseObject(0, '글을 끌어올렸습니다.');
	}

	/**
	 * 카테고리 일괄 이동
	 */
	public function procYeokboxMoveCategoryBulk()
	{
		$logged_info = Context::get('logged_info');

		if($logged_info->is_admin !== 'Y') {
			return new BaseObject(-1, 'msg_not_permitted');
		}

		$vars = Context::getRequestVars();
		$category_srl = intval($vars->category_srl);
		$target_srl = intval($vars->target_srl);
		$module_srl = intval($vars->module_srl);

		if(!$category_srl || !$target_srl || !$module_srl) {
			return new BaseObject(-1, "필수 값이 누락되었습니다");
		}

		$args = new \stdClass();
		$args->module_srl = $module_srl;
		$args->category_srl = $category_srl;
		$args->list_count = 10000;

		$output = executeQueryArray('document.getDocumentList', $args, ['document_srl']);
		if(!$output->toBool()) {
			return $output;
		}
		
		$document_list = $output->data;
		if (empty($document_list)) {
			 return new BaseObject(0, '이동할 게시글이 없습니다.');
		}

		// 트랜잭션 시작 (대량 업데이트 속도 향상 필수 요소)
		$oDB = \DB::getInstance();
		$oDB->beginTransaction();

		try {
			foreach($document_list as $document) {
				$args = new \stdClass();
				$args->document_srl = $document->document_srl;
				$args->category_srl = $target_srl;
	
				$output = executeQuery('yeokbox.updateDocumentCategorySrl', $args);
				if(!$output->toBool()) {
					$oDB->rollback();
					return $output;
				}

				\DocumentController::clearDocumentCache($document->document_srl);
			}
			
			// 모든 작업 성공 시 커밋
			$oDB->commit();

		} catch (\Exception $e) {
			$oDB->rollback();
			return new BaseObject(-1, '오류가 발생하여 작업을 취소했습니다: ' . $e->getMessage());
		}

		// 카테고리 개수 업데이트
		$oDocumentController = getController('document');
		$oDocumentController->updateCategoryCount($module_srl, $category_srl);
		$oDocumentController->updateCategoryCount($module_srl, $target_srl);

		return new BaseObject(0, '최대 1000개의 글의 카테고리 일괄 이전을 완료했습니다.');
	}
}