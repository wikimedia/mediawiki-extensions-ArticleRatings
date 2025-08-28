<?php

class RatingData {
	/**
	 * Pull rating definitions from the [[MediaWiki:Are-ratings]] system message, or
	 * if it's totally empty, raise an error.
	 *
	 * @return array
	 */
	public static function getJSON() {
		$msg = wfMessage( 'are-ratings' )->inContentLanguage();
		$json = $msg->plain();
		if ( $msg->isDisabled() ) {
			trigger_error( 'ARE Error: empty JSON' );
		}
		return json_decode( $json, true );
	}

	public static function getAllRatings() {
		$JSON = self::getJSON();

		$returners = [];

		foreach ( $JSON as $data ) {
			$returners[] = new Rating( $data['codename'] );
		}

		return $returners;
	}

	public static function getDefaultRating() {
		$JSON = self::getJSON();

		return new Rating( $JSON[0]['codename'] );
	}
}
