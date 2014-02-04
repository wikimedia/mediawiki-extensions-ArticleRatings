<?php
class AreHooks {
	public static function onTitleMoveComplete( Title $title, Title $newtitle, User $user, $oldid, $newid ) {
		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select(
			'ratings',
			'ratings_rating',
			/* @todo FIXME: this is a hack...it works because Title has a toString() method,
			but you really should be calling the proper method here (probably $title->getPrefixedText()?)
			*/
			array( 'ratings_title' => $title ),
			__METHOD__
		);
		$no = $res->numRows();
		$row = $res->fetchRow();
		$rating = $row['ratings_rating'];

		if ( $no != 0 ) {
			$dbw = wfGetDB( DB_MASTER );
			$res = $dbw->update(
				'ratings',
				array( 'ratings_rating' => $rating ),
				array( 'ratings_title' => $newtitle ),
				__METHOD__
			);
		}

		return true;
	}

	public static function onBaseTemplateToolbox( BaseTemplate $skin, array &$toolbox ) {
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
