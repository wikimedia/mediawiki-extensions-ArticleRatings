<?php
/**
 * API module to get the current rating of pages, potentially also limited by namespace.
 *
 * @file
 * @ingroup API
 * @date 28 August 2025
 * @see https://phabricator.wikimedia.org/T403131
 */

use MediaWiki\Api\ApiQueryBase;
use MediaWiki\Api\ApiResult;
use Wikimedia\ParamValidator\ParamValidator;
use Wikimedia\ParamValidator\TypeDef\IntegerDef;

class ApiQueryARERatings extends ApiQueryBase {

	/** @var array User-supplied request parameters */
	private $params;

	/** @inheritDoc */
	public function __construct( $query, $moduleName ) {
		parent::__construct( $query, $moduleName, 'are' );
	}

	/** @inheritDoc */
	public function execute() {
		$this->params = $this->extractRequestParams();

		$limit = $this->params['limit'] ?? 50;

		$this->buildDBquery();

		$res = $this->select( __METHOD__ );

		$item = [];

		$result = $this->getResult();
		$count = 0;

		foreach ( $res as $row ) {
			if ( ++$count > $limit ) {
				// We've reached the one extra which shows that there are
				// additional results to be had. Stop here...
				$this->setContinueEnumParameter( 'continue', $row->page_id );
				break;
			}

			// Add the results to the $item array
			$item['page_id'] = $row->page_id;
			$item['page_title'] = $row->ratings_title;
			$item['page_namespace'] = (int)$row->ratings_namespace;
			$item['rating'] = $row->ratings_rating;

			$fit = $result->addValue( [ 'query', $this->getModuleName() ], null, $item );
			if ( !$fit ) {
				$this->setContinueEnumParameter( 'continue', $row->page_id );
				break;
			}
		}

		if ( empty( $item ) ) {
			$this->dieWithError( [ 'apierror-are-no-pages' ], 'nodata' );
		}

		$result->addIndexedTagName( [ 'query', $this->getModuleName() ], 'item' );
	}

	/**
	 * Build the database query based on parameters passed to the module.
	 */
	private function buildDBquery() {
		$this->addTables( [ 'page', 'ratings' ] );
		$this->addFields( [
			'page_id',
			'ratings_title',
			'ratings_rating',
			'ratings_namespace'
		] );

		if ( $this->params['continue'] !== null ) {
			$cont = explode( '|', $this->params['continue'] );
			$this->dieContinueUsageIf( count( $cont ) != 1 );
			$op = $this->params['order'] == 'DESC' ? '<' : '>';
			$cont_from = $this->getDB()->addQuotes( $cont[0] );
			$this->addWhere( "page_id $op $cont_from" );
		}

		// isset(), because 0 is a totally valid NS
		// NB: We do NOT check against $wgARENamespaces because this API module is merely
		// a getter, not a setter, unlike e.g. Special:ChangeRating.
		// Furthermore, it's totally possible and OK to move a rated page to a namespace which does *not*
		// have ratings enabled; that obviously should not clear the rating or prevent it from being read.
		if ( isset( $this->params['namespace'] ) ) {
			$this->addWhereFld( 'ratings_namespace', $this->params['namespace'] );
		}

		$this->addOption( 'GROUP BY', 'page_id' );
		// @note This might look like a SQL injection point at a first glance but it's not, because 'ASC' and 'DESC'
		// are the only allowed values, as set in getAllowedParams() below!
		$this->addOption( 'ORDER BY', "page_id {$this->params['order']}" );
		// plus one for the continue stuff!
		$this->addOption( 'LIMIT', intval( $this->params['limit'] ) + 1 );

		// @codingStandardsIgnoreStart
		// We must JOIN against the core page table to get the page_id, ARE's own table only
		// stores the page title and namespace :-(
		$this->addJoinConds( [ 'page' => [ 'LEFT JOIN', 'page_title = ratings_title AND page_namespace = ratings_namespace' ] ] );
		// @codingStandardsIgnoreEnd
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'continue' => [
				ApiBase::PARAM_HELP_MSG => 'api-help-param-continue',
			],
			'limit' => [
				ParamValidator::PARAM_DEFAULT => 50,
				ParamValidator::PARAM_TYPE => 'limit',
				IntegerDef::PARAM_MIN => 1,
				IntegerDef::PARAM_MAX => ApiBase::LIMIT_BIG1,
				IntegerDef::PARAM_MAX2 => ApiBase::LIMIT_BIG2
			],
			'namespace' => [
				ParamValidator::PARAM_DEFAULT => NS_MAIN,
				ParamValidator::PARAM_TYPE => 'namespace'
			],
			'order' => [
				ParamValidator::PARAM_DEFAULT => 'DESC',
				ParamValidator::PARAM_TYPE => [ 'ASC', 'DESC' ]
			]
		];
	}

	/** @inheritDoc */
	public function getPossibleErrors() {
		return [ 'apierror-are-no-pages' ];
	}

	/** @inheritDoc */
	public function getExamplesMessages() {
		return [
			'action=query&list=are-ratings' => 'apihelp-query+are-ratings-example-1',
			'action=query&list=are-ratings&arelimit=100' => 'apihelp-query+are-ratings-example-2',
		];
	}

	/** @inheritDoc */
	public function getHelpUrls() {
		return 'https://www.mediawiki.org/wiki/Special:MyLanguage/Extension:ArticleRatings#API';
	}
}
