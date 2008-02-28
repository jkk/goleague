<?php

function connect($host, $user, $pass) {
    $dbc = @mysql_connect($host, $user, $pass, true);
    if (!$dbc) die(mysql_error());
    mysql_select_db("emptysky", $dbc);
}

// Dispatch to a phtml page or to a class method or die
function dispatch($site_class) {
    $in_path = $_REQUEST['path'];
    $path = array_map("pacify_path", explode("/", $in_path));
    if (!$path[0]) $method = "home";
    else $method = $path[0];
    $params = count($path) == 1 ? null : array_slice($path, 1);
    
    // .phtml page?
    if (file_exists($method . ".phtml")) {
        include($method . ".phtml");
        return;
    }
    
    // Special method suffixes for defined resources:
    //      foobars -- browse
    //      foobars/add (GET) -- add_form
    //      foobars/add (POST) -- add
    //      foobars/123 -- view
    //      foobars/123/edit (GET) -- edit_form
    //      foobars/123/edit (POST) -- edit
    $vars = get_class_vars($site_class);
    $resources = either($vars['resources'], array());
    $res_len = 0;
    foreach ($resources as $resource) {
        if (preg_match("/^" . preg_quote($resource, '/') . "(\/|$)/", $in_path)) {
            $res_len = count(explode("/", $resource));
            $method = str_replace("/", "_", $resource);
            $params = array_slice($path, $res_len);
        }
    }
    if ($res_len) {
        if (!$params)
            $method .= "_browse";
        elseif ($params[0] == "add")
            if (count($_POST)) {
                $method .= "_add";
                $params = array($_POST);
            } else
                $method .= "_add_form";
        elseif ($params[1] == "edit")
            if (count($_POST)) {
                $method .= "_edit";
                $params = array($params[0], $_POST);
            } else
                $method .= "_edit_form";
        else
            $method .= "_view";
    }
    
    // Password protection
    $protected = either($vars['protected'], array());
    foreach ($protected as $protected_path => $auth) {
        $is_protected = strpos($in_path, $protected_path) === 0;
        $is_authed = $auth['username'] == $_SERVER['PHP_AUTH_USER'] &&
            $auth['password'] == $_SERVER['PHP_AUTH_PW'];
        if ($is_protected && !$is_authed) {
            header('WWW-Authenticate: Basic realm="' . SITE_NAME . ' Admin"');
            header('HTTP/1.0 401 Unauthorized');
            echo "Sod off.";
            exit;
        }
    }
    
    // Hand off to class method
    $method = str_replace("-", "_", $method);
    if (!method_exists($site_class, $method)) {
        die("WTF is \"" . htmlentities($method) . "\"?");
    } else {
        call_user_func_array(array('Site', $method), $params);
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

function redir($path, $show_feedback=false) {
    header("location: " . URL_ROOT . $path . ($show_feedback ? "?success=1" : ""));
    exit;
}

function insert_header($title=null) {
    include(HEADER_PATH);
    if ($_REQUEST['path']) {
        $breadcrumbs = array_slice(explode("/", $_REQUEST['path']), 0, -1);
        echo "<div id='breadcrumbs'>";
        echo "<a href='" . URL_ROOT . "'>" . SITE_NAME . "</a>";
        $trail = array(preg_replace("/\/$/", "", URL_ROOT));
        foreach ($breadcrumbs as $crumb) {
            $trail[] = $crumb;
            echo " &gt; <a href='". implode("/", $trail) . "'>$crumb</a>";
        }
        echo "</div>";
    }
    if ($title) echo "<h2>$title</h2>";
    if ($_REQUEST['success']) {
        echo "<p id='feedback'>Success!</p>";
    }
}

function insert_footer() {
    include(FOOTER_PATH);
}

function insert_content($title, $content) {
    insert_header($title);
    echo $content;
    insert_footer();
}

// Return a table for all rows from a query, hyperlinking to the first col
// with the name of the second col
function browse_table($select, $base_href="") {
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
    if (!$res) return null;
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

function get_safe_values($values) {
    $safe_keys = array_map("mysql_real_escape_string", array_keys($values));
    $safe_values = array();
    foreach (array_values($values) as $value)
        $safe_values[] = ($value == "now()" ? "now()" :
            "'" . mysql_real_escape_string($value) . "'");
    return array($safe_keys, $safe_values);
}

function insert_row($table, $values) {
    list($safe_keys, $safe_values) = get_safe_values($values);
    $query = "insert into `" . mysql_real_escape_string($table) . "`" .
        " (" . implode(",", $safe_keys) . ")" .
        " values (" . implode(",", $safe_values) . ")";
    @mysql_query($query);
    return mysql_insert_id();
}

function update_row($table, $values, $where) {
    list($safe_keys, $safe_values) = get_safe_values($values);
    $query = "update `" . mysql_real_escape_string($table) . "` set ";
    for ($i = 0; $i < count($safe_keys); $i++)
        $query .= $safe_keys[$i] . "=" . $safe_values[$i] . ", ";
    $query = preg_replace("/, $/", "", $query);
    $query .= " where $where";
    @mysql_query($query);
}

function delete_rows($table, $where="") {
    $query = "delete from `" . mysql_real_escape_string($table) . "`" .
        ($where ? " where $where" : "");
    @mysql_query($query);
}

function get_checkboxes($rows, $name, $value, $text, $checked_field="") {
    if (!$checked_field) $checked_field = $value;
    $retval = "";
    foreach ($rows as $row) {
        $retval .= "<div>" .
            "<input name='${name}[]' type='checkbox' id='cb-" . $row[$value] . "'" .
                " value='" . $row[$value] . "'" .
                ($row[$checked_field] ? " checked" : "") . "> " .
            "<label for='cb-" . $row[$value] . "'>" . $row[$text] . "</label>" .
            "</div>";
    }
    return $retval;
}

function get_select($rows, $name, $value, $text, $default="", $selected_field="") {
    echo "<select id='$name' name='$name'>";
    if ($default)
        echo "<option value=''>$default</option>";
    foreach ($rows as $row)
        echo "<option value='" . $row[$value] . "'" .
            ($row[$selected_field] ? " selected" : "") . ">" .
            $row[$text] . "</option>";
    echo "</select>";
}


?>