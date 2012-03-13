<?php
//
// Definition of ezjscCssInlineImageOptimizer class
//
// Created on: 2012-01-19 19:00
// Author: johan.beronius@athega.se
//

class ezjscCssInlineImageOptimizer
{
    /**
     * Inline small images as Data URL in the CSS to minimize number of HTTP requests.
     *
     * @param string $css Concated Css string
     * @param int $packLevel Level of packing, values: 2-3
     * @return string
     */
    public static function optimize( $css, $packLevel = 2 )
    {
        $maxBytes = 2048;
        $ezjscINI    = eZINI::instance( 'ezjscore.ini' );
        if ( $ezjscINI->hasVariable( 'ezjscCssInlineImageOptimizer', 'InlineImageMaxBytes' ) )
        {
            $maxBytes = (int) $ezjscINI->variable( 'ezjscCssInlineImageOptimizer', 'InlineImageMaxBytes' );
        }

        if ( $packLevel > 2 && $maxBytes > 0 && preg_match_all( "/url\(\s*[\'|\"]?([A-Za-z0-9_\-\/\.\\%?&#]+)[\'|\"]?\s*\)/ix", $css, $urlMatches ) )
        {
           $urlMatches = array_unique( $urlMatches[1] );
           foreach ( $urlMatches as $match )
           {
               if ( $match[0] === '/' && preg_match("/\.(gif|png|jpe?g)$/i", $match, $imageType))
               {
                   $imagePath = '.' . $match;
                   $imageSize = filesize($imagePath);
                   if ($imageSize !== false && $imageSize > 0 && $imageSize < $maxBytes)
                   {
                        if ($imageType[1] == 'jpg')
                            $imageType[1] = 'jpeg';

                        $imageContents = file_get_contents($imagePath);
                        $dataURL = 'data:image/' . $imageType[1] . ';base64,' . base64_encode($imageContents);
                        $css = str_replace( $match, $dataURL, $css );
                   }
               }
           }
        }
        return $css;
    }
}
?>
