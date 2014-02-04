<?php

class SpecialChangeRating extends SpecialPage {
	public function __construct() {
		parent::__construct( 'ChangeRating', 'changeRating' );
	}

	public function execute( $page ) {
		// If the user doesn't have sufficient permissions to use this special
		// page, display an error
		$this->checkPermissions();

		// Show a message if the database is in read-only mode
		$this->checkReadOnly();

		// Set the page title, robot policies, etc.
		$this->setHeaders();

		$out = $this->getOutput();
		$request = $this->getRequest();
		$title = Title::newFromText( $page );

		if ( is_null( $title ) ) {
			$out->addWikiMsg( 'changerating-missing-parameter' );
		} elseif ( !$title->exists() ) {
			$out->addWikiMsg( 'changerating-no-such-page', $page );
		} else {
			$out->addWikiMsg( 'changerating-back', $page );

			$dbr = wfGetDB( DB_SLAVE );

			$ratingto = $request->getVal( 'ratingTo' );

			if ( !is_null( $ratingto ) ) {
				$ratingto = substr( $ratingto, 0, 2 );

				$res = $dbr->selectField(
					'ratings',
					'ratings_rating',
					array(
						'ratings_title' => $page,
						'ratings_namespace' => $title->getNamespace()
					),
					__METHOD__
				);
				$oldrating = new RatingData( $res );
				$oldratingname = $oldrating->getAttr( 'name' );

				$dbw = wfGetDB( DB_MASTER );

				$res = $dbw->update(
					'ratings',
					array( 'ratings_rating' => $ratingto ),
					array(
						'ratings_title' => $page,
						'ratings_namespace' => $title->getNamespace()
					),
					__METHOD__
				);

				$reason = $request->getVal( 'reason' );
				$out->addWikiMsg( 'changerating-success' );

				$rating = new RatingData( $ratingto );
				$ratingname = $rating->getAttr( 'name' );

				$logEntry = new ManualLogEntry( 'ratings', 'change' );
				$logEntry->setPerformer( $this->getUser() );
				$logEntry->setTarget( Title::newFromText( strval( $page ) ) );
				$logEntry->setParameters( array(
					'4::newrating' => $ratingname,
					'5::oldrating' => $oldratingname
				) );
				if ( !is_null( $reason ) ) {
					$logEntry->setComment( $reason );
				}

				$logId = $logEntry->insert();
				$logEntry->publish( $logId );
			}

			$head = $this->msg( 'changerating-intro-text', $page )->parseAsBlock();
			$head .= '<form name="change-rating" action="" method="get">';
			$foot = $this->msg( 'changerating-reason' )->plain();
			$foot .= ' <input type="text" name="reason" size="50" /><br />';
			$foot .= Html::input( 'wpSubmit', $this->msg( 'changerating-submit' )->plain(), 'submit' );
			$foot .= '</form>​';
			$body = '';

			$res = $dbr->select(
				'ratings',
				array( 'ratings_rating', 'ratings_title' ),
				array( 'ratings_title' => $page ),
				__METHOD__
			);
			$row = $res->fetchRow();
			$field = $row['ratings_rating'];

			$ratings = RatingData::getAllRatings();

			foreach ( $ratings as $data ) {
				$rating = new RatingData( $data );

				if ( $data == $field ) {
					$attribs = array( 'checked' => 'checked' );
				} else {
					$attribs = array();
				}

				$label = $rating->getAboutLink();

				$pic = $rating->getImage();

				$radio = Html::input( 'ratingTo', $data, 'radio', $attribs );
				$radio .= $this->msg( 'word-separator' )->parse();

				$body = $body . $radio . $pic . $label . '<br />';
			}

			$html = $head . $body . $foot;
			$out->addHTML( $html );
		}
	}
}