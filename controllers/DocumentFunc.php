<?php

namespace Rhymix\Modules\Yeokbox\Controllers;

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
	private const RECRUIT_CATEGORY_SRL = 1351253;
	private const BUMP_POINT_COST = 1000;
	private const BUMPABLE_SECONDS = 86400;
	private const BULK_MOVE_LIMIT = 10000;

	/**
	 * 글을 끌어올릴 수 있는지 등록일을 기준으로 확인합니다.
	 *
	 * @param string|null $regdate YmdHis 형식 등록일
	 * @return bool
	 */
	private function isBumpableByDate(?string $regdate): bool
	{
		if (!$regdate) {
			return false;
		}

		$targetTimestamp = strtotime($regdate);
		if ($targetTimestamp === false) {
			return false;
		}

		return (time() - $targetTimestamp) >= self::BUMPABLE_SECONDS;
	}

	/**
	 * 글 끌어올리기
	 */
	public function procYeokboxBumpDocument()
	{
		$loggedInfo = Context::get('logged_info');
		$targetSrl = (int) Context::get('target_srl');
		$isAdmin = ($loggedInfo->is_admin === 'Y');

		$oDocument = \DocumentModel::getDocument($targetSrl);
		if (!$oDocument->isExists() || !$oDocument->isEditable() || !$oDocument->isAccessible()) {
			return new BaseObject(-1, 'msg_not_permitted');
		}

		if (!$isAdmin && !$this->isBumpableByDate($oDocument->get('regdate'))) {
			return new BaseObject(-1, '작성한지 1일 이상 경과하지 않은 글은 끌어올릴 수 없습니다.');
		}

		if (!$isAdmin && (int) $oDocument->get('category_srl') !== self::RECRUIT_CATEGORY_SRL) {
			return new BaseObject(-1, '구인 카테고리 이외 글은 끌어올릴 수 없습니다.');
		}

		if (!$isAdmin && \PointModel::getPoint($loggedInfo->member_srl) < self::BUMP_POINT_COST) {
			return new BaseObject(-1, '포인트가 부족합니다.');
		}

		$obj = new \stdClass();
		$obj->document_srl = $oDocument->get('document_srl');
		$obj->list_order = getNextSequence() * -1;
		$obj->update_order = $obj->list_order;

		$output = executeQuery('yeokbox.bumpDocument', $obj);
		if (!$output->toBool()) {
			return $output;
		}

		\DocumentController::clearDocumentCache($obj->document_srl);

		if (!$isAdmin) {
			\PointController::setPoint($loggedInfo->member_srl, self::BUMP_POINT_COST, 'minus');
		}

		return new BaseObject(0, '글을 끌어올렸습니다.');
	}

	/**
	 * 카테고리 일괄 이동
	 */
	public function procYeokboxMoveCategoryBulk()
	{
		$loggedInfo = Context::get('logged_info');
		if ($loggedInfo->is_admin !== 'Y') {
			return new BaseObject(-1, 'msg_not_permitted');
		}

		$vars = Context::getRequestVars();
		$sourceCategorySrl = (int) $vars->category_srl;
		$targetCategorySrl = (int) $vars->target_srl;
		$moduleSrl = (int) $vars->module_srl;

		if (!$sourceCategorySrl || !$targetCategorySrl || !$moduleSrl) {
			return new BaseObject(-1, '필수 값이 누락되었습니다');
		}

		$args = new \stdClass();
		$args->module_srl = $moduleSrl;
		$args->category_srl = $sourceCategorySrl;
		$args->list_count = self::BULK_MOVE_LIMIT;

		$output = executeQueryArray('document.getDocumentList', $args, ['document_srl']);
		if (!$output->toBool()) {
			return $output;
		}

		$documentList = $output->data;
		if (empty($documentList)) {
			return new BaseObject(0, '이동할 게시글이 없습니다.');
		}

		$oDB = \DB::getInstance();
		$oDB->beginTransaction();

		try {
			foreach ($documentList as $document) {
				$updateArgs = new \stdClass();
				$updateArgs->document_srl = $document->document_srl;
				$updateArgs->category_srl = $targetCategorySrl;

				$output = executeQuery('yeokbox.updateDocumentCategorySrl', $updateArgs);
				if (!$output->toBool()) {
					$oDB->rollback();
					return $output;
				}

				\DocumentController::clearDocumentCache($document->document_srl);
			}

			$oDB->commit();
		} catch (\Exception $e) {
			$oDB->rollback();
			return new BaseObject(-1, '오류가 발생하여 작업을 취소했습니다: ' . $e->getMessage());
		}

		$oDocumentController = getController('document');
		$oDocumentController->updateCategoryCount($moduleSrl, $sourceCategorySrl);
		$oDocumentController->updateCategoryCount($moduleSrl, $targetCategorySrl);

		return new BaseObject(0, sprintf('최대 %d개의 글의 카테고리 일괄 이전을 완료했습니다.', self::BULK_MOVE_LIMIT));
	}
}
