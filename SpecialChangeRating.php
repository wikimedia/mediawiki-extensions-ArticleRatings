<?php

use MediaWiki\Html\Html;
use MediaWiki\HTMLForm\HTMLForm;
use MediaWiki\MediaWikiServices;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

class SpecialChangeRating extends SpecialPage {
	public function __construct() {
		parent::__construct( 'ChangeRating', 'change-rating' );
	}

	/** @inheritDoc */
	public function doesWrites() {
		return true;
	}

	/**
	 * Render the special page.
	 *
	 * @param string|null $page Name of the page we're going to rate; if not specified,
	 *  the user will be presented with a form that allows them to choose a page.
	 */
	public function execute( $page ) {
		global $wgARENamespaces;

		$this->checkPermissions();
		$this->checkReadOnly();
		$this->setHeaders();

		$out = $this->getOutput();
		$request = $this->getRequest();
		$user = $this->getUser();
		if ( !$page ) {
			$page = urldecode( $request->getVal( 'pagetitle' ) );
		}
		$title = Title::newFromText( $page );

		if ( $title === null ) {
			$this->displayPageSearchForm();
		} elseif ( !$title->exists() ) {
			$out->addWikiMsg( 'changerating-no-such-page', $page );
		} else {
			$namespaces = $wgARENamespaces ?? MediaWikiServices::getInstance()
				->getNamespaceInfo()->getContentNamespaces();
			if ( !in_array( $title->getNamespace(), $namespaces ) ) {
				$out->addWikiMsg( 'are-disallowed' );
				return;
			}

			$out->setSubtitle(
				$this->msg( 'changerating-back', $title->getFullText() )->parse()
			);

			$ratingto = $request->getVal( 'ratingTo' );

			$output = '';

			if (
				$request->wasPosted() &&
				$user->matchEditToken( $request->getVal( 'wpRatingToken' ) ) &&
				$ratingto !== null
			) {
				$ratingto = substr( $ratingto, 0, 2 );

				$isValidRatingCodename = self::validateRatingCodename( $ratingto );

				if ( !$isValidRatingCodename ) {
					$out->addHTML( Html::errorBox( $this->msg( 'changerating-error-invalid-rating' )->escaped() ) );
					// @todo FIXME: I don't *love* this _but_ it gets the job done
					// as tampered forms are such an edge case.
					// From the user's POV, it's mildly annoying to have to click to a link
					// instead of being shown the form here because they literally _are_ on
					// this page already, but oh well.
					$out->addReturnTo( $this->getPageTitle( $title->getPrefixedText() ) );
					return;
				}

				// @todo FIXME: this is now _also_ done inside insertOrUpdateRating() :-(
				// But we need the value here, too...
				$resOldRating = self::getCurrentRatingForPage( $title );

				if ( $resOldRating === $ratingto ) {
					// Pointless. Raise an error.
					// phpcs:disable Generic.Files.LineLength
					$out->addHTML( Html::errorBox( $this->msg( 'changerating-error-no-changes-requested' )->escaped() ) );
				} else {
					$changedRows = self::insertOrUpdateRating( $ratingto, $title );

					if ( $changedRows > 0 ) {
						$out->addHTML( Html::successBox( $this->msg( 'changerating-success' )->escaped() ) );
					} else {
						$out->addHTML( Html::errorBox( $this->msg( 'error' )->escaped() ) );
					}

					$rating = new Rating( $ratingto );
					// We're not guaranteed to have an old rating
					if ( !$resOldRating ) {
						$seed = RatingData::getDefaultRating()->getCodename();
					} else {
						$seed = $resOldRating;
					}
					$oldrating = new Rating( $seed );

					$reason = $request->getVal( 'reason' );

					self::log( $user, $title, $rating, $oldrating, $reason );
				}
			} elseif ( $request->wasPosted() && !$user->matchEditToken( $request->getVal( 'wpRatingToken' ) ) ) {
				// Cross-site request forgery (CSRF) attempt or something, display an error
				$output .= Html::errorBox( $this->msg( 'sessionfailure' )->parse() );
			}

			$output .= $this->msg( 'changerating-intro-text', $title->getFullText() )->parseAsBlock()
				. '<form name="change-rating" action="" method="post">';

			$currentRating = self::getCurrentRatingForPage( $title );

			$ratings = RatingData::getAllRatings();

			foreach ( $ratings as $rating ) {
				if ( $rating->getCodename() == $currentRating ) {
					$attribs = [ 'checked' => 'checked' ];
				} else {
					$attribs = [];
				}

				$output .= Html::input( 'ratingTo', $rating->getCodename(), 'radio', $attribs );
				$output .= $this->msg( 'word-separator' )->parse();

				$output .= $rating->getImage();
				$output .= $rating->getAboutLink();

				$output .= '<br />';
			}

			$output .= $this->msg( 'changerating-reason' )->escaped() .
				' <input type="text" name="reason" size="50" /><br />' .
				Html::hidden( 'wpRatingToken', $user->getEditToken() ) .
				Html::input( 'wpSubmit', $this->msg( 'changerating-submit' )->plain(), 'submit' ) .
				'</form>';

			$output .= $this->msg( 'changerating-log-text', $page )->parseAsBlock();

			$out->addHTML( $output );

			LogEventsList::showLogExtract( $out, 'ratings', $title );
		}
	}

