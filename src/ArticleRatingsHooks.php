<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\SpecialPage\SpecialPage;
use MediaWiki\Title\Title;

class ArticleRatingsHooks {

	/**
	 * Register the <rating> tag with the Parser.
	 *
	 * @param MediaWiki\Parser\Parser $parser
	 */
	public static function onParserFirstCallInit( $parser ) {
		$parser->setHook( 'rating', [ __CLASS__, 'renderRating' ] );
	}

	/**
	 * Callback for the above function.
	 *
	 * @param mixed $input User-supplied input [unused]
	 * @param array $args Arguments for the tag (<rating page="Some page" ... />)
	 * @param MediaWiki\Parser\Parser $parser
	 * @param MediaWiki\Parser\PPFrame $frame
	 * @return string
	 */
	public static function renderRating( $input, array $args, $parser, $frame ) {
		global $wgAREUseInitialRatings, $wgARENamespaces;

		$out = '';
		$services = MediaWikiServices::getInstance();

		if ( isset( $args['page'] ) && $args['page'] ) {
			$page = $parser->recursiveTagParse( $args['page'], $frame ); // parse variables like {{{1}}}

			$title = Title::newFromText( $page );

			if ( $title && $title->exists() ) {
				$out .= '<span class="mw-rating-tag-page">';
			} else {
				return wfMessage( 'are-no-such-page', $page )->parse();
			}

			if ( $title->isRedirect() ) { // follow redirects
				$wikipage = $services->getWikiPageFactory()->newFromTitle( $title );
				$content = $wikipage->getContent( RevisionRecord::FOR_PUBLIC );
				$title = $content->getRedirectTarget();
			}

			$showAboutLink = false;
		} else {
			$title = $parser->getTitle();
			$out .= '<span class="mw-rating-tag">';

			$showAboutLink = true;
		}

		$namespaces = $wgARENamespaces ?? $services->getNamespaceInfo()->getContentNamespaces();
		if ( !in_array( $title->getNamespace(), $namespaces ) ) {
			return wfMessage( 'are-disallowed' )->parse();
		}

		if ( isset( $args['initial-rating'] ) && $wgAREUseInitialRatings ) {
			$initRating = $args['initial-rating'];
		}

		$connectionProvider = $services->getConnectionProvider();
		$dbr = $connectionProvider->getReplicaDatabase();

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

			$dbw = $connectionProvider->getPrimaryDatabase();

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
		Title $title, Title $newTitle, $user, $reason, $status
	) {
		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

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
	}

	/**
	 * Add a "change rating" link to the sidebar for privileged users on pages
	 * which have already been rated.
	 *
	 * @param Skin $skin
	 * @param array &$sidebar
	 */
	public static function onSidebarBeforeOutput( Skin $skin, &$sidebar ) {
		if ( $skin->getUser()->isAllowed( 'change-rating' ) ) {
			$title = $skin->getTitle();
			$dbr = MediaWikiServices::getInstance()->getConnectionProvider()->getReplicaDatabase();

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
				$sidebar['TOOLBOX'][] = [
					'id' => 'rating',
					'msg' => 'are-change-rating',
					'href' => SpecialPage::getTitleFor( 'ChangeRating', $title->getFullText() )
						->getFullURL()
				];
			}
		}
	}

	/**
	 * Hook to remove the ratings DB entry when a page is deleted.
	 * While not actually needed for pages, prevents deleted pages appearing on MassRatings
	 *
	 * @param WikiPage &$article
	 * @param MediaWiki\User\User &$user
	 * @param string $reason
	 * @param int $id
	 * @param MediaWiki\Content\Content|null $content
	 * @param ManualLogEntry $logEntry
	 */
	public static function onArticleDeleteComplete(
		WikiPage &$article, &$user, $reason, $id, $content, $logEntry
	) {
		$title = $article->getTitle();

		$dbw = MediaWikiServices::getInstance()->getConnectionProvider()->getPrimaryDatabase();

		$res = $dbw->delete(
			'ratings',
			[
				'ratings_title' => $title->getDBkey(),
				'ratings_namespace' => $title->getNamespace()
			],
			__METHOD__
		);
	}

	/**
	 * Creates the necessary database table when the user runs
	 * maintenance/update.php.
	 *
	 * @param MediaWiki\Installer\DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( $updater ) {
		$updater->addExtensionTable( 'ratings', __DIR__ . '/../ratings.sql' );
	}
}
