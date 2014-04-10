<?php
class AreHooks {
	public static function onTitleMove( Title $title, Title $newtitle, User $user ) {
		$dbw = wfGetDB( DB_MASTER );

		$res = $dbw->update(
			'ratings',
			array( 'ratings_title' => $newtitle->getDBkey(), 'ratings_namespace' => $newtitle->getNamespace() ),
			array( 'ratings_title' => $title->getDBkey(), 'ratings_namespace' => $title->getNamespace() ),
			__METHOD__
		);

		return true;
	}

	public static function onBaseTemplateToolbox( BaseTemplate $skin, array &$toolbox ) {
		if ( $skin->getSkin()->getUser()->isAllowed( 'change-rating' ) ) {
			$title = $skin->getSkin()->getTitle();
			$dbr = wfGetDB( DB_SLAVE );

			$res = $dbr->select(
				'ratings',
				'ratings_rating',
				array(
					'ratings_title' => $title->getDBkey(),
					'ratings_namespace' => $title->getNamespace()
				),
				__METHOD__
			);

			if ( $res && $res->numRows() ) {
				$toolbox['rating'] = array(
					'text' => $skin->getSkin()->msg( 'are-change-rating' )->text(),
					'href' => SpecialPage::getTitleFor( 'ChangeRating', $title->getFullText() )->getFullURL()
				);
			}
		}

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
		$updater->addExtensionUpdate( array( 'addTable', 'ratings', $file, true ) );
		return true;
	}
}
