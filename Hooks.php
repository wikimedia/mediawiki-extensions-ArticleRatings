<?php
class AreHooks {
	public static function onTitleMoveComplete( Title $title, Title $newtitle, User $user, $oldid, $newid ) {
		$dbr = wfGetDB( DB_SLAVE );

		$old = $dbr->addQuotes($title);
		$new = $dbr->addQuotes($newtitle);
		$res = $dbr->select(
			'ratings',
			'ratings_rating',
			'ratings_title = ' . $old
		);
		$no = $res->numRows();
		$row = $res->fetchRow();
		$rating = $row['ratings_rating'];

		if ( $no != 0 ) {
			$dbw = wfGetDB( DB_MASTER );
			$res = $dbw->update(
				'ratings',
				array( 'ratings_rating' => $rating ),
				array( 'ratings_title = ' . $new )
			);
		}
		return true;
	}

	public static function onBaseTemplateToolbox( BaseTemplate $skin, array &$toolbox ) {

		global $wgRequest, $wgUser, $wgArticlePath;

		$title = $skin->getSkin()->getTitle();
		$dbr = wfGetDB( DB_SLAVE );

		$res = $dbr->select(
			'ratings',
			'ratings_rating',
			array(
				'ratings_title' => $title->getDBkey(),
				'ratings_namespace' => $title->getNamespace()
			)
		);

		if ( $res && $res->numRows() ) {

			$url = str_replace( '$1', 'Special:ChangeRating/' . $title->getFullText(), $wgArticlePath );

			$toolbox['rating'] = array(
				'text' => $skin->getSkin()->msg( 'are-change-rating' )->text(),
				'href' => $url
			);
		}
		return true;
	}
}
