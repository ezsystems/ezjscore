<?php
//
// Created on: <16-Jun-2008 00:00:00 ar>
//
// ## BEGIN COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
// SOFTWARE NAME: eZ JSCore extension for eZ Publish
// SOFTWARE RELEASE: 1.x
// COPYRIGHT NOTICE: Copyright (C) 1999-2011 eZ Systems AS
// SOFTWARE LICENSE: GNU General Public License v2.0
// NOTICE: >
//   This program is free software; you can redistribute it and/or
//   modify it under the terms of version 2.0  of the GNU General
//   Public License as published by the Free Software Foundation.
// 
//   This program is distributed in the hope that it will be useful,
//   but WITHOUT ANY WARRANTY; without even the implied warranty of
//   MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
//   GNU General Public License for more details.
// 
//   You should have received a copy of version 2.0 of the GNU General
//   Public License along with this program; if not, write to the Free
//   Software Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston,
//   MA 02110-1301, USA.
// 
// 
// ## END COPYRIGHT, LICENSE AND WARRANTY NOTICE ##
//

/* 
 * Brief: ezjsc rpc server call
 * Lets you call custom php code(s) from javascript to return json / xhtml / xml / text 
 */

$http           = eZHTTPTool::instance();
$callType       = isset($Params['type']) ? $Params['type'] : 'call';
$callFnList     = array();
$debugOutput    = isset($Params['debug']) ? $Params['debug'] : false;

// prefere post parameters, as they are more encoding safe
if ( $http->hasPostVariable( 'ezjscServer_call_seperator' ) )
    $callSeperator = $http->postVariable( 'ezjscServer_call_seperator' );
else
    $callSeperator = '@SEPERATOR$';

if ( $http->hasPostVariable( 'ezjscServer_stream_seperator' ) )
    $streamSeperator = $http->postVariable( 'ezjscServer_stream_seperator' );
else
    $streamSeperator = '@END$';

if ( $http->hasPostVariable( 'ezjscServer_function_arguments' ) )
    $callList = explode( $callSeperator, strip_tags( $http->postVariable( 'ezjscServer_function_arguments' ) ) );
else if ( isset( $Params['function_arguments'] ) )
    $callList = explode( $callSeperator, strip_tags( $Params['function_arguments'] ) );
else
    $callList = array();

// Allow get parameter to be set to test in browser
if ( isset( $_GET['ContentType'] ) )
{
    $contentType = $_GET['ContentType'];
}
else
{
    $contentType = ezjscAjaxContent::getHttpAccept();
    header('Vary: Accept');
}

// set http headers
if ( $contentType === 'xml' )
{
    header('Content-Type: text/xml; charset=utf-8');
}
else if ( $contentType === 'javascript' )
{
    $contentType = 'json';
    header('Content-Type: text/javascript; charset=utf-8');
}
else if ( $contentType === 'json' )
{
    header('Content-Type: application/json; charset=utf-8');
}

// abort if no calls where found
if ( !$callList )
{
    header( $_SERVER['SERVER_PROTOCOL'] . ' 500 Internal Server Error' );
    $response = array( 'error_text' => 'No server call defined', 'content' => '' );
    echo ezjscAjaxContent::autoEncode( $response, $contentType );
    eZExecution::cleanExit();
    return;
}


// prepare calls
foreach( $callList as $call )
{
    $temp = ezjscServerRouter::getInstance( explode( '::', $call ), true, true );
    $callFnList[] = $temp === null ? $call : $temp;
}

$callFnListCount = count( $callFnList ) -1;

// do calls
if ( $callType === 'stream' )
{
    if ( isset( $Params['interval'] )
      && is_numeric( $Params['interval'] )
      && $Params['interval'] > 49 )
    {
        // intervall in milliseconds, minimum is 0.05 seconds
        $callInterval = $Params['interval'] * 1000;
    } 
    else
    {
        // default interval is every 0.5 seconds
        $callInterval = 500 * 1000;
    }

    $endTime = time() + 29;
    while ( @ob_end_clean() );
    // flush 256 bytes first to force IE to not buffer the stream
    if ( strpos( eZSys::serverVariable( 'HTTP_USER_AGENT' ), 'MSIE' ) !== false )
    {
        echo '                                                  ';
        echo '                                                  ';
        echo '                                                  ';
        echo '                                                  ';
        echo "                                                  \n";
    }
    // set_time_limit(65);
    while( time() < $endTime )
    {
        echo $streamSeperator . implode( $callSeperator, multipleezjscServerCalls( $callFnList, $contentType ) );
        flush();
        usleep( $callInterval );
    }
}
else
{
    echo implode( $callSeperator, multipleezjscServerCalls( $callFnList, $contentType ) );
}


function multipleezjscServerCalls( $calls, $contentType = 'json' )
{
    $r = array();
    foreach( $calls as $key => $call )
    {
        $response = array( 'error_text' => '', 'content' => '' );
        if( $call instanceOf ezjscServerRouter )
        {
            try
            {
                $response['content'] =  $call->call();
            }
            catch ( Exception $e )
            {
                $response['error_text'] = $e->getMessage();
            }
        }
        else
        {
            $response['error_text'] = 'Not a valid ezjscServerRouter argument: "' . htmlentities( $call, ENT_QUOTES ) . '"';
        }
        $r[] = ezjscAjaxContent::autoEncode( $response, $contentType );
    }
    return $r;
}



if ( $debugOutput && ( $contentType === 'xml' || $contentType === 'xhtml' ) )
{
    echo "<!--\r\n";
    eZDebug::printReport( false, false );
    echo "\r\n-->";
}
else if ( $debugOutput && $contentType === 'json' )
{
    echo "/*\r\n";
    eZDebug::printReport( false, false );
    echo "\r\n*/";
}

eZDB::checkTransactionCounter();
eZExecution::cleanExit();
