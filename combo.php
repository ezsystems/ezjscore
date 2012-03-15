<?php
/**
 * @copyright Copyright (C) 1999-2012 eZ Systems AS. All rights reserved.
 * @license http://www.gnu.org/licenses/gpl-2.0.txt GNU General Public License v2
 * @version //autogentag//
 * @package ezjscore
 */

/**
 * YUI combo loader.
 *
 * It allows to dynamically pack YUI JavaScript and CSS files to decrease the
 * number of HTTP requests and then increase frontend performances.
 * The defaut configuration is optimized for performances, you might tweak it
 * in config.combo.php, see config.combo.php-RECOMMENDED
 */

if ( file_exists( 'config.combo.php' ) )
{
    include( 'config.combo.php' );
}

if ( !defined( 'COMBO_YUI_BASE' ) )
    define( 'COMBO_YUI_BASE', 'design/standard/lib/yui/' );

if ( !defined( 'COMBO_DEBUG' ) )
    define( 'COMBO_DEBUG', false );

if ( !defined( 'COMBO_APC_CACHE' ) )
    define( 'COMBO_APC_CACHE', function_exists( 'apc_fetch' ) );

if ( !defined( 'COMBO_APC_TTL' ) )
    define( 'COMBO_APC_TTL', 0 );


if ( !isset( $_SERVER['QUERY_STRING'] ) )
{
    die( 'nothing to do' );
}

$yuiFiles = explode( '&', $_SERVER['QUERY_STRING'] );
$cacheKey = md5( $_SERVER['QUERY_STRING'] . COMBO_YUI_BASE );

header( "Cache-Control: max-age=315360000" );
header(
    "Expires: " . date( "D, j M Y H:i:s", strtotime( "now + 10 years" ) ) . " GMT"
);

if ( strpos( $yuiFiles[0], '.js' ) !== false )
{
    header( 'Content-Type: application/x-javascript' );
}
else
{
    header( 'Content-Type: text/css' );
}

if ( COMBO_APC_CACHE )
{
    $cache = apc_fetch( $cacheKey );
    if ( $cache !== false )
    {
        if ( COMBO_DEBUG === true )
        {
            header( "X-Combo-Debug: fetched from cache; key=$cacheKey" );
        }
        echo $cache;
        die();
    }
    else
    {
        header( "X-Combo-Debug: empty cache; key=$cacheKey" );
    }
}

$code = '';
$error = false;
foreach ( $yuiFiles as $f )
{
    $file = COMBO_YUI_BASE . str_replace( '../', '', $f );
    if ( file_exists( $file ) )
    {
        $code .= file_get_contents( $file );
    }
    else if ( COMBO_DEBUG === true )
    {
        $error = true;
        $code .= "/* ERROR: {$file} does not exist */";
    }
}
if ( $error !== true && COMBO_APC_CACHE )
{
    if ( COMBO_DEBUG === true )
    {
        header( "X-Combo-Debug: stored in cache; key=$cacheKey" );
    }
    apc_store( $cacheKey, $code, COMBO_APC_TTL );
}
echo $code;
