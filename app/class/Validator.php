<?php

class Validate {

	public static $MIN_VALUE	= 1;
	public static $MAX_VALUE	= 2;
	public static $MIN_LENGTH	= 3;
	public static $MAX_LENGTH	= 4;
	public static $REG_PATTERN	= 5;
	public static $DATE_FORMAT	= 6;
	public static $IS_STRING	= 7;
	public static $IS_INT	= 8;
	public static $IS_FLOAT	= 9;
	public static $IS_BOOL	= 10;
	public static $FILTER_VAR	= 11;
	
	public static function c($value, $validators) {

		foreach($validators as $validator => $pattern ) {
			switch($validator) {
				case self::$MIN_VALUE :
					if( $value < $pattern )
						throw new Exception();
					break;

				case self::$MAX_VALUE :
					if( $value > $pattern )
						throw new Exception();
					break;

				case self::$MIN_LENGTH :
					if( mb_strlen($value, 'UTF-8') < $pattern)
						throw new Exception();
					break;

				case self::$MAX_LENGTH :
					if( mb_strlen($value, 'UTF-8') > $pattern)
						throw new Exception();
					break;

				case self::$REG_PATTERN :
					if( ! preg_match_all($pattern, $value, $matches, PREG_SET_ORDER) )
						throw new Exception();
					break;

				case self::$DATE_FORMAT :
					$d = DateTime::createFromFormat($pattern, $value);
					$result = ($d && $d->format($pattern) == $value);
					if($result === false) 
						throw new Exception("");
					break;

				case self::$IS_STRING :
					if( !is_string($value) && $pattern === false)
						throw new Exception();
					break;
					
				case self::$IS_INT :
					if( !is_int($value) && $pattern === false)
						throw new Exception();
					break;

				case self::$IS_FLOAT :
					if( !is_float($value) && $pattern === false)
						throw new Exception();
					break;

				case self::$IS_BOOL :
					if( !is_bool($value) && $pattern === false)
						throw new Exception();
					break;
				case self::$FILTER_VAR :
					if ( filter_var($value, $pattern) === false)
						throw new Exception();
			}
		}
	}
}
