<?php
function connect_db($dbhost, $username, $password, $dbname){
	if (PHP_VERSION_ID < 50500) {
		$dbhandle = mysql_connect($dbhost, $username, $password);
		if (!$dbhandle) {
		    echo('Error connect to db. '.mysql_error());
		}
		mysql_select_db($dbname, $dbhandle);
	}else{
		$dbhandle = new mysqli($dbhost, $username, $password, $dbname);
		if ($dbhandle->connect_errno) {
		    echo "Error connect to db: (" . $mysqli->connect_errno . ") " . $mysqli->connect_error;
		}
	}
	return $dbhandle;
}

function query_db($dbhandle, $query){
	if (PHP_VERSION_ID < 50500) {
		$res_q = mysql_query($query);
	}else{
		$res_q = mysqli_query($dbhandle, $query);
	}
	
	return $res_q;
}

function fetch_assoc($res_q){
	if (PHP_VERSION_ID < 50500) {
		$_data = mysql_fetch_assoc($res_q);
	}else{
		$_data = mysqli_fetch_assoc($res_q);
	}
	return $_data;
}

function close_db($dbhandle){
	if (PHP_VERSION_ID < 50500) {
		mysql_close($dbhandle);
	}else{
		mysqli_close($dbhandle);
	}
}

?>