<?php

use MediaWiki\MediaWikiServices;

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

			$out->addWikiMsg( 'changerating-back', $title->getFullText() );

			$dbr = wfGetDB( DB_REPLICA );

			$ratingto = $request->getVal( 'ratingTo' );

			$output = '';

			if (
				$request->wasPosted() &&
				$user->matchEditToken( $request->getVal( 'wpRatingToken' ) ) &&
				$ratingto !== null
			) {
				$ratingto = substr( $ratingto, 0, 2 );

				$resOldRating = $dbr->selectField(
					'ratings',
					'ratings_rating',
					[
						'ratings_title' => $title->getDBkey(),
						'ratings_namespace' => $title->getNamespace()
					],
					__METHOD__
				);

				$dbw = wfGetDB( DB_PRIMARY );
				// If there is an entry, update it.
				// If there isn't, we need to *create* it before we can even
				// think of updating it :^)
				// (Whaddya know, trying to UPDATE something that doesn't exist
				// seems to fail silently, nothing tells you that "you gotta INSERT
				// first", the code just fails and sends you on a wild goose chase
				// for half an hour or so...)
				if ( $resOldRating ) {
					$res = $dbw->update(
						'ratings',
						[ 'ratings_rating' => $ratingto ],
						[
							'ratings_title' => $title->getDBkey(),
							'ratings_namespace' => $title->getNamespace()
						],
						__METHOD__
					);
				} else {
					$res = $dbw->insert(
						'ratings',
						[
							'ratings_rating' => $ratingto,
							'ratings_title' => $title->getDBkey(),
							'ratings_namespace' => $title->getNamespace()
						],
						__METHOD__
					);
				}

				$reason = $request->getVal( 'reason' );
				$out->addWikiMsg( 'changerating-success' );

				$rating = new Rating( $ratingto );
				// We're not guaranteed to have an old rating
				if ( !$resOldRating ) {
					$seed = RatingData::getDefaultRating();
				} else {
					$seed = $resOldRating;
				}
				$oldrating = new Rating( $seed );

				$logEntry = new ManualLogEntry( 'ratings', 'change' );
				$logEntry->setPerformer( $user );
				$logEntry->setTarget( $title );
				$logEntry->setParameters( [
					'4::newrating' => $rating->getName(),
					'5::oldrating' => $oldrating->getName()
				] );
				if ( $reason !== null ) {
					$logEntry->setComment( $reason );
				}

				$logId = $logEntry->insert();
				$logEntry->publish( $logId );
			} elseif ( $request->wasPosted() && !$user->matchEditToken( $request->getVal( 'wpRatingToken' ) ) ) {
				// Cross-site request forgery (CSRF) attempt or something, display an error
				$output .= Html::errorBox( $this->msg( 'sessionfailure' )->parse() );
			}

			$output .= $this->msg( 'changerating-intro-text', $title->getFullText() )->parseAsBlock()
				. '<form name="change-rating" action="" method="post">';

			$currentRating = $dbr->selectField(
				'ratings',
				'ratings_rating',
				[
					'ratings_title' => $title->getDBkey(),
					'ratings_namespace' => $title->getNamespace()
				],
				__METHOD__
			);

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

			$loglist = new LogEventsList( $this->getContext() );
			$pager = new LogPager(
				$loglist,
				'ratings',
				'',
				$title
			);

			$log = $pager->getBody();
			if ( $log ) {
				$output .= $this->msg( 'changerating-log-text', $page )->parseAsBlock() . $log;
			} else {
				$output .= $this->msg( 'changerating-nolog-text', $page )->parseAsBlock() . $log;
			}

			$out->addHTML( $output );
		}
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
