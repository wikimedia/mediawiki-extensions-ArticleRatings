<?php

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;

class ArticleRatingsHooks {

	/**
	 * Register the <rating> tag with the Parser.
	 *
	 * @param Parser $parser
	 */
	public static function onParserFirstCallInit( Parser $parser ) {
		$parser->setHook( 'rating', [ __CLASS__, 'renderRating' ] );
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
				if ( method_exists( MediaWikiServices::class, 'getWikiPageFactory' ) ) {
					// MW 1.36+
					$wikipage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( $title );
				} else {
					// @phan-suppress-next-line PhanUndeclaredStaticMethod
					$wikipage = WikiPage::factory( $title );
				}
				$content = $wikipage->getContent( RevisionRecord::FOR_PUBLIC );
				if ( method_exists( $content, 'getUltimateRedirectTarget' ) ) {
					// Deprecated in 1.38, removed in 1.41
					// @see https://phabricator.wikimedia.org/T296430
					// @phan-suppress-next-line PhanUndeclaredMethod
					$title = $content->getUltimateRedirectTarget();
				} else {
					$title = $content->getRedirectTarget();
				}
			}

			$showAboutLink = false;
		} else {
			$title = $parser->getTitle();
			$out .= '<span class="mw-rating-tag">';

			$showAboutLink = true;
		}

		$namespaces = $wgARENamespaces ?? MediaWikiServices::getInstance()
			->getNamespaceInfo()->getContentNamespaces();
		if ( !in_array( $title->getNamespace(), $namespaces ) ) {
			return wfMessage( 'are-disallowed' )->parse();
		}

		if ( isset( $args['initial-rating'] ) && $wgAREUseInitialRatings ) {
			$initRating = $args['initial-rating'];
		}

		$dbr = self::getDBHandle( 'read' );

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

			$dbw = self::getDBHandle( 'write' );

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
		$dbw = self::getDBHandle( 'write' );

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
			$dbr = self::getDBHandle( 'read' );

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
	 * @param User &$user
	 * @param string $reason
	 * @param int $id
	 * @param Content|null $content
	 * @param ManualLogEntry $logEntry
	 */
	public static function onArticleDeleteComplete(
		WikiPage &$article, User &$user, $reason, $id, $content, $logEntry
	) {
		$title = $article->getTitle();

		$dbw = self::getDBHandle( 'write' );

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
	 * @param DatabaseUpdater $updater
	 */
	public static function onLoadExtensionSchemaUpdates( DatabaseUpdater $updater ) {
		$updater->addExtensionTable( 'ratings', __DIR__ . '/ratings.sql' );
	}

	/**
	 * Get a handle for performing database operations.
	 *
	 * This is pretty much wfGetDB() in disguise with support for MW 1.39+
	 * _without_ triggering WMF CI warnings/errors.
	 *
	 * @see https://phabricator.wikimedia.org/T273239
	 * @see https://phabricator.wikimedia.org/T330641
	 *
	 * @param string $type 'read' or 'write', depending on what we need to do
	 * @return \Wikimedia\Rdbms\IDatabase|\Wikimedia\Rdbms\IReadableDatabase
	 */
	public static function getDBHandle( $type = 'read' ) {
		$services = MediaWikiServices::getInstance();
		if ( $type === 'read' ) {
			if ( method_exists( $services, 'getConnectionProvider' ) ) {
				return $services->getConnectionProvider()->getReplicaDatabase();
			} else {
				return $services->getDBLoadBalancer()->getConnection( DB_REPLICA );
			}
		} elseif ( $type === 'write' ) {
			if ( method_exists( $services, 'getConnectionProvider' ) ) {
				return $services->getConnectionProvider()->getPrimaryDatabase();
			} else {
				return $services->getDBLoadBalancer()->getConnection( DB_PRIMARY );
			}
		}
	}
}
