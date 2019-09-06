<?php
use \Psr\Http\Message\ServerRequestInterface as Request;
use \Psr\Http\Message\ResponseInterface as Response;

require __DIR__ . '/../vendor/autoload.php';

$configs = [
	'settings' => [
		'displayErrorDetails' => true,
		'db' => [
			'name' => $_ENV['DB_NAME'],
			'user' => $_ENV['DB_USER'],
			'password' => $_ENV['DB_PASSWORD'],
			'host' => $_ENV['DB_HOST'],
		],
	],
];

function convert_special_char( $item ) {
	$item->title = utf8_encode( $item->title );
	return $item;
}

$container = new \Slim\Container;;
$app = new \Slim\App( $configs );

$container = $app->getContainer();
$container['logger'] = function( $c ) {
	$logger = new \Monolog\Logger( 'training_logger' );
	$file_handler = new \Monolog\Handler\StreamHandler( 'logs/app.log' );
	$logger->pushHandler( $file_handler );
	return $logger;
};

$container['db'] = function( $c ) {
	$db = $c['settings']['db'];
	$pdo = new PDO( 'mysql:host=' . $db['host'] . ';dbname=' . $db['name'], $db['user'], $db['password'] );
	$pdo->setAttribute( PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION );
	$pdo->setAttribute( PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC );
	return $pdo;
};

$app->get( '/viewlogs', function( Request $request, Response $response ) {
	$logs_content = file_get_contents( 'logs/app.log' );
	echo '<pre>' . $logs_content . '</pre>';
});

$app->get('/autocomplete', function ( Request $request, Response $response ) {
	$callback = $_REQUEST['callback'];
	$sql = "SELECT
			ft.*,
			CASE
				WHEN provider LIKE ':query_condition_like' THEN 1
				WHEN title LIKE ':query_condition_like' THEN 1
				ELSE 0
			END AS score_out
		FROM
			(SELECT
				provider,
				post_title AS title,
				post_url AS url,
				MATCH(
					post_title,
					provider,
					LEVEL,
					delivery,
					SUBJECT,
					partner,
					location
				) AGAINST (':query_condition_match' IN BOOLEAN MODE) AS score_in
			FROM
				`wp_fts_product`
			WHERE MATCH(
				post_title,
				provider,
				LEVEL,
				delivery,
				SUBJECT,
				partner,
				location
			) AGAINST (':query_condition_match' IN BOOLEAN MODE)) AS ft
		ORDER BY score_out DESC, score_in DESC LIMIT 10;";

	try {
		$keys = explode( ' ', trim( preg_replace( '/\s+/', ' ', $_REQUEST['q'] ) ) );

		$query_condition_match = '';
		for ($i=0; $i < count( $keys ); $i++) {
			if( 0 === $i ) {
				$query_condition_match .= $keys[ $i ] . "*";
			} else {
				$query_condition_match .= ' +' . $keys[ $i ] . '*';
			}
		}

		$query_condition_like = $_REQUEST['q'] . '%';

		$executed_query = str_replace(':query_condition_like', $query_condition_like, $sql);
		$executed_query = str_replace(':query_condition_match', $query_condition_match, $executed_query);

		$this->db->query("SET NAMES 'utf8'");

		$result = $this->db->query( $executed_query );
		$data = $result->fetchAll(PDO::FETCH_OBJ);
		$res = array_map('convert_special_char', $data);

		$res['records'] = [ "posts" => $data ];
		$res['record_count'] = count( $data );
		$response->withHeader('Content-type', 'application/javascript');
		$response_data = json_encode( $res, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_HEX_AMP | JSON_UNESCAPED_UNICODE );
		echo sprintf("%s(%s)", $callback, iconv( "UTF-8", "ISO-8859-1", $response_data ) );
	} catch(PDOException $ex) {
		$this->logger->addInfo( $sql );
		$this->logger->addError( $ex->getMessage() );
		$response->withHeader('Content-type', 'application/javascript');
		echo sprintf("%s(%s)", $callback, '{"records": {"posts": []},"record_count": 0}');
	}
});

$app->get('/postcodes', function ( Request $request, Response $response, $args ) {
	try {
		$short_dictionary = ['s8','w3','w4','e3','b8','m1','g3','l4','n1','s2','b6','s9','w2','e6','e2','s6','b1','m9','g2','b9','s4','m6','w6','m7','n4','g1','s7','m8','e4','n3','e5','n2','g4','n8','e8','n6','l8','w5','l7','n9','l6','l5','l9','s5','e7','g5','e9','w7','n5','w8','n7','s3','m5','l3','w9','m2','b7','s1','e1', 'ae'];

		$keys = explode( ' ', $_GET['q'] );
		$exist_in_dict = false;

		$query_condition = '';
		for ( $i = 0; $i < count( $keys ); $i++ ) {
			if ( 0 === $i ) {
				if ( in_array( strtolower( $keys[ $i ] ), $short_dictionary ) ) {
					$query_condition .= $keys[ $i ] . '*';
					$exist_in_dict = true;
				} else {
					$query_condition .= '+' . $keys[ $i ] . '*';
				}
			} else {
				$query_condition .= ' +' . $keys[ $i ] . '*';
			}
		}

		$db = new PDO( 'mysql:host=' . $this->get( 'settings' )['db']['host'] . ';dbname=' . $this->get( 'settings' )['db']['name'], $this->get( 'settings' )['db']['user'], $this->get( 'settings' )['db']['password'] );

		$db->query( "SET NAMES 'utf8'" );

		$query_condition_postcode = str_replace( '*', '', $query_condition );

		$result = $db->prepare( 'SELECT wp_postcodes.*,
			MATCH(`postcode_sector`, `town`, `county`) AGAINST(:query_condition IN BOOLEAN MODE) AS full_relevance,
			( MATCH(`postcode_sector`) AGAINST(:query_condition_postcode IN BOOLEAN MODE) + 2 ) AS postcode_relevance,
			( MATCH(`town`) AGAINST(:query_condition IN BOOLEAN MODE) + 3 ) AS town_relevance,
			( MATCH(`county`) AGAINST(:query_condition IN BOOLEAN MODE) + 1 ) AS county_relevance
		FROM `wp_postcodes`
		WHERE MATCH(`postcode_sector`, `town`, `county`) AGAINST(:query_condition IN BOOLEAN MODE) ORDER BY `town_relevance` + `full_relevance` + `postcode_relevance` + `county_relevance` DESC LIMIT 10' );

		$result->execute( array( 'query_condition' => $query_condition, 'query_condition_postcode' => $query_condition_postcode ) );
		$data = $result->fetchAll( PDO::FETCH_OBJ );
		$res['records'] = [ 'zipcodes' => $data ];

		return $response->withJson( $res );
	} catch ( PDOException $ex ) {
		$this->logger->addError( $ex->getMessage() );
		$response->withJson( '{"error":"' . $ex->getMessage() . '"}' );
	}
});

$app->run();
