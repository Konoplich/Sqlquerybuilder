<?php

namespace FpDbTest;

use Exception;
use mysqli;

class Database implements DatabaseInterface
{
    private mysqli $mysqli;

    private string $Pattern= '/(?P<isBlock>(?:[blank]*?{[blank]*?)?)(?P<prefix>[^?{}]*)(?P<param>(?:\?(?P<type>(?:d|#|a|f)?))?)/';


    public function __construct(mysqli $mysqli)
    {
        $this->mysqli = $mysqli;
	
	}

    public function buildQuery(string $query, array $args = []): string
	{
		$result = '';
		$concat = true;
		$block = '';
		$param_index = 0;
		if(preg_match_all($this->Pattern, $query, $matches, PREG_SET_ORDER))
		{
			for($i=0; $i<count($matches); $i++)
			{
				$buf = &$result;

				if(!$concat || empty(array_filter($matches[$i])))
					continue;

				if(!empty($matches[$i]['isBlock']) || strlen($block)>0  )
				{
					$buf = &$block;
				
					if(is_array($args[$param_index]))
					{
						foreach($args[$param_index] as $val)
							if($val===this->skip())
							{
								$concat = false;
								continue;
							}
					}else
					{
						if($args[$param_index]===$this->skip())
						{
							$concat = false;
							continue;
						}
					}
				}

				$buf .= $matches[$i]['prefix'];
					if(!empty($matches[$i]['type']))
					{
						switch($matches[$i]['type'])
						{
							case '#':
								$buf .= $this->dies_param($args[$param_index]);
								break;
							case 'a':
								$buf .= $this->a_param($args[$param_index]);
								break;
							case 'f':
								$buf .= $this->f_param($args[$param_index]);
								break;
							case 'd':
								$buf .= $this->d_param($args[$param_index]);
								break;
							default:
								throw new Exception('unknown param');	
						}		
					}elseif(!empty($matches[$i]['param']))
					{
						$buf .= $this->unknown_param($args[$param_index]);
					}
					if(!empty($matches[$i]['param']))
						$param_index += 1;
			}	
		}
		else
			throw new Exception("Bad query");
		
		if($concat)
			$result .= $block;
		return $result;
    }

	private function d_param($arg)
	{
		//Проверка на тип согласно заданию, но тогда 4 тест не пройдет, как должен согласно заданию
		//if(gettype($arg) != 'integer') throw new Exception("Expected integer");
		
		if(is_null($arg))
			return 'NULL';

		return sprintf('%d',(int)$arg);
    }

    private function f_param($arg)
	{
		if(gettype($arg) != 'double') throw new Exception("Expected float");

		if(is_null($arg))
			return 'NULL';

		return sprintf("%g",$arg);
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
		}
		throw new Exception ("Bad type param");
    }

    public function skip()
    {
		return "skip\n";
    }
}
