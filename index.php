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
        
    var $protected = array(
        "admin" => array("username" => "emptysky", "password" => "xxx"));
    
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
    
    function admin() {
        insert_content(
            "League Admin",
            "<ul>
                <li><a href='" . href("admin/bands") . "'>Bands</a></li>
                <li><a href='" . href("admin/rounds") . "'>Rounds</a></li>
                <li><a href='" . href("report") . "'>Report Result</a></li>
            </ul>");
    }
    
    function admin_bands_browse() {
        insert_content(
            "Bands",
            "<p><a href='" . href("admin/bands/add") . "'>Add Band</a></p>" .
            browse_table("select bid, name as band from bands order by name", "admin/bands/"));
    }
    
    function admin_bands_view($bid, $checkboxes=false) {
        $band = fetch_row("select * from bands where bid='$bid'");
        $player_select = "select p.pid, name as player, status
            from players p join players_to_bands pb on p.pid=pb.pid and pb.bid='$bid'
            order by name";
        if ($checkboxes) {
            echo get_checkboxes(fetch_rows($player_select), "pids", "pid", "player");
            return;
        }
        insert_header("Band: " . htmlentities($band['name']));
        echo browse_table($player_select, "admin/players/");
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
        insert_new_players($bid, $values['new_players']);
        redir("admin/bands/$bid", true);
    }
    
    function admin_bands_add_form() {
        insert_header("Add Band");
        ?>
        <form action="<?=href("admin/bands/add")?>" method="post">
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
        insert_new_players($bid, $values['new_players']);
        redir("admin/bands", true);
    }
    
    function admin_rounds_browse() {
        insert_content(
            "Rounds",
            "<p><a href='" . href("admin/rounds/add") . "'>Add Round</a></p>" .
            browse_table(
                "select r.rid, concat_ws('', date_format(r.begins, '%c/%e'), ' - ',
                    date_format(r.ends, '%c/%e')) as round, b.name as band
                    from rounds r join bands b on r.bid=b.bid
                    order by r.begins desc",
                "admin/rounds/"));
    }
    
    function admin_rounds_view($rid) {
        $round = fetch_row("select concat(date_format(begins, '%c/%e'), ' - ',
            date_format(ends, '%c/%e')) as date_range, r.*, b.name as band
            from rounds r join bands b on r.bid=b.bid
            where rid='$rid'");
        $players = fetch_rows("select p.pid, p.name, pr.pid as in_round
            from players p join players_to_bands pb
            on p.pid=pb.pid and pb.bid='" . $round['bid'] . "'
            left join players_to_rounds pr on p.pid=pr.pid
            order by p.name");
        insert_header("Round: " . $round['date_range'] . ", Band " . $round['band']);
        echo "<p>Players:</p>";
        echo "<form action='" . href("admin/rounds/$rid/edit") . "' method='post'>";
        echo "<div id='players'>";
        echo get_checkboxes($players, "pids", "pid", "name", "in_round");
        echo "</div>";
        echo "<input type='submit' value='Update Players'>";
        echo "</form>";
        insert_footer();
    }
    
    function admin_rounds_edit($rid, $values) {
        delete_rows("players_to_rounds", "rid='$rid'");
        foreach ($values['pids'] as $pid) {
            insert_row("players_to_rounds", array("pid" => $pid, "rid" => $rid));
        }
        redir("admin/rounds/$rid", true);
    }
    
    function admin_rounds_add_form() {
        insert_header("Add Round");
        ?>
        <form action='<?=href("admin/rounds/add")?>' method='post'>
        <div>Band:</div>
        <select id='bid' name="bid">
        <option value=''>[Select a band...]</option>
        <?php
            $bands = fetch_rows("select bid, name from bands order by name");
            foreach ($bands as $band)
                echo "<option value='" . $band['bid'] . "'>" . $band['name'] . "</option>";
        ?>
        </select>
        <div>Begin date:</div>
        <input type="text" name="begins" size="10"> <span>YYYY-MM-DD</span>
        <div>End date:</div>
        <input type="text" name="ends" size="10"> <span>YYYY-MM-DD</span>
        <div>Players:</div>
        <div id='players'>[Select a band]</div>
        <input type="submit" value="Add Band">
        </form>
        
        <script>
        (function() {
            function updateCheckboxes(bid) {
                if (!bid) return;
                $("#players").load("../bands/" + bid + "/checkboxes");
            }
            $("#bid").bind("change", function() { updateCheckboxes(this.value); });
            updateCheckboxes($("#bid")[0].value);
        })();
        </script>
        <?php
        insert_footer();
    }
    
    function admin_rounds_add($values) {
        $rid = insert_row("rounds", array(
            "bid" => $values['bid'],
            "begins" => $values['begins'],
            "ends"  => $values['ends']));
        foreach ($values['pids'] as $pid)
            insert_row("players_to_rounds", array("pid" => $pid, "rid" => $rid));
        redir("admin/rounds", true);
    }
    
    function admin_players_view($pid, $action="") {
        $player = fetch_row("select * from players where pid='$pid'");
        if ($action == "toggle") {
            update_row(
                "players",
                array("status" => ($player['status'] == "active" ? "inactive" : "active")),
                "pid='$pid'");
            redir("admin/players/$pid", true);
        }
        insert_header("Player: " . htmlentities($player['name']));
        echo "<p><a href='" . href("admin/players/$pid/toggle") . "'>" .
            ($player['status'] == "active" ? "Deactivate" : "Activate") . "</a></p>";
        echo "<p><a href='" . href("players/$pid") . "'>View full record</a></p>";
        insert_footer();
    }
}

// Helper functions

function get_checkboxes($rows, $name, $value, $text, $checked_field="") {
    if (!$checked_field) $checked_field = $value;
    $retval = "";
    foreach ($rows as $row) {
        $retval .= "<div>" .
            "<input name='${name}[]' type='checkbox' id='cb-" . $row[$value] . "'" .
                " value='" . $row[$value] . "' " .
                ($row[$checked_field] ? "checked" : "") . "> " .
            "<label for='cb-" . $row[$value] . "'>" . $row[$text] . "</label>" .
            "</div>";
    }
    return $retval;
}

function insert_new_players($bid, $input) {
    $new_players = preg_split("/(\r\n|\r|\n)/", $input);
    foreach ($new_players as $new_player) {
        if (!$new_player) continue;
        $pid = insert_row("players", array("name" => $new_player));
        insert_row("players_to_bands", array("pid" => $pid, "bid" => $bid));
    }
}

?>
