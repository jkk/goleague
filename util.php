<?php

function connect($host, $user, $pass) {
    $dbc = @mysql_connect($host, $user, $pass, true);
    if (!$dbc) die(mysql_error());
    mysql_select_db("emptysky", $dbc);
}

// Dispatch to a phtml page or to a Site method or die
function dispatch($site_class) {
    $in_path = $_REQUEST['path'];
    $path = array_map("pacify_path", explode("/", $in_path));
    if (!$path[0]) $method = "home";
    else $method = $path[0];
    $params = count($path) == 1 ? null : array_slice($path, 1);
    if (file_exists($method . ".phtml")) {
        include($method . ".phtml");
        return;
    }
    $vars = get_class_vars($site_class);
    $resources = either($vars['resources'], array());
    $res_len = 0;
    foreach ($resources as $resource) {
        if (strpos($in_path, $resource) === 0) {
            $res_len = count(explode("/", $resource));
            $method = str_replace("/", "_", $resource);
            $params = array_slice($path, $res_len);
        }
    }
    // Special method suffixes for defined resources:
    //      foobars -- browse
    //      foobars/add (GET) -- add_form
    //      foobars/add (POST) -- add
    //      foobars/edit (GET) -- edit_form
    //      foobars/edit (POST) -- edit
    //      foobars/123 -- view
    if ($res_len) {
        if (!$params)
            $method .= "_browse";
        elseif ($params[0] == "add")
            if (count($_POST))
                $method .= "_add";
            else
                $method .= "_add_form";
        elseif ($params[0] == "edit")
            if (count($_POST))
                $method .= "_edit";
            else
                $method .= "_edit_form";
        else
            $method .= "_view";
    }
    if (!method_exists($site_class, $method)) {
        die("WTF is \"" . htmlentities($method) . "\"?");
    } else {
        call_user_func_array(array('Site', str_replace("-", "_", $method)), $params);
    }    
}

function either($a, $b) {
    return $a ? $a : $b;
}

function href($path) {
    return URL_ROOT . $path;
}

// Allow only alphanumeric characters and dashes in URL params
function pacify_path($path) {
    return strtolower(preg_replace("/[^a-zA-Z0-9-]/", "", $path));
}
function insert_header($title="") {
    include("../../new/header.phtml");
    if ($title) echo "<h2>$title</h2>";
}

function insert_footer() {
    include("../../new/footer.phtml");
}

function insert_content($title, $content) {
    insert_header($title);
    echo $content;
    insert_footer();
}

// Return a table for all rows from a query, hyperlinking to the first col
// with the name of the second col
function browse_table($select, $base_href) {
    $rows = fetch_rows($select);
    if (!count($rows))
        return "<p><i>None</i></p>\n";
    $retval = "<table class='browse-table'>\n<tr>";
    foreach (array_slice(array_keys($rows[0]), 1) as $key)
        $retval .= "<th>" . htmlentities($key) . "</th>";
    $retval .= "</tr>\n";
    $href = "";
    foreach ($rows as $row) {
        $row_num++;
        $retval .= "<tr>";
        $col_num = 0;
        foreach ($row as $key => $value) {
            $col_num++;
            $out_value = htmlentities($value);
            if ($col_num == 1) {
                $href = href($base_href . $out_value);
                continue;
            } elseif ($col_num == 2) {
                $out_value = "<a href='$href'>$out_value</a>";
            }
            $retval .= "<td>$out_value</td>";
        }
        $retval .= "</tr>\n";
    }
    $retval .= "</table>\n";
    return $retval;
}

function fetch_row($select) {
    $res = @mysql_query($select);
    return mysql_fetch_array($res, MYSQL_ASSOC);
}

function fetch_rows($select) {
    $res = @mysql_query($select);
    if (!$res) return array();
    $rows = array();
    while ($row = mysql_fetch_array($res, MYSQL_ASSOC))
        $rows[] = $row;
    return $rows;
}

function insert_row($table, $values) {
    $safe_keys = array_map("mysql_real_escape_string", array_keys($values));
    $safe_values = array();
    foreach (array_values($values) as $value)
        $safe_values[] = "'" . mysql_real_escape_string($value) . "'";
    $query = "insert into `" . mysql_real_escape_string($table) . "`" .
        " (" . implode(",", $safe_keys) . ")" .
        " values (" . implode(",", $safe_values) . ")";
    @mysql_query($query);
    return mysql_insert_id();
}

?>