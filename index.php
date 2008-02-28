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
        "rounds",
        "results",
        "admin/bands",
        "admin/rounds",
        "admin/players");
        
    var $protected = array(
        "admin" => array("username" => "emptysky", "password" => "xxx"));
    
    function rounds_browse() {
        insert_content(
            "Archived Rounds",
            browse_table("select r.rid, concat_ws('', 'Band ', b.name, ', ',
                date_format(r.begins, '%c/%e'), ' - ', date_format(r.ends, '%c/%e')) as round
                from rounds r join bands b on r.bid=b.bid
                order by b.name, r.begins desc",
                "rounds/"));
    }
    
    function rounds_view($rid) {
        insert_header("Round Results");
        if ($rid == "current") {
            $latest_rounds = get_latest_rounds();
            foreach ($latest_rounds as $round) {
                echo "<h3>Round: " . $round['round'] . "</h3>";
                echo result_matrix($round['rid']);
            }
        } else {
            $round = fetch_row("select concat_ws('', 'Band ', b.name, ', ',
                date_format(r.begins, '%c/%e'), ' - ', date_format(r.ends, '%c/%e')) as round
                from rounds r join bands b on r.bid=b.bid
                where r.rid='$rid'");
            echo "<h3>Round: " . $round['round'] . "</h3>";
            echo result_matrix($rid);
        }
        insert_footer();
    }
    
    function players_browse() {
        insert_content(
            "Players",
            browse_table("select pid, name as player from players order by name", "players/"));
    }
    
    function results_browse() {
        redir("rounds/current");
    }
    
    function results_view($ids) {
        list($rid, $pw, $pb) = split("-", $ids);
        insert_header();
        $result = fetch_row("select r.result, pw.name as white, pb.name as black,
            r.sgf, r.report_date as date
            from results r join players pw on r.pw=pw.pid
            join players pb on r.pb=pb.pid 
            where r.pw='$pw' and r.pb='$pb' and r.rid='$rid'");
        $sgf = href("sgf/" . htmlentities($result['sgf']));
        echo "<h3>" . $result['white'] . " (W) vs. " . $result['black'] . " (B)</h3>";
        echo "<p><a href='$sgf'>Download .SGF</a></p>";
        echo "<div class='eidogo-player-auto' sgf='$sgf'>";
        insert_footer();
    }
    
    function results_add_form($action) {
        insert_header("Report Result");
        ?>
        <form action="<?=href("results/add")?>" method="post" enctype="multipart/form-data">
            <div>Round:</div>
        <?php
            $latest_rounds = get_latest_rounds();
            echo get_select($latest_rounds, "rid", "rid", "round", "[Select a round...]");
        ?>
            <div>White player:</div>
            <span id='pw-shell'>
            <select id='pw' name='pw'><option value=''>[Select a round]</option></select>
            </span>
            
            <div>Black player:</div>
            <span id='pb-shell'>
            <select id='pb' name='pb'><option value=''>[Select a round]</option></select>
            </span>
            
            <div>Result:</div>
            <select id='result' name='result'>
                <option value='W+'>White won</option>
                <option value='B+'>Black won</option>
                <option value='NR'>No result</option>
            </select>
            
            <div>SGF:</div>
            <input type="file" name="sgf">
            
            <input type='submit' value='Submit'>
        </form>
        <script>
        $("#rid").bind("change", function() {
            $.get("../rounds-players-select/" + this.value, null, function(html) {
                $("#pw-shell").html(html.replace(/\{pids\}/g, "pw"));
                $("#pb-shell").html(html.replace(/\{pids\}/g, "pb"));
            });
        });
        </script>
        <?php
        insert_footer();
    }
    
    function rounds_players_select($rid) {
        $players = fetch_rows("select p.pid, p.name
            from players p join players_to_rounds pr on p.pid=pr.pid and pr.rid='$rid'
            order by name");
        echo get_select($players, "{pids}", "pid", "name", "[Select a player...]");
    }
    
    function results_add($values) {
        $sgf = "";
        if ($_FILES['sgf'] && $_FILES['sgf']['error'] == 0) {
            $sgf = $values['rid'] . "-" . $_FILES['sgf']['name'];
            move_uploaded_file($_FILES['sgf']['tmp_name'], "sgf/" . $sgf);
            chmod("sgf/" . $sgf, 0777);
        }
        insert_row("results", array(
            "rid" => $values['rid'],
            "pw" => $values['pw'],
            "pb" => $values['pb'],
            "result" => $values['result'],
            "sgf" => $sgf,
            "report_date" => "now()"));
        redir("rounds/" . $values['rid'], true,
            "<a href='" . href("results/add") . "'>Add another result?</a>");
    }
    
    function admin() {
        insert_content(
            "Admin",
            "<ul>
                <li><a href='" . href("results/add") . "'>Report Result</a></li>
                <li><a href='" . href("admin/rounds") . "'>Rounds</a></li>
                <li><a href='" . href("admin/bands") . "'>Bands</a></li>
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
        insert_header("Band: " . htmlentities($band['name']));
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
        $players = fetch_rows("select distinct p.pid, p.name, pr.pid as in_round
            from players p join players_to_bands pb
            on p.pid=pb.pid and pb.bid='" . $round['bid'] . "'
            left join players_to_rounds pr on p.pid=pr.pid and pr.rid='$rid'
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
        <?php
            $bands = fetch_rows("select bid, name from bands order by name");
            get_select($bands, "bid", "bid", "name", "[Select a band...]");
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
        insert_footer();
    }
    
    function bands_players_checkboxes($bid) {
        $player_select = "select p.pid, p.name as player
            from players p join players_to_bands pb on p.pid=pb.pid and pb.bid='$bid'
            order by name";
        echo get_checkboxes(fetch_rows($player_select), "pids", "pid", "player");
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

function result_matrix($rid) {
    $players_x = fetch_rows("select p.pid, p.name
        from players p join players_to_rounds pr on p.pid=pr.pid and pr.rid='$rid'
        order by p.name");
    $results = fetch_rows("select * from results where rid='$rid'");
    $players_y = array();
    foreach ($players_x as $p) $players_y[] = $p;
    echo "<table class='result-matrix'>";
    $first_y = true;
    $first_x = true;
    echo "<tr><th>&nbsp;</th>";
    foreach ($players_x as $px) {
        echo "<th>" . $px['name'] . "</th>";
    }
    echo "<th>Score</th></tr>";
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
            echo ($px['pid'] == $py['pid'] ? "<td class='x'>&nbsp;</td>" : "<td>$result</td>");
            $first_x = false;
        }
        echo "<td>$wins-$losses</td>";
        echo "</tr>";
        $first_y = false;
    }
    echo "</table>";
}

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

function get_latest_rounds() {
    $rounds = fetch_rows("select r.rid, concat_ws('', 'Band ', b.name, ', ',
        date_format(r.begins, '%c/%e'), ' - ', date_format(r.ends, '%c/%e')) as round, r.bid
        from rounds r join bands b on r.bid=b.bid
        order by b.name");
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

function insert_new_players($bid, $input) {
    $new_players = preg_split("/(\r\n|\r|\n)/", $input);
    foreach ($new_players as $new_player) {
        if (!$new_player) continue;
        $pid = insert_row("players", array("name" => $new_player));
        insert_row("players_to_bands", array("pid" => $pid, "bid" => $bid));
    }
}

?>
