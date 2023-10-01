<?php
/**
* Cotlib Plugin / Functions
*
* @package cotlib
* @author Dmitri Beliavski
* @copyright (c) 2023 seditio.by
*/

defined('COT_CODE') or die('Wrong URL');

/**
* Returns structure cats with no access
*/
function sedby_black_cats() {
	$db_structure = Cot::$db->structure;
	$noread = "";
	$res = Cot::$db->query("SELECT DISTINCT structure_area FROM $db_structure");
	while ($row = $res->fetch()) {
		$authCats = cot_authCategories($row['structure_area']);
		// Previuosly:
		// if (!$authCats['readAll']) {
		// 	$blocked = array_diff(array_keys(Cot::$structure[$row['structure_area']]), $authCats['read']);
		// 	$blocked = "'" . implode("','", array_values($blocked)) . "'";
		// 	$noread = empty($noread) ? $blocked : $noread . "," . $blocked;
		// }
		// Now simpler:
		if ($authCats['readNotAllowed']) {
			$blocked = "'" . implode("','", array_values($authCats['readNotAllowed'])) . "'";
			$noread = empty($noread) ? $blocked : $noread . "," . $blocked;
		}
	}
	return $noread;
}

/**
* Builds WHERE statement for MySQL query
*/
function sedby_build_where($array) {
	$array = array_filter($array);
	if (!empty($array)) {
		$where = " WHERE " . implode(" AND ", $array) . " ";
	}
	else {
		$where = '';
	}
	return $where;
}

/**
* Returns URL parameters for various areas
*/
function sedby_geturlarea() {
  if (defined('COT_ADMIN')) {
    $out = 'admin';
	// Obsolete
  // } elseif (defined('COT_PLUG')) {
  //   $out = 'plug';
  } else {
    $out = Cot::$env['ext'];
  }
  return $out;
}

/**
* Returns URL parameters for various areas
*/
function sedby_geturlparams() {
  if (defined('COT_LIST')) {
    global $list_url_path;
    $out = $list_url_path;
  } elseif (defined('COT_PAGES')) {
    global $urlParams;
    $out = $urlParams;
  } elseif (defined('COT_USERS')) {
    // global $m, $id, $u;
    // $out = empty($m) ? array() : array('m' => $m, 'id' => $id, 'u' => $u);
    global $m, $id, $u;
    $out = empty($m) ? array() : array('m' => $m, 'id' => $id);
  } elseif (defined('COT_ADMIN')) {
    global $m, $p, $a, $user;
    $out = array('m' => $m, 'p' => $p, 'a' => $a, 'user' => $user);
  } else {
    $out = array();
  }
  return $out;
}

/**
* array_shift for associated arrays
*/
function &array_shift2(&$array) {
  if (count($array) > 0) {
    $key = key($array);
    $first = &$array[$key];
  } else {
    $first = null;
  }
  array_shift($array);
  return $first;
}

/**
* Encrypts or decrypts string
*
* @param  string $action 01.  Action (encrypt || decrypt)
* @param  string $string 02.  String to encrypt / decrypt
* @param  string $key    03. Secret key
* @param  string $iv     04. Initialization vector
* @param  string $method 05. Encryption method (optional)
* @return string         Encrypted / decrypted string
*/
function sedby_encrypt_decrypt($action, $string, $key, $iv, $method = '') {
  $method = empty($method) ? 'AES-256-CBC' : $method;
  $key = hash('sha256', $key);
  $iv = substr(hash('sha256', $iv), 0, 16);
  if ($action == 'encrypt') {
    $output = openssl_encrypt($string, $method, $key, 0, $iv);
    $output = base64_encode($output);
  } elseif ($action == 'decrypt') {
    $output = base64_decode($string);
    $output = openssl_decrypt($output, $method, $key, 0, $iv);
  }
  return $output;
}

/**
* Converts multidimensional array to string
*
* @param  string $glue  01. Separator
* @param  string $array 02. Array
* @return string        List of array(s) values
*/
function sedby_implode_all($glue, $array) {
  for ($i = 0; $i < count($array); $i++) {
    if (@is_array($array[$i])) {
      $array[$i] = sedby_implode_all($glue, $array[$i]);
    }
  }
  return implode($glue, $array);
}

/**
* Returns condition as SQL string
*
* @param    string  $cc_mode  01. Selection mode: single, array, black or white
* @param    string  $cc_cats  02. Category (or categories in double quotes, comma separated)
* @param    bool    $cc_subs  03. Include subcategories
* @return  string            Condition as SQL string
*/
function sedby_compilecats($cc_mode, $cc_cats, $cc_subs) {

  if (!empty($cc_cats) && ($cc_mode == 'single' || $cc_mode == 'array_white' || $cc_mode == 'array_black' || $cc_mode == 'white' || $cc_mode == 'black')) {
    $cc_cats = str_replace(' ', '', $cc_cats);
    if ($cc_mode == 'single') {
      if ($cc_subs == false) {
        $cc_where = "page_cat = " . Cot::$db->quote($cc_cats);
      } else {
        $cc_cats = cot_structure_children('page', $cc_cats, $cc_subs);
        $cc_where = ($cc_cats > 1) ? "page_cat IN ('" . implode("','", $cc_cats) . "')" : "AND page_cat = " . Cot::$db->quote($cc_cats[0]);
      }
    }
    elseif (($cc_mode == 'array_white') || $cc_mode == 'array_black') {
      $what = ($cc_mode == 'array_black') ? "NOT" : "";
      if ($cc_subs == false) {
        $cc_cats = '"'.implode('","', $cc_cats).'"';
        $cc_where = "page_cat " . $what . " IN ($cc_cats)";
      } else {
        $tempcats = array();
        foreach ($cc_cats as $value) {
          $tempcats[] = cot_structure_children('page', $value, true);
        }
        $cc_where = "page_cat " . $what . " IN ('" . sedby_implode_all("','", $tempcats) . "')";
      }
    }
    elseif (($cc_mode == 'white') || $cc_mode == 'black') {
      $what = ($cc_mode == 'black') ? "NOT" : "";
      $cc_cats = explode(';', $cc_cats);
      if ($cc_subs == false) {
        $cc_where = "page_cat " . $what . " IN ('" . implode("','", $cc_cats) . "')";
      } else {
        $tempcats = array();
        foreach ($cc_cats as $value) {
          $tempcats[] = cot_structure_children('page', $value, true);
        }
        $cc_where = "page_cat " . $what . " IN ('" . sedby_implode_all("','", $tempcats) . "')";
      }
    }
  } else {
    $cc_where = '';
  }
  return $cc_where;
}
