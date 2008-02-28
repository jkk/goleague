<?php

include("util.php");

define("SITE_NAME", "Empty Sky League");
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
        "admin/rounds",
        "admin/players");
    
    function standings() {
        insert_header("Player Standings");
        echo "<p>blah</p>";
        insert_footer();
    }
    
    function archive() {
        
    }
    
    function report() {
        
    }
    
    function players_browse() {
        insert_content(
            "Players",
            browse_table("select pid, name as player from players order by name", "players/"));
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
        insert_header("Band: " . htmlentities($band['name']));
        echo browse_table(
            "select p.pid, name as player, status
                from players p, players_to_bands pb
                where p.pid=pb.pid and pb.bid='$bid'
                order by name",
            "admin/players/");
        ?>
        <form action='<?=href("admin/bands/$bid/edit")?>' method='post'>
            Add players to this band (one name per line):<br>
            <textarea name="new_players"></textarea><br>
            <input type="submit" value="Add Players">
        </form>
        <?php
        insert_footer();
    }
    
    function admin_bands_edit($bid, $values) {
        $new_players = preg_split("/(\r\n|\r|\n)/", $values['new_players']);
        foreach ($new_players as $new_player) {
            $pid = insert_row("players", array("name" => $new_player));
            insert_row("players_to_bands", array("pid" => $pid, "bid" => $bid));
        }
        redir("admin/bands/$bid");
    }
    
    function admin_bands_add_form() {
        insert_header("Add Band");
        ?>
        <form action='<?=href("admin/bands/add")?>' method='post'>
        <div>Band name:</div>
        <input type="text" name="name">
        <div>Players, one name per line:</div>
        <textarea name="new_players"></textarea>
        <input type="submit" value="Add Band">
        </form>
        <?php
        insert_footer();
    }
    
    function admin_bands_add($values) {
        $bid = insert_row("bands", array("name" => $values['name']));
        $new_players = preg_split("/(\r\n|\r|\n)/", $values['new_players']);
        foreach ($new_players as $new_player) {
            $pid = insert_row("players", array("name" => $new_player));
            insert_row("players_to_bands", array("pid" => $pid, "bid" => $bid));
        }
        redir("admin/bands");
    }
    
    function admin_players_view($pid, $action="") {
        $player = fetch_row("select * from players where pid='$pid'");
        if ($action == "toggle") {
            update_row(
                "players",
                array("status" => ($player['status'] == "active" ? "inactive" : "active")),
                "pid='$pid'");
            redir("admin/players/$pid");
        }
        insert_header("Player: " . htmlentities($player['name']));
        echo "<p><a href='" . href("admin/players/$pid/toggle") . "'>" .
            ($player['status'] == "active" ? "Deactivate" : "Activate") . "</a></p>";
        echo "<p><a href='" . href("players/$pid") . "'>View full record</a></p>";
        insert_footer();
    }
}

?>
