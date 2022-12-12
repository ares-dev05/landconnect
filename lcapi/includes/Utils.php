<?php
	error_reporting(E_ALL);
	
	function tr() { echo "<tr>"; }
	function ctr() { echo "</tr>"; }

	function safe($obj,$default=NULL) { return isset($obj)?$obj:$default; }
	function getkey($arr,$key,$default=NULL) { return isset($arr[$key])?$arr[$key]:$default; }
	
	function getvar( $name, $default=NULL )
	{
		return isset($_GET[$name]) ? $_GET[$name] : $default;
	}
	
	function randString($length=16) {
		$characters = '0123456789abcdefghijklmnopqrstuvwxyz';
		$randomString = '';
		for ($i = 0; $i < $length; $i++) {
			$randomString .= $characters[rand(0, strlen($characters) - 1)];
		}
		return $randomString;
	}
	
	function baseResponseObj( $source=NULL ) {
		$res = new stdClass();
		$res -> ok = true;
		if ( $source ) $res -> source = $source;
		
		return $res;
	}
	
	function errorResponseObj( $msg="", $source=NULL ) {
		$res = new stdClass();
		$res -> ok = false;
		$res -> msg = $msg;
		if ( $source ) $res -> source = $source;
		
		return $res;
	}
	
	function sp( $name, $default="" ) {
		return
			isset( $_POST[$name] ) ?
			$_POST[$name] : $default;
	}
	
	function post( $name ) {
		return $_POST[$name];
	}

	function debug_log( $message ) {
		$file = fopen( __DIR__."/log.txt", "a" );
		fwrite( $file, $message."\n" );
		fclose( $file );
	}

	/**
	 * @param $fields array
	 * @return bool
	 */
	function hasPostVars( $fields )
	{
		foreach ( $fields as $field )
			if ( !isset($_POST[$field]) )
				return false;

		return true;
	}
?>