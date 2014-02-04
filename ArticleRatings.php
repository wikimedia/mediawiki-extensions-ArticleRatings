<?php

# Not an entry point
if( !defined( 'MEDIAWIKI' ) ) {
	die('This file is a MediaWiki extension, it is not a valid entry point');
}

# Check tables and stuff
function areAddColumn() {
	$dbw = wfGetDB( DB_MASTER );
	$q = $dbw->tableName( 'ratings' );
	$success = $dbw->query(
		'CREATE TABLE ' . $q . '
		(
		ratings_title varchar(255),
		ratings_rating tinytext,
		ratings_namespace int
		)',
		__METHOD__,
		true
	);
}
//$wgExtensionFunctions[] = 'areAddColumn';

# Load Classes
$wgAutoloadClasses['RatingData'] = __DIR__ . '/RatingDataClass.php';

# Load Hooks
$wgAutoloadClasses['AreHooks'] = __DIR__ . '/Hooks.php';
$wgHooks['BaseTemplateToolbox'][] = 'AreHooks::onBaseTemplateToolbox';
$wgHooks['TitleMoveComplete'][] = 'AreHooks::onTitleMoveComplete';
$wgHooks['ParserFirstCallInit'][] = 'wfRatingParserInit';

include( __DIR__ . "/RatingTag.php" );

function wfRatingParserInit( Parser $parser ) {
	$parser->setHook( 'rating', 'wfRatingRender' );
	return true;
}

# Credits
$wgExtensionCredits['other'][] = array(
	'path' => __FILE__,
	'name' => 'ArticleRating',
	'author' => 'UltrasonicNXT/Adam Carter',
	'url' => 'https://www.mediawiki.org/wiki/Extension:ArticleRatings',
	'descriptionmsg' => 'ratings-desc',
	'version' => '2.1',
);

# Special:SpecialPages
$wgSpecialPageGroups['ChangeRating'] = 'other';
$wgSpecialPageGroups['MassRatings'] = 'other';

# Set up Special:ChangeRating
$wgAutoloadClasses['SpecialChangeRating'] = __DIR__ . '/SpecialChangeRating.php';
$wgSpecialPages['ChangeRating'] = 'SpecialChangeRating';

# Set up Special:MassRatings
$wgAutoloadClasses['SpecialMassRatings'] = __DIR__ . '/SpecialMassRatings.php';
$wgSpecialPages['MassRatings'] = 'SpecialMassRatings';

# i18n
$wgExtensionMessagesFiles['ArticleRatings'] = __DIR__ . '/ArticleRatings.i18n.php';

# Logs
$wgLogTypes[] = 'ratings';
$wgLogActionsHandlers['ratings/*'] = 'LogFormatter';

# Groups
$wgGroupPermissions['reviewer']['changeRating'] = true;