	/**
	 * Given a Title, returns that page's current rating (if any).
	 *
	 * @param MediaWiki\Title\Title $title
	 * @return string|bool Current rating for the given page title, if any, or bool false on failure
	 */
	public static function getCurrentRatingForPage( $title ) {
		$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();
		$rating = $dbr->selectField(
			'ratings',
			'ratings_rating',
			[
				'ratings_title' => $title->getDBkey(),
				'ratings_namespace' => $title->getNamespace()
			],
			__METHOD__
		);
		if ( !$rating ) {
			return false;
		} else {
			return $rating;
		}
	}

	/**
	 * Validate the two-character codename, in case if someone tried to tamper with the form.
	 *
	 * @param string $ratingTo Target rating codename
	 * @return bool True if valid, false if not
	 */
	public static function validateRatingCodename( $ratingTo ) {
		// Make sure it absolutely, definitely, positively is only two characters long
		$ratingTo = substr( $ratingTo, 0, 2 );

		// Pessimism FTW
		$validRatingCodename = false;

		$ratings = RatingData::getAllRatings();

		foreach ( $ratings as $rating ) {
			if ( $rating->getCodename() == $ratingTo ) {
				$validRatingCodename = true;
			}
		}

		return $validRatingCodename;
	}

	/**
	 * INSERT a rating for a page if it has none yet, or if it has, UPDATE it to $ratingTo.
	 *
	 * @param string $ratingTo New rating (two-character codename)
	 * @param MediaWiki\Title\Title $title The page being rated
	 * @return int Number of rows affected by the DB query, if any
	 */
	public static function insertOrUpdateRating( $ratingTo, $title ) {
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();
		$resOldRating = self::getCurrentRatingForPage( $title );
		// If there is an entry, update it.
		// If there isn't, we need to *create* it before we can even
		// think of updating it :^)
		// (Whaddya know, trying to UPDATE something that doesn't exist
		// seems to fail silently, nothing tells you that "you gotta INSERT
		// first", the code just fails and sends you on a wild goose chase
		// for half an hour or so...)
		if ( $resOldRating ) {
			$dbw->update(
				'ratings',
				[ 'ratings_rating' => $ratingTo ],
				[
					'ratings_title' => $title->getDBkey(),
					'ratings_namespace' => $title->getNamespace()
				],
				__METHOD__
			);
		} else {
			$dbw->insert(
				'ratings',
				[
					'ratings_rating' => $ratingTo,
					'ratings_title' => $title->getDBkey(),
					'ratings_namespace' => $title->getNamespace()
				],
				__METHOD__
			);
		}
		return $dbw->affectedRows();
	}

	/**
	 * Log a rating change to Special:Log/ratings.
	 *
	 * @param MediaWiki\User\User $user The user who performed the action
	 * @param MediaWiki\Title\Title $title Rated page
	 * @param Rating $newRating New Rating object for the given page (Title)
	 * @param Rating $oldRating Old Rating object for the given page (Title)
	 * @param string|null $reason User-supplied additional rating comment, if any
	 */
	public static function log( $user, $title, $newRating, $oldRating, $reason = null ) {
		$logEntry = new ManualLogEntry( 'ratings', 'change' );
		$logEntry->setPerformer( $user );
		$logEntry->setTarget( $title );
		$logEntry->setParameters( [
			'4::newrating' => $newRating->getName(),
			'5::oldrating' => $oldRating->getName()
		] );
		if ( $reason !== null ) {
			$logEntry->setComment( $reason );
		}

		$logId = $logEntry->insert();
		$logEntry->publish( $logId );
	}

	/**
	 * Display a form for searching a page, with autocompletion and all that fancy stuff!
	 *
	 * @see https://phabricator.wikimedia.org/T164233
	 */
	private function displayPageSearchForm() {
		$fields = [
			'target' => [
				'type' => 'title',
				'creatable' => true,
				'name' => 'pagetitle',
				'default' => '',
				'label-message' => 'changecontentmodel-title-label',
			]
		];

		$htmlForm = HTMLForm::factory( 'ooui', $fields, $this->getContext() );
		$htmlForm
			->addHiddenField( 'title', $this->getPageTitle() )
			->setAction( '' )
			->setMethod( 'get' )
			->setName( 'are-page-search' )
			->setSubmitTextMsg( 'search' )
			->setWrapperLegend( '' )
			->prepareForm()
			->displayForm( false );
	}
}
