<?php

namespace Rhymix\Modules\Yeokbox\Models;

use ModuleController;
use ModuleModel;

/**
 * 역박스 커스텀
 * 
 * Copyright (c) Lastorder-DC
 * 
 * Generated with https://www.poesis.org/tools/modulegen/
 */
class Config
{
	/**
	 * 모듈 설정 캐시를 위한 변수.
	 */
	protected static $_cache = null;
	
	/**
	 * 모듈 전체 설정을 가져오는 함수
	 * 
	 * @return \stdClass
	 */
	public static function getConfig(): \stdClass
	{
		if (self::$_cache === null)
		{
			self::$_cache = ModuleModel::getModuleConfig('yeokbox') ?: new \stdClass();
		}

		self::$_cache->yeokka_member_srl = (int) (self::$_cache->yeokka_member_srl ?? 4);
		self::$_cache->vote_count = (int) (self::$_cache->vote_count ?? 50);
		self::$_cache->super_vote_count = (int) (self::$_cache->super_vote_count ?? 50);
		self::$_cache->read_count = (int) (self::$_cache->read_count ?? 1000);
		return self::$_cache;
	}

	/**
	 * 인기글 최소 추천수를 가져오는 함수
	 * 
	 * @return int
	 */
	public static function getVoteCount(): int
	{
		if (self::$_cache === null)
		{
			self::$_cache = ModuleModel::getModuleConfig('yeokbox') ?: new \stdClass();
		}

		self::$_cache->vote_count = (int) (self::$_cache->vote_count ?? 50);
		return self::$_cache->vote_count;
	}

	/**
	 * 초인기글 최소 추천수를 가져오는 함수
	 * 
	 * @return int
	 */
	public static function getSuperVoteCount(): int
	{
		if (self::$_cache === null)
		{
			self::$_cache = ModuleModel::getModuleConfig('yeokbox') ?: new \stdClass();
		}

		self::$_cache->super_vote_count = (int) (self::$_cache->super_vote_count ?? 50);
		return self::$_cache->super_vote_count;
	}

	/**
	 * 초인기글 최소 조회수를 가져오는 함수
	 * 
	 * @return int
	 */
	public static function getReadCount(): int
	{
		if (self::$_cache === null)
		{
			self::$_cache = ModuleModel::getModuleConfig('yeokbox') ?: new \stdClass();
		}

		self::$_cache->read_count = (int) (self::$_cache->read_count ?? 1000);
		return self::$_cache->read_count;
	}
	
	/**
	 * 모듈 전체 설정을 저장하는 함수
	 * 
	 * @param \stdClass $config
	 * @return \BaseObject
	 */
	public static function setConfig(\stdClass $config): \BaseObject
	{
		$oModuleController = ModuleController::getInstance();
		$result = $oModuleController->insertModuleConfig('yeokbox', $config);
		if ($result->toBool())
		{
			self::$_cache = $config;
		}
		return $result;
	}
}
