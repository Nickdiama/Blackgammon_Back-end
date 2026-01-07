<?php
require_once "lib/game.php";

function show_board() {
	header('Content-type: application/json');
	print json_encode(read_board(), JSON_PRETTY_PRINT);
}

function read_board() {
    global $mysqli;
	$sql = 'select * from board';
	$st = $mysqli->prepare($sql);
	$st->execute();
	$res = $st->get_result();
	return($res->fetch_all(MYSQLI_ASSOC));
}

function reset_board($input) {
	global $mysqli;
	$sql = 'call clean_board()';
	$mysqli->query($sql);
	show_board($input);
}

function move_piece($from, $to, $token) {
    global $mysqli;
	//Έλεγχος ύπαρξης token
	if($token==null || $token=='') {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"token is not set."]);
		exit;
	}

	//Έλεγχος ύπαρξης παίκτη με αυτό το token
	$color = current_color($token); //Επιστρέφει το χρώμα που έχει ο παίκτης με το συγκεκριμένο token
	if($color==null ) {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"You are not a player of this game."]);
		exit;
	}

	//Εάν το παιχνίδι δεν βρίσκεται σε κατάσταση started, raise error
	$status = read_status();
	if($status['status']!='started') {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"Game is not in action."]);
		exit;
	}
	
	//Έλεγχος αν είναι η σειρά του παίκτη
	if($status['p_turn']!=$color) {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"It is not your turn."]);
		exit;
	}

	// Τοπικά ζάρια
    if ($status['dice1'] === $status['dice2']) {
        $dice = array_fill(0, 4, $status['dice1']); // διπλή
    } else {
        $dice = [$status['dice1'], $status['dice2']];
    }
	
	$color_piece = ($color=='W') ? 1 : 2; // Χρώμα κομματιού: 1 για άσπρο, 2 για μαύρο
	// Έλεγχος αν υπάρχει κομμάτι του παίκτη στη θέση 'from'
	$st = $mysqli->prepare("SELECT $color_piece FROM board WHERE position = ?");
	$st->bind_param("i", $from);
	$st->execute();
	$cnt = $st->get_result()->fetch_row()[0];
	if ($cnt <= 0) {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"No piece of yours at the 'from' position."]);
		exit;
	}

	// Έλεγχος αν η απόσταση είναι σύμφωνη με τις τιμές των ζαριών
	$distance = abs($to - $from);
	if ($distance!=$status['dice1'] && $distance!=$status['dice2'] && $distance!=($status['dice1']+$status['dice2'])) {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"You can only move by the dice values."]);
		exit;
	}

	
	$st = $mysqli->prepare("SELECT top_color FROM board WHERE position = ?");
	$st->bind_param("i", $to);
	$st->execute();
	$top_color = $st->get_result()->fetch_row()[0];

	// Έλεγχος αν το κομμάτι είναι μπλοκαρισμένο
	if ($cnt != $top_color && $top_color != null) {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"Your piece is blocked by opponent piece."]);
		exit;
	}

	// Έλεγχος αν η θέση 'to' είναι κατειλημμένη από δύο ή περισσότερα κομμάτια του αντιπάλου
	if ($color != $top_color && $top_color != null) {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"The 'to' position is blocked by opponent pieces."]);
		exit;
	}
	
	// Κίνηση κομματιού
	$sql = 'call move_piece(?,?,?)';
	$st = $mysqli->prepare($sql);
	$st->bind_param("sii", $token, $from, $to);
	$st->execute();
	header('Content-type: application/json');
	print json_encode(read_board(), JSON_PRETTY_PRINT);

	//Ενημέρωση top_color
	$st = $mysqli->prepare("SELECT * FROM board WHERE position = ?");
    $st->bind_param('i', $to);
    $st->execute();
    $rows = $st->get_result()->fetch_all(MYSQLI_ASSOC);
    foreach ($rows as $r) {
        if ($r['position'] == $to)   $dst = $r;
    }
	if ($color == 'W'){
		if($dst['b_count']==1){
			$mysqli->query("UPDATE board SET b_count=1, top_color='W' WHERE position=$to");
		} elseif ($dst['b_count'] > 1) {
			$mysqli->query("UPDATE board SET w_count=w_count+1, top_color='W' WHERE position=$to");
		}
	} else {
		if($dst['w_count']==1){
			$mysqli->query("UPDATE board SET w_count=1, top_color='B' WHERE position=$to");
		} elseif ($dst['w_count'] > 1) {
			$mysqli->query("UPDATE board SET b_count=b_count+1, top_color='B' WHERE position=$to");
		}
	}
	print json_encode($dice, JSON_PRETTY_PRINT);

	// Αφαίρεση χρησιμοποιημένου ζαριού
	foreach ($dice as $key => $value) {
		if ($value == $distance) {
			unset($dice[$key]);
			break;
		}
	}
	
	//Αλλαγή σειράς
	if (count($dice) == 0) {
        $next = ($color === 'W') ? 'B' : 'W';
        $mysqli->query("UPDATE game_status SET p_turn='$next', dice1=NULL, dice2=NULL");
    }

}

?>