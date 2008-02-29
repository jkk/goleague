<?php

include("util.php");

//////////////////////////////////////////////////////////////////////////////
// Config
//////////////////////////////////////////////////////////////////////////////

define("SITE_NAME", "Empty Sky League");
define("FILE_ROOT", dirname(__FILE__));
define("HEADER_PATH", "../../new/header.phtml");
define("FOOTER_PATH", "../../new/footer.phtml");
define("URL_ROOT", "/league/new/");
define("USERNAME", "emptysky");
define("PASSWORD", "xxx");

connect("localhost", "emptysky", USERNAME, PASSWORD);
dispatch("Site");

//////////////////////////////////////////////////////////////////////////////
// Site pages/actions
//////////////////////////////////////////////////////////////////////////////

class Site {
    
    var $resources = array(
        "results",
        "players",
        "rounds",
        "admin/results",
        "admin/rounds",
        "admin/bands");
        
    var $protected = array(
        "admin" => array("username" => USERNAME, "password" => PASSWORD));
    
    // Show all rounds
    function rounds_browse() {
        content(
            "Archived Rounds",
            browse_table("select r.rid, concat_ws('', 'Band ', b.name, ', ',
                date_format(r.begins, '%c/%e'), ' - ', date_format(r.ends, '%c/%e')) as round
                from rounds r join bands b on r.bid=b.bid
                order by b.name, r.begins desc",
                "rounds/"));
    }
    
    // Show result matrix for the most current rounds (one per band) or for a
    // specific round
    function rounds_view($rid) {
        head("Round Results");
        if ($rid == "current") {
            $latest_rounds = get_latest_rounds();
            foreach ($latest_rounds as $round) {
                echo "<h3>" . $round['round'] . "</h3>";
                echo result_matrix($round['rid']);
            }
        } else {
            $round = fetch_row("select concat_ws('', 'Band ', b.name, ', ',
                date_format(r.begins, '%c/%e'), ' - ', date_format(r.ends, '%c/%e')) as round
                from rounds r join bands b on r.bid=b.bid
                where r.rid='$rid'");
            echo "<h3>" . $round['round'] . "</h3>";
            echo result_matrix($rid);
        }
        foot();
    }
    
    // Show full list of players
    function players_browse() {
        content(
            "Players",
            browse_table("select pid, name as player from players order by name", "players/"));
    }
    
    function players_view() {
        // TODO: implement and link to this
    }
    
    function results_browse() {
        redir("rounds/current");
    }
    
    // Display a game's SGF using EidoGo
    function results_view($ids) {
        list($rid, $pw, $pb) = split("-", $ids);
        $result = fetch_row("select r.result, pw.name as white, pb.name as black,
            r.sgf, r.report_date as date
            from results r join players pw on r.pw=pw.pid
            join players pb on r.pb=pb.pid 
            where r.pw='$pw' and r.pb='$pb' and r.rid='$rid'");
        $sgf = href("sgf/" . htmlentities($result['sgf']));
        head($result['white'] . " (W) vs. " . $result['black'] . " (B)");
        echo "<p><a href='$sgf'>Download .SGF</a></p>";
        echo "<div class='eidogo-player-auto' sgf='$sgf'>";
        foot();
    }
    
    // Add a new game result
    function results_add_form() {
        head("Report Result");
        result_form("results/add");
        foot();
    }
    
    // Save a game result's SGF and insert details into the DB
    function results_add($values) {
        save_result($values, true);
        redir("rounds/" . $values['rid'], true,
            "<a href='" . href("results/add") . "'>Add another result?</a>");
    }
    
    // Spit out a <select> element of players for a given round
    function rounds_players_select($rid) {
        $players = fetch_rows("select p.pid, p.name
            from players p join players_to_rounds pr on p.pid=pr.pid and pr.rid='$rid'
            order by name");
        echo get_select($players, "{pids}", "pid", "name", "[Select a player...]");
    }
    
    // Admin front page
    function admin() {
        content(
            "Admin",
            "<ul>
                <li><a href='" . href("results/add") . "'>Report Game Result</a></li>
                <li><a href='" . href("admin/results") . "'>Manage Game Results</a></li>
                <li><a href='" . href("admin/rounds") . "'>Manage Rounds</a></li>
                <li><a href='" . href("admin/bands") . "'>Manage Bands</a></li>
            </ul>");
    }
    
    // Show all bands for admin editing
    function admin_bands_browse() {
        content(
            "Bands",
            "<p><a href='" . href("admin/bands/add") . "'>Add Band</a></p>" .
            browse_table("select bid, name as band from bands order by name", "admin/bands/"));
    }
    
    // View a band's players, with option to add new players
    // TODO: ability to remove players from band
    function admin_bands_view($bid, $checkboxes=false) {
        $band = fetch_row("select * from bands where bid='$bid'");
        head("Band: " . htmlentities($band['name']));
        echo browse_table(
            "select '', p.name as player
                from players p join players_to_bands pb on p.pid=pb.pid and pb.bid='$bid'
                order by name",
            "admin/players/");
        ?>
        <form action='<?=href("admin/bands/$bid/edit")?>' method='post'>
            Add players to this band (one name per line):<br>
            <textarea name="new_players"></textarea><br>
            <input type="submit" value="Add Players">
        </form>
        <?php
        foot();
    }
    
    // Add new players to a band
    function admin_bands_edit($bid, $values) {
        insert_new_players($bid, $values['new_players']);
        redir("admin/bands/$bid", true);
    }
    
    // Show form to add a new band
    function admin_bands_add_form() {
        head("Add Band");
        ?>
        <form action="<?=href("admin/bands/add")?>" method="post">
        <div>Band name:</div>
        <input type="text" name="name">
        <div>Players, one name per line:</div>
        <textarea name="new_players"></textarea>
        <input type="submit" value="Add Band">
        </form>
        <?php
        foot();
    }
    
    // Insert a new band into the DB
    function admin_bands_add($values) {
        $bid = insert_row("bands", array("name" => $values['name']));
        insert_new_players($bid, $values['new_players']);
        redir("admin/bands", true);
    }
    
    // Show all rounds for admin editing
    function admin_rounds_browse() {
        content(
            "Rounds",
            "<p><a href='" . href("admin/rounds/add") . "'>Add Round</a></p>" .
            browse_table("select r.rid, concat_ws('', 'Band ', b.name, ', ',
                date_format(r.begins, '%c/%e'), ' - ', date_format(r.ends, '%c/%e')) as round
                from rounds r join bands b on r.bid=b.bid
                order by r.begins desc",
                "admin/rounds/"));
    }
    
    // Show players for a band, with options to activate/deactivate them using
    // checkboxes
    function admin_rounds_view($rid) {
        $round = fetch_row("select concat(date_format(begins, '%c/%e'), ' - ',
            date_format(ends, '%c/%e')) as date_range, r.*, b.name as band
            from rounds r join bands b on r.bid=b.bid
            where rid='$rid'");
        $players = fetch_rows("select distinct p.pid, p.name, pr.pid as in_round
            from players p join players_to_bands pb
            on p.pid=pb.pid and pb.bid='" . $round['bid'] . "'
            left join players_to_rounds pr on p.pid=pr.pid and pr.rid='$rid'
            order by p.name");
        head("Round: " . $round['date_range'] . ", Band " . $round['band']);
        ?>
        <form action='<?=href("admin/rounds/$rid/edit")?>' method='post'>
        <div>Begin date:</div>
        <input type="text" name="begins" size="10" value='<?=$round['begins']?>'> <span>YYYY-MM-DD</span>
        <div>End date:</div>
        <input type="text" name="ends" size="10" value='<?=$round['ends']?>'> <span>YYYY-MM-DD</span>
        <p>Players:</p>
        <div id='players'>
        <?= get_checkboxes($players, "pids", "pid", "name", "in_round") ?>
        </div>
        <input type='submit' value='Update Round'>
        </form>
        <?php
        foot();
    }
    
    // Update a band's player list
    function admin_rounds_edit($rid, $values) {
        update_rows("rounds", array("begins" => $values['begins'], "ends" => $values['ends']),
            "rid='$rid'");
        delete_rows("players_to_rounds", "rid='$rid'");
        foreach ($values['pids'] as $pid) {
            insert_row("players_to_rounds", array("pid" => $pid, "rid" => $rid));
        }
        redir("admin/rounds/$rid", true);
    }
    
    // Show form to add a new round
    function admin_rounds_add_form() {
        head("Add Round");
        ?>
        <form action='<?=href("admin/rounds/add")?>' method='post'>
        <div>Band:</div>
        <?php
            $bands = fetch_rows("select bid, name from bands order by name");
            echo get_select($bands, "bid", "bid", "name", "[Select a band...]");
        ?>
        <div>Begin date:</div>
        <input type="text" name="begins" size="10"> <span>YYYY-MM-DD</span>
        <div>End date:</div>
        <input type="text" name="ends" size="10"> <span>YYYY-MM-DD</span>
        <div>Players:</div>
        <div id='players'>[Select a band]</div>
        <input type="submit" value="Add Round">
        </form>
        
        <script>
        (function() {
            function updateCheckboxes(bid) {
                if (!bid) return;
                $("#players").load("../../bands-players-checkboxes/" + bid);
            }
            $("#bid").bind("change", function() { updateCheckboxes(this.value); });
            updateCheckboxes($("#bid")[0].value);
        })();
        </script>
        <?php
        foot();
    }
    
    // Spit out checkboxes for players within a given band
    function bands_players_checkboxes($bid) {
        $player_select = "select p.pid, p.name as player
            from players p join players_to_bands pb on p.pid=pb.pid and pb.bid='$bid'
            order by name";
        echo get_checkboxes(fetch_rows($player_select), "pids", "pid", "player");
    }
    
    // Insert band details and players into the DB
    function admin_rounds_add($values) {
        $rid = insert_row("rounds", array(
            "bid" => $values['bid'],
            "begins" => $values['begins'],
            "ends"  => $values['ends']));
        foreach ($values['pids'] as $pid)
            insert_row("players_to_rounds", array("pid" => $pid, "rid" => $rid));
        redir("admin/rounds", true);
    }
    
    function admin_results_browse() {
        head("Game Results");
        echo browse_table("select concat(r.rid, '-', pw, '-', pb), r.result,
                pw.name as white, pb.name as black, report_date as date
                from results r join players pw on r.pw=pw.pid
                join players pb on r.pb=pb.pid
                order by report_date desc",
            "admin/results/");
        foot();
    }
    
    function admin_results_view($ids) {
        list($rid, $pw, $pb) = split("-", $ids);
        head("Edit Game Result");
        $result = fetch_row("select pw, pb, rid, result, sgf, report_date
            from results where pw='$pw' and pb='$pb' and rid='$rid'");
        result_form("admin/results/$ids/edit", $result);
        foot();
    }
    
    function admin_results_edit($ids, $values) {
        save_result($values);
        redir("admin/results", true);
    }
}


//////////////////////////////////////////////////////////////////////////////
// Helper functions
//////////////////////////////////////////////////////////////////////////////

// Show a table of all game results for a given round
function result_matrix($rid) {
    $players_x = fetch_rows("select p.pid, p.name
        from players p join players_to_rounds pr on p.pid=pr.pid and pr.rid='$rid'");
    // Include players no longer assigned to the round but that have results
    $orphans = fetch_rows("select p.pid, p.name
        from players p join results r on (p.pid=r.pw or p.pid=r.pb)
        where r.rid='$rid'");
    $orphan_ids = array();
    foreach ($orphans as $orphan) {
        $found = false;
        foreach ($players_x as $px)
            if ($px['pid'] == $orphan['pid']) $found = true;
        if (!$found) {
            $players_x[] = $orphan;
            $orphan_ids[] = $orphan['pid'];
        }
    }
    usort($players_x, create_function('$a, $b', 'return strcmp($a["name"], $b["name"]);'));
    $players_y = array();
    foreach ($players_x as $p) $players_y[] = $p;
    $results = fetch_rows("select * from results where rid='$rid'");
    echo "<table class='result-matrix'>";
    $first_y = true;
    $first_x = true;
    echo "<tr><th>&nbsp;</th>";
    foreach ($players_x as $px) {
        echo "<th class='top'>" . $px['name'] . "</th>";
    }
    echo "<th class='score'>Score</th></tr>";
    foreach ($players_y as $py) {
        $wins = 0;
        $losses = 0;
        echo "<tr>";
        $first_x = true;
        foreach ($players_x as $px) {
            if ($first_x)
                echo "<th>" . $py['name'] . "</th>";
            list($result, $presult) = get_result($rid, $results, $px['pid'], $py['pid']);
            if ($presult == 1)
                $losses++;
            elseif ($presult == 2)
                $wins++;
            $is_orphan = (in_array($px['pid'], $orphan_ids) || in_array($py['pid'], $orphan_ids));
            $is_self = $px['pid'] == $py['pid'];
            $class = ($is_self || $is_orphan ? "x " : "");
            $class .= ($presult == 1 ? "loss" : ($presult == 2 ? "win" : ""));
            echo "<td class='$class'>" . ($is_self ? "&nbsp;" : $result) . "</td>";
            $first_x = false;
        }
        echo "<td class='score'>$wins-$losses</td>";
        echo "</tr>";
        $first_y = false;
    }
    echo "</table>";
}

// Determine the end result of a game for use in the result matrix
function get_result($rid, $results, $pid1, $pid2) {
    if ($pid1 == $pid2) return array("-", 0);
    foreach ($results as $result) {
        if (($pid1 == $result['pw'] || $pid1 == $result['pb']) &&
            ($pid2 == $result['pw'] || $pid2 == $result['pb'])) {
            if ($result['result'] == "NR" || !$result['result']) {
                $presult = 0;
                $retresult = $result['result'];
            } elseif (($result['result'] == "W+" && $pid1 == $result['pw']) ||
                      ($result['result'] == "B+" && $pid1 == $result['pb'])) {
                $presult = 1;
                $retresult = $result['result'] == "W+" ? "B-" : "W-";
            } else {
                $presult = 2;
                $retresult = $result['result'];
            }
            if ($result['sgf'])
                $retresult = "<a href='" . href("results/$rid-" . $result['pw'] .
                    "-" . $result['pb']). "'>$retresult</a>";
            return array($retresult, $presult);
        }
    }
    return array("-", 0);
}

// Get the latest round for each band
function get_latest_rounds() {
    $rounds = fetch_rows("select r.rid, concat_ws('', 'Band ', b.name, ', ',
        date_format(r.begins, '%c/%e'), ' - ', date_format(r.ends, '%c/%e')) as round, r.bid
        from rounds r join bands b on r.bid=b.bid
        order by b.name, r.begins desc");
    // Show one round per band
    $latest_rounds = array();
    $bids = array();
    foreach ($rounds as $round) {
        if (!in_array($round['bid'], $bids)) {
            $latest_rounds[] = $round;
            $bids[] = $round['bid'];
        }
    }
    return $latest_rounds;
}

// Insert new players into the DB using one-name-per-line input source
function insert_new_players($bid, $input) {
    $new_players = preg_split("/(\r\n|\r|\n)/", $input);
    foreach ($new_players as $new_player) {
        if (!$new_player) continue;
        $pid = insert_row("players", array("name" => $new_player));
        insert_row("players_to_bands", array("pid" => $pid, "bid" => $bid));
    }
}

// Spit out form to edit result
function result_form($action, $values=array()) {
    ?>
    <form action="<?=href($action)?>" method="post" enctype="multipart/form-data">
        <div>Round:</div>
    <?php
        if ($values['rid'])
            $rounds = fetch_rows("select r.rid, concat_ws('', 'Band ', b.name, ', ',
                date_format(r.begins, '%c/%e'), ' - ', date_format(r.ends, '%c/%e')) as round,
                r.rid='" . $values['rid'] . "' as selected
                from rounds r join bands b on r.bid=b.bid
                order by r.begins desc");
        else
            $rounds = get_latest_rounds();
        echo get_select($rounds, "rid", "rid", "round", "[Select a round...]", "selected");
        if ($values['pw'] && $values['pb']) {
            $pw = get_select(fetch_rows("select pid, name, pid='" . $values['pw'] . "' as selected
                from players order by name"), "pw", "pid", "name",
                "[Select a player...]", "selected");
            $pb = get_select(fetch_rows("select pid, name, pid='" . $values['pb'] . "' as selected
                from players order by name"), "pb", "pid", "name",
                "[Select a player...]", "selected");
        } else {
            $pw = "<select id='pw' name='pw'><option value=''>[Select a round]</option></select>";
            $pb = "<select id='pb' name='pb'><option value=''>[Select a round]</option></select>";
        }
    ?>
        <div>White player:</div>
        <span id='pw-shell'>
        <?=$pw?>
        </span>
        
        <div>Black player:</div>
        <span id='pb-shell'>
        <?=$pb?>
        </span>
        
        <div>Result:</div>
        <select id='result' name='result'>
            <option value='W+'<?=($values['result'] == "W+" ? "selected" : "")?>>White won</option>
            <option value='B+'<?=($values['result'] == "B+" ? "selected" : "")?>>Black won</option>
            <option value='NR'<?=($values['result'] == "NR" ? "selected" : "")?>>No result</option>
        </select>
        
        <div>SGF:</div>
        <?php
            if ($values['sgf'])
                echo "<a href='" . href("sgf/" . htmlentities($values['sgf'])) . "'>".
                    htmlentities($values['sgf']) . "</a><br>";
        ?>
        <input type="file" name="sgf">
        
        <input type='submit' value='Submit'>
    </form>
    <?php if (!$values['pw'] && !$values['pb']) { ?>
        <script>
        $("#rid").bind("change", function() {
            $.get("../rounds-players-select/" + this.value, null, function(html) {
                $("#pw-shell").html(html.replace(/\{pids\}/g, "pw"));
                $("#pb-shell").html(html.replace(/\{pids\}/g, "pb"));
            });
        });
        </script>
    <?php
    }
}

// Insert or update result info as appropriate
function save_result($values, $insert=false) {
    list($pw, $pb, $rid) = array($values['pw'], $values['pb'], $values['rid']);
    $db_values = array(
        "rid" => $rid,
        "pw" => $pw,
        "pb" => $pb,
        "result" => $values['result'],
        "report_date" => "now()");
    if ($_FILES['sgf'] && $_FILES['sgf']['error'] == 0) {
        $sgf = $values['rid'] . "-" . $_FILES['sgf']['name'];
        move_uploaded_file($_FILES['sgf']['tmp_name'], "sgf/" . $sgf);
        chmod("sgf/" . $sgf, 0777);
        $db_values['sgf'] = $sgf;
    }
    if ($insert)
        insert_row("results", $db_values);
    else
        update_rows("results", $db_values, "pw='$pw' and pb='$pb' and rid='$rid'");
}

?>
