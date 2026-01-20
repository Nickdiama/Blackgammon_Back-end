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

	$color_piece = ($color === 'W') ? 1 : 2; // Χρώμα κομματιού: 1 για άσπρο, 2 για μαύρο
//-----------------------------------------------------------------------------------------------	
	// Έλεγχος αν υπάρχει κομμάτι του παίκτη στη θέση 'from'
	//$st = $mysqli->prepare("SELECT $color_piece FROM board WHERE position = ?");
	//$st->bind_param("i", $from);
	//$st->execute();
	//$cnt = $st->get_result()->fetch_row()[0];
	//if ($cnt <= 0) {
		//header("HTTP/1.1 400 Bad Request");
		//print json_encode(['errormesg'=>"No piece of yours at the 'from' position."]);
		//exit;
	//}
//------------------------------------------------------------------------------------------------
	//Ελεγχος κατεύθυνσης κίνησης
	if ($color == 'W' && $to <= $from) {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"You cannot go backwards."]);
		exit;
	} elseif ($color == 'B' && $to >= $from) {
		header("HTTP/1.1 400 Bad Request"); 
		print json_encode(['errormesg'=>"You cannot go backwards."]);
		exit;
	}

	// Έλεγχος αν η απόσταση είναι σύμφωνη με τις τιμές των ζαριών
	$distance = abs($to - $from);
	if ($distance == 0) {
    	header("HTTP/1.1 400 Bad Request");
    	print json_encode(['errormesg'=>"You cannot stay in the same position."]);
    	exit;
	}elseif ($distance!=$status['dice1'] && $distance!=$status['dice2'] && $distance!=($status['dice1']+$status['dice2']) && 
		($distance!=$status['dice3'] && $distance!=$status['dice4'] && $distance!=($status['dice3']+$status['dice4']))) {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"You can only move by the dice values."]);
		exit;
	}

	$st = $mysqli->prepare("SELECT top_color FROM board WHERE position = ?");
	$st->bind_param("i", $from);
	$st->execute();
	$top_color_from = $st->get_result()->fetch_row()[0];

	$st = $mysqli->prepare("SELECT top_color FROM board WHERE position = ?");
	$st->bind_param("i", $to);
	$st->execute();
	$top_color_to = $st->get_result()->fetch_row()[0];

	// Έλεγχος αν το κομμάτι είναι μπλοκαρισμένο
	if ($color != $top_color_from && $top_color_from != null) {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"Your piece is blocked by opponent piece."]);
		exit;
	}

	// Έλεγχος αν η θέση 'to' είναι κατειλημμένη από δύο ή περισσότερα κομμάτια του αντιπάλου
	if ($color != $top_color_to && $top_color_to != null) {
		header("HTTP/1.1 400 Bad Request");
		print json_encode(['errormesg'=>"The 'to' position is blocked by opponent pieces."]);
		exit;
	}

	// Κίνηση κομματιού
	$sql = 'call move_piece(?,?,?)';
	$st = $mysqli->prepare($sql);
	$st->bind_param("sii", $token, $from, $to);
	$st->execute();

	//Ενημέρωση top_color
	$st = $mysqli->prepare("SELECT w_count, b_count FROM board WHERE position = ?");
    $st->bind_param('i', $to);
    $st->execute();
    $dst = $st->get_result()->fetch_assoc();

	$st = $mysqli->prepare("SELECT w_count, b_count FROM board WHERE position = ?");
	$st->bind_param('i', $from);
	$st->execute();
	$src = $st->get_result()->fetch_assoc();
    
	$w_from = $src['w_count'];
	$b_from = $src['b_count'];
	$w_to = $dst['w_count'];
	$b_to = $dst['b_count'];

	if ($color == 'W'){
		if($b_to==1){
			$mysqli->query("UPDATE board SET top_color='W' WHERE position=$to"); //Πλάκωμα
		}elseif ($w_to>=2){
			$mysqli->query("UPDATE board SET top_color='W' WHERE position=$to");
		}
		
		if ($b_from<=1 && $w_from<=1){
			$mysqli->query("UPDATE board SET top_color=NULL WHERE position=$from");
		}
	} else {
		if($w_to==1){
			$mysqli->query("UPDATE board SET top_color='B' WHERE position=$to"); //Πλάκωμα
		}elseif ($b_to>=2){
			$mysqli->query("UPDATE board SET top_color='B' WHERE position=$to");
		}
		
		if ($w_from<=1 && $b_from<=1){
			$mysqli->query("UPDATE board SET top_color=NULL WHERE position=$from");
		}
	}

	header('Content-type: application/json');
	print json_encode(read_board(), JSON_PRETTY_PRINT);

	// Αφαίρεση χρησιμοποιημένου ζαριού
	if ($status['dice3'] != null || $status['dice4'] != null) {
		$double_count = $status['double_count'];
		if($double_count < 4){
			$double_count ++;
			$mysqli->query("UPDATE game_status SET dice{$double_count}=NULL");
			$mysqli->query("UPDATE game_status SET double_count={$double_count}");
		} 
	}else {
		$mysqli->query("UPDATE game_status SET double_count=0");
		if ($distance == $status['dice1']) {
			$mysqli->query("UPDATE game_status SET dice1=NULL");
		} elseif ($distance == $status['dice2']) {
			$mysqli->query("UPDATE game_status SET dice2=NULL");
		} elseif ($distance == ($status['dice1'] + $status['dice2'])) {
			$mysqli->query("UPDATE game_status SET dice1=NULL, dice2=NULL");
		}
	}

	$status = read_status();

	//Αλλαγή σειράς
	if ($status['dice1'] == null && $status['dice2'] == null && $status['dice3'] == null && $status['dice4'] == null) {
        $next = ($color === 'W') ? 'B' : 'W';
        $mysqli->query("UPDATE game_status SET p_turn='$next'");
    }

}

?>