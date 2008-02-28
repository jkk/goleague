<?php

include("util.php");

define("FILE_ROOT", dirname(__FILE__));
define("HEADER_PATH", "../../new/header.phtml");
define("FOOTER_PATH", "../../new/footer.phtml");
define("URL_ROOT", "/league/new/");

connect("localhost", "emptysky", "xxx");
dispatch("Site");

class Site {
    
    var $resources = array(
        "players",
        "admin/bands",
        "admin/rounds");
    
    function standings() {
        insert_header("Player Standings");
        echo "<p>blah</p>";
        insert_footer();
    }
    
    function archive() {
        
    }
    
    function report() {
        
    }
    
    function admin($subpath="", $subaction="") {
        insert_content(
            "League Admin",
            "<ul>
                <li><a href='" . href("admin/bands") . "'>Bands</a></li>
                <li><a href='" . href("admin/rounds") . "'>Rounds</a></li>
                <li><a href='" . href("report") . "'>Report Result</a></li>");
    }
    
    function admin_bands_browse() {
        insert_content(
            "Bands",
            "<p><a href='" . href("admin/bands/add") . "'>Add Band</a></p>" .
            browse_table("select bid, name as band from bands order by name", "admin/bands/"));
    }
    
    function admin_bands_view($bid) {
        $band = fetch_row("select * from bands where bid='$bid'");
        insert_header("Band: " . $band['name']);
        echo browse_table(
            "select p.pid, name as player
                from players p, players_to_bands pb
                where p.pid=pb.pid and pb.bid='$bid'
                order by name",
            "players/");
        insert_footer();
    }
    
    function admin_bands_add_form() {
        $players = fetch_rows("select pid, name from players order by name");
        insert_header("Add Band");
        ?>
        <form action='<?=href("admin/bands/add")?>' method='post'>
        <table>
        <tr><td>Band name:</td><td><input type="text" name="name"></td></tr>
        <tr><td>&nbsp;</td><td>
            <p>
                <?php if (count($players)) {
                    echo "Check all players that apply.";
                } ?>
                Enter new players in the text box below, one name per line.</p>
            <textarea name="new_players"></textarea>
        </td></tr>
        <tr><td>&nbsp;</td><td><input type="submit" value="Add Band"></td></tr>
        </table>
        </form>
        <?php
        insert_footer();
    }
    
    function admin_bands_add($values) {
        $bid = insert_row("bands", array("name" => $_POST['name']));
        $new_players = preg_split("/(\r\n|\r|\n)/", $_POST['new_players']);
        foreach ($new_players as $new_player) {
            $pid = insert_row("players", array("name" => $new_player));
            insert_row("players_to_bands", array("pid" => $pid, "bid" => $bid));
        }
        header("location: " . href("admin/bands"));
    }
}

?>
