<?php

$subject = 'Foo bar';

$prefs = array(
    'scheme' => 'Q',
    'input-charset' => 'UTF-8',
    'output-charset' => 'US-ASCII',
    'line-length' => 76,
    'line-break-chars' => "\r\n",
);

echo 'Original: ' . $subject . PHP_EOL;
$enc = iconv_mime_encode( 'Subject', $subject, $prefs );
var_dump( $enc );  // will show bool(false)
?>

