<?php
/**
 * A special page for rating multiple pages at once.
 *
 * @file
 * @ingroup Extensions
 * @author Jack Phoenix
 * @date 6-8 May 2024
 */

use MediaWiki\Html\Html;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\FormSpecialPage;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Status\Status;
use MediaWiki\Title\Title;

class SpecialBatchRating extends FormSpecialPage {
	/** @var int Amount of successfully rated pages after form submission */
	public static $success = 0;

	/** @var int Amount of pages not rated after form submission */
	public static $fail = 0;

	/** @var array Successfully rated page names (incl. namespace; spaces, not underscores) */
	public static $successPageNames = [];

	/** @var array Page names of pages that could not be rated (incl. namespace; spaces, not underscores) */
	public static $failPageNames = [];

	public function __construct() {
		parent::__construct( 'BatchRating', 'batch-rate' );
	}

	/** @inheritDoc */
	public function doesWrites() {
		return true;
	}

	/** @inheritDoc */
	protected function getGroupName() {
		return 'pagetools';
	}

	/**
	 * Show the special page
	 *
	 * @param string|null $par Parameter passed to the page, if any
	 * @throws UserBlockedError
	 * @return void
	 */
	public function execute( $par ) {
		$user = $this->getUser();

		# Check permissions
		$this->checkPermissions();

		# Show a message if the database is in read-only mode
		$this->checkReadOnly();

		# If user is blocked, they don't need to access this page
		if ( $user->getBlock() ) {
			throw new UserBlockedError( $user()->getBlock() );
		}

		parent::execute( $par );
	}

	/** @inheritDoc */
	protected function getFormFields() {
		$fields = [
			'titles' => [
				'type' => 'textarea',
				'label-message' => 'batchrating-titles',
			],
			'new-rating' => [
				'type' => 'select',
				'label-message' => 'batchrating-new-rating',
				'options' => self::buildRatingDropDown()
			],
		];
		return $fields;
	}

	/**
	 * Build the drop-down listing of rating options.
	 * 'link', 'image' and 'category' are skipped currently.
	 *
	 * @return array An array of [ 'Display name' => 'codename' ] entries
	 */
	private static function buildRatingDropDown() {
		$ratings = RatingData::getAllRatings();

		$options = [];

		foreach ( $ratings as $rating ) {
			// The order looks wrong (at least to me) but is in practise correct
			// First (!) comes the _displayed_ name, then the actual value. Confusing!
			$options[$rating->getName()] = $rating->getCodename();
		}

		return $options;
	}

	/**
	 * The result of submitting the form
	 *
	 * @param array $data
	 * @return Status
	 */
	public function onSubmit( array $data ) {
		global $wgARENamespaces;

		$titles = $data['titles'];
		if ( !$titles ) {
			// No pages to rate?
			return Status::newFatal( 'batchrating-error-no-titles' );
		}

		$newRating = $data['new-rating'];
		if ( !$newRating ) {
			// No rating?! (wtf)
			return Status::newFatal( 'batchrating-error-no-rating' );
		}

		if ( !SpecialChangeRating::validateRatingCodename( $newRating ) ) {
			// Tampered form
			return Status::newFatal( 'changerating-error-invalid-rating' );
		}

		$user = $this->getUser();
		$reason = $this->msg( 'batchrating-log-summary' )->inContentLanguage()->escaped();
		$namespaces = $wgARENamespaces ?? MediaWikiServices::getInstance()
				->getNamespaceInfo()->getContentNamespaces();
		$success = $fail = 0;

		$titles = explode( "\n", $titles );
		// All good, I guess, so let's go!
		// @todo FIXME: batch queries/limit titles to 500 or something reasonable/etc. to make sure
		// that this can't be abused
		foreach ( $titles as $title ) {
			$title = Title::newFromText( $title );
			if ( !$title ) {
				continue;
			}

			if ( !$title->exists() ) {
				// Pages need to exist before they can be rated!
				continue;
			}

			if ( !in_array( $title->getNamespace(), $namespaces ) ) {
				// Skip if it's in a namespace we don't support
				continue;
			}

			$resOldRating = SpecialChangeRating::getCurrentRatingForPage( $title );

			if ( $resOldRating === $newRating ) {
				// Skip if no change; we don't really wanna bail out like the ChangeRating special page does
				continue;
			}

			$changedRows = SpecialChangeRating::insertOrUpdateRating( $newRating, $title );

			if ( $changedRows > 0 ) {
				self::$success++;
				self::$successPageNames[] = $title->getPrefixedText();
			} else {
				self::$fail++;
				// @todo FIXME: This kinda <s>should</s> NEEDS TO happen earlier on, but...
				// Needs to, because "no change because article already has such rating" IS a valid case
				self::$failPageNames[] = $title->getPrefixedText();
			}

			$rating = new Rating( $newRating );
			// We're not guaranteed to have an old rating
			if ( !$resOldRating ) {
				$seed = RatingData::getDefaultRating()->getCodename();
			} else {
				$seed = $resOldRating;
			}
			$oldRating = new Rating( $seed );

			SpecialChangeRating::log( $user, $title, $rating, $oldRating, $reason );
		}

		return Status::newGood();
	}

	/** @inheritDoc */
	public function onSuccess() {
		$out = $this->getOutput();
		// Report the # of successfully updated rating as well as the # of failures
		// phpcs:disable Generic.Files.LineLength
		$out->addHTML( Html::successBox( $this->msg( 'batchrating-success' )->numParams( self::$success )->params( self::$successPageNames )->parse() ) );
		// phpcs:disable Generic.Files.LineLength
		$out->addHTML( Html::errorBox( $this->msg( 'batchrating-fail' )->numParams( self::$fail )->params( self::$failPageNames )->parse() ) );
	}
}
