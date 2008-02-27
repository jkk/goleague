<?php

class Site {
    
    function standings() {
        insert_header("Player Standings");
        echo "<p>blah</p>";
        insert_footer();
    }
    
    function archive() {
        
    }
    
    function report() {
        
    }
}

// Dispatch to the appropriate method
$path = explode("/", $_REQUEST['path']);
if (!$path[0]) $path = array("home");
$sub_path = count($path) == 1 ? array(null) : array_slice($path, 1);
if (!method_exists('Site', $path[0])) {
    $path[0] = preg_replace("/[^a-zA-Z0-9-]/", "", $path[0]); // Safety first!
    if (file_exists($path[0] . ".phtml")) {
        include($path[0] . ".phtml");
    } else {
        die("WTF? \"" . $path[0] . "\" not understood.");
    }
} else {
    call_user_func_array(array('Site', $path[0]), $sub_path);
}

function insert_header($title="") {
    include("../../new/header.phtml");
    if ($title) echo "<h2>$title</h2>";
}

function insert_footer() {
    include("../../new/footer.phtml");
}

?>
