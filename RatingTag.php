<?php

function wfRatingRender( $input, array $args, Parser $parser, PPFrame $frame ) {
	global $wgAREUseInitialRatings, $wgARENamespaces;
	$out = '<div class="mw-rating-tag">';

	if ( !in_array( $parser->getTitle()->getNamespace(), $wgARENamespaces ) ) {
		return wfMessage( 'are-disallowed' )->parse();
	}

	if ( isset( $args['page'] ) && $args['page'] ) {
		$title = Title::newFromText( $args['page'] );

		if ( $title && $title->exists() ) {
			$out .= wfMessage( 'are-rating-for-page', $title->getFullText() )->parse();
			$out .= wfMessage( 'word-separator' )->parse();
		} else {
			return wfMessage( 'are-no-such-page', $args['page'] )->parse();
		}
	} else {
		$title = $parser->getTitle();
	}

	if ( isset( $args['initial-rating'] ) && $wgAREUseInitialRatings ) {
		$initRating = $args['initial-rating'];
	}

	$dbr = wfGetDB( DB_SLAVE );

	$field = $dbr->selectField(
		'ratings',
		'ratings_rating',
		array(
			'ratings_title' => $title->getDBkey(),
			'ratings_namespace' => $title->getNamespace(),
		),
		__METHOD__
	);

	if ( !$field ) { // create rating
		$ratings = RatingData::getAllRatings();

		if ( isset( $args['initial-rating'] ) && in_array( $initRating, $ratings ) ) {
			$useRating = $initRating;
		} else {
			$useRating = 'un';
		}

		$dbw = wfGetDB( DB_MASTER );

		$dbw->insert(
			'ratings',
			array(
				'ratings_rating' => $useRating,
				'ratings_title' => $title->getDBkey(),
				'ratings_namespace' => $title->getNamespace()
			),
			__METHOD__
		);

		$field = 'un';
	}

	$rating = new RatingData( $field );

	$out .= $rating->getAboutLink() . $rating->getImage() . '</div>';

	return $out;
}