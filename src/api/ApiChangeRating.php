<?php
/**
 * ArticleRatings API module for changing an article's rating.
 *
 * @file
 * @ingroup API
 * @date 2-3 May 2024
 * @author Jack Phoenix
 * @see https://phabricator.wikimedia.org/T146552
 */

use MediaWiki\Api\ApiBase;
use MediaWiki\MediaWikiServices;
use Wikimedia\ParamValidator\ParamValidator;

class ApiChangeRating extends ApiBase {

	/**
	 * Main entry point.
	 */
	public function execute() {
		global $wgARENamespaces;

		// Get the request parameters
		$params = $this->extractRequestParams();

		$this->checkUserRightsAny( 'change-rating' );

		$pageObj = $this->getTitleOrPageId( $params );
		$title = $pageObj->getTitle();
		$user = $this->getUser();

		// Respect $wgARENamespaces here as well...
		$namespaces = $wgARENamespaces ?? MediaWikiServices::getInstance()
				->getNamespaceInfo()->getContentNamespaces();
		if ( !in_array( $title->getNamespace(), $namespaces ) ) {
			$this->dieWithError( 'are-disallowed' );
		}

		$ratingTo = $params['rating-to'];

		$isValidRatingCodename = SpecialChangeRating::validateRatingCodename( $ratingTo );

		if ( !$isValidRatingCodename ) {
			$this->dieWithError( 'apierror-invalid-rating' );
		}

		$reason = $params['reason'];

		$output = '';
		// <copypasta> from SpecialChangeRating#execute FOR TESTING. REFACTOR BEFORE SENDING BACK.

		// @todo FIXME: this is now _also_ done inside insertOrUpdateRating() :-(
		// But we need the value here, too...
		$resOldRating = SpecialChangeRating::getCurrentRatingForPage( $title );

		if ( $resOldRating === $ratingTo ) {
			// Pointless. Raise an error.
			$this->dieWithError( 'changerating-error-no-changes-requested' );
		}

		$rowsUpdated = SpecialChangeRating::insertOrUpdateRating( $ratingTo, $title );
		if ( $rowsUpdated > 0 ) {
			$output = 'changerating-success';
		} else {
			// @todo FIXME :)
			$this->dieWithError( 'error' );
		}

		$rating = new Rating( $ratingTo );
		// We're not guaranteed to have an old rating
		if ( !$resOldRating ) {
			$seed = RatingData::getDefaultRating()->getCodename();
		} else {
			$seed = $resOldRating;
		}
		$oldRating = new Rating( $seed );

		SpecialChangeRating::log( $user, $title, $rating, $oldRating, $reason );
		// </copypasta>

		$this->getResult()->addValue( null, $this->getModuleName(),
			[ 'result' => $output ]
		);
	}

	/** @inheritDoc */
	public function needsToken() {
		return 'csrf';
	}

	/** @inheritDoc */
	public function isWriteMode() {
		return true;
	}

	/** @inheritDoc */
	public function getAllowedParams() {
		return [
			'title' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			'pageid' => [
				ParamValidator::PARAM_TYPE => 'integer',
			],
			'reason' => [
				ParamValidator::PARAM_TYPE => 'string',
			],
			'rating-to' => [
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true
			],
		];
	}

	/** @inheritDoc */
	protected function getExamplesMessages() {
		return [
			// phpcs:disable Generic.Files.LineLength
			'action=change-rating&title=71040_The_Disney_Castle&rating-to=FA&reason=Meets featured article criteria, as per the vote' => 'apihelp-change-rating-example-1',
		];
	}

}
