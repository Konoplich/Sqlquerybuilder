<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
    }
    private function swap(&$val1, &$val2)
    {
	$tmp=$val1;
	$val1=$val2;
	$val2=$tmp;
    }
    private function getFunc($query, &$pos, $max, &$suffix)
    {
        $func = 'unknown_param';
        $param_len = 0;
	do
	{
	    $pos++;
	    switch($query[$pos])
	    {
		case ' ':
		    $suffix .= ' '; 
		    break;
		case 'd':
        	    $func = 'd_param';
		    break;
		case 'f':
		    $func = 'f_param';
		    break;
		case 'a':
		    $func = 'a_param';
		    break;
		case '#':
		    $func = 'dies_param';
		    break;
		default:
		    throw new \Exception("Unknown param type: ".$query[$pos]." in ".$query);
	    }
	    $param_len++;
	}
	while($pos<$max && !$query[$pos]===' ');
	
	switch($param_len)
	{
	    case 0:
		$func = 'unknown_param';
		break;
	    case 1:
	        break;
	    default:
	        throw new Exception("Bad param: ".$param_len);
	}
	return $func;
    }

    public function buildQuery(string $query, array $args = []): string
    {
	//debug print "template: ".$query."\n";
	$result = '';
	$buf = '';
	$pos = 0;
	$max = strlen($query);
	$param = false;
	$index_param = 0;
	$skip = false;
	$block = '';
	while($pos<$max)
	{	
	    //debug print "scan: [".$query[$pos]."]\n";
	    switch($query[$pos])
	    {
		case '}':
			//debug print "clear skip: [".$buf."]\n";
			$result .= $buf;
			$buf = '';
			$this->swap($result, $block);
			if($skip)
			{
			    $skip = false;
			    $block = '';
			    break;
			}
			//debug print "conncat block: [".$block."]\n";
			$result .= $block;
			break;
		case '{':
		    $result .= $buf;
		    $buf='';
		    $skip = $args[$index_param]===$this->skip();
		    $this->swap($result, $block);
		    //debug print "read block: [".$block."], concat: [".$result."]\n";
		    break;
		case '?':
		    //debug print "case ?, buf: [".$buf."] param^".$args[$index_param]."\n";
		    $suffix = '';
		    $func = $this->getFunc($query, $pos, $max, $suffix);
		    if($args[$index_param]===$this->skip() || $skip)
		    {
			//debug print "set skip: [".$buf."]\n";
			$skip = true;
			$index_param++;
			break;
		    }
		    //debug print "insert param: ".$args[$index_param]."\n";
		    $p = $this->$func($args[$index_param]);
		    $result .= $buf.$p.$suffix;
		    $index_param++;
		    $buf = '';
		    //debug print "param: [".$p."] concat [".$result."]\n";
		    break;
		case ' ':
		    //debug print "case space\n";
		    $keyword = strtolower($buf);
		    switch($keyword)
		    {
			case "select":
			case "from":
			case "where":
			case "update":
			case "set":
			case "and":
			case "in":
			    //debug print "case keyword: [".$keyword."]\n";
			    $buf = strtoupper($buf);
		    }
		    $result .= $buf.' ';
		    //debug print "keyword: [".$keyword."], buf: [".$buf."], concat: [".$result."].\n";
		    $buf = '';
		    break;
		default:
		    $buf.= $query[$pos];
		    //debug print "case default, buf:[".$buf."]\n";

	    }
	    $pos++;
	}
	//debug print "pos:".$pos." max:".$max."\n";
	//debug print "result: [".$result.$buf."]\n";
	return $result.$buf;
    }

    private function d_param($arg)
    {
	if(is_null($arg))
	    return 'NULL';

	return sprintf('%d',$arg);
    }

    private function f_param($arg)
    {
	if(is_null($arg))
	    return 'NULL';

	return sprintf("%f",$arg);
    }

    private function a_param($arg)
    {
	if(count($arg)===0)
	    throw new Exception("Bad param value");
	$ret='';
	$i=0;
	if(is_int(array_key_first($arg)))
	{
	    foreach($arg as $val)
	    {
		$ret .= $i===0 ? '' : ', ';
		$ret .= $this->unknown_param($val);
		$i++;
	    }
	}
	else
	{
	    reset($arg);
	    $i = 0;
	    foreach($arg as $key => $val)
	    {
		$ret .= $i===0 ? '' : ', ';
		$ret .= '`'.$key . '` = '.$this->unknown_param($val);
		$i++;
	    }
	}
	return $ret;

    }

    private function dies_param($arg)
    {
	//debug print ("# param: ".$arg."\n");
	$ret = '';
	$i = 0;
	if(is_array($arg))
	{
    	    foreach($arg as $v)
    	    {
		$ret .= $i===0 ? '' : ', ';
		$ret .= '`'.$v.'`';
		$i++;
	    }
	}
	else
	{
	    $ret = '`'.$arg.'`'; 
	}
    
	return $ret;

    }

    private function unknown_param($arg)
    {
	if(is_null($arg))
	    return 'NULL';

	switch(gettype($arg))
	{
	    case 'string':
		return '\''.$arg.'\'';
	    case 'double':
		return $this->f_param($arg);
	    case 'integer':
		return $this->d_param($arg);
	    case 'boolean':
		return $arg ? 1 : 0;
	    default:
		throw new Exception ("Bad type param");
		
	}
	return null;
    }

    public function skip()
    {
	return "skip\n";
    }
}
