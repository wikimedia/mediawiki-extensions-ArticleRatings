<?php

function wfRatingRender( $input, array $args, Parser $parser, PPFrame $frame ) {
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
			$useRating = RatingData::getDefaultRating();
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

		$field = $useRating;
	}

	$rating = new RatingData( $field );

	$aboutLink = '';

	if ( $showAboutLink ) {
		$aboutLink = $rating->getAboutLink();
	}

	$out .= $aboutLink . $rating->getImage() . '</span>';

	return $out;
}