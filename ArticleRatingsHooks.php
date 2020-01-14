<?php

class AreHooks {

	/**
	 * Extension registration callback -- set $wgARENamespaces to $wgContentNa
	 */
	public static function onRegisterExtension() {
		global $wgARENamespaces;
		$wgARENamespaces = MWNamespace::getContentNamespaces();
	}

	/**
	 * Register the <rating> tag with the Parser.
	 *
	 * @param Parser $parser
	 * @return bool
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'rating', [ __CLASS__, 'renderRating' ] );
		return true;
	}

	/**
	 * Callback for the above function.
	 *
	 * @param mixed $input User-supplied input [unused]
	 * @param array $args Arguments for the tag (<rating page="Some page" ... />)
	 * @param Parser $parser
	 * @param PPFrame $frame
	 * @return string
	 */
	public static function renderRating( $input, array $args, Parser $parser, PPFrame $frame ) {
		global $wgAREUseInitialRatings, $wgARENamespaces;

		$out = '';

		if ( isset( $args['page'] ) && $args['page'] ) {
			$page = $parser->recursiveTagParse( $args['page'], $frame ); // parse variables like {{{1}}}

			$title = Title::newFromText( $page );

			if ( $title && $title->exists() ) {
				$out .= '<span class="mw-rating-tag-page">';
			} else {
				return wfMessage( 'are-no-such-page', $page )->parse();
			}

			if ( $title->isRedirect() ) { // follow redirects
				$wikipage = WikiPage::factory( $title );
				$content = $wikipage->getContent( Revision::FOR_PUBLIC );
				$title = $content->getUltimateRedirectTarget();
			}

			$showAboutLink = false;
		} else {
			$title = $parser->getTitle();
			$out .= '<span class="mw-rating-tag">';

			$showAboutLink = true;
		}

		if ( !in_array( $title->getNamespace(), $wgARENamespaces ) ) {
			return wfMessage( 'are-disallowed' )->parse();
		}

		if ( isset( $args['initial-rating'] ) && $wgAREUseInitialRatings ) {
			$initRating = $args['initial-rating'];
		}

		$dbr = wfGetDB( DB_REPLICA );

		$field = $dbr->selectField(
			'ratings',
			'ratings_rating',
			[
				'ratings_title' => $title->getDBkey(),
				'ratings_namespace' => $title->getNamespace(),
			],
			__METHOD__
		);

		if ( $field ) {
			$useRating = new Rating( $field );
		} else { // create rating
			$ratings = RatingData::getAllRatings();

			$useRating = RatingData::getDefaultRating();

			if ( isset( $args['initial-rating'] ) ) {
				foreach ( $ratings as $rating ) {
					// check if the rating actually exists
					if ( $args['initial-rating'] == $rating->getCodename() ) {
						$useRating = $rating;
					}
				}
			}

			$dbw = wfGetDB( DB_MASTER );

			$dbw->insert(
				'ratings',
				[
					'ratings_rating' => $useRating->getCodename(),
					'ratings_title' => $title->getDBkey(),
					'ratings_namespace' => $title->getNamespace()
				],
				__METHOD__
			);
		}

		$aboutLink = '';

		if ( $showAboutLink ) {
			$aboutLink = $useRating->getAboutLink();
		}

		$out .= $aboutLink . $useRating->getImage() . '</span>';

		return $out;
	}

	public static function onTitleMove(
		Title $title, Title $newTitle, User $user, $reason, Status $status
	) {
		$dbw = wfGetDB( DB_MASTER );

		$res = $dbw->update(
			'ratings',
			[
				'ratings_title' => $newTitle->getDBkey(),
				'ratings_namespace' => $newTitle->getNamespace()
			],
			[
				'ratings_title' => $title->getDBkey(),
				'ratings_namespace' => $title->getNamespace()
			],
			__METHOD__
		);

		return true;
	}

	public static function onBaseTemplateToolbox( BaseTemplate $skin, array &$toolbox ) {
		if ( $skin->getSkin()->getUser()->isAllowed( 'change-rating' ) ) {
			$title = $skin->getSkin()->getTitle();
			$dbr = wfGetDB( DB_REPLICA );

			$res = $dbr->select(
				'ratings',
				'ratings_rating',
				[
					'ratings_title' => $title->getDBkey(),
					'ratings_namespace' => $title->getNamespace()
				],
				__METHOD__
			);

			if ( $res && $res->numRows() ) {
				$toolbox['rating'] = [
					'text' => $skin->getSkin()->msg( 'are-change-rating' )->text(),
					'href' => SpecialPage::getTitleFor( 'ChangeRating', $title->getFullText() )
						->getFullURL()
				];
			}
		}

		return true;
	}

	/**
	 * Hook to remove the ratings DB entry when a page is deleted.
	 * While not actually needed for pages, prevents deleted pages appearing on MassRaitings
	 *
	 * @param WikiPage &$article
	 * @param User &$user
	 * @param string $reason
	 * @param int $id
	 * @param unknown $content
	 * @param unknown $logEntry
	 * @return bool
	 */
	public static function onArticleDeleteComplete(
		WikiPage &$article, User &$user, $reason, $id, $content, $logEntry
	) {
		$title = $article->getTitle();

		$dbw = wfGetDB( DB_MASTER );

		$res = $dbw->delete(
			'ratings',
			[
				'ratings_title' => $title->getDBkey(),
				'ratings_namespace' => $title->getNamespace()
			],
			__METHOD__
		);

		return true;
	}

	/**
	 * Creates the necessary database table when the user runs
	 * maintenance/update.php.
	 *
	 * @param DatabaseUpdater $updater
	 * @return bool
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$file = __DIR__ . '/ratings.sql';
		$updater->addExtensionTable( 'ratings', $file );
		return true;
	}
}
