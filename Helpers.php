<?php
/**
 * Created by PhpStorm.
 * User: james
 * Date: 9/9/16
 * Time: 11:17 AM
 */

function human_filesize($bytes, $decimals = 2)
{
    $size = ['B', 'KB', 'MB', 'GB'];
    $factor = floor((strlen($bytes) - 1) / 3);

    return sprintf("%.{$decimals}f", $bytes /pow(1024, $factor) . @$size[$factor]);
}

function is_image($mimeType)
{
    return stars_with($mimeType, 'image/');
}