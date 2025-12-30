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

	$color_piece = ($color=='w') ? 1 : 2; // Χρώμα κομματιού: 1 για άσπρο, 2 για μαύρο
	
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

	// Κίνηση κομματιού
	global $mysqli;
	$sql = 'call move_piece(?,?,?)';
	$st = $mysqli->prepare($sql);
	$st->bind_param("sii", $token, $from, $to);
	$st->execute();
	header('Content-type: application/json');
	print json_encode(read_board(), JSON_PRETTY_PRINT);
}

?>