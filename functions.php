<?php

function oaipmh_harvester_config($key, $default = null)
{
    $config = Zend_Registry::get('bootstrap')->getResource('Config');
    if (isset($config->plugins->OaipmhHarvester->$key)) {
        return $config->plugins->OaipmhHarvester->$key;
    } else if ($default) {
        return $default;
    } else {
        return null;
    }
}

function oaipmh_harvester_snippet($str, $len, $app = '…')
{
    $use = $str;
    if (strlen($str) > $len) {
        $use = substr($str, 0, $len - strlen($app)) . $app;
    }
    return $use;
}

//from Peter from dezzignz.com 05-Apr-2010 11:30 @ php.net
function oaipmh_harvester_mb_string_to_array($str) {
    if (empty($str)) return false;
    $len = mb_strlen($str);
    $array = array();
    for ($i = 0; $i < $len; $i++) {
        $array[] = mb_substr($str, $i, 1);
    }
    return $array;
}

function oaipmh_harvester_mb_chunk_split($str, $len, $glue) {
    if (empty($str)) return false;
    $array = oaipmh_harvester_mb_string_to_array($str);
    $n = 0;
    $new = '';
    foreach ($array as $char) {
        if ($n < $len) $new .= $char;
        elseif ($n == $len) {
            $new .= $glue . $char;
            $n = 0;
        }
        $n++;
    }
    return $new;
